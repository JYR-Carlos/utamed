<#
.SYNOPSIS
    Script de diagnóstico de seguridad cuantitativo para pull & rebase.
    Utiliza git merge-tree para simular en memoria y medir el número exacto de conflictos.
#>

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host " [SAFE-PULL-REBASE] DIAGNOSTICO CUANTITATIVO" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan

# 1. Detectar rama actual
$CurrentBranch = (git branch --show-current).Trim()
if (-not $CurrentBranch) {
    Write-Error "No se pudo determinar la rama actual de Git."
}

# 2. Verificar operaciones pendientes (rebase/merge a medias)
$GitDir = (git rev-parse --git-dir).Trim()
$RebaseMerge = Test-Path "$GitDir/rebase-merge"
$RebaseApply = Test-Path "$GitDir/rebase-apply"
$MergeHead   = Test-Path "$GitDir/MERGE_HEAD"

if ($RebaseMerge -or $RebaseApply -or $MergeHead) {
    Write-Host "`n[VEREDICTO] [CANCELADO] OPERACION PENDIENTE EN CURSO" -ForegroundColor Red
    Write-Host "Hay un rebase o merge en conflicto o incompleto en el repositorio." -ForegroundColor Red
    Write-Host "Comandos para resolver:" -ForegroundColor Yellow
    Write-Host "  - Para cancelar el rebase pendiente: git rebase --abort" -ForegroundColor Gray
    Write-Host "  - Para cancelar el merge pendiente:  git merge --abort" -ForegroundColor Gray
    exit 1
}

# 3. Refrescar referencias remotas si es posible
Write-Host "`nActualizando referencias con origin (git fetch -p origin)..." -ForegroundColor Gray
try {
    $null = git fetch -p origin 2>&1
} catch {
    Write-Warning "No se pudo contactar con origin (usando referencias locales cacheadas)."
}

# 4. Determinar rama remota
$RemoteBranch = "origin/$CurrentBranch"
$HasRemote = (git rev-parse --verify $RemoteBranch 2>$null)

if (-not $HasRemote) {
    Write-Host "`n[VEREDICTO] [SEGURO] RAMA SIN SEGUIMIENTO REMOTO" -ForegroundColor Green
    Write-Host "La rama '$CurrentBranch' no existe aun en origin. Puedes publicarla con:" -ForegroundColor Yellow
    Write-Host "  git push -u origin $CurrentBranch" -ForegroundColor Gray
    exit 0
}

# 5. Commits ahead / behind
$Counts = (git rev-list --left-right --count "$RemoteBranch...HEAD").Trim()
$Behind, $Ahead = $Counts -split '\s+'

Write-Host "`nEstado de Commits:"
Write-Host "  Commits locales por delante (ahead): $Ahead"
Write-Host "  Commits remotos por traer (behind):   $Behind"

if ([int]$Behind -eq 0) {
    Write-Host "`n[VEREDICTO] [SEGURO] YA ESTA AL DIA" -ForegroundColor Green
    Write-Host "No hay commits nuevos en $RemoteBranch. No es necesario hacer pull." -ForegroundColor Gray
    exit 0
}

# 6. Estado del Worktree (modificaciones locales sin commitear)
$StatusLines = git status --porcelain=v1
$LocalDirty = $StatusLines.Count -gt 0

# 7. Simulación en memoria con git merge-tree
Write-Host "`nSimulando integracion en memoria con git merge-tree..." -ForegroundColor Gray
$MergeTreeProcess = Start-Process -FilePath "git" -ArgumentList "merge-tree", "--write-tree", "--messages", "HEAD", $RemoteBranch -NoNewWindow -PassThru -RedirectStandardOutput "$env:TEMP\merge_tree_out.txt" -RedirectStandardError "$env:TEMP\merge_tree_err.txt"
$MergeTreeProcess.WaitForExit()
$MergeTreeExitCode = $MergeTreeProcess.ExitCode

$MergeTreeOutput = Get-Content "$env:TEMP\merge_tree_out.txt" -Raw -ErrorAction SilentlyContinue
if (-not $MergeTreeOutput) { $MergeTreeOutput = "" }

# Extraer métricas cuantitativas
$AutoMergeLines = @($MergeTreeOutput -split "`n" | Where-Object { $_ -match "^Auto-merging\s+(.+)$" })
$ConflictLines   = @($MergeTreeOutput -split "`n" | Where-Object { $_ -match "CONFLICT\s*\((.+)\):\s*(.+)$" })

$AutoMergedFiles = @($AutoMergeLines | ForEach-Object { ($_ -replace "^Auto-merging\s+", "").Trim() })
$ConflictedFiles = @($ConflictLines | ForEach-Object { ($_ -replace "^CONFLICT.*:\s*Merge conflict in\s+", "").Trim() })

Write-Host "------------------------------------------" -ForegroundColor Cyan
Write-Host " METRICAS CUANTITATIVAS DE COLISION" -ForegroundColor Cyan
Write-Host "------------------------------------------" -ForegroundColor Cyan
Write-Host "Archivos auto-fusionables (sin colision de lineas): $($AutoMergedFiles.Count)"
if ($AutoMergedFiles.Count -gt 0) {
    $AutoMergedFiles | ForEach-Object { Write-Host "  [OK] $_" -ForegroundColor Green }
}

Write-Host "Conflictos reales de codigo (colision de lineas):   $($ConflictedFiles.Count)"
if ($ConflictedFiles.Count -gt 0) {
    $ConflictedFiles | ForEach-Object { Write-Host "  [!] $_" -ForegroundColor Red }
}

# 8. Emitir Veredicto
if ($ConflictedFiles.Count -gt 0) {
    Write-Host "`n[VEREDICTO] [CANCELADO] $($ConflictedFiles.Count) CONFLICTO(S) REAL(ES) DE CODIGO" -ForegroundColor Red
    Write-Host "Se cancela el pull rebase preventivamente para evitar marcadores de conflicto no deseados." -ForegroundColor Red
    
    if ($ConflictedFiles.Count -ge 3) {
        Write-Host "`n[!] Estructura compleja detectada ($($ConflictedFiles.Count) conflictos). Sugerencia:" -ForegroundColor Yellow
        Write-Host "  git branch backup/pre-rebase-$(Get-Date -Format 'yyyyMMdd-HHmm')" -ForegroundColor Gray
    }
    
    Write-Host "`nArchivos que requieren atencion:" -ForegroundColor Yellow
    $ConflictedFiles | ForEach-Object { Write-Host "  - $_" -ForegroundColor Red }
    exit 1
} elseif ($LocalDirty) {
    Write-Host "`n[VEREDICTO] [PRECAUCION] 0 CONFLICTOS REALES (WORKTREE SUCIO)" -ForegroundColor Yellow
    Write-Host "Los commits son 100% compatibles, pero hay cambios sin guardar en el worktree." -ForegroundColor Yellow
    Write-Host "`nAccion recomendada:" -ForegroundColor Yellow
    Write-Host "  git stash push -u -m 'safe-pull-stash'" -ForegroundColor Gray
    Write-Host "  git pull --rebase origin $CurrentBranch" -ForegroundColor Cyan
    Write-Host "  git stash pop" -ForegroundColor Gray
} else {
    Write-Host "`n[VEREDICTO] [SEGURO] 0 CONFLICTOS REALES - PROCEDER CON PULL REBASE" -ForegroundColor Green
    Write-Host "La integracion es limpia. 'git pull --rebase' realiza todo el trabajo directamente:" -ForegroundColor Green
    Write-Host "`nComando directo:" -ForegroundColor Yellow
    Write-Host "  git pull --rebase origin $CurrentBranch" -ForegroundColor Cyan
}

Write-Host "`n==========================================" -ForegroundColor Cyan
