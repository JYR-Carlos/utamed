## 🔐 FASE 0: Hook usePermissions() - Guía de Integración

### ✅ Lo que se ha creado

1. **`types/permissions.types.ts`**
   - Tipo `Permission` con todos los permisos granulares (cursos:create, equipo:manage, etc.)
   - `UserPermissionSet` para validar respuesta del servidor

2. **`lib/composables/usePermissions.ts`**
   - Hook reactivo que lee permisos de `page.props.auth.user.permissions`
   - Funciones: `can()`, `cannot()`, `canAll()`, `canAny()`
   - Soporte para wildcards (`*`, `cursos:*`, `cursos/equipo:*`)

3. **`lib/constants/permissions.ts`**
   - Constantes centralizadas: `PERMISSIONS.CURSOS.CREATE`, `PERMISSIONS.CURSOS.EQUIPO.MANAGE`, etc.
   - Evita typos y facilita refactoring

---

### 🔧 INTEGRACIÓN BACKEND (Laravel)

#### PASO 1: Definir Gate/Policy en Laravel

```php
// app/Policies/CursoPolicy.php
public function create(User $user)
{
    return $user->can('cursos:create');
}

public function update(User $user, Curso $curso)
{
    return $user->can('cursos:update');
}

// Para secciones
public function createSeccion(User $user, Curso $curso)
{
    return $user->can('cursos/secciones:create');
}

// Para equipo
public function manageEquipo(User $user, Curso $curso)
{
    return $user->can('cursos/equipo:manage'); // ← Solo admin
}

public function editEquipo(User $user, Curso $curso)
{
    // Admin puede hacer todo, docente solo si es dueño del curso
    return $user->can('cursos/equipo:edit') || $curso->id_docente === $user->id;
}
```

#### PASO 2: Autorización en Controlador

```php
// app/Http/Controllers/Admin/CursoController.php

public function store(StoreCursoRequest $request)
{
    // Esto lo hace Inertia automáticamente, pero puedes ser explícito:
    $this->authorize('create', Curso::class);
    
    // ... crear curso
}

public function edit(Curso $curso)
{
    $this->authorize('update', $curso);
    
    return Inertia::render('admin/Cursos/Edit', [
        'curso' => $curso,
        // IMPORTANTE: Pasar permisos aquí
        'permissions' => [
            'cursos:create' => $this->authorize('create', Curso::class, false),
            'cursos:update' => $this->authorize('update', $curso, false),
            'cursos:delete' => $this->authorize('delete', $curso, false),
            'cursos/secciones:create' => $this->authorize('createSeccion', [$curso], false),
            'cursos/equipo:manage' => $this->authorize('manageEquipo', [$curso], false),
            'cursos/programa:create' => $this->authorize('createPrograma', [$curso], false),
        ]
    ]);
}
```

**O MEJOR AÚN (Recomendado)**: Pasar el array de permisos del usuario

```php
public function index()
{
    return Inertia::render('admin/Cursos', [
        'cursos' => Curso::paginate(),
        // Pasar permisos del usuario autenticado actual
        'auth' => [
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'permissions' => auth()->user()->getPermissions('cursos'), // ← De tu tabla de permisos
            ],
        ],
    ]);
}
```

#### PASO 3: Modelo User - Método getPermissions()

```php
// app/Models/User.php

class User extends Authenticatable
{
    public function getPermissions(?string $resource = null): array
    {
        $permissions = $this->permissions()->pluck('slug')->toArray();
        
        if ($resource) {
            // Filtrar por recurso si se especifica
            return array_filter($permissions, fn($p) => str_starts_with($p, $resource . ':'));
        }
        
        return $permissions;
    }
}
```

---

### 📝 USO EN COMPONENTES

#### Imports para usar el hook

```svelte
<script lang="ts">
  import { usePermissions } from '@/lib/composables/usePermissions';
  import { PERMISSIONS } from '@/lib/constants/permissions';

  const { can, cannot, canAll, canAny } = usePermissions();
</script>
```

#### Mostrar/ocultar botones según permisos

```svelte
<!-- Botón Crear -->
{#if can(PERMISSIONS.CURSOS.CREATE)}
  <button onclick={openCreateModal}>Nueva Curso</button>
{/if}

<!-- Botones en tabla (edit, delete) -->
{#each cursos as curso}
  <tr>
    <td>{curso.nombre}</td>
    <td>
      {#if can(PERMISSIONS.CURSOS.UPDATE)}
        <button onclick={() => openEditModal(curso)}>Editar</button>
      {/if}
      {#if can(PERMISSIONS.CURSOS.DELETE)}
        <button onclick={() => openDeleteDialog(curso)}>Eliminar</button>
      {/if}
    </td>
  </tr>
{/each}

<!-- Modal con lógica de permisos -->
{#if showModal}
  <!-- Secciones: mostrar "Agregar" solo si puede crear -->
  {#if can(PERMISSIONS.CURSOS.SECCIONES.CREATE)}
    <button onclick={addSeccion}>Agregar Sección</button>
  {/if}

  <!-- Equipo: mostrar modal admin o limitado según permisos -->
  {#if can(PERMISSIONS.CURSOS.EQUIPO.MANAGE)}
    <!-- Mostrar CourseTeamModal (admin) -->
    <CourseTeamModal />
  {:else if can(PERMISSIONS.CURSOS.EQUIPO.EDIT)}
    <!-- Mostrar CourseTeamModalDocente (limitado) -->
    <CourseTeamModalDocente />
  {/if}
{/if}

<!-- Programa: granularidad por acción -->
{#if can(PERMISSIONS.CURSOS.PROGRAMA.CREATE)}
  <button>Crear Programa</button>
{/if}
{#if can(PERMISSIONS.CURSOS.PROGRAMA.UPDATE)}
  <button>Editar Programa</button>
{/if}
{#if can(PERMISSIONS.CURSOS.PROGRAMA.APPROVE)}
  <button>Aprobar</button>
{/if}

<!-- Validaciones múltiples (AND) -->
{#if canAll([PERMISSIONS.CURSOS.READ, PERMISSIONS.CURSOS.EQUIPO.MANAGE])}
  <!-- Solo mostrar si puede leer cursos Y gestionar equipo -->
{/if}

<!-- Validaciones múltiples (OR) -->
{#if canAny([PERMISSIONS.CURSOS.CREATE, PERMISSIONS.CURSOS.UPDATE])}
  <!-- Mostrar si puede crear O editar -->
{/if}
```

---

### 🧪 Testing el Hook

#### Test: can() con exacto

```ts
describe('usePermissions', () => {
  it('debería retornar true para permiso exacto', () => {
    // Mock: page.props.auth.user.permissions = ['cursos:create']
    const { can } = usePermissions();
    expect(can('cursos:create')).toBe(true);
    expect(can('cursos:delete')).toBe(false);
  });
});
```

#### Test: can() con wildcards

```ts
it('debería retornar true para wildcard', () => {
  // Mock: page.props.auth.user.permissions = ['cursos:*']
  const { can } = usePermissions();
  expect(can('cursos:create')).toBe(true);
  expect(can('cursos:delete')).toBe(true);
  expect(can('departamentos:create')).toBe(false);
});
```

---

### 🚨 IMPORTANTE: Server-Side Validation

El frontend **NUNCA** debe confiar en `can()` para lógica crítica. Siempre validar en el servidor:

```php
// ❌ MALO: Solo validar en frontend
if (can('cursos:delete')) {
  $curso->delete(); // ¿Qué si alguien falsifica los permisos?
}

// ✅ BUENO: Validar en ambos lados
$this->authorize('delete', $curso); // Laravel rechaza si no tiene permiso
if ($curso->delete()) {
  return response()->json(['success' => true]);
}
```

---

### ✅ Checklist Fase 0

- [x] Crear `types/permissions.types.ts` con tipo `Permission`
- [x] Crear `lib/composables/usePermissions.ts` con hook reactivo
- [x] Crear `lib/constants/permissions.ts` para constantes centralizadas
- [ ] **Backend**: Definir Gates/Policies en Laravel
- [ ] **Backend**: Método `getPermissions()` en modelo User
- [ ] **Backend**: Pasar permisos en respuesta Inertia
- [ ] **Frontend**: Importar hook en componentes
- [ ] **Frontend**: Reemplazar hardcoded checks con `can()`

---

### 🔗 Próximos Pasos (Phases 1-4)

Una vez que el backend pase los permisos, los componentes simplemente importan el hook:

```svelte
<script>
  import { usePermissions } from '@/lib/composables';
  import { PERMISSIONS } from '@/lib/constants/permissions';
  
  const { can } = usePermissions();
</script>

<!-- Y lo usan naturalmente en la UI -->
{#if can(PERMISSIONS.CURSOS.DELETE)}
  <DeleteButton />
{/if}
```

No hay que cambiar mucho más. El hook está **listo para usar en todos los componentes** desde hoy.
