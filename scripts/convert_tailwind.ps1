$dir = "resources\js\pages\admin"

$btn = 'class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white border-0 rounded-lg font-medium cursor-pointer transition-all shadow-sm active:scale-95"'
$inp = 'class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"'

function ConvertBase($file) {
    $c = [System.IO.File]::ReadAllText($file)
    $c = $c -replace 'class="page-container"', 'class="p-8 max-w-6xl mx-auto"'
    $c = $c -replace 'class="page-header"', 'class="flex justify-between items-start mb-8"'
    $c = $c -replace 'class="page-title"', 'class="text-3xl font-bold text-gray-900 mb-1"'
    $c = $c -replace 'class="page-description"', 'class="text-sm text-gray-500"'
    $c = $c -replace 'class="btn-primary"', $btn
    $c = $c -replace 'class="form-row"', 'class="grid grid-cols-2 gap-4"'
    $c = $c -replace 'class="form-group"', 'class="mb-4"'
    $c = $c -replace 'class="form-label"', 'class="block text-sm font-medium text-gray-700 mb-2"'
    $c = $c -replace 'class="form-input"', $inp
    $c = $c -replace '(?s)\s*<style>.*?</style>', ''
    [System.IO.File]::WriteAllText($file, $c)
    Write-Host "OK: $file"
}

# ── 1. Asignaturas ──────────────────────────────────────────────────────────
ConvertBase("$dir\Asignaturas.svelte")

# ── 2. Departamentos ────────────────────────────────────────────────────────
ConvertBase("$dir\Departamentos.svelte")

# ── 3. Facultades (+ flash messages) ────────────────────────────────────────
$f = "$dir\Facultades.svelte"
$c = [System.IO.File]::ReadAllText($f)
$c = $c -replace 'class="page-container"', 'class="p-8 max-w-6xl mx-auto"'
$c = $c -replace 'class="page-header"', 'class="flex justify-between items-start mb-8"'
$c = $c -replace 'class="page-title"', 'class="text-3xl font-bold text-gray-900 mb-1"'
$c = $c -replace 'class="page-description"', 'class="text-sm text-gray-500"'
$c = $c -replace 'class="btn-primary"', $btn
$c = $c -replace 'class="form-group"', 'class="mb-4"'
$c = $c -replace 'class="form-label"', 'class="block text-sm font-medium text-gray-700 mb-2"'
$c = $c -replace 'class="form-input"', $inp
$c = $c -replace 'class="flash flash-success"', 'class="px-4 py-3 rounded-md text-sm mb-4 bg-green-50 border border-green-200 text-green-800"'
$c = $c -replace 'class="flash flash-error"', 'class="px-4 py-3 rounded-md text-sm mb-4 bg-red-50 border border-red-200 text-red-800"'
$c = $c -replace '(?s)\s*<style>.*?</style>', ''
[System.IO.File]::WriteAllText($f, $c)
Write-Host "OK: Facultades"

# ── 4. Usuarios (+ tipo-selector) ───────────────────────────────────────────
$f = "$dir\Usuarios.svelte"
$c = [System.IO.File]::ReadAllText($f)
$c = $c -replace 'class="page-container"', 'class="p-8 max-w-6xl mx-auto"'
$c = $c -replace 'class="page-header"', 'class="flex justify-between items-start mb-6"'
$c = $c -replace 'class="page-title"', 'class="text-3xl font-bold text-gray-900 mb-1"'
$c = $c -replace 'class="page-description"', 'class="text-sm text-gray-500"'
$c = $c -replace 'class="btn-primary"', $btn
$c = $c -replace 'class="form-row"', 'class="grid grid-cols-2 gap-4"'
$c = $c -replace 'class="form-group"', 'class="mb-4"'
$c = $c -replace 'class="form-label"', 'class="block text-sm font-medium text-gray-700 mb-2"'
$c = $c -replace 'class="form-input"', $inp
$c = $c -replace 'class="form-divider"', 'class="h-px bg-gray-200 my-4 mb-4"'
$c = $c -replace 'class="form-section-title"', 'class="text-sm font-semibold text-gray-700 mb-3"'
# tipo-selector container
$c = $c -replace 'class="tipo-selector"', 'class="flex gap-2 p-1 bg-gray-100 rounded-lg w-fit mb-6"'
# tipo-btn with class:active — replace the full button pattern for each tipo
$c = $c -replace 'class="tipo-btn" class:active=\{currentTipo === ''estudiante''\}', 'class="{currentTipo === `''estudiante''` ? `''px-5 py-2 border-0 rounded-md font-medium cursor-pointer transition-all bg-white text-blue-500 shadow-sm''` : `''px-5 py-2 border-0 rounded-md font-medium cursor-pointer transition-all bg-transparent text-gray-500 hover:text-gray-700''`}"'
$c = $c -replace 'class="tipo-btn" class:active=\{currentTipo === ''docente''\}', 'class="{currentTipo === `''docente''` ? `''px-5 py-2 border-0 rounded-md font-medium cursor-pointer transition-all bg-white text-blue-500 shadow-sm''` : `''px-5 py-2 border-0 rounded-md font-medium cursor-pointer transition-all bg-transparent text-gray-500 hover:text-gray-700''`}"'
$c = $c -replace 'class="tipo-btn" class:active=\{currentTipo === ''administrador''\}', 'class="{currentTipo === `''administrador''` ? `''px-5 py-2 border-0 rounded-md font-medium cursor-pointer transition-all bg-white text-blue-500 shadow-sm''` : `''px-5 py-2 border-0 rounded-md font-medium cursor-pointer transition-all bg-transparent text-gray-500 hover:text-gray-700''`}"'
$c = $c -replace '(?s)\s*<style>.*?</style>', ''
[System.IO.File]::WriteAllText($f, $c)
Write-Host "OK: Usuarios"
