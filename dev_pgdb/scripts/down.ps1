# Script para apagar la base de datos PostgreSQL
# Uso: .\scripts\down.ps1

$DevDbPath = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$originalLocation = Get-Location

try {
	Set-Location $DevDbPath

	Write-Host "🛑 Deteniendo PostgreSQL..." -ForegroundColor Yellow

	docker-compose down

	Write-Host "✅ PostgreSQL detenido correctamente" -ForegroundColor Green
}
finally {
	Set-Location $originalLocation
}
