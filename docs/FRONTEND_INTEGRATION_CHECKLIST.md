# Integration Checklist - Two-Tier Syllabus Frontend

## Step 1: Component Files Created ✓

All new Svelte components have been created in:
- `resources/js/components/custom/admin/CreateProgramaModal.svelte`
- `resources/js/components/custom/admin/ProgramaStateBadges.svelte`
- `resources/js/components/custom/admin/ProgramaActionButtons.svelte`
- `resources/js/components/custom/admin/CompletenessProgressBar.svelte`
- `resources/js/components/custom/admin/ProgramasListView.svelte`
- `resources/js/components/custom/admin/ProgramaDetailView.svelte`
- `resources/js/utils/formatters.ts` - Helper functions

## Step 2: Page Components Created ✓

Main page components:
- `resources/js/pages/admin/Programas_New.svelte` - List view with new features
- `resources/js/pages/admin/ProgramaDetail.svelte` - Individual program detail view

## Step 3: Update Laravel Routes

**In `routes/web.php`:**

```php
Route::middleware(['auth', 'role:admin,docente'])->prefix('admin')->group(function () {
    // Programs routes - Update existing
    Route::get('/cursos/{cursoId}/programas', [ProgramaController::class, 'index'])->name('programas.index');
    Route::post('/cursos/{cursoId}/programas', [ProgramaController::class, 'store'])->name('programas.store');
    
    // New routes for detail and actions
    Route::get('/programas/{id}', [ProgramaController::class, 'show'])->name('programas.show');
    Route::put('/programas/{id}', [ProgramaController::class, 'update'])->name('programas.update');
    Route::get('/programas/{id}/edit', [ProgramaController::class, 'edit'])->name('programas.edit');
    
    // Admin-only approval routes
    Route::post('/programas/{id}/approve', [ProgramaController::class, 'approve'])
        ->middleware('role:admin')
        ->name('programas.approve');
    Route::post('/programas/{id}/reject', [ProgramaController::class, 'reject'])
        ->middleware('role:admin')
        ->name('programas.reject');
});
```

## Step 4: Update ProgramaController Methods

### Update the `index()` method:

```php
public function index($cursoId)
{
    $curso = Curso::findOrFail($cursoId);
    $this->authorize('view', $curso);
    
    $programas = Programa::where('id_curso', $cursoId)
        ->with(['creator', 'reviewer'])
        ->orderBy('fecha_creacion', 'desc')
        ->get()
        ->map(function ($p) {
            $p->completenessPercentage = $this->programaService->calculateCompletenessPercentage($p);
            return $p;
        });
    
    return inertia('admin/Programas_New', [
        'cursoId' => $cursoId,
        'cursoNombre' => $curso->nombre_curso,
        'programas' => $programas,
        'userRole' => Auth::user()->rol ?? 'usuario',
        'userId' => Auth::id(),
    ]);
}
```

### Add `show()` method:

```php
public function show($id)
{
    $programa = Programa::with(['creator', 'reviewer'])->findOrFail($id);
    
    $this->authorize('view', $programa);
    
    $programa->completenessPercentage = $this->programaService->calculateCompletenessPercentage($programa);
    
    return inertia('admin/ProgramaDetail', [
        'programa' => $programa,
        'userRole' => Auth::user()->rol ?? 'usuario',
        'userId' => Auth::id(),
    ]);
}
```

### Add `update()` method:

```php
public function update($id)
{
    $programa = Programa::findOrFail($id);
    
    $this->authorize('update', $programa);
    
    $validated = request()->validate([
        'data_syllabus' => 'required|array'
    ]);
    
    $programa->update([
        'data_syllabus' => $validated['data_syllabus'],
        'es_actual' => true
    ]);
    
    // Check for automatic conversion
    $tipoOriginal = $programa->getTipoSyllabus();
    if ($this->shouldConvertToCompleto($programa)) {
        $programa->data_syllabus['metadata']['tipo_syllabus'] = 'COMPLETO';
        $programa->estado = 'COMPLETO';
        $programa->save();
    }
    
    return redirect()->back()->with('success', 'Programa actualizado');
}

private function shouldConvertToCompleto($programa)
{
    if ($programa->getTipoSyllabus() !== 'BASICO') return false;
    
    $secciones = $programa->data_syllabus['secciones'] ?? [];
    $optionalSections = ['III', 'IV', 'V', 'IX'];
    
    foreach ($optionalSections as $section) {
        if (isset($secciones[$section]) && !empty($secciones[$section])) {
            return true;
        }
    }
    
    return false;
}
```

## Step 5: Update Model Relationships

**In `Programa.php` Model:**

Make sure relationships are defined:

```php
public function creator()
{
    return $this->belongsTo(Usuario::class, 'creado_por', 'id_usuario');
}

public function reviewer()
{
    return $this->belongsTo(Usuario::class, 'revisado_por', 'id_usuario');
}
```

## Step 6: Verify Backend Methods Exist

Ensure these methods exist in **ProgramaService** and are working:

```php
// In ProgramaService.php
public function calculateCompletenessPercentage($programa): int
{
    // Should return 0-100 based on required sections
}

public function getRequiredSecciones($tipo): array
{
    // Returns ['I','II','VI','VII','VIII'] for BASICO, or ['I'...'IX'] for COMPLETO
}

public function isComplete($programa): bool
{
    // Check if all required sections have content
}
```

## Step 7: Test the Integration

### Test 1: List View
1. Navigate to `/admin/cursos/{cursoId}/programas`
2. Should see new list with:
   - State badges (BASICO_COMPLETO, COMPLETO, APROBADO, PUBLICADO)
   - Tipo badges (Básico/Completo)
   - Completeness progress bars
   - Filter buttons
   - Search functionality
   - Creator/reviewer info

### Test 2: Create New Program
1. Click "Nuevo Programa" button
2. Select BASICO or COMPLETO
3. Fill in course information
4. Submit
5. Should create program in state BASICO_COMPLETO or COMPLETO

### Test 3: Edit Program
1. Click edit button on a program card (if creator or admin)
2. Launch modal to edit sections
3. Save changes
4. If adding sections III/IV/V/IX to BASICO, should auto-convert to COMPLETO

### Test 4: Approve/Reject (Admin only)
1. As admin, navigate to program in COMPLETO state
2. Click "Aprobar" (Approve) button
3. Confirm in modal
4. State should change to APROBADO
5. revisado_por should be filled with admin user ID

### Test 5: Reject Program
1. As admin, navigate to program in APROBADO state
2. Click "Rechazar" (Reject) button
3. Enter rejection reason
4. State should revert to BASICO_COMPLETO or COMPLETO
5. revisado_por should be cleared

## Step 8: Configure Inertia Route Mapping

**In `config/inertia.php` or your Inertia setup:**

Make sure routes are properly mapped:

```php
// If using resolve helper
resolve('inertia')->loadFrom([
    'routes' => [
        'programas' => [
            'index' => 'admin/Programas_New',
            'show' => 'admin/ProgramaDetail',
            'create' => 'admin/CreatePrograma', // optional
        ]
    ]
]);
```

## Step 9: Verify Icons and Dependencies

All components use **lucide-svelte** icons. Ensure it's installed:

```bash
npm install lucide-svelte
```

Check component imports are working:
```svelte
import { Plus, X, Check, Eye, Edit2, ArrowLeft, ChevronRight, AlertCircle } from 'lucide-svelte';
```

## Step 10: Verify UI Component Library

Ensure your UI component library has:
- `Button` - with variants (default, outline, destructive)
- `Input` - text inputs
- `Textarea` - multiline input
- `Label` - form labels

These should be in `resources/js/components/ui/`

## Step 11: Build Frontend

```bash
npm run build
# or for development with watch
npm run dev
```

## Step 12: Database/Migration Considerations

No new migrations needed! The existing `programa` table should have:
- `estado` (VARCHAR, stores new states)
- `data_syllabus` (JSONB, stores metadata with tipo_syllabus)
- `creado_por` (INTEGER, FK to usuario)
- `revisado_por` (INTEGER, nullable, FK to usuario)

Current migration is sufficient.

## Common Issues & Solutions

### Issue: "Cannot find module '@/components/ui/button'"
**Solution:** Check your `vite.config.js` has the alias configured:
```js
import { fileURLToPath } from 'node:url'
export default defineConfig({
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url))
    }
  }
})
```

### Issue: Components not visible/styled
**Solution:** 
1. Run `npm run build`
2. Clear browser cache
3. Check Tailwind purge includes `resources/js/**/*.{svelte,ts}`

### Issue: API calls returning 404
**Solution:**
1. Check route definitions in `routes/web.php`
2. Verify controller methods exist
3. Check CSRF token in meta tag: `<meta name="csrf-token">`

### Issue: Authorization errors on approve/reject
**Solution:**
1. Ensure user has 'admin' role
2. Check `ProgramaPolicy::approve()` and `reject()` methods
3. Verify `Auth::user()->rol` returns correct role name

### Issue: Programa not showing completeness percentage
**Solution:**
1. Ensure `ProgramaService::calculateCompletenessPercentage()` is implemented
2. Verify `programas.map()` in controller calls the method
3. Check required sections are defined correctly for tipo_syllabus

## Next Steps

After integration:

1. **Test thoroughly** in development environment
2. **Add error handling** in components for network failures
3. **Add loading states** for long API calls
4. **Add success notifications** after actions
5. **Consider adding** bulk operations for admin
6. **Add audit logging** for approve/reject actions
7. **Create student view** (simplified, read-only version)

---

**Support:** Refer to `FRONTEND_IMPLEMENTATION_GUIDE.md` for detailed component documentation.
