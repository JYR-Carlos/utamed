# Script para reconstruir la imagen sin cache y levantar PostgreSQL
# Uso: .\scripts\rebuild.ps1

$DevDbPath = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$originalLocation = Get-Location

try {
    Set-Location $DevDbPath

    Write-Host "🔄 Reconstruyendo PostgreSQL sin cache..." -ForegroundColor Magenta
    Write-Host ""

    # Detener contenedor si está corriendo
    Write-Host "🛑 Deteniendo contenedor actual..." -ForegroundColor Yellow
    docker-compose down 2>&1 | Out-Null

    # Remover imagen vieja
    Write-Host "🗑️  Removiendo imagen anterior..." -ForegroundColor Yellow
    $imageName = "utamed-postgres"
    docker rmi "$imageName`:latest" 2>&1 | Out-Null

    # Compilar sin cache
    Write-Host "🔨 Compilando imagen sin cache..." -ForegroundColor Yellow
    docker-compose build --no-cache

    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "✅ Imagen compilada correctamente" -ForegroundColor Green
        Write-Host ""
        Write-Host "🚀 Levantando PostgreSQL..." -ForegroundColor Green
        docker-compose up -d

        # Esperar a que la BD esté lista
        Write-Host "⏳ Esperando que PostgreSQL esté listo..." -ForegroundColor Yellow
        $maxAttempts = 30
        $attempt = 0

        while ($attempt -lt $maxAttempts) {
            $result = docker-compose exec -T postgres pg_isready -U utamed -d utamed 2>&1
            if ($LASTEXITCODE -eq 0) {
                Write-Host "✅ PostgreSQL está listo!" -ForegroundColor Green
                break
            }
            $attempt++
            Start-Sleep -Seconds 1
        }

        Write-Host ""
        Write-Host "📊 Detalles de la conexión:" -ForegroundColor Cyan
        Write-Host "  Host: localhost" -ForegroundColor White
        Write-Host "  Puerto: 5432" -ForegroundColor White
        Write-Host "  Base de datos: utamed" -ForegroundColor White
        Write-Host "  Usuario: utamed" -ForegroundColor White
        Write-Host "  Contraseña: utamed_dev_password" -ForegroundColor White
    } else {
        Write-Host "❌ Error al compilar la imagen" -ForegroundColor Red
        exit 1
    }
}
finally {
    Set-Location $originalLocation
}
