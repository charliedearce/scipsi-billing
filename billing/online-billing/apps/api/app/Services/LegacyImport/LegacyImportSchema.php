<?php

namespace App\Services\LegacyImport;

use Illuminate\Validation\ValidationException;

class LegacyImportSchema
{
    private ?array $definitions = null;

    public function tables(): array
    {
        return $this->definitions ??= json_decode(file_get_contents(resource_path('legacy-import-schema.json')), true, 32, JSON_THROW_ON_ERROR);
    }

    public function record(array $record): array
    {
        $table = $record['table'] ?? null;
        $schema = is_string($table) ? ($this->tables()[$table] ?? null) : null;
        $data = $record['data'] ?? null;
        $key = $record['key'] ?? null;
        if (! $schema || ! is_array($data) || ! is_string($key) || ! preg_match('/^(?:0|[1-9][0-9]{0,19})$/D', $key)
            || array_diff(array_keys($record), ['table', 'key', 'data'])) {
            $this->invalid('Unsupported table or invalid source identity. Use the provided legacy exporter.');
        }
        $columns = explode(' ', $schema['columns']);
        if (array_diff(array_keys($data), $columns) || array_diff($columns, array_keys($data))) {
            $this->invalid('Source columns differ from the supported legacy schema.');
        }
        foreach ($data as $value) {
            if ($value !== null && (! is_string($value) || mb_strlen($value) > 10000 || str_contains($value, "\0"))) {
                $this->invalid('Source values must be decimal-safe strings or null, up to 10,000 characters.');
            }
        }
        if (isset($schema['key']) && $data[$schema['key']] !== $key) {
            $this->invalid('Source identity does not match the row.');
        }
        if ($table === 'tbl_settings' && $key !== '1') {
            $this->invalid('Historical settings must be a single source row.');
        }
        $projected = [];
        $issues = [];
        foreach (['reference' => 100, 'related' => 100, 'account' => 100, 'name' => 255, 'date' => 40, 'status' => 32] as $field => $length) {
            $value = isset($schema[$field]) ? $data[$schema[$field]] : null;
            $projected[$field] = $value === null ? null : mb_substr(trim($value), 0, $length);
        }
        foreach ($schema['amounts'] ?? [] as $target => $source) {
            $value = $data[$source];
            if (! is_string($value) || ! preg_match('/^-?\d{1,16}(?:\.\d{1,8})?$/D', trim($value))
                || bccomp(trim($value), bcadd(trim($value), '0', 2), 8) !== 0) {
                $issues[] = 'INVALID_AMOUNT:'.$source;
                $projected[$target] = null;
            } else {
                $projected[$target] = bcadd(trim($value), '0', 2);
            }
        }
        if (isset($schema['status']) && ! in_array($projected['status'], ['FALSE', 'TRUE'], true)) {
            $issues[] = 'UNKNOWN_STATUS';
        }
        $date = $projected['date'];
        if (isset($schema['date']) && (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d{1,7})?$/D', $date)
            || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))
            || (int) substr($date, 11, 2) > 23 || (int) substr($date, 14, 2) > 59 || (int) substr($date, 17, 2) > 59)) {
            $issues[] = 'INVALID_DATE';
        } elseif (isset($schema['date']) && ((int) substr($projected['date'], 0, 4) < 2000 || substr($projected['date'], 0, 10) > now('Asia/Manila')->toDateString())) {
            $issues[] = 'DATE_REVIEW';
        }
        if (in_array($table, ['tbl_bill_trans', 'tbl_item_trans', 'tbl_or_trans', 'tbl_orbill_trans', 'tbl_bill_no', 'tbl_or_no'], true)
            && ! preg_match('/^[0-9]{10}$/D', $projected['reference'] ?? '')) {
            $issues[] = 'NUMBER_FORMAT_REVIEW';
        }
        ksort($data);

        return [
            'source_table' => $record['table'], 'source_id' => $key,
            'row_hash' => hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'reference' => $projected['reference'], 'related_reference' => $projected['related'],
            'account_number' => $projected['account'], 'display_name' => $projected['name'],
            'source_date' => $projected['date'], 'source_status' => $projected['status'],
            ...array_intersect_key($projected, $schema['amounts'] ?? []),
            'payload' => json_encode($data, JSON_THROW_ON_ERROR),
            'issues' => json_encode($issues, JSON_THROW_ON_ERROR),
        ];
    }

    public function invalid(string $message): never
    {
        throw ValidationException::withMessages(['package' => [$message]]);
    }
}
