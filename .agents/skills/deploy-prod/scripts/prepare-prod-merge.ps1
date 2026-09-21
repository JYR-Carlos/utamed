<#
.SYNOPSIS
    Script de diagnóstico previo al merge y despliegue a la rama produccion.
    Simula en memoria con git merge-tree para comprobar que no existan conflictos antes de tocar ramas.
#>

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host " [DEPLOY-PROD] DIAGNOSTICO PREVIO A PRODUCCION" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan

# 1. Detectar rama actual
$SourceBranch = (git branch --show-current).Trim()
if (-not $SourceBranch) {
    Write-Error "No se pudo determinar la rama actual de Git."
    exit 1
}

Write-Host "- Rama origen de trabajo: " -NoNewline
Write-Host "$SourceBranch" -ForegroundColor Yellow

if ($SourceBranch -eq "produccion") {
    Write-Host "`n[AVISO] Ya te encuentras en la rama 'produccion'." -ForegroundColor Cyan
    Write-Host "No es necesario realizar un merge. Puedes ejecutar directamente el despliegue:" -ForegroundColor Yellow
    Write-Host "  bash produccion/despliegue.sh" -ForegroundColor Gray
    exit 0
}

# 2. Verificar estado del worktree (no deben haber cambios sin commitear)
$StatusLines = git status --porcelain=v1
if ($StatusLines.Count -gt 0) {
    Write-Host "`n[VEREDICTO] [CANCELADO] WORKTREE SUCIO" -ForegroundColor Red
    Write-Host "Existen archivos modificados o sin seguimiento en la rama '$SourceBranch'." -ForegroundColor Red
    Write-Host "Para evitar perder cambios, debes hacer commit o stash antes de mergear a produccion:" -ForegroundColor Yellow
    Write-Host "  git status" -ForegroundColor Gray
    exit 1
}

# 3. Verificar operaciones pendientes
$GitDir = (git rev-parse --git-dir).Trim()
$RebaseMerge = Test-Path "$GitDir/rebase-merge"
$RebaseApply = Test-Path "$GitDir/rebase-apply"
$MergeHead   = Test-Path "$GitDir/MERGE_HEAD"

if ($RebaseMerge -or $RebaseApply -or $MergeHead) {
    Write-Host "`n[VEREDICTO] [CANCELADO] OPERACION PENDIENTE EN CURSO" -ForegroundColor Red
    Write-Host "Hay un rebase o merge incompleto en el repositorio." -ForegroundColor Red
    Write-Host "Cancela la operación pendiente antes de continuar:" -ForegroundColor Yellow
    Write-Host "  git merge --abort  (o git rebase --abort)" -ForegroundColor Gray
    exit 1
}

# 4. Actualizar referencias remotas
Write-Host "`nActualizando referencias remotas (git fetch -p origin)..." -ForegroundColor Gray
try {
    $null = git fetch -p origin 2>&1
} catch {
    Write-Warning "No se pudo contactar con origin. Se usaran las referencias cacheadas."
}

# 5. Verificar existencia de origin/produccion
$HasRemoteProd = (git rev-parse --verify origin/produccion 2>$null)
if (-not $HasRemoteProd) {
    Write-Error "La rama 'origin/produccion' no fue encontrada en el repositorio remoto."
    exit 1
}

# 6. Simulación en memoria con git merge-tree
Write-Host "Simulando integracion de '$SourceBranch' en 'origin/produccion' con git merge-tree..." -ForegroundColor Gray

$MergeTreeOutput = & git merge-tree --write-tree --messages origin/produccion HEAD 2>&1
$MergeTreeExit = $LASTEXITCODE

# Analizar colisiones
$ConflictLines = $MergeTreeOutput | Where-Object { $_ -match "^CONFLICT \(content\): Merge conflict in (.+)$" }
$ConflictFiles = @()
foreach ($line in $ConflictLines) {
    if ($line -match "^CONFLICT \(content\): Merge conflict in (.+)$") {
        $ConflictFiles += $Matches[1].Trim()
    }
}

$RealConflictsCount = $ConflictFiles.Count

Write-Host "------------------------------------------"
Write-Host " METRICAS CUANTITATIVAS DE INTEGRACION" -ForegroundColor Cyan
Write-Host "------------------------------------------"
Write-Host "Conflictos reales de codigo: $RealConflictsCount"

if ($RealConflictsCount -gt 0 -or $MergeTreeExit -ne 0) {
    Write-Host "`n[VEREDICTO] [CANCELADO] SE DETECTARON CONFLICTOS AL MERGEAR EN PRODUCCION" -ForegroundColor Red
    Write-Host "Los cambios colisionan directamente con la rama de produccion." -ForegroundColor Red
    Write-Host "Archivos en conflicto:" -ForegroundColor Yellow
    foreach ($f in $ConflictFiles) {
        Write-Host "  - $f" -ForegroundColor Red
    }
    Write-Host "`nAccion recomendada: Trae produccion a tu rama primero para resolver los conflictos localmente:" -ForegroundColor Yellow
    Write-Host "  git merge origin/produccion" -ForegroundColor Gray
    exit 1
}

Write-Host "`n[VEREDICTO] [SEGURO] 0 CONFLICTOS - LISTO PARA MERGE Y DESPLIEGUE" -ForegroundColor Green
Write-Host "La integracion es 100% limpia sin riesgo de colision." -ForegroundColor Gray
Write-Host "`nComandos recomendados para ejecutar el merge, push y despliegue:" -ForegroundColor Yellow

$Commands = @"
git checkout produccion
git pull --rebase origin produccion
git merge $SourceBranch --no-ff -m "merge: integrar $SourceBranch en produccion"
git push origin produccion
bash produccion/despliegue.sh
git checkout $SourceBranch
"@

Write-Host $Commands -ForegroundColor Gray
Write-Host "==========================================" -ForegroundColor Cyan
exit 0
