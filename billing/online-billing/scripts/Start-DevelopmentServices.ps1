$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$secretDirectory = Join-Path $projectRoot '.local'
$secretPath = Join-Path $secretDirectory 'postgres-password'
New-Item -ItemType Directory -Force -Path $secretDirectory | Out-Null
if (-not (Test-Path -LiteralPath $secretPath)) {
    $bytes = New-Object byte[] 32
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($bytes) } finally { $generator.Dispose() }
    [Convert]::ToBase64String($bytes) | Set-Content -LiteralPath $secretPath -NoNewline -Encoding ascii
}
Push-Location $projectRoot
try {
    docker compose config --quiet
    if ($LASTEXITCODE -ne 0) { throw 'Compose validation failed.' }
    docker compose up -d --wait --wait-timeout 60
    if ($LASTEXITCODE -ne 0) { throw 'Development services did not become healthy.' }
} finally { Pop-Location }
