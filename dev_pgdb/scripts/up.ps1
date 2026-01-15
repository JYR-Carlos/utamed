# Script para levantar la base de datos PostgreSQL
# Uso: .\scripts\up.ps1

param(
    [switch]$Build = $false
)

$DevDbPath = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$originalLocation = Get-Location

try {
    Set-Location $DevDbPath

    # Verificar si existe .env, si no, crearlo desde .env.example
    $envFile = Join-Path $DevDbPath ".env"
    $envExampleFile = Join-Path $DevDbPath ".env.example"

    if (-not (Test-Path $envFile)) {
        if (Test-Path $envExampleFile) {
            Write-Host "📝 Archivo .env no encontrado. Creando desde .env.example..." -ForegroundColor Yellow
            Copy-Item $envExampleFile $envFile
            Write-Host "✅ Archivo .env creado. Por favor, configura los valores necesarios." -ForegroundColor Green
            Write-Host "⚠️  Edita $envFile antes de continuar." -ForegroundColor Yellow
            Write-Host ""
            
            # Preguntar si desea continuar o editar primero
            $response = Read-Host "¿Deseas continuar de todas formas? (s/N)"
            if ($response -notmatch '^[sS]$') {
                Write-Host "Operación cancelada. Edita el archivo .env y vuelve a ejecutar el script." -ForegroundColor Cyan
                exit 0
            }
        } else {
            Write-Host "❌ No se encontró .env ni .env.example" -ForegroundColor Red
            exit 1
        }
    }

    Write-Host "🚀 Iniciando PostgreSQL 17.7 con Docker..." -ForegroundColor Green

    if ($Build) {
        Write-Host "🔨 Compilando imagen..." -ForegroundColor Yellow
        docker-compose build --no-cache
    }

    docker-compose up -d

    # Esperar a que la BD esté lista
    Write-Host "⏳ Esperando que PostgreSQL esté listo..." -ForegroundColor Yellow
    $maxAttempts = 30
    $attempt = 0

    while ($attempt -lt $maxAttempts) {
        $result = docker-compose exec -T postgres pg_isready -U utamed -d utamed 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✅ PostgreSQL está listo!" -ForegroundColor Green
            Write-Host ""
            Write-Host "📊 Detalles de la conexión:" -ForegroundColor Cyan
            Write-Host "  Host: localhost" -ForegroundColor White
            Write-Host "  Puerto: 5432" -ForegroundColor White
            Write-Host "  Base de datos: utamed" -ForegroundColor White
            Write-Host "  Usuario: utamed" -ForegroundColor White
            Write-Host "  Contraseña: utamed_dev_password" -ForegroundColor White
            Write-Host ""
            break
        }
        $attempt++
        Start-Sleep -Seconds 1
    }

    if ($attempt -eq $maxAttempts) {
        Write-Host "❌ Tiempo agotado esperando PostgreSQL" -ForegroundColor Red
        exit 1
    }
}
finally {
    Set-Location $originalLocation
}
