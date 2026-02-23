# 📋 GUÍA RÁPIDA: Implementing the Complete Programa Workflow

## 📂 Archivos Plantilla Creados

Las siguientes plantillas se encuentran en la raíz del proyecto:

1. **PLANTILLA_Admin_ProgramaController.php** → Copiar a `app/Http/Controllers/Admin/ProgramaController.php`
2. **PLANTILLA_Student_ProgramaController.php** → Copiar a `app/Http/Controllers/Student/ProgramaController.php`
3. **PLANTILLA_Cambios_Administrativo_ProgramaController.txt** → Guía de qué agregar en `Administrativo/ProgramaController.php`
4. **PLANTILLA_admin_Programas.svelte** → Copiar a `resources/js/pages/admin/Programas.svelte`
5. **PLANTILLA_student_Courses_Programa.svelte** → Copiar a `resources/js/pages/student/Courses/Programa.svelte`

---

## 🗺️ ORDEN DE IMPLEMENTACIÓN RECOMENDADO

### FASE 1: Base de Datos (30 minutos)

```bash
# 1. Abrir PgModeler
# 2. En tabla "administrativo.programa", agregar estos campos:
#    - aprobado_por: INTEGER, nullable, FK a usuario.usuario
#    - rechazado_por: INTEGER, nullable, FK a usuario.usuario
#    - fecha_aprobacion: TIMESTAMP, nullable
#    - fecha_rechazo: TIMESTAMP, nullable
#    - razon_rechazo: VARCHAR(500), nullable
#    - unc_programa: SMALLINT NOT NULL DEFAULT 1

# 3. Agregar índices:
#    - idx_programa_estado (estado)
#    - idx_programa_aprobado_por (aprobado_por)
#    - idx_programa_id_curso_estado (id_curso, estado)

# 4. Exportar dump.sql
# 5. Copiar estructura en database-model/init_scripts/01-sql_def.sql

# 6. Crear y ejecutar migración:
php artisan make:migration add_programa_approval_fields
# (Agregar los campos nuevos y índices)
php artisan migrate
```

---

### FASE 2: Permisos (10 minutos)

**Archivo: `app/Support/Permissions.php`**

Buscar línea con `const CURSOS_PROGRAMAS_VER` y agregar después:

```php
const CURSOS_PROGRAMAS_CREAR = 'cursos/programas:crear';
const CURSOS_PROGRAMAS_EDITAR = 'cursos/programas:editar';
const CURSOS_PROGRAMAS_APROBAR = 'cursos/programas:aprobar';
const CURSOS_PROGRAMAS_RECHAZAR = 'cursos/programas:rechazar';
const CURSOS_PROGRAMAS_VER_BORRADOR = 'cursos/programas:ver_borrador';
const CURSOS_PROGRAMAS_VER_APROBADO = 'cursos/programas:ver_aprobado';
```

---

### FASE 3: Controladores (45 minutos)

#### 3A. Crear Admin/ProgramaController.php

```bash
# Copiar contenido de PLANTILLA_Admin_ProgramaController.php a:
# app/Http/Controllers/Admin/ProgramaController.php
```

#### 3B. Crear Student/ProgramaController.php

```bash
# Copiar contenido de PLANTILLA_Student_ProgramaController.php a:
# app/Http/Controllers/Student/ProgramaController.php
```

#### 3C. Actualizar Administrativo/ProgramaController.php

Seguir instrucciones en `PLANTILLA_Cambios_Administrativo_ProgramaController.txt`:
- Importar `Log`
- Cambiar método `store()` para estado inicial = BORRADOR
- Agregar método `submit()` al final

---

### FASE 4: Políticas (20 minutos)

**Archivo: `app/Policies/ProgramaPolicy.php`**

Validar que existen estos métodos (agregar si falta):

```php
// Actualizar método view()
public function view(Usuario $user, Programa $model): bool
{
    // Admin siempre
    if ($this->isAdmin($user)) {
        return true;
    }

    // Docente creador
    if ($this->isAssignedDocente($user, $model)) {
        return true;
    }

    // Estudiante SOLO si programa está APROBADO y está inscrito en curso
    if ($user->estudiante && $model->estado === 'APROBADO') {
        $inscripcion = \App\Models\Curso\InscripcionCurso::where('id_estudiante', $user->estudiante->id_estudiante)
            ->where('id_curso', $model->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->exists();
        return $inscripcion;
    }

    return parent::view($user, $model);
}

// AGREGAR método approve()
public function approve(Usuario $user, Programa $model): bool
{
    if ($this->isAdmin($user)) {
        return true;
    }

    return $user->rolesAsignados()
        ->where('esta_activo', true)
        ->where('fue_eliminado', false)
        ->whereIn('nombre', ['Jefe de Carrera', 'Coordinador de Carrera'])
        ->exists();
}

// AGREGAR método reject()
public function reject(Usuario $user, Programa $model): bool
{
    return $this->approve($user, $model);
}
```

---

### FASE 5: Rutas (15 minutos)

**Archivo: `routes/web.php`**

En sección **Admin** (alrededor línea 163):

```php
// Programa (Syllabus) Management - Admin
Route::get('programas', [AdminProgramaController::class, 'index'])
    ->name('programas.index');
Route::get('programas/stats', [AdminProgramaController::class, 'getStats'])
    ->name('programas.stats');
Route::get('cursos/{curso}/programa/revisar', [AdminProgramaController::class, 'show'])
    ->name('cursos.programa.revisar');
Route::put('cursos/{curso}/programa/aprobar', [AdminProgramaController::class, 'approve'])
    ->name('cursos.programa.aprobar');
Route::put('cursos/{curso}/programa/rechazar', [AdminProgramaController::class, 'reject'])
    ->name('cursos.programa.rechazar');

// También agregar en docente routes:
Route::post('cursos/{curso}/programa/enviar', [ProgramaController::class, 'submit'])
    ->name('cursos.programa.submit');
```

En sección **Estudiante** (alrededor línea 238):

```php
// Program (Syllabus) View
Route::get('cursos/{curso}/programa', [\App\Http\Controllers\Student\ProgramaController::class, 'show'])
    ->name('cursos.programa.show');
```

**Agregar import si no existe:**

```php
use App\Http\Controllers\Admin\ProgramaController as AdminProgramaController;
use App\Http\Controllers\Student\ProgramaController as StudentProgramaController;
```

---

### FASE 6: Tipos TypeScript (10 minutos)

**Archivo: `resources/js/types/admin.types.ts`**

Actualizar o agregar interface:

```typescript
export interface Programa {
    id_programa: number;
    id_curso: number;
    version_programa: number;
    estado: 'BORRADOR' | 'PENDIENTE_APROBACION' | 'APROBADO' | 'RECHAZADO';
    data_syllabus: {
        secciones: Array<any>;
        metadata?: any;
    };
    creado_por: number;
    aprobado_por?: number;
    rechazado_por?: number;
    fecha_creacion: string;
    fecha_aprobacion?: string;
    fecha_rechazo?: string;
    razon_rechazo?: string;
    es_actual: boolean;
    autor?: Usuario;
    revisor?: Usuario;
}
```

---

### FASE 7: Vistas - Admin (30 minutos)

**Crear: `resources/js/pages/admin/Programas.svelte`**

Copiar y adaptar:
```bash
# Copiar PLANTILLA_admin_Programas.svelte a:
# resources/js/pages/admin/Programas.svelte
```

**Crear: `resources/js/pages/admin/ProgramaDetalle.svelte`**

Similar a la anterior pero vista detallada con:
- Botones APROBAR / RECHAZAR
- Modal para razón de rechazo
- Display completo del syllabus

---

### FASE 8: Vistas - Docente (20 minutos)

**Actualizar: `resources/js/pages/docente/Programa.svelte`**

Agregar después del encabezado:

```svelte
<!-- Mostrar estado del programa -->
{#if programa?.estado}
    <div class="p-4 rounded-lg mb-6 {programa.estado === 'APROBADO' ? 'bg-green-50 border border-green-200' : programa.estado === 'RECHAZADO' ? 'bg-red-50 border border-red-200' : 'bg-yellow-50 border border-yellow-200'}">
        <p class="font-semibold {programa.estado === 'APROBADO' ? 'text-green-700' : programa.estado === 'RECHAZADO' ? 'text-red-700' : 'text-yellow-700'}">
            Estado: 
            {#if programa.estado === 'BORRADOR'}
                🟡 En Edición
            {:else if programa.estado === 'PENDIENTE_APROBACION'}
                🔵 Pendiente de Aprobación
            {:else if programa.estado === 'APROBADO'}
                ✅ Aprobado
            {:else if programa.estado === 'RECHAZADO'}
                ❌ Rechazado - Ver razón abajo
            {/if}
        </p>
        
        {#if programa.estado === 'RECHAZADO' && programa.razon_rechazo}
            <p class="text-red-700 mt-2"><strong>Razón:</strong> {programa.razon_rechazo}</p>
        {/if}
    </div>
{/if}

<!-- Botón enviar para aprobación (solo si BORRADOR) -->
{#if programa?.estado === 'BORRADOR'}
    <button on:click={submitForApproval} class="bg-blue-600 text-white px-4 py-2 rounded mb-6">
        Enviar para Aprobación
    </button>
{/if}
```

Agregar función:

```typescript
async function submitForApproval() {
    try {
        const response = await fetch(`/docente/cursos/${curso.id_curso}/programa/enviar`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });
        
        if (response.ok) {
            toast.success('Programa enviado para aprobación');
            // Recargar datos
            location.reload();
        } else {
            const error = await response.json();
            toast.error(error.message || 'Error al enviar programa');
        }
    } catch (error) {
        console.error(error);
        toast.error('Error al enviar programa');
    }
}
```

---

### FASE 9: Vistas - Estudiante (30 minutos)

**Crear: `resources/js/pages/student/Courses/Programa.svelte`**

```bash
# Copiar PLANTILLA_student_Courses_Programa.svelte a:
# resources/js/pages/student/Courses/Programa.svelte
```

**Actualizar: `resources/js/pages/student/Courses/Show.svelte`**

Agregar sección para programa:

```svelte
{#if tiene_programa}
    <Link href={`/estudiante/cursos/${curso.id_curso}/programa`} class="btn btn-primary">
        Ver Programa
    </Link>
{:else}
    <p class="text-slate-500">Programa no disponible</p>
{/if}
```

---

### FASE 10: Otros Cambios (15 minutos)

**Actualizar: `app/Http/Controllers/Student/CourseController.php`**

Cambiar método `show()`:

```php
public function show(int $id)
{
    $curso = Curso::findOrFail($id);
    
    // Verificar inscripción
    $estudiante = auth()->user()->estudiante;
    $inscripcion = $estudiante->inscripcionCursos()
        ->where('id_curso', $id)
        ->where('estado_inscripcion', 'INSCRITO')
        ->first();
    
    abort_if(!$inscripcion, 403);
    
    // Cargar programa aprobado
    $programa = Programa::where('id_curso', $id)
        ->where('estado', 'APROBADO')
        ->where('es_actual', true)
        ->first();
    
    $tiene_programa = $programa !== null;
    
    return Inertia::render('student/Courses/Show', [
        'curso' => $curso,
        'tiene_programa' => $tiene_programa,
        'programa' => $programa,
    ]);
}
```

---

## ✅ TESTING CHECKLIST

```bash
# 1. Docente crea programa
# 2. Docente ve estado BORRADOR
# 3. Docente edita programa
# 4. Docente envía para aprobación → estado PENDIENTE_APROBACION
# 5. Admin ve programa en lista
# 6. Admin aprueba → estado APROBADO ✅
# 7. Estudiante ve programa en curso
# 8. Admin rechaza → estado RECHAZADO
# 9. Docente ve razón de rechazo
# 10. Docente edita y envía de nuevo
```

---

## 🔍 TROUBLESHOOTING

### "El programa no aparece en lista admin"
- Verificar estado = PENDIENTE_APROBACION
- Verificar permisos del usuario admin en contexto

### "Estudiante no ve programa"
- Verificar estado = APROBADO
- Verificar inscripción en curso (estado = INSCRITO)

### "Error al enviar para aprobación"
- Verificar que existe ruta POST `/docente/cursos/{id}/programa/enviar`
- Verificar tokens CSRF

---

## 📞 RECURSOS

- PLAN_IMPLEMENTACION_FLUJO_PROGRAMA.md - Documentación completa
- Plantillas en raíz del proyecto
- ProgramaPolicy.php - Lógica de autorización
- routes/web.php - Definición de rutas
