<#
.SYNOPSIS
    Script auxiliar para la skill branch-review.
    Extrae métricas, lista de commits, diffs y detecta posibles antipatrones.

.PARAMETER BaseBranch
    Nombre de la rama base de comparación (por defecto: main).
#>

param (
    [string]$BaseBranch = "main"
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# 1. Detectar rama actual
$CurrentBranch = (git branch --show-current).Trim()
if (-not $CurrentBranch) {
    Write-Error "No se pudo determinar la rama actual de Git."
}

# 2. Verificar existencia de la rama base
$MergeBase = ""
try {
    $MergeBase = (git merge-base $BaseBranch HEAD).Trim()
} catch {
    # Intentar con origin/$BaseBranch
    try {
        $BaseBranch = "origin/$BaseBranch"
        $MergeBase = (git merge-base $BaseBranch HEAD).Trim()
    } catch {
        Write-Warning "Rama base '$BaseBranch' no encontrada. Comparando contra HEAD~10."
        $MergeBase = (git rev-parse HEAD~10 2>$null)
    }
}

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host " [BRANCH REVIEW] ANALISIS DE RAMA: $CurrentBranch" -ForegroundColor Green
Write-Host " Base de comparacion: $BaseBranch ($MergeBase)" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan

# 3. Estado Ahead / Behind
$Counts = (git rev-list --left-right --count "$BaseBranch...HEAD").Trim()
$Behind, $Ahead = $Counts -split '\s+'
Write-Host "Commits: $Ahead por delante (ahead), $Behind por detras (behind) de $BaseBranch"

# 4. Lista de commits
Write-Host "`n--- Historial de Commits ---" -ForegroundColor Yellow
$Commits = git log "$BaseBranch..HEAD" --oneline --reverse
if ($Commits) {
    $Commits | ForEach-Object { Write-Host "  $_" }
} else {
    Write-Host "  (No hay commits nuevos respecto a $BaseBranch)" -ForegroundColor Gray
}

# 5. Estadísticas de archivos
Write-Host "`n--- Estadisticas de Cambio (Diff Stat) ---" -ForegroundColor Yellow
git diff "$BaseBranch...HEAD" --shortstat
Write-Host ""
git diff "$BaseBranch...HEAD" --stat

# 6. Detección rápida de antipatrones en mensajes
Write-Host "`n--- Deteccion de Antipatrones en Mensajes ---" -ForegroundColor Yellow
$WipMatches = git log "$BaseBranch..HEAD" --grep="wip" --grep="fix" --grep="test" --grep="cambios" --grep="arreglos" --grep="temp" -i --oneline
if ($WipMatches) {
    Write-Host "[!] Commits con posibles mensajes vagos/WIP detectados:" -ForegroundColor Red
    $WipMatches | ForEach-Object { Write-Host "   $_" -ForegroundColor Red }
} else {
    Write-Host "[OK] No se detectaron patrones de mensajes vagos evidentes." -ForegroundColor Green
}

# 7. Detección de logs de depuración en el diff
Write-Host "`n--- Deteccion de Sentencias de Depuracion en Diff ---" -ForegroundColor Yellow
$DebugPatterns = @("console\.log", "dd\(", "dump\(", "var_dump\(", "print_r\(", "debugger;")
$PatternRegex = ($DebugPatterns -join "|")

$DebugMatches = git diff "$BaseBranch...HEAD" -G "$PatternRegex" --name-only
if ($DebugMatches) {
    Write-Host "[!] Archivos con posibles sentencias de depuracion activas:" -ForegroundColor Red
    $DebugMatches | ForEach-Object { Write-Host "   $_" -ForegroundColor Red }
} else {
    Write-Host "[OK] No se detectaron sentencias de depuracion obvias en los cambios." -ForegroundColor Green
}

Write-Host "`n==========================================" -ForegroundColor Cyan
Write-Host " Analisis completado." -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan
