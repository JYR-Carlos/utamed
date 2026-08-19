#!/usr/bin/env pwsh
<#
Funciones compartidas por los scripts de base de datos.

Se dot-sourcea:  . (Join-Path $PSScriptRoot 'lib/comun.ps1')
#>

function Get-ValorEnv {
    <#
    .SYNOPSIS
    Lee una clave de un archivo .env.

    .DESCRIPTION
    Replica lo que hace phpdotenv en lo que importa: comillas y comentario al
    final de la linea. Un .env con `APP_ENV=production # despliegue` vale
    `production` para Laravel; cualquier lectura que no quite el comentario ve
    otra cosa y toma la decision equivocada.
    #>
    param(
        [Parameter(Mandatory = $true)][string]$Archivo,
        [Parameter(Mandatory = $true)][string]$Clave
    )

    if (-not (Test-Path $Archivo)) { return $null }

    $linea = Select-String -Path $Archivo -Pattern "^\s*$Clave\s*=" | Select-Object -First 1
    if (-not $linea) { return $null }

    $valor = ($linea.Line -replace "^\s*$Clave\s*=", '').Trim()

    if ($valor -match '^"([^"]*)"' -or $valor -match "^'([^']*)'") {
        return $Matches[1].Trim()
    }

    return ($valor -replace '\s+#.*$', '').Trim()
}

function Get-PhpConPgsql {
    <#
    .SYNOPSIS
    Devuelve la ruta de un binario de PHP que tenga pdo_pgsql, o $null.

    .DESCRIPTION
    El `php` del PATH suele ser herd-lite, que NO trae pdo_pgsql: con el,
    `artisan migrate` falla por driver y el error no tiene nada que ver con la
    migracion. Se prueban los candidatos en orden y se devuelve el primero que
    sirva.
    #>
    param([string]$Preferido)

    $candidatos = @()
    if ($Preferido)       { $candidatos += $Preferido }
    if ($env:PHP_BINARIO) { $candidatos += $env:PHP_BINARIO }
    $candidatos += (Join-Path $HOME '.config/herd/bin/php.bat')
    $candidatos += 'php'

    foreach ($c in $candidatos) {
        if (-not $c) { continue }
        try {
            $salida = & $c -r "echo extension_loaded('pdo_pgsql') ? 'SI' : 'NO';" 2>$null
            if ($LASTEXITCODE -eq 0 -and ($salida -join '') -match 'SI') { return $c }
        } catch {
            continue
        }
    }

    return $null
}

function Assert-PhpConPgsql {
    <#
    Igual que Get-PhpConPgsql, pero corta la ejecucion con un mensaje util en
    lugar de dejar que falle mas adelante con un error de driver.
    #>
    param([string]$Preferido)

    $php = Get-PhpConPgsql -Preferido $Preferido
    if ($php) { return $php }

    Write-Host ''
    Write-Host 'ERROR: no se encontro un binario de PHP con pdo_pgsql.' -ForegroundColor Red
    Write-Host 'El php del PATH suele ser herd-lite, que no trae el driver de Postgres.' -ForegroundColor Yellow
    Write-Host 'Opciones:' -ForegroundColor Yellow
    Write-Host '  - definir PHP_BINARIO con la ruta al PHP correcto, o' -ForegroundColor Yellow
    Write-Host '  - pasar -Php <ruta> al script.' -ForegroundColor Yellow
    Write-Host ''
    exit 1
}

function Get-ConfigTesting {
    <#
    .SYNOPSIS
    Lee las variables DB_* del entorno de tests desde phpunit.xml.

    .DESCRIPTION
    No se usa un .env.testing porque `.env*` esta en .gitignore: seria un archivo
    que cada quien tendria que crearse a mano y que se desincronizaria en
    silencio. phpunit.xml esta versionado y ya es la fuente de verdad del entorno
    de tests, asi que se lee de ahi.
    #>
    param([Parameter(Mandatory = $true)][string]$Raiz)

    $ruta = Join-Path $Raiz 'phpunit.xml'
    if (-not (Test-Path $ruta)) { return @{} }

    $xml = [xml](Get-Content $ruta -Raw)
    $vars = @{}
    foreach ($nodo in $xml.SelectNodes('//php/env')) {
        if ($nodo.name -like 'DB_*' -or $nodo.name -eq 'APP_ENV') {
            $vars[$nodo.name] = $nodo.value
        }
    }

    return $vars
}
