# SCIPSI Online Billing - Comprehensive Test Runner
# Verifies Docker containers, Laravel API test suite, and Vue frontend production build.

$ErrorActionPreference = "Stop"
$rootDir = Split-Path -Parent $PSScriptRoot

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host " SCIPSI Online Billing - Test Suite" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan

# 1. Check Docker Services
Write-Host "`n[1/3] Checking Docker compose services..." -ForegroundColor Yellow
$postgresStatus = docker compose -f "$rootDir\compose.yaml" ps postgres --format "{{.Status}}"
$redisStatus = docker compose -f "$rootDir\compose.yaml" ps redis --format "{{.Status}}"

if ($postgresStatus -match 'healthy|running|Up') {
    Write-Host "  [OK] PostgreSQL service is up: $postgresStatus" -ForegroundColor Green
} else {
    Write-Error "  [FAIL] PostgreSQL service is not running or unhealthy: $postgresStatus"
}

if ($redisStatus -match 'healthy|running|Up') {
    Write-Host "  [OK] Redis service is up: $redisStatus" -ForegroundColor Green
} else {
    Write-Error "  [FAIL] Redis service is not running or unhealthy: $redisStatus"
}

# 2. Run Backend API Tests
Write-Host "`n[2/3] Running Laravel API test suite against PostgreSQL..." -ForegroundColor Yellow
Push-Location "$rootDir\apps\api"
try {
    & php artisan test
    if ($LASTEXITCODE -ne 0) {
        throw "Laravel API tests failed with exit code $LASTEXITCODE"
    }
    Write-Host "  [OK] All Laravel API tests passed!" -ForegroundColor Green
} finally {
    Pop-Location
}

# 3. Run Frontend Build & Type Check
Write-Host "`n[3/3] Running Vue/TypeScript build and type check..." -ForegroundColor Yellow
Push-Location "$rootDir\apps\web"
try {
    & pnpm.cmd build
    if ($LASTEXITCODE -ne 0) {
        throw "Frontend build failed with exit code $LASTEXITCODE"
    }
    Write-Host "  [OK] Frontend build passed!" -ForegroundColor Green
} finally {
    Pop-Location
}

Write-Host "`n=========================================" -ForegroundColor Cyan
Write-Host " All verification checks passed cleanly!" -ForegroundColor Green
Write-Host "=========================================`n" -ForegroundColor Cyan
