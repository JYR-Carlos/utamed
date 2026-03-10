#!/usr/bin/env pwsh
<#
.SYNOPSIS
Wrapper para database-model/scripts/soft_reset.ps1

.DESCRIPTION
Ejecuta el script de reset suave de la base de datos desde la raíz del proyecto.

.PARAMETER Testing
Flag para ejecutar el reset en la base de datos de testing.

.EXAMPLE
.\soft_reset.ps1
.\soft_reset.ps1 --testing
#>

# Obtener la ruta del script wrapper (raíz del proyecto)
$rootDir = Split-Path -Parent $MyInvocation.MyCommand.Path

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