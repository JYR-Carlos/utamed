$dir = "resources\js\pages\admin"
$btn = 'class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white border-0 rounded-lg font-medium cursor-pointer transition-all shadow-sm active:scale-95"'
$inp = 'class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:bg-gray-100 disabled:cursor-not-allowed"'

# ── 7. DetalleMalla.svelte ───────────────────────────────────────────────────
$f = "$dir\DetalleMalla.svelte"
$c = [System.IO.File]::ReadAllText($f)

# ── Base page
$c = $c -replace 'class="page-container"', 'class="p-8 max-w-[1400px] mx-auto"'
$c = $c -replace 'class="page-header"', 'class="flex justify-between items-start mb-8 gap-8"'
$c = $c -replace 'class="page-title"', 'class="text-3xl font-bold text-gray-900"'
$c = $c -replace 'class="page-description"', 'class="text-sm text-gray-500 mt-0.5"'
$c = $c -replace 'class="btn-primary"', $btn
$c = $c -replace 'class="form-row"', 'class="grid grid-cols-2 gap-4"'
$c = $c -replace 'class="form-group"', 'class="mb-4"'
$c = $c -replace 'class="form-label"', 'class="block text-sm font-medium text-gray-700 mb-2"'
$c = $c -replace 'class="form-input"', $inp

# ── Header extras
$c = $c -replace 'class="header-actions"', 'class="flex gap-4 items-center"'
$c = $c -replace 'class="credits-badge"', 'class="bg-blue-50 px-6 py-3 rounded-lg border-2 border-blue-500"'
$c = $c -replace 'class="credits-label"', 'class="text-blue-800 text-sm font-medium mr-2"'
$c = $c -replace 'class="credits-value"', 'class="text-blue-800 text-2xl font-bold"'

# ── Malla grid
$c = $c -replace 'class="malla-container"', 'class="flex flex-col gap-8"'
$c = $c -replace 'class="year-section"', 'class="bg-white rounded-xl p-6 shadow-sm"'
$c = $c -replace 'class="year-title"', 'class="text-2xl font-bold text-gray-900 mb-6 pb-3 border-b-2 border-gray-200"'
$c = $c -replace 'class="semesters-grid"', 'class="grid grid-cols-2 gap-6"'
$c = $c -replace 'class="semester-column"', 'class="min-h-[200px]"'
$c = $c -replace 'class="semester-title"', 'class="text-lg font-semibold text-gray-700 mb-4 px-3 py-2 bg-gray-50 rounded-md"'
$c = $c -replace 'class="asignaturas-list"', 'class="flex flex-col gap-3"'
$c = $c -replace 'class="empty-message"', 'class="text-gray-400 text-center py-8 italic"'
$c = $c -replace 'class="empty-state"', 'class="text-center py-16 bg-white rounded-xl shadow-sm"'

# ── Asignatura card
$c = $c -replace 'class="asignatura-card"', 'class="bg-gray-50 border border-gray-200 rounded-lg p-4 transition-all hover:border-blue-400 hover:shadow-[0_2px_8px_rgba(59,130,246,0.1)]"'
$c = $c -replace 'class="asignatura-header"', 'class="flex justify-between items-center mb-2"'
$c = $c -replace 'class="asignatura-code"', 'class="font-mono font-semibold text-blue-500 text-sm"'
$c = $c -replace 'class="asignatura-credits"', 'class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-semibold"'
$c = $c -replace 'class="asignatura-name"', 'class="text-gray-900 font-medium text-sm mb-2"'
$c = $c -replace 'class="asignatura-type"', 'class="inline-block bg-gray-100 text-gray-500 px-2 py-1 rounded text-xs mb-2"'
$c = $c -replace 'class="asignatura-actions"', 'class="flex gap-2 mt-3"'
$c = $c -replace 'class="btn-edit"', 'class="px-3 py-1.5 bg-blue-50 text-blue-700 border-0 rounded text-xs font-medium cursor-pointer hover:bg-blue-100 transition-colors"'
$c = $c -replace 'class="btn-delete"', 'class="px-3 py-1.5 bg-red-50 text-red-600 border-0 rounded text-xs font-medium cursor-pointer hover:bg-red-100 transition-colors"'

# ── Search dropdown (asignatura selector)
$c = $c -replace 'class="asignatura-search-container"', 'class="relative"'
$c = $c -replace 'class="search-input form-input"', 'class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:bg-gray-100 disabled:cursor-not-allowed"'
$c = $c -replace 'class="asignaturas-dropdown"', 'class="absolute top-full left-0 right-0 bg-white border border-gray-300 border-t-0 rounded-b-md max-h-[300px] overflow-y-auto z-10 shadow-[0_4px_12px_rgba(0,0,0,0.15)]"'
$c = $c -replace '"asignaturas-dropdown empty"', '"p-4 text-center bg-white border border-gray-300 border-t-0 rounded-b-md absolute top-full left-0 right-0 z-10"'
$c = $c -replace 'class="no-results"', 'class="text-gray-400 text-sm m-0"'
$c = $c -replace 'class="dropdown-item"', 'class="w-full px-3.5 py-3 border-0 border-b border-gray-100 last:border-0 bg-white text-left cursor-pointer flex items-center gap-3 hover:bg-blue-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"'
$c = $c -replace 'class="dropdown-item-code"', 'class="font-mono font-semibold text-blue-500 text-sm min-w-[80px]"'
$c = $c -replace 'class="dropdown-item-info"', 'class="flex-1 min-w-0"'
$c = $c -replace 'class="dropdown-item-name"', 'class="text-gray-900 font-medium text-sm truncate"'
$c = $c -replace 'class="dropdown-item-credits"', 'class="text-gray-400 text-xs mt-0.5"'

# ── Error alert
$c = $c -replace 'class="error-alert"', 'class="flex gap-4 p-4 mb-4 bg-red-50 border border-red-200 rounded-lg items-start"'
$c = $c -replace 'class="error-icon"', 'class="text-2xl shrink-0"'
$c = $c -replace 'class="error-content"', 'class="flex-1 min-w-0"'
$c = $c -replace 'class="error-title"', 'class="m-0 mb-1 text-red-600 font-semibold text-sm"'
$c = $c -replace 'class="error-message"', 'class="m-0 text-red-800 text-sm leading-relaxed break-words"'

# ── Remove style block
$c = $c -replace '(?s)\s*<style>.*?</style>', ''
[System.IO.File]::WriteAllText($f, $c)
Write-Host "OK: DetalleMalla"
