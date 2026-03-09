$dir = "resources\js\pages\admin"

$rcBtnPrimary = 'class="inline-flex items-center gap-1.5 px-[1.125rem] py-[0.55rem] bg-gradient-to-br from-blue-500 to-blue-600 text-white border-0 rounded-lg text-sm font-medium cursor-pointer transition-all shadow-sm no-underline hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"'
$rcBtnGhost = 'class="inline-flex items-center gap-1.5 px-4 py-[0.55rem] bg-white text-gray-700 border border-gray-300 rounded-lg text-sm font-medium cursor-pointer no-underline hover:bg-gray-50 hover:border-gray-400 transition-all disabled:opacity-50 disabled:cursor-not-allowed"'

# ── 6. InscripcionesCursos.svelte ────────────────────────────────────────────
$f = "$dir\InscripcionesCursos.svelte"
$c = [System.IO.File]::ReadAllText($f)

# ESTADO_CFG: update badge class strings → Tailwind
$c = $c -replace "cls: 'badge-inscrito'", "cls: 'bg-emerald-100 text-emerald-800'"
$c = $c -replace "cls: 'badge-retirado'", "cls: 'bg-amber-100 text-amber-800'"
$c = $c -replace "cls: 'badge-anulado'", "cls: 'bg-gray-100 text-gray-500'"
$c = $c -replace "cls: 'badge-suspendido'", "cls: 'bg-indigo-100 text-indigo-800'"
$c = $c -replace "cls: 'badge-aprobado'", "cls: 'bg-emerald-100 text-emerald-800'"
$c = $c -replace "cls: 'badge-reprobado'", "cls: 'bg-red-100 text-red-700'"
# rowCls: keep short codes for use in template logic
$c = $c -replace "rowCls: ''", "rowCls: 'normal'"
$c = $c -replace "rowCls: 'row-dimmed'", "rowCls: 'dimmed'"
$c = $c -replace "rowCls: 'row-voided'", "rowCls: 'voided'"

# ── Page/Container classes
$c = $c -replace 'class="rc-page"', 'class="py-7 px-8 max-w-[1100px] mx-auto"'
$c = $c -replace 'class="rc-breadcrumb"', 'class="mb-4"'
$c = $c -replace 'class="rc-back-btn"', 'class="inline-flex items-center gap-1.5 text-[0.8125rem] text-gray-500 bg-transparent border-0 cursor-pointer p-0 hover:text-gray-900 transition-colors"'
$c = $c -replace 'class="rc-header"', 'class="flex justify-between items-start flex-wrap gap-4 mb-6"'
$c = $c -replace 'class="rc-header-left"', 'class="flex items-start gap-5 flex-wrap"'
$c = $c -replace 'class="rc-title"', 'class="text-[1.625rem] font-bold text-gray-900 mb-0.5 mt-0"'
$c = $c -replace 'class="rc-subtitle"', 'class="text-[0.8125rem] text-gray-500 m-0"'
$c = $c -replace 'class="rc-header-actions"', 'class="flex items-center gap-2.5 shrink-0"'

# ── Capacity pill
$c = $c -replace 'class="rc-capacity"', 'class="flex flex-col items-center bg-green-50 border-[1.5px] border-green-200 rounded-[10px] px-3.5 py-2"'
$c = $c -replace 'class="capacity-ring"', 'class="flex items-baseline gap-0.5"'
$c = $c -replace 'class="capacity-num"', 'class="text-xl font-extrabold text-green-600"'
$c = $c -replace 'class="capacity-sep"', 'class="text-sm text-gray-400"'
$c = $c -replace 'class="capacity-total"', 'class="text-base font-semibold text-gray-700"'
$c = $c -replace 'class="capacity-label"', 'class="text-[0.6875rem] text-gray-500 mt-0.5"'

# ── Buttons
$c = $c -replace 'class="rc-btn-primary"', $rcBtnPrimary
$c = $c -replace 'class="rc-btn-ghost"', $rcBtnGhost

# ── Loading/Empty
$c = $c -replace 'class="rc-loading"', 'class="flex items-center justify-center gap-3 py-12 text-gray-500"'
$c = $c -replace 'class="rc-spinner"', 'class="w-5 h-5 border-[2.5px] border-gray-200 border-t-blue-500 rounded-full animate-spin"'
$c = $c -replace 'class="rc-empty"', 'class="flex flex-col items-center gap-4 py-16 text-center"'
$c = $c -replace 'class="rc-empty-msg"', 'class="text-gray-500 text-[0.9375rem]"'
$c = $c -replace 'class="rc-error-msg"', 'class="text-red-600 text-sm"'

# ── Table
$c = $c -replace 'class="rc-table-wrap"', 'class="overflow-x-auto border border-gray-200 rounded-xl shadow-sm"'
$c = $c -replace 'class="rc-table"', 'class="w-full border-collapse text-sm"'
$c = $c -replace 'class="th-center"', 'class="px-4 py-3 text-center text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"'

# ── State badge elements
$c = $c -replace 'class="estado-wrapper"', 'class="relative inline-block"'
$c = $c -replace 'class="backdrop-close"', 'class="fixed inset-0 z-10"'
$c = $c -replace 'class="estado-menu"', 'class="absolute top-[calc(100%+6px)] left-0 z-20 bg-white border border-gray-200 rounded-lg shadow-[0_4px_16px_rgba(0,0,0,0.12)] min-w-[185px] overflow-hidden"'
$c = $c -replace 'class="estado-menu-item"', 'class="flex items-center gap-2 w-full px-3.5 py-2 text-[0.8125rem] text-gray-700 bg-white border-0 cursor-pointer text-left hover:bg-gray-100 transition-colors"'
$c = $c -replace 'class="t-icon"', 'class="text-sm"'
$c = $c -replace 'class="btn-delete"', 'class="p-1.5 text-gray-300 bg-transparent border-0 cursor-pointer rounded inline-flex items-center hover:bg-red-50 hover:text-red-500 transition-all"'

# ── Modal styles
$c = $c -replace 'class="modal-backdrop"', 'class="fixed inset-0 bg-black/45 z-50"'
$c = $c -replace 'class="modal-dialog"', 'class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[60] bg-white rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.18)] w-[min(540px,calc(100vw-2rem))] max-h-[88dvh] flex flex-col overflow-hidden"'
$c = $c -replace 'class="modal-header"', 'class="flex justify-between items-start px-6 pt-5 pb-4 border-b border-slate-100 shrink-0"'
$c = $c -replace 'class="modal-title"', 'class="text-[1.0625rem] font-bold text-gray-900 m-0"'
$c = $c -replace 'class="modal-subtitle"', 'class="text-[0.8125rem] text-gray-500 mt-0.5 mb-0"'
$c = $c -replace 'class="modal-close"', 'class="p-1.5 border-0 bg-transparent rounded-md text-gray-400 cursor-pointer hover:bg-gray-100 hover:text-gray-900 transition-all"'
$c = $c -replace 'class="modal-search-zone"', 'class="px-6 py-3.5 border-b border-slate-100 shrink-0"'
$c = $c -replace 'class="modal-search-wrap"', 'class="relative"'
$c = $c -replace 'class="search-icon"', 'class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"'
$c = $c -replace 'class="modal-search-input"', 'class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"'
$c = $c -replace 'class="bulk-error"', 'class="mt-2 flex items-center gap-1.5 text-red-600 text-[0.8125rem]"'
$c = $c -replace 'class="modal-list-area"', 'class="overflow-y-auto flex-1 min-h-0"'
$c = $c -replace 'class="modal-loading"', 'class="flex items-center justify-center gap-3 py-10 text-gray-500"'
$c = $c -replace 'class="modal-empty"', 'class="py-10 text-center text-gray-500 text-sm"'
$c = $c -replace 'class="select-all-row"', 'class="flex items-center gap-2.5 px-5 py-2.5 border-b border-slate-100 cursor-pointer hover:bg-gray-50"'
$c = $c -replace 'class="select-all-label"', 'class="text-sm font-medium text-gray-700"'
$c = $c -replace 'class="student-check-list"', 'class="divide-y divide-slate-100"'
$c = $c -replace 'class="student-check-info"', 'class="flex flex-col min-w-0"'
$c = $c -replace 'class="student-check-name"', 'class="text-sm font-medium text-gray-900 truncate"'
$c = $c -replace 'class="student-check-user"', 'class="text-xs text-gray-500"'
$c = $c -replace 'class="modal-footer"', 'class="px-6 py-4 border-t border-slate-100 flex justify-between items-center shrink-0"'
$c = $c -replace 'class="selected-counter"', 'class="text-sm text-gray-500"'
$c = $c -replace 'class="modal-footer-actions"', 'class="flex gap-2.5"'
$c = $c -replace 'class="btn-spinner"', 'class="inline-block w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2"'

# ── Course selector
$c = $c -replace 'class="sel-header"', 'class="mb-5"'
$c = $c -replace 'class="sel-search-wrap"', 'class="relative mb-5"'
$c = $c -replace 'class="sel-search-input"', 'class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"'
$c = $c -replace 'class="sel-grid"', 'class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-3"'
$c = $c -replace 'class="sel-card"', 'class="text-left bg-white border border-gray-200 rounded-xl p-4 cursor-pointer hover:border-blue-400 hover:shadow-md transition-all"'
$c = $c -replace 'class="sel-card-top"', 'class="flex justify-between items-center mb-1.5"'
$c = $c -replace 'class="sel-card-code"', 'class="text-xs font-mono font-semibold text-blue-600"'
$c = $c -replace 'class="sel-card-period"', 'class="text-xs text-gray-400"'
$c = $c -replace 'class="sel-card-name"', 'class="font-semibold text-gray-900 text-sm mb-1"'
$c = $c -replace 'class="sel-card-meta"', 'class="text-xs text-gray-500 mb-2.5"'
$c = $c -replace 'class="sel-card-arrow"', 'class="flex items-center gap-1 text-xs font-medium text-blue-500 mt-2"'

# ── Remove style block
$c = $c -replace '(?s)\s*<style>.*?</style>', ''
[System.IO.File]::WriteAllText($f, $c)
Write-Host "OK: InscripcionesCursos (phase 1 - simple replacements)"
