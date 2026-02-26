# Frontend Implementation Guide - Two-Tier Syllabus System

## Overview

This document describes the new Svelte components created for the two-tier syllabus system with states: `BASICO_COMPLETO`, `COMPLETO`, `APROBADO`, and `PUBLICADO`.

## Components Structure

### 1. **CreateProgramaModal.svelte**
Dialog for creating new programs with tipo_syllabus selection (BASICO or COMPLETO).

**Location:** `resources/js/components/custom/admin/CreateProgramaModal.svelte`

**Props:**
```svelte
interface Props {
  isOpen: boolean;           // Controls dialog visibility
  cursoId: number;           // Course ID for the program
  cursoNombre: string;       // Course name display
  onClose: () => void;       // Callback when dialog closes
}
```

**Features:**
- Radio buttons to select BASICO (5 sections) or COMPLETO (9 sections)
- Form for Sección I (course identification)
- Form for Sección II (description and purpose)
- Validation: min 100 characters for description, positive credits
- Auto-fills other sections with placeholder data
- POST to `/admin/cursos/{cursoId}/programas`

**Usage:**
```svelte
<CreateProgramaModal
  isOpen={showCreateModal}
  cursoId={123}
  cursoNombre="Programming Fundamentals"
  onClose={() => (showCreateModal = false)}
/>
```

---

### 2. **ProgramaStateBadges.svelte**
Displays status badges for programa estado, tipo_syllabus, and completeness percentage.

**Location:** `resources/js/components/custom/admin/ProgramaStateBadges.svelte`

**Props:**
```svelte
interface Props {
  estado: string;                        // 'BASICO_COMPLETO' | 'COMPLETO' | 'APROBADO' | 'PUBLICADO'
  tipoSyllabus?: string;                // 'BASICO' | 'COMPLETO'
  completenessPercentage?: number;      // 0-100
}
```

**Badge Colors:**
- BASICO_COMPLETO: Blue badge with Clock icon
- COMPLETO: Purple badge with AlertCircle icon
- APROBADO: Green badge with CheckCircle icon
- PUBLICADO: Teal badge with CheckCircle icon
- Type badge: Shows "Básico (5)" or "Completo (9)"
- Completeness: Orange badge only if > 0%

**Usage:**
```svelte
<ProgramaStateBadges
  estado="COMPLETO"
  tipoSyllabus="BASICO"
  completenessPercentage={75}
/>
```

---

### 3. **CompletenessProgressBar.svelte**
Shows completeness percentage with color-coded progress bar.

**Location:** `resources/js/components/custom/admin/CompletenessProgressBar.svelte`

**Props:**
```svelte
interface Props {
  percentage: number;   // 0-100
  tipo: string;         // 'BASICO' | 'COMPLETO'
  showLabel?: boolean;  // Default: true
}
```

**Colors:**
- 0-49%: Orange (incompleto)
- 50-74%: Yellow (almost complete)
- 75-99%: Blue (80% complete)
- 100%: Green (ready for approval)

**Usage:**
```svelte
<CompletenessProgressBar
  percentage={85}
  tipo="COMPLETO"
/>
```

---

### 4. **ProgramaActionButtons.svelte**
Action buttons for edit, view, approve, and reject (role-based).

**Location:** `resources/js/components/custom/admin/ProgramaActionButtons.svelte`

**Props:**
```svelte
interface Props {
  programa: Programa;
  userRole: string;                    // 'admin' | 'docent' | 'ayudante' | etc
  userIdl: number;                     // Current user ID
  onApprove: (id: number) => void;
  onReject: (id: number, razon: string) => void;
  onEdit: (id: number) => void;
}
```

**Behavior:**
- Edit: Visible if user is creator OR admin, and estado is BASICO_COMPLETO or COMPLETO
- Approve: Only for Admin, only if estado === 'COMPLETO'
- Reject: Only for Admin, only if estado === 'APROBADO'
  - Opens modal for rejection reason input
- View: Always visible

**API Calls:**
- POST `/admin/programas/{id}/approve` - Admin approval
- POST `/admin/programas/{id}/reject` - Admin rejection with razon_rechazo

**Usage:**
```svelte
<ProgramaActionButtons
  programa={programData}
  userRole="admin"
  userIdl={userId}
  onApprove={(id) => router.reload()}
  onReject={(id, razon) => router.reload()}
  onEdit={(id) => router.visit(`/admin/programas/${id}/edit`)}
/>
```

---

### 5. **ProgramasListView.svelte**
Main list view showing all programs with filtering and pagination.

**Location:** `resources/js/components/custom/admin/ProgramasListView.svelte`

**Props:**
```svelte
interface Props {
  programas: Programa[];
  cursoId: number;
  cursoNombre: string;
  userRole: string;
  userId: number;
}
```

**Features:**
- Search by asignatura or codigo
- Filter buttons by estado (BASICO_COMPLETO, COMPLETO, APROBADO, PUBLICADO)
- Shows count per filter
- Completeness progress bar per program
- Creator and reviewer info
- Creation date
- Integrated action buttons (Edit, Approve/Reject)
- Create modal trigger

**Statistics Display:**
- All, BASICO_COMPLETO, COMPLETO, APROBADO, PUBLICADO counts

**Usage:**
```svelte
<ProgramasListView
  programas={allPrograms}
  cursoId={123}
  cursoNombre="Programming I"
  userRole="admin"
  userId={5}
/>
```

---

### 6. **ProgramaDetailView.svelte**
Detailed view of a single program with edit capability.

**Location:** `resources/js/components/custom/admin/ProgramaDetailView.svelte`

**Props:**
```svelte
interface Props {
  programa: Programa;
  userRole: string;
  userId: number;
}
```

**Features:**
- Back button to list view
- Full programa information (version, código, metadata)
- Completeness bar
- Creator, reviewer, and section info
- Edit button (conditional based on permissions)
- SyllabusEditor integration for editing
- BASICO→COMPLETO conversion notice when editing
- Save/Cancel buttons
- PUT `/admin/programas/{id}` API call for updates

**Edit Restrictions:**
- Only creator or admin can edit
- Program must be in BASICO_COMPLETO or COMPLETO state

**Usage:**
```svelte
<ProgramaDetailView
  programa={programDetail}
  userRole="docent"
  userId={userId}
/>
```

---

### 7. **Page Components**

#### **Programas_New.svelte** (Main Programs List Page)
**Location:** `resources/js/pages/admin/Programas_New.svelte`

**Expected Inertia Props:**
```typescript
interface Props {
  cursoId: number;
  cursoNombre: string;
  programas: Programa[];
  userRole: string;
  userId: number;
}
```

**Usage in Controller:**
```php
return inertia('admin/Programas_New', [
    'cursoId' => $curso->id_curso,
    'cursoNombre' => $curso->nombre_curso,
    'programas' => $programas->load('creator', 'reviewer'),
    'userRole' => Auth::user()->rol,
    'userId' => Auth::id(),
]);
```

---

#### **ProgramaDetail.svelte** (Single Program Detail Page)
**Location:** `resources/js/pages/admin/ProgramaDetail.svelte`

**Expected Inertia Props:**
```typescript
interface Props {
  programa: Programa;
  userRole: string;
  userId: number;
}
```

**Usage in Controller:**
```php
return inertia('admin/ProgramaDetail', [
    'programa' => $programa->load('creator', 'reviewer'),
    'userRole' => Auth::user()->rol,
    'userId' => Auth::id(),
]);
```

---

## Data Structure Requirements

### Programa Model Expected Fields

All components expect the Programa object to have this structure:

```typescript
{
  id_programa: number;
  version_programa: number;
  estado: string; // 'BASICO_COMPLETO' | 'COMPLETO' | 'APROBADO' | 'PUBLICADO'
  creado_por: number;
  revisado_por?: number;
  fecha_creacion: string; // ISO 8601 date
  data_syllabus: {
    metadata?: {
      tipo_syllabus: string;      // 'BASICO' | 'COMPLETO'
      curso?: string;             // Course code
      asignatura?: string;        // Course name
      creditos?: number;
    };
    secciones?: {
      I?: { contenido: any };
      II?: { contenido: any };
      III?: { contenido: any };
      IV?: { contenido: any };
      V?: { contenido: any };
      VI?: { contenido: any };
      VII?: { contenido: any };
      VIII?: { contenido: any };
      IX?: { contenido: any };
    };
  };
  completenessPercentage?: number;  // 0-100, calculated by backend
  creator?: {
    id_usuario: number;
    nombre_completo: string;
  };
  reviewer?: {
    id_usuario: number;
    nombre_completo: string;
  };
}
```

---

## Backend Integration Points

### Controller Changes Required

The ProgramaController needs to be updated to:

1. **Update `index()` method:**
   - Calculate `completenessPercentage` per programa
   - Load `creator` and `reviewer` relationships
   - Return BASICO_COMPLETO/COMPLETO/APROBADO/PUBLICADO states

```php
public function index($cursoId)
{
    $programas = Programa::where('id_curso', $cursoId)
        ->with(['creator', 'reviewer'])
        ->get()
        ->map(function ($p) {
            $p->completenessPercentage = $this->programaService->calculateCompletenessPercentage($p);
            return $p;
        });
    
    return inertia('admin/Programas_New', [
        'cursoId' => $cursoId,
        'cursoNombre' => Curso::find($cursoId)->nombre_curso,
        'programas' => $programas,
        'userRole' => Auth::user()->rol,
        'userId' => Auth::id(),
    ]);
}
```

2. **Add `show()` method for detail page:**
```php
public function show($id)
{
    $programa = Programa::with(['creator', 'reviewer'])->findOrFail($id);
    $programa->completenessPercentage = $this->programaService->calculateCompletenessPercentage($programa);
    
    return inertia('admin/ProgramaDetail', [
        'programa' => $programa,
        'userRole' => Auth::user()->rol,
        'userId' => Auth::id(),
    ]);
}
```

3. **Create/Update methods:**
   - Already implemented in backend - components POST/PUT to existing endpoints

---

## API Endpoints Expected

| Method | Endpoint                            | Description                                   |
| ------ | ----------------------------------- | --------------------------------------------- |
| POST   | `/admin/cursos/{cursoId}/programas` | Create new program (from CreateProgramaModal) |
| GET    | `/admin/programas/{id}`             | Get program detail                            |
| PUT    | `/admin/programas/{id}`             | Update program/syllabus content               |
| POST   | `/admin/programas/{id}/approve`     | Admin approve program                         |
| POST   | `/admin/programas/{id}/reject`      | Admin reject program with reason              |

---

## Styling Notes

All components use **Tailwind CSS** with:
- Custom color scheme for states
- Responsive design (mobile-first)
- Accessible badges and buttons
- Consistent spacing and typography

---

## Future Enhancements

1. Export programa to PDF
2. Version history tracking
3. Bulk approve/reject
4. Comments/notes system for admin feedback
5. Rich text editor for secciones content
6. Student view (simplified, read-only)

---

## Troubleshooting

### Components not rendering
- Ensure all imports are correct (using `@/` alias)
- Check that all required props are passed
- Verify Tailwind CSS is properly configured

### API calls failing
- Check CSRF token is present in meta tag
- Verify endpoint URLs match your Laravel routes
- Check user authentication/authorization

### Styling issues
- Ensure tailwindcss is built: `npm run build`
- Check for CSS class conflicts
- Use browser dev tools to inspect elements

### Type errors
- Ensure TypeScript types match backend data structure
- Import types from correct locations
- Check prop interfaces match component definitions
