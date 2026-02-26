# Frontend Architecture & Component Interaction

## 📐 Component Hierarchy

```
├── Pages
│   ├── Programas_New.svelte (List View)
│   │   └── ProgramasListView.svelte
│   │       ├── CreateProgramaModal.svelte
│   │       │   ├── Input
│   │       │   ├── Label
│   │       │   ├── Textarea
│   │       │   └── Button
│   │       └── [Each Program Card]
│   │           ├── ProgramaStateBadges.svelte
│   │           ├── CompletenessProgressBar.svelte
│   │           ├── ProgramaActionButtons.svelte
│   │           │   └── [Approve/Reject Dialogs]
│   │           └── Button (View Details)
│   │
│   ├── ProgramaDetail.svelte (Detail View)
│   │   └── ProgramaDetailView.svelte
│   │       ├── ProgramaStateBadges.svelte
│   │       ├── CompletenessProgressBar.svelte
│   │       ├── SyllabusEditor.svelte (Edit Mode)
│   │       └── ProgramaActionButtons.svelte
│   │
│   └── ProgramaEdit.svelte (Edit Page)
│       └── SyllabusEditor.svelte
│           └── Section Editors (Text areas for each section)
│
├── Components (Reusables)
│   ├── CreateProgramaModal.svelte
│   ├── ProgramaStateBadges.svelte
│   ├── ProgramaActionButtons.svelte
│   ├── CompletenessProgressBar.svelte
│   ├── ProgramasListView.svelte
│   └── ProgramaDetailView.svelte
│
└── Utils
    └── formatters.ts
        ├── formatDate()
        ├── formatState()
        ├── formatTipoSyllabus()
        └── getEstadoColorClass()
```

---

## 🔄 Data Flow

### 1. List View → Detail View

```
Programas_New.svelte
    ↓
ProgramasListView.svelte
    ↓ (user clicks "Ver detalles")
router.visit('/admin/programas/{id}')
    ↓
ProgramaDetail.svelte
    ↓
ProgramaDetailView.svelte (displays with edit mode available)
```

### 2. Create Program Flow

```
ProgramasListView.svelte (click "Nuevo Programa")
    ↓
CreateProgramaModal opens
    ↓ (user fills form and submits)
POST /admin/cursos/{cursoId}/programas
    ↓
Backend creates programa with tipo_syllabus
    ↓
router.reload()
    ↓
Updated list displayed with new program
```

### 3. Edit Program Flow

```
ProgramaDetailView.svelte (click "Editar")
    ↓
Enter edit mode
    ↓
SyllabusEditor.svelte shows editable sections
    ↓ (user makes changes and saves)
PUT /admin/programas/{id}
    ↓
Backend updates data_syllabus
    ↓
Checks for BASICO→COMPLETO conversion
    ↓
router.reload()
    ↓
Detail view refreshed with new data
```

### 4. Admin Approval Flow

```
ProgramaActionButtons.svelte (COMPLETO estado)
    ↓ (click "Aprobar")
Approval confirmation dialog
    ↓ (confirm)
POST /admin/programas/{id}/approve
    ↓
Backend:
  - Validates all required sections
  - Changes estado to APROBADO
  - Sets revisado_por = admin_id
    ↓
onApprove callback
    ↓
router.reload()
    ↓
Program now shows APROBADO badge
```

### 5. Admin Rejection Flow

```
ProgramaActionButtons.svelte (APROBADO estado)
    ↓ (click "Rechazar")
Rejection modal with reason input
    ↓ (enter reason and confirm)
POST /admin/programas/{id}/reject
    + Data: { razon_rechazo: "..." }
    ↓
Backend:
  - Stores rejection reason
  - Estado returns to BASICO_COMPLETO or COMPLETO
  - Clears revisado_por field
    ↓
onReject callback
    ↓
router.reload()
    ↓
Program back in editable state
```

---

## 🎯 State Management

### Program State Transitions

```
CREATE
  ↓
BASICO_COMPLETO (if tipo='BASICO')
  ↓ (add sections III/IV/V/IX)
COMPLETO
  ↓ (admin approves)
APROBADO
  ↓ (admin approves)
PUBLICADO

OR

CREATE
  ↓
COMPLETO (if tipo='COMPLETO')
  ↓ (admin approves)
APROBADO
  ↓ (admin approves)
PUBLICADO
```

### Rejection Path

```
APROBADO
  ↓ (admin rejects)
BASICO_COMPLETO or COMPLETO (depending on tipo_syllabus)
  ↓ (editor can modify)
COMPLETO
  ↓ (admin approves again)
APROBADO
```

---

## 📱 Component Dependencies

### External UI Library

Required components from `resources/js/components/ui/`:
- `Button.svelte` - Used in all dialogs and action buttons
- `Input.svelte` - Form fields in CreateProgramaModal
- `Label.svelte` - Form labels
- `Textarea.svelte` - Multi-line text fields
- `Card.svelte` - (Optional) Wrapper for content sections

### Icons from lucide-svelte

- `Plus` - New item button
- `X` - Close/Cancel
- `Check` - Approve action
- `Eye` - View details
- `Edit2` - Edit action
- `Save` - Save changes
- `ArrowLeft` - Back button
- `ChevronRight` - Expand/Next
- `AlertCircle` - Warning/Info
- `Clock` - BASICO_COMPLETO state
- `CheckCircle` - APROBADO/PUBLICADO states

### Utilities

- `formatDate()` - Format timestamps
- `formatState()` - Convert internal states to display text
- `formatTipoSyllabus()` - Convert tipo to display text
- `getEstadoColorClass()` - Tailwind color classes for states

---

## 🔐 Permission Boundaries

### View Page Access
```
✅ Creator or Admin - Can view own/all programas
✅ Docent - Can view curso's programas
✅ Ayudante - Can view if authorized
```

### Create Program
```
✅ Admin - Full access
✅ Docent - Can create
❌ Ayudante - Cannot create
```

### Edit Program
```
✅ Creator (Docent/Admin) - Only own programs in BASICO_COMPLETO/COMPLETO
✅ Admin - Can edit any program in BASICO_COMPLETO/COMPLETO
❌ Others - No edit access
```

### Approve Program
```
✅ Admin - Only action available
❌ Others - No access (buttons hidden)
Condition: Program must be COMPLETO
```

### Reject Program
```
✅ Admin - Only action available
❌ Others - No access (buttons hidden)
Condition: Program must be APROBADO
```

---

## 📊 Component Prop Dependencies

```
Programas_New
  └─ props: { cursoId, cursoNombre, programas, userRole, userId }
     └─ ProgramasListView
        ├─ programas (passed down)
        ├─ cursoId (passed down)
        ├─ cursoNombre (passed down)
        ├─ userRole (passed down)
        ├─ userId (passed down)
        └─ [For each programa]
           ├─ CreateProgramaModal (child)
           │  └─ onClose()
           └─ [Programa Card]
              ├─ ProgramaStateBadges
              │  └─ estado, tipoSyllabus, completenessPercentage
              ├─ CompletenessProgressBar
              │  └─ percentage, tipo
              └─ ProgramaActionButtons
                 ├─ programa
                 ├─ userRole
                 ├─ userId
                 └─ callbacks: onApprove, onReject, onEdit

ProgramaDetail
  └─ props: { programa, userRole, userId }
     └─ ProgramaDetailView
        ├─ programa (passed down)
        ├─ userRole (passed down)
        ├─ userId (passed down)
        ├─ ProgramaStateBadges
        │  └─ estado, tipoSyllabus, completenessPercentage
        ├─ CompletenessProgressBar
        │  └─ percentage, tipo
        └─ SyllabusEditor (edit mode)

ProgramaEdit
  └─ props: { programa, userRole, userId }
     └─ Uses same structure as ProgramaDetail but edit-only
```

---

## 🔄 API Call Sequence

### User Creates Program

```
1. CreateProgramaModal renders
2. User selects tipo_syllabus (BASICO or COMPLETO)
3. User fills form
4. User clicks "Crear Programa"
5. POST /admin/cursos/{cursoId}/programas
   Payload:
   {
     tipo_syllabus: "BASICO" | "COMPLETO",
     secciones: { I: {...}, II: {...}, ... }
   }
6. Backend returns { success, programa }
7. router.reload()
8. New programa appears in list
```

### User Edits Program

```
1. User clicks Edit button (ProgramaActionButtons)
2. Open edit page or modal
3. SyllabusEditor displays sections
4. User modifies content
5. User clicks "Guardar"
6. PUT /admin/programas/{id}
   Payload:
   {
     data_syllabus: { metadata: {...}, secciones: {...} }
   }
7. Backend checks for auto-conversion
8. backend returns { success }
9. router.reload()
10. Refreshed data displayed
```

### Admin Approves Program

```
1. Admin sees COMPLETO estado
2. Clicks "Aprobar" button (ProgramaActionButtons)
3. Confirmation modal appears
4. Admin confirms
5. POST /admin/programas/{id}/approve
   Payload: {} (empty, just checking permissions)
6. Backend:
   - Validates user is Admin
   - Validates programa is COMPLETO
   - Calls validate() for all required sections
   - Updates estado to APROBADO
   - Updates revisado_por = admin_id
   - Returns { success, programa }
7. onApprove callback triggers
8. router.reload()
9. Programa now shows APROBADO badge
10. "Rechazar" button now visible (if admin)
```

### Admin Rejects Program

```
1. Admin sees APROBADO estado
2. Clicks "Rechazar" button
3. Modal opens for rejection reason
4. Admin enters reason: "Falta sección V"
5. Admin confirms
6. POST /admin/programas/{id}/reject
   Payload:
   {
     razon_rechazo: "Falta sección V"
   }
7. Backend:
   - Validates user is Admin
   - Validates programa is APROBADO
   - Stores razon_rechazo
   - Returns estado to previous (tipo-based):
     - BASICO_COMPLETO if tipo='BASICO'
     - COMPLETO if tipo='COMPLETO'
   - Clears revisado_por
   - Returns { success, programa }
8. onReject callback triggers
9. router.reload()
10. Programa reverts to editable state
11. Editor can now modify and resubmit
```

---

## 🎨 Styling Architecture

### Tailwind Configuration

All components use standard Tailwind utilities:
- Colors: blue, purple, green, teal, orange, yellow, gray
- Spacing: Standard TW scale (4px units)
- Breakpoints: Mobile-first (sm, md, lg, xl)
- Additional: rounded, border, shadow, transition classes

### Color Mapping

```
Estado             | BG Class            | Text Class         | Icon Color
─────────────────────────────────────────────────────────────────────────
BASICO_COMPLETO    | bg-blue-100         | text-blue-700      | text-blue-600
COMPLETO           | bg-purple-100       | text-purple-700    | text-purple-600
APROBADO           | bg-green-100        | text-green-700     | text-green-600
PUBLICADO          | bg-teal-100         | text-teal-700      | text-teal-600

Completeness %     | Progress Bar Color
─────────────────────────────────────────
0-49%              | bg-orange-500
50-74%             | bg-yellow-500
75-99%             | bg-blue-500
100%               | bg-green-500
```

---

## ⚡ Performance Considerations

1. **Lazy Loading**: Use Inertia.js to avoid full-page loads
2. **Memoization**: Computed properties use `$derived` for reactivity
3. **Filtered Lists**: Client-side search/filter for ~50-100 items
4. **Server Pagination**: If >100 items, implement backend pagination
5. **Component Splitting**: Each component is focused and reusable

---

## 🔍 Debug Checklist

When something doesn't work:

1. **Check Network Tab** - See actual API responses
2. **Check Browser Console** - Look for JS errors
3. **Check Style Inspector** - Verify Tailwind classes are applied
4. **Check Laravel Log** - See backend errors
5. **Check Route Mapping** - Verify Inertia routes are correct
6. **Check Props** - Ensure parent passes correct data types
7. **Check Permissions** - Verify user role is set correctly

---

## 📚 Related Documentation

- `FRONTEND_IMPLEMENTATION_GUIDE.md` - Detailed component documentation
- `FRONTEND_INTEGRATION_CHECKLIST.md` - Integration steps
- `PLAN_IMPLEMENTACION_PROGRAMAS.md` - Overall architecture
- `ARQUITECTURA_PROGRAMA_JSONB.md` - Data structure details
