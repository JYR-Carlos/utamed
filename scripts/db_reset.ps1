#!/usr/bin/env pwsh
<#
.SYNOPSIS
Reconstruye la base de desarrollo o la de tests: reset + migrate + seed.

.DESCRIPTION
ATENCIÓN: esto DESTRUYE todos los datos. Ejecuta XX-reset_db.sql, que hace
DROP DATABASE. "Soft" sólo significa que no borra el volumen de Docker.
Para aplicar un cambio de modelo sin perder datos hay que usar migraciones:
ver docs/FLUJO_MIGRACIONES_ESQUEMA.md

Es el punto de entrada de `composer db:soft-reset` y `composer db:soft-reset-testing`.
Existe para que las tres partes de la reconstrucción usen el mismo PHP y la
misma base. Antes eran una línea suelta en composer.json que invocaba `php`
pelado (el del PATH, sin pdo_pgsql) y que, en modo testing, reseteaba el
contenedor de integración pero migraba y sembraba el de desarrollo.

.PARAMETER Testing
Trabaja sobre la base de integración (la de phpunit.xml) en vez de la de
desarrollo.

.PARAMETER Php
Ruta al binario de PHP. Si no se pasa, se busca uno con pdo_pgsql.

.PARAMETER SinSeed
Corre el reset y las migraciones, pero no db:seed.

.EXAMPLE
pwsh -File scripts/db_reset.ps1
pwsh -File scripts/db_reset.ps1 -Testing
#>

[CmdletBinding()]
param(
    [switch]$Testing,
    [string]$Php,
    [switch]$SinSeed
)

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'lib/comun.ps1')

$raiz = Split-Path -Parent $PSScriptRoot

# El PHP se resuelve ANTES de tocar la base: no tiene sentido destruirla y
# descubrir despues que no hay con que migrar.
$php = Assert-PhpConPgsql -Preferido $Php

# ---------------------------------------------------------------------------
# Entorno destino
# ---------------------------------------------------------------------------
if ($Testing) {
    $config = Get-ConfigTesting -Raiz $raiz
    if (-not $config['DB_DATABASE']) {
        Write-Host 'ERROR: no se pudo leer la configuracion DB_* de phpunit.xml.' -ForegroundColor Red
        exit 1
    }
    foreach ($clave in $config.Keys) {
        Set-Item -Path "env:$clave" -Value $config[$clave]
    }
    $env:APP_ENV = 'testing'
    $etiqueta = "testing ($($env:DB_HOST):$($env:DB_PORT)/$($env:DB_DATABASE))"
} else {
    $envApp = Get-ValorEnv -Archivo (Join-Path $raiz '.env') -Clave 'APP_ENV'
    $etiqueta = "desarrollo ($(Get-ValorEnv -Archivo (Join-Path $raiz '.env') -Clave 'DB_HOST')/$(Get-ValorEnv -Archivo (Join-Path $raiz '.env') -Clave 'DB_DATABASE'))"
    if ($envApp) { $env:APP_ENV = $envApp }
}

Write-Host ''
Write-Host "Reconstruyendo la base de $etiqueta" -ForegroundColor Cyan
Write-Host 'Esto hace DROP DATABASE: se pierden todos los datos.' -ForegroundColor Yellow
Write-Host ''

# ---------------------------------------------------------------------------
# 1. Reset (pasa por el wrapper, que es el que tiene la guarda de entorno)
# ---------------------------------------------------------------------------
$wrapper = Join-Path $raiz 'soft_reset.ps1'
if ($Testing) { & $wrapper --testing } else { & $wrapper }
if ($LASTEXITCODE -ne 0) {
    Write-Host 'El reset fallo. No se migra ni se siembra.' -ForegroundColor Red
    exit $LASTEXITCODE
}

# ---------------------------------------------------------------------------
# 2. Migraciones
# ---------------------------------------------------------------------------
Write-Host ''
Write-Host 'Aplicando migraciones...' -ForegroundColor Cyan
& $php (Join-Path $raiz 'artisan') migrate --force
if ($LASTEXITCODE -ne 0) {
    Write-Host 'artisan migrate fallo.' -ForegroundColor Red
    exit $LASTEXITCODE
}

# ---------------------------------------------------------------------------
# 3. Semillas
# ---------------------------------------------------------------------------
if (-not $SinSeed) {
    Write-Host ''
    Write-Host 'Sembrando...' -ForegroundColor Cyan
    & $php (Join-Path $raiz 'artisan') db:seed --force
    if ($LASTEXITCODE -ne 0) {
        Write-Host 'artisan db:seed fallo.' -ForegroundColor Red
        exit $LASTEXITCODE
    }
}

Write-Host ''
Write-Host "Base de $etiqueta reconstruida." -ForegroundColor Green
Write-Host ''
