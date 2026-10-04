<?php

namespace App\Services\LegacyImport;

use PDO;
use RuntimeException;
use SensitiveParameter;

class LegacySqlServerReader
{
    public function __construct(private LegacyImportSchema $schema) {}

    public function available(): bool
    {
        return in_array('dblib', PDO::getAvailableDrivers(), true);
    }

    public function inspect(#[SensitiveParameter] array $connection): array
    {
        $pdo = $this->connect($connection);
        $mode = $this->consistency($pdo, $connection);
        $this->verifySchema($pdo);

        return ['consistency' => $mode, 'tables' => count($this->schema->tables()), 'message' => 'Connection and legacy schema verified. Ready for a read-only snapshot.'];
    }

    public function extract(#[SensitiveParameter] array $connection, string $destination, callable $progress): void
    {
        $pdo = $this->connect($connection);
        $mode = $this->consistency($pdo, $connection);
        $isolation = match ($mode) {
            'READ_ONLY_DATABASE' => 'READ COMMITTED',
            'SNAPSHOT' => 'SNAPSHOT',
            default => 'SERIALIZABLE',
        };
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL '.$isolation);
        $pdo->exec('SET LOCK_TIMEOUT 15000');
        $pdo->beginTransaction();
        $stream = null;
        try {
            $this->verifySchema($pdo);
            $counts = [];
            foreach ($this->schema->tables() as $table => $definition) {
                $count = (int) $pdo->query("SELECT COUNT_BIG(*) FROM [dbo].[{$table}]")->fetchColumn();
                if (($table === 'tbl_settings' && $count > 1) || $count > 5000000) {
                    throw new RuntimeException('Unsupported source row counts.');
                }
                $counts[$table] = $count;
            }
            $expected = array_sum($counts);
            if ($expected > 5000000) {
                throw new RuntimeException('Source exceeds the five-million-row limit.');
            }
            $progress(0, $expected);
            $stream = fopen($destination, 'xb');
            $sourceKey = LegacySqlImportService::sourceKeyForDatabase((string) $connection['database']);
            $this->writeLine($stream, [
                'format' => 'scipsi-legacy-history', 'version' => 1, 'source_key' => $sourceKey,
                'exported_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'), 'consistency' => $mode, 'scope' => 'FULL_HISTORY', 'counts' => $counts,
            ]);
            $read = 0;
            foreach ($this->schema->tables() as $table => $definition) {
                $columns = array_map(fn (string $column): string => (isset($definition['date']) && $column === $definition['date']) || $column === 'sysdate'
                    ? "CONVERT(nvarchar(max), [{$column}], 126) AS [{$column}]"
                    : "CONVERT(nvarchar(max), [{$column}]) AS [{$column}]", explode(' ', $definition['columns']));
                $order = isset($definition['key']) ? ' ORDER BY ['.$definition['key'].']' : '';
                $statement = $pdo->query('SELECT '.implode(', ', $columns)." FROM [dbo].[{$table}]".$order);
                $tableRead = 0;
                while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
                    $record = ['table' => $table, 'key' => isset($definition['key']) ? $data[$definition['key']] : '1', 'data' => $data];
                    $this->schema->record($record);
                    $this->writeLine($stream, $record);
                    $tableRead++;
                    $read++;
                    if ($read % 500 === 0) {
                        $progress($read, $expected);
                    }
                }
                $statement->closeCursor();
                if ($tableRead !== $counts[$table]) {
                    throw new RuntimeException('Inconsistent source counts.');
                }
            }
            $progress($read, $expected);
            $pdo->commit();
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    protected function connect(#[SensitiveParameter] array $connection): PDO
    {
        if (! $this->available()) {
            throw new RuntimeException('SQL Server driver is not installed.');
        }

        return new PDO('dblib:host='.$connection['host'].':'.$connection['port'].';dbname='.$connection['database'].';charset=UTF-8;appname=SCIPSI Legacy Reader', $connection['username'], $connection['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_STRINGIFY_FETCHES => true,
        ]);
    }

    private function consistency(PDO $pdo, array $connection): string
    {
        $row = $pdo->query('SELECT is_read_only, snapshot_isolation_state FROM sys.databases WHERE database_id = DB_ID()')->fetch();
        if ($row && (int) $row['is_read_only'] === 1) {
            return 'READ_ONLY_DATABASE';
        }
        if ($row && (int) $row['snapshot_isolation_state'] === 1) {
            return 'SNAPSHOT';
        }
        if ($row && ($connection['restored_database'] ?? false)) {
            return 'RESTORED_SERIALIZABLE';
        }
        throw new RuntimeException('Use a read-only/restored source or pre-enabled snapshot isolation.');
    }

    private function verifySchema(PDO $pdo): void
    {
        foreach ($this->schema->tables() as $table => $definition) {
            $statement = $pdo->query("SELECT name, TYPE_NAME(system_type_id) AS type_name FROM sys.columns WHERE object_id = OBJECT_ID(N'dbo.{$table}')");
            $types = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach (explode(' ', $definition['columns']) as $column) {
                if (! isset($types[$column]) || ! in_array($types[$column], ['varchar', 'nvarchar', 'char', 'nchar', 'text', 'ntext', 'decimal', 'numeric', 'bigint', 'int', 'smallint', 'tinyint', 'datetime', 'smalldatetime', 'datetime2', 'date', 'bit'], true)) {
                    throw new RuntimeException('Source schema or numeric types differ from the supported legacy database.');
                }
            }
            $columns = implode(', ', array_map(fn (string $column): string => '['.$column.']', explode(' ', $definition['columns'])));
            $pdo->query("SELECT TOP (0) {$columns} FROM [dbo].[{$table}]")->closeCursor();
        }
    }

    /** @param resource $stream */
    private function writeLine($stream, array $data): void
    {
        $line = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
        if (strlen($line) >= 1048576 || ftell($stream) + strlen($line) > 1073741824 || fwrite($stream, $line) !== strlen($line)) {
            throw new RuntimeException('The private snapshot exceeded a safe size or could not be written.');
        }
    }
}
