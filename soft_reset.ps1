#!/usr/bin/env pwsh
<#
.SYNOPSIS
Wrapper para database-model/scripts/soft_reset.ps1

.DESCRIPTION
Ejecuta el script de reset de la base de datos desde la raíz del proyecto.

ATENCIÓN: esto DESTRUYE todos los datos. El script subyacente ejecuta
XX-reset_db.sql, que hace DROP DATABASE. "Soft" sólo significa que no borra el
volumen de Docker. Para aplicar un cambio de modelo sin perder datos, usar
migraciones: ver docs/FLUJO_MIGRACIONES_ESQUEMA.md

.PARAMETER Testing
Flag para ejecutar el reset en la base de datos de testing.

.PARAMETER Force
Corre aunque no se pueda determinar el entorno. No sirve para saltarse la
prohibición en producción: ahí aborta igual.

.EXAMPLE
.\soft_reset.ps1
.\soft_reset.ps1 --testing
#>

# $PSScriptRoot y no $MyInvocation: este último queda vacío si el script se
# invoca dot-sourced o vía pwsh -Command, y la guarda no encontraría el .env.
$rootDir = $PSScriptRoot

# ---------------------------------------------------------------------------
# Guardia: este script destruye datos. Nunca debe correr en producción.
#
# Falla CERRADA. Antes, un entorno que no se pudiera determinar se asumía de
# desarrollo y el script seguía adelante hasta el DROP DATABASE; y sólo se
# reconocían los valores exactos "production" y "prod", así que "staging" o
# "prod-eu" pasaban de largo. Ahora sólo corre si el entorno está en la lista
# blanca de entornos desechables.
# ---------------------------------------------------------------------------
$entornosPermitidos = @('local', 'development', 'dev', 'testing', 'test')

. (Join-Path $rootDir 'scripts/lib/comun.ps1')

$appEnv = $env:APP_ENV
if (-not $appEnv) {
    $appEnv = Get-ValorEnv -Archivo (Join-Path $rootDir '.env') -Clave 'APP_ENV'
}
$appEnv = if ($appEnv) { $appEnv.Trim() } else { '' }

$forzar = ($args -contains '--force') -or ($args -contains '-Force')

if (-not $appEnv) {
    Write-Host ''
    Write-Host 'ABORTADO: no se pudo determinar APP_ENV.' -ForegroundColor Red
    Write-Host 'Este script hace DROP DATABASE; sin saber el entorno no se ejecuta.' -ForegroundColor Red
    Write-Host 'Define APP_ENV en .env o en el entorno, o pasa --force si sabes lo que haces.' -ForegroundColor Yellow
    Write-Host ''
    if (-not $forzar) { exit 1 }
    Write-Host '--force activo: se continúa sin conocer el entorno.' -ForegroundColor Yellow
}
elseif ($entornosPermitidos -notcontains $appEnv.ToLowerInvariant()) {
    Write-Host ''
    Write-Host "ABORTADO: APP_ENV=$appEnv no está en la lista de entornos desechables." -ForegroundColor Red
    Write-Host "Permitidos: $($entornosPermitidos -join ', ')" -ForegroundColor Red
    Write-Host 'Este script hace DROP DATABASE. Los datos no se recuperan.' -ForegroundColor Red
    Write-Host 'Para aplicar un cambio de esquema sin borrar: php artisan migrate' -ForegroundColor Yellow
    Write-Host 'Ver docs/FLUJO_MIGRACIONES_ESQUEMA.md' -ForegroundColor Yellow
    Write-Host ''
    exit 1
}

# La guarda mira APP_ENV, pero lo que el script borra lo decide DB_*. Un .env con
# APP_ENV=local apuntando a una base compartida pasa la guarda igual, así que se
# muestra el destino antes de tocarlo.
$dbHost = Get-ValorEnv -Archivo (Join-Path $rootDir '.env') -Clave 'DB_HOST'
$dbName = Get-ValorEnv -Archivo (Join-Path $rootDir '.env') -Clave 'DB_DATABASE'
if ($dbHost -and $dbHost -notmatch '^(127\.0\.0\.1|localhost|::1)$') {
    Write-Host ''
    Write-Host "ABORTADO: DB_HOST=$dbHost no es local." -ForegroundColor Red
    Write-Host 'Este script sólo está pensado para la base de desarrollo en Docker.' -ForegroundColor Red
    Write-Host ''
    exit 1
}
Write-Host "Destino del reset: $dbHost/$dbName (APP_ENV=$appEnv)" -ForegroundColor DarkGray

# Construir la ruta del script real
$scriptPath = Join-Path $rootDir "database-model" "scripts" "soft_reset.ps1"

# Verificar que el script existe
if (-not (Test-Path $scriptPath)) {
    Write-Error "Script no encontrado: $scriptPath"
    exit 1
}

# Pasar todos los argumentos tal cual (sin procesarlos como parámetros)
& $scriptPath @args

cd ..  ## Volver a la raíz del proyecto después de ejecutar el script