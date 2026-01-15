# Script para limpiar los datos de PostgreSQL
# Uso: .\scripts\clean.ps1

param(
    [switch]$Force = $false
)

$DevDbPath = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$DataPath = Join-Path $DevDbPath "data"
$originalLocation = Get-Location

try {
    Set-Location $DevDbPath

    if (Test-Path $DataPath) {
        if (-not $Force) {
            $response = Read-Host "⚠️  ¿Estás seguro de que deseas borrar todos los datos? (s/n)"
            if ($response -ne 's' -and $response -ne 'S') {
                Write-Host "❌ Operación cancelada" -ForegroundColor Yellow
                exit 0
            }
        }

        Write-Host "🗑️  Deteniendo PostgreSQL..." -ForegroundColor Yellow
        docker-compose down

        Write-Host "🗑️  Borrando directorio de datos..." -ForegroundColor Red
        Remove-Item -Path $DataPath -Recurse -Force

        Write-Host "✅ Datos borrados correctamente" -ForegroundColor Green
    } else {
        Write-Host "ℹ️  No hay datos para borrar" -ForegroundColor Cyan
    }
}
finally {
    Set-Location $originalLocation
}
