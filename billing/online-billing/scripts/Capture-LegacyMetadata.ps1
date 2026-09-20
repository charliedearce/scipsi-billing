param(
    [Parameter(Mandatory=$true)][string]$Server,
    [Parameter(Mandatory=$true)][string]$Database
)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$outputDirectory = Join-Path $projectRoot '.local/discovery'
New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null
# Integrated authentication only. No application config or stored credentials are read.
$builder = New-Object System.Data.SqlClient.SqlConnectionStringBuilder
$builder['Data Source'] = $Server
$builder['Initial Catalog'] = $Database
$builder['Integrated Security'] = $true
$builder['Connect Timeout'] = 5
$builder['Application Name'] = 'SCIPSI Phase 0 metadata read'
$connection = New-Object System.Data.SqlClient.SqlConnection($builder.ConnectionString)
function Read-Rows([string]$sql) {
    $command = $connection.CreateCommand()
    $command.CommandTimeout = 20
    $command.CommandText = "SET LOCK_TIMEOUT 5000; SET NOCOUNT ON; $sql"
    try {
        $reader = $command.ExecuteReader()
        try {
            while ($reader.Read()) {
                $row = [ordered]@{}
                for ($i=0; $i -lt $reader.FieldCount; $i++) {
                    $row[$reader.GetName($i)] = if ($reader.IsDBNull($i)) { $null } else { $reader.GetValue($i) }
                }
                [pscustomobject]$row
            }
        } finally { $reader.Dispose() }
    } finally { $command.Dispose() }
}
try {
    $connection.Open()
    $queries = [ordered]@{
        database = "SELECT CAST(SERVERPROPERTY('ProductVersion') AS varchar(50)) AS engine_version, compatibility_level, collation_name FROM sys.databases WHERE name=DB_NAME()"
        tables = "SELECT s.name AS schema_name,t.name, SUM(CASE WHEN p.index_id IN (0,1) THEN p.rows ELSE 0 END) AS approximate_rows FROM sys.tables t JOIN sys.schemas s ON s.schema_id=t.schema_id LEFT JOIN sys.partitions p ON p.object_id=t.object_id GROUP BY s.name,t.name ORDER BY s.name,t.name"
        columns = "SELECT OBJECT_SCHEMA_NAME(c.object_id) AS schema_name,OBJECT_NAME(c.object_id) AS table_name,c.column_id,c.name,TYPE_NAME(c.user_type_id) AS data_type,c.max_length,c.precision,c.scale,c.is_nullable,c.is_identity,c.is_computed FROM sys.columns c JOIN sys.tables t ON t.object_id=c.object_id ORDER BY table_name,c.column_id"
        indexes = "SELECT OBJECT_NAME(i.object_id) AS table_name,i.name,i.is_unique,i.is_primary_key,i.type_desc,c.name AS column_name,ic.key_ordinal,ic.is_included_column FROM sys.indexes i JOIN sys.tables t ON t.object_id=i.object_id JOIN sys.index_columns ic ON ic.object_id=i.object_id AND ic.index_id=i.index_id JOIN sys.columns c ON c.object_id=ic.object_id AND c.column_id=ic.column_id WHERE i.index_id>0 ORDER BY table_name,i.name,ic.key_ordinal"
        foreign_keys = "SELECT OBJECT_NAME(f.parent_object_id) AS table_name,f.name,COL_NAME(fc.parent_object_id,fc.parent_column_id) AS column_name,OBJECT_NAME(f.referenced_object_id) AS referenced_table,COL_NAME(fc.referenced_object_id,fc.referenced_column_id) AS referenced_column FROM sys.foreign_keys f JOIN sys.foreign_key_columns fc ON fc.constraint_object_id=f.object_id"
        constraints = "SELECT OBJECT_NAME(parent_object_id) AS table_name,name,type_desc FROM sys.objects WHERE type IN ('D','C','PK','UQ') ORDER BY table_name,name"
        procedures = "SELECT p.name,m.definition FROM sys.procedures p LEFT JOIN sys.sql_modules m ON m.object_id=p.object_id WHERE p.is_ms_shipped=0 ORDER BY p.name"
        parameters = "SELECT OBJECT_NAME(p.object_id) AS procedure_name,p.name,TYPE_NAME(p.user_type_id) AS data_type,p.max_length,p.precision,p.scale,p.is_output FROM sys.parameters p JOIN sys.procedures sp ON sp.object_id=p.object_id ORDER BY procedure_name,p.parameter_id"
        triggers = "SELECT t.name,OBJECT_NAME(t.parent_id) AS parent_name,t.is_disabled,m.definition FROM sys.triggers t LEFT JOIN sys.sql_modules m ON m.object_id=t.object_id WHERE t.is_ms_shipped=0"
        views = "SELECT v.name,m.definition FROM sys.views v LEFT JOIN sys.sql_modules m ON m.object_id=v.object_id WHERE v.is_ms_shipped=0"
        defaults = "SELECT OBJECT_NAME(parent_object_id) AS table_name,name,definition FROM sys.default_constraints"
        checks = "SELECT OBJECT_NAME(parent_object_id) AS table_name,name,definition FROM sys.check_constraints"
        permissions = "SELECT class_desc,permission_name,state_desc,COUNT(*) AS grants_count FROM sys.database_permissions GROUP BY class_desc,permission_name,state_desc"
    }
    $snapshot = [ordered]@{ captured_at_utc = [DateTime]::UtcNow.ToString('o'); scope = 'Metadata only; database provenance and restore not verified' }
    foreach ($name in $queries.Keys) { $snapshot[$name] = @(Read-Rows $queries[$name]) }
    $outputPath = Join-Path $outputDirectory ('metadata-' + [DateTime]::UtcNow.ToString('yyyyMMdd-HHmmss-fff') + '.json')
    $snapshot | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $outputPath -Encoding UTF8
    Write-Output "Metadata saved to ignored local path: $outputPath"
    Write-Output "Tables=$($snapshot.tables.Count); procedures=$($snapshot.procedures.Count); foreign_keys=$($snapshot.foreign_keys.Count); triggers=$($snapshot.triggers.Count)"
    Write-Output 'This is a metadata inventory, not a database backup or restore proof. Review locally before sharing definitions.'
} finally { $connection.Dispose() }
