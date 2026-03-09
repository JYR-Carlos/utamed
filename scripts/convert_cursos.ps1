$dir = "resources\js\pages\admin"

$btn = 'class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white border-0 rounded-lg font-medium cursor-pointer transition-all shadow-sm active:scale-95"'
$inp = 'class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"'

# ── 5. Cursos.svelte ─────────────────────────────────────────────────────────
$f = "$dir\Cursos.svelte"
$c = [System.IO.File]::ReadAllText($f)
# Base page classes
$c = $c -replace 'class="page-container"', 'class="p-8 max-w-6xl mx-auto"'
$c = $c -replace 'class="page-header"', 'class="flex justify-between items-start mb-8"'
$c = $c -replace 'class="page-title"', 'class="text-3xl font-bold text-gray-900 mb-1"'
$c = $c -replace 'class="page-description"', 'class="text-sm text-gray-500"'
$c = $c -replace 'class="btn-primary"', $btn
$c = $c -replace 'class="form-row"', 'class="grid grid-cols-2 gap-4"'
$c = $c -replace 'class="form-group"', 'class="mb-4"'
$c = $c -replace 'class="form-label"', 'class="block text-sm font-medium text-gray-700 mb-2"'
$c = $c -replace 'class="form-input"', $inp
# Sections-specific classes
$c = $c -replace 'class="secciones-list"', 'class="flex flex-col gap-3 mb-8"'
$c = $c -replace 'class="seccion-item"', 'class="flex items-center gap-4 p-3 bg-slate-50 border border-slate-200 rounded-lg"'
$c = $c -replace 'class="seccion-info"', 'class="min-w-[120px]"'
$c = $c -replace 'class="badget"', 'class="inline-block px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-semibold"'
$c = $c -replace 'class="seccion-docente"', 'class="flex-1"'
$c = $c -replace 'class="btn-icon"', 'class="p-2 text-red-500 bg-transparent border-0 cursor-pointer rounded-md flex items-center justify-center hover:bg-red-50"'
$c = $c -replace 'class="add-seccion-form"', 'class="p-4 bg-slate-50 border border-dashed border-slate-300 rounded-lg"'
# Docente classes
$c = $c -replace 'class="docente-display"', 'class="flex items-center gap-2 p-3 bg-white border border-slate-200 rounded-md"'
$c = $c -replace 'class="docente-name"', 'class="flex-1 text-sm text-slate-800 font-medium"'
$c = $c -replace 'class="docente-empty"', 'class="text-sm text-slate-400 italic"'
$c = $c -replace 'class="btn-edit-docente"', 'class="p-1 text-sky-600 bg-transparent border-0 cursor-pointer rounded flex items-center justify-center hover:bg-sky-100 transition-colors"'
$c = $c -replace 'class="edit-docente-form"', 'class="p-4 bg-gray-50 border-2 border-blue-500 rounded-lg my-4"'
$c = $c -replace 'class="docente-details-panel"', 'class="p-4 bg-sky-50 border border-blue-200 rounded-lg my-4"'
$c = $c -replace 'class="details-grid"', 'class="grid grid-cols-2 gap-4"'
$c = $c -replace 'class="detail-item"', 'class="flex flex-col gap-1"'
$c = $c -replace 'class="detail-label"', 'class="text-xs font-semibold text-slate-500 uppercase tracking-wide"'
$c = $c -replace 'class="detail-value"', 'class="text-sm text-slate-800 font-medium"'
# form-actions + btn-secondary + btn-action + btn-inscriptions
$c = $c -replace 'class="form-actions"', 'class="flex gap-3 mt-4"'
$c = $c -replace 'class="btn-secondary"', 'class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-md font-medium cursor-pointer transition-all hover:bg-slate-50 hover:border-slate-300 disabled:opacity-50 disabled:cursor-not-allowed"'
$c = $c -replace 'class="btn-action"', 'class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium bg-white text-gray-700 cursor-pointer transition-all hover:bg-gray-50 hover:border-gray-400"'
$c = $c -replace 'class="btn-inscriptions"', 'class="bg-blue-50 text-blue-800 border-blue-300 hover:bg-blue-100 hover:border-blue-400"'
# Remove style block
$c = $c -replace '(?s)\s*<style>.*?</style>', ''
[System.IO.File]::WriteAllText($f, $c)
Write-Host "OK: Cursos"
