<#
.SYNOPSIS
    Script auxiliar para la skill make-commits.
    Clasifica los archivos del worktree (modificados, staged, untracked) por dominios lógicos.
#>

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host " [MAKE-COMMITS] INSPECCION DE WORKTREE" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan

# 1. Obtener lista completa de archivos modificados y sin seguimiento
$StatusLines = git status -uall --porcelain=v1
if (-not $StatusLines) {
    Write-Host "[OK] El árbol de trabajo está completamente limpio. No hay cambios pendientes." -ForegroundColor Green
    exit 0
}

$Modified = @()
$Untracked = @()
$Deleted = @()

foreach ($line in $StatusLines) {
    if ($line.Length -ge 4) {
        $Code = $line.Substring(0, 2)
        $File = $line.Substring(3).Trim()

        if ($Code.Contains("?") ) {
            $Untracked += $File
        } elseif ($Code.Contains("D")) {
            $Deleted += $File
        } else {
            $Modified += $File
        }
    }
}

Write-Host "`n--- Resumen de Cambios ---" -ForegroundColor Yellow
Write-Host "Archivos Modificados: $($Modified.Count)"
Write-Host "Archivos Nuevos (Untracked): $($Untracked.Count)"
Write-Host "Archivos Eliminados: $($Deleted.Count)"

Write-Host "`n--- Agrupacion por Dominios ---" -ForegroundColor Yellow

$AllFiles = $Modified + $Untracked + $Deleted

$Groups = [ordered]@{
    "Tooling y Agentes"     = @($AllFiles | Where-Object { $_ -match "^\.agents/" })
    "Modelos y DB"           = @($AllFiles | Where-Object { $_ -match "^app/Models/|^database/" -and $_ -notmatch "^\.agents/" })
    "Servicios y Logica"     = @($AllFiles | Where-Object { $_ -match "^app/Services/|^app/Actions/|^app/Jobs/" })
    "Controladores y Rutas"  = @($AllFiles | Where-Object { $_ -match "^app/Http/|^routes/" })
    "Comandos CLI y Consola" = @($AllFiles | Where-Object { $_ -match "^app/Console/|^scripts/" })
    "Pruebas (Tests)"        = @($AllFiles | Where-Object { $_ -match "^tests/" })
    "Frontend / UI"          = @($AllFiles | Where-Object { $_ -match "^resources/|^public/" })
    "Configuracion y Docs"   = @($AllFiles | Where-Object { $_ -match "^config/|^docs/|\.md$|\.json$|\.env" -and $_ -notmatch "^\.agents/|^app/Models/" })
}

foreach ($groupKey in $Groups.Keys) {
    $items = $Groups[$groupKey]
    if ($items.Count -gt 0) {
        Write-Host "`n[$groupKey] ($($items.Count) archivos):" -ForegroundColor Cyan
        $items | ForEach-Object { Write-Host "  - $_" }
    }
}

Write-Host "`n==========================================" -ForegroundColor Cyan
Write-Host " Inspeccion completada." -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan
