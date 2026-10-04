#requires -Version 5.1
<#
.SYNOPSIS
Exports allowlisted SCIPSI legacy history without changing the source database.
.DESCRIPTION
Run against a restored/read-only database, or one with snapshot isolation already enabled.
The operator must verify that a database passed with -RestoredDatabase really is a disposable
restore: SERIALIZABLE reads can block writers. This script never enables database settings.
The ZIP contains private customer and financial information. Repeat exports of the same
database stay together automatically unless -SourceKey is set for an unusual second lineage.
.EXAMPLE
.\Export-LegacyHistory.ps1 -Server '.\SQLEXPRESS' -Database 'billing_restore' -OutputPath 'D:\Private\history.zip' -RestoredDatabase
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$Server,
    [Parameter(Mandatory = $true)][string]$Database,
    [string]$SourceKey,
    [Parameter(Mandatory = $true)][string]$OutputPath,
    [System.Management.Automation.PSCredential]$Credential,
    [switch]$RestoredDatabase,
    [ValidateRange(1, 10000)][int]$SampleRowsPerTable,
    [switch]$TrustServerCertificate
)
$ErrorActionPreference = 'Stop'
if (-not $SourceKey) {
    $SourceKey = (($Database.ToLower() -replace '[^a-z0-9_-]+', '-') -replace '^[-_]+|[-_]+$', '')
    if ($SourceKey.Length -lt 3 -or $SourceKey -notmatch '^[a-zA-Z]') { $SourceKey = 'db-' + $SourceKey }
}
if ($SourceKey -notmatch '^[a-zA-Z0-9][a-zA-Z0-9_-]{2,63}$') {
    throw 'Could not derive a source identity from the database name. Pass -SourceKey with 3-64 letters, digits, dashes or underscores.'
}
Add-Type -AssemblyName System.Data
Add-Type -AssemblyName System.IO.Compression
$schemaPath = Join-Path $PSScriptRoot 'legacy-import-schema.json'
if (-not (Test-Path -LiteralPath $schemaPath)) {
    $schemaPath = Join-Path (Split-Path $PSScriptRoot -Parent) 'legacy-import-schema.json'
}
$schema = Get-Content -LiteralPath $schemaPath -Raw | ConvertFrom-Json
$destination = [System.IO.Path]::GetFullPath($OutputPath)
if ([System.IO.Path]::GetExtension($destination) -ne '.zip') { throw 'OutputPath must end in .zip.' }
if (-not [System.IO.Directory]::Exists([System.IO.Path]::GetDirectoryName($destination))) {
    throw 'Create a private output directory first. Do not use a public or web-served directory.'
}
$builder = New-Object System.Data.SqlClient.SqlConnectionStringBuilder
$builder.DataSource = $Server
$builder.InitialCatalog = $Database
$builder.ApplicationName = 'SCIPSI Read-only History Export'
$builder.ConnectTimeout = 30
$builder.Encrypt = $true
$builder.TrustServerCertificate = [bool]$TrustServerCertificate
if ($Credential) {
    $builder.UserID = $Credential.UserName
    $builder.Password = $Credential.GetNetworkCredential().Password
} else { $builder.IntegratedSecurity = $true }
$connection = New-Object System.Data.SqlClient.SqlConnection($builder.ConnectionString)
$builder.Clear()
$transaction = $null
$fileStream = $null
$archive = $null
$writer = $null
$createdOutput = $false
$completed = $false
try {
    $connection.Open()
    $modeCommand = $connection.CreateCommand()
    $modeCommand.CommandText = 'SELECT is_read_only, snapshot_isolation_state FROM sys.databases WHERE database_id = DB_ID()'
    $modeReader = $modeCommand.ExecuteReader()
    if (-not $modeReader.Read()) { throw 'Cannot verify source database consistency.' }
    $readOnly = $modeReader.GetBoolean(0)
    $snapshot = [int]$modeReader.GetValue(1) -eq 1
    $modeReader.Close()
    $modeCommand.Dispose()
    if ($readOnly) {
        $consistency = 'READ_ONLY_DATABASE'
        $transaction = $connection.BeginTransaction([System.Data.IsolationLevel]::ReadCommitted)
    } elseif ($snapshot) {
        $consistency = 'SNAPSHOT'
        $transaction = $connection.BeginTransaction([System.Data.IsolationLevel]::Snapshot)
    } elseif ($RestoredDatabase) {
        $consistency = 'RESTORED_SERIALIZABLE'
        $transaction = $connection.BeginTransaction([System.Data.IsolationLevel]::Serializable)
    } else {
        throw 'Use a restored database with -RestoredDatabase, a read-only database, or pre-enabled snapshot isolation. No export was created.'
    }
    $counts = [ordered]@{}
    foreach ($tableProperty in $schema.PSObject.Properties) {
        $tableName = $tableProperty.Name
        if ($tableName -notmatch '^tbl_[a-z_]+$') { throw 'Invalid schema table.' }
        $countCommand = $connection.CreateCommand()
        $countCommand.Transaction = $transaction
        $countCommand.CommandTimeout = 300
        $countCommand.CommandText = "SELECT COUNT_BIG(*) FROM [dbo].[$tableName]"
        $rowCount = [long]$countCommand.ExecuteScalar()
        $countCommand.Dispose()
        if ($tableName -eq 'tbl_settings' -and $rowCount -gt 1) {
            throw 'Multiple settings rows have no stable source identity. Review the database before exporting.'
        }
        if ($SampleRowsPerTable) { $rowCount = [Math]::Min($rowCount, $SampleRowsPerTable) }
        $counts[$tableName] = $rowCount
    }
    $total = ($counts.Values | Measure-Object -Sum).Sum
    if ($total -gt 5000000) { throw 'Source exceeds the five-million-row package limit.' }
    $fileStream = [System.IO.File]::Open($destination, [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None)
    $createdOutput = $true
    $archive = New-Object System.IO.Compression.ZipArchive($fileStream, [System.IO.Compression.ZipArchiveMode]::Create, $true)
    $entry = $archive.CreateEntry('history.jsonl', [System.IO.Compression.CompressionLevel]::Optimal)
    $writer = New-Object System.IO.StreamWriter($entry.Open(), (New-Object System.Text.UTF8Encoding($false)))
    $manifest = [ordered]@{
        format = 'scipsi-legacy-history'; version = 1; source_key = $SourceKey
        exported_at = [DateTime]::UtcNow.ToString('yyyy-MM-ddTHH:mm:ss.fffZ')
        consistency = $consistency
        scope = $(if ($SampleRowsPerTable) { 'SAMPLE' } else { 'FULL_HISTORY' })
        counts = $counts
    }
    $writer.WriteLine(($manifest | ConvertTo-Json -Compress -Depth 8))
    $culture = [Globalization.CultureInfo]::InvariantCulture
    foreach ($tableProperty in $schema.PSObject.Properties) {
        $tableName = $tableProperty.Name
        $definition = $tableProperty.Value
        $columns = $definition.columns.Split(' ')
        foreach ($column in $columns) { if ($column -notmatch '^[a-z_]+$') { throw 'Invalid schema column.' } }
        $selectColumns = ($columns | ForEach-Object { "[$_]" }) -join ', '
        $top = if ($SampleRowsPerTable) { "TOP ($SampleRowsPerTable) " } else { '' }
        $order = if ($definition.key) { " ORDER BY [$($definition.key)]" } else { '' }
        $command = $connection.CreateCommand()
        $command.Transaction = $transaction
        $command.CommandTimeout = 300
        $command.CommandText = "SELECT $top$selectColumns FROM [dbo].[$tableName]$order"
        $reader = $command.ExecuteReader()
        $written = 0L
        try {
            while ($reader.Read()) {
                $data = [ordered]@{}
                for ($index = 0; $index -lt $reader.FieldCount; $index++) {
                    $value = $reader.GetValue($index)
                    if ($value -is [DBNull]) { $text = $null }
                    elseif ($value -is [DateTime]) { $text = $value.ToString('yyyy-MM-ddTHH:mm:ss.fff', $culture) }
                    elseif ($value -is [double] -or $value -is [single]) { $text = $value.ToString('R', $culture) }
                    elseif ($value -is [System.IFormattable]) { $text = $value.ToString($null, $culture) }
                    else { $text = [string]$value }
                    $data[$reader.GetName($index)] = $text
                }
                $key = if ($definition.key) { $data[$definition.key] } else { '1' }
                $record = [ordered]@{ table = $tableName; key = $key; data = $data }
                $writer.WriteLine(($record | ConvertTo-Json -Compress -Depth 8))
                $written++
            }
        } finally { $reader.Close(); $command.Dispose() }
        if ($written -ne $counts[$tableName]) { throw 'Row counts changed during extraction. Discard this package.' }
        Write-Host "$tableName : $written rows exported"
    }
    $writer.Dispose(); $writer = $null
    $archive.Dispose(); $archive = $null
    $fileStream.Dispose(); $fileStream = $null
    $transaction.Commit()
    $completed = $true
    Write-Host 'Export complete. Upload this private ZIP to System > Legacy Import & History. No source data was changed.'
    if ($SampleRowsPerTable) { Write-Warning 'SAMPLE ONLY: independently sampled tables can have missing relationships. This is not a migration rehearsal.' }
} catch {
    if ($transaction) { try { $transaction.Rollback() } catch {} }
    # SQL errors can contain connection details. Do not echo the connection or credentials.
    if ($_.Exception -is [System.Data.SqlClient.SqlException] -or $_.Exception.InnerException -is [System.Data.SqlClient.SqlException]) {
        throw 'Export failed. Verify source schema, permissions, consistency mode and TLS on the legacy workstation. No source data was changed.'
    }
    throw
} finally {
    if ($writer) { $writer.Dispose() }
    if ($archive) { $archive.Dispose() }
    if ($fileStream) { $fileStream.Dispose() }
    if ($transaction) { $transaction.Dispose() }
    $connection.Dispose()
    if ($createdOutput -and -not $completed -and [System.IO.File]::Exists($destination)) {
        [System.IO.File]::Delete($destination)
    }
}
