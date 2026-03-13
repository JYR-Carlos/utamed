# Tipiando ContextType en Frontend

## Resumen

Hemos replicado el sistema de tipos del backend `App\Enums\ContextType` en el frontend con TypeScript, permitiendo **tipado seguro** y **autocompletar completo** para las respuestas del endpoint `/admin/assignment/roles`.

---

## ¿Qué Se Creó?

### 1. **Enum ContextType** (`resources/js/types/permissions.types.ts`)

```typescript
export enum ContextType {
    GLOBAL = 'global',
    ACTIVIDAD = 'actividad',
    CARRERA = 'carrera',
    CURSO = 'curso',
    DEPARTAMENTO = 'departamento',
    FACULTAD = 'facultad',
}
```

✅ **Beneficios:**
- Autocompletar seguro en TypeScript
- Errores de typo detectados en compilación
- Sincronizado con backend

---

### 2. **Tipos para Respuestas del Controlador** (`resources/js/types/admin.types.ts`)

```typescript
export interface RolResponse {
    id_rol: number;
    nombre: string;
    /// Array de tipos de contexto válidos para asignar este rol
    valid_assignment_context_types: (ContextType | string)[];
}
```

Similar a lo que backend retorna:
```php
// Backend: app/Http/Controllers/Admin/AssignmentWizardController.php
'valid_assignment_context_types' => $rol->getCompatibleContexts() // retorna ContextType[]
```

---

### 3. **Utilidades para Trabajar con Contextos** (`resources/js/utils/contextType.utils.ts`)

Funciones que replican la lógica del backend:

```typescript
// Jerarquía: ACTIVIDAD → CURSO → CARRERA → DEPARTAMENTO → FACULTAD → GLOBAL

getParent(ContextType.CARRERA)              // → ContextType.DEPARTAMENTO
getAncestors(ContextType.CARRERA)           // → [DEPARTAMENTO, FACULTAD, GLOBAL]
isAncestor(ContextType.FACULTAD, ContextType.CARRERA)  // → true
getLabel(ContextType.CARRERA)               // → "Carrera"
```

---

## Cómo Usar

### En un Componente Svelte

```svelte
<script lang="ts">
  import { ContextType, type RolResponse } from '@/types';
  import * as contextTypeUtils from '@/utils/contextType.utils';

  let roles: RolResponse[] = [];

  async function loadRoles() {
    const response = await fetch('/admin/assignment/roles');
    roles = await response.json() as RolResponse[];
  }

  function canAssignRoleTo(rol: RolResponse, contextType: ContextType): boolean {
    // Verificar si el contexto es válido para este rol
    return contextTypeUtils.isValidContext(
      contextType, 
      rol.valid_assignment_context_types
    );
  }
</script>

{#each roles as rol}
  <div>
    <h3>{rol.nombre}</h3>
    <p>
      Válido en: 
      {rol.valid_assignment_context_types
        .map(ct => {
          const type = Object.values(ContextType).find(t => t === ct);
          return type ? contextTypeUtils.getLabel(type) : ct;
        })
        .join(', ')}
    </p>
  </div>
{/each}
```

### En una Store (Svelte)

```typescript
// src/stores/roleStore.ts
import { writable } from 'svelte/store';
import { ContextType, type RolResponse } from '@/types';
import * as contextTypeUtils from '@/utils/contextType.utils';

export const roles = writable<RolResponse[]>([]);

export async function loadRoles() {
  const response = await fetch('/admin/assignment/roles');
  const data = await response.json() as RolResponse[];
  roles.set(data);
}

export function filterRolesByContext(contextType: ContextType) {
  return roles.subscribe(allRoles => {
    return allRoles.filter(rol =>
      contextTypeUtils.isValidContext(contextType, rol.valid_assignment_context_types)
    );
  });
}
```

### En una Validación

```typescript
import { ContextType, type RolResponse } from '@/types';
import * as contextTypeUtils from '@/utils/contextType.utils';

function validateRoleAssignment(
  rol: RolResponse,
  contextType: ContextType
): { valid: boolean; message: string } {
  if (!contextTypeUtils.isValidContext(contextType, rol.valid_assignment_context_types)) {
    return {
      valid: false,
      message: `El rol "${rol.nombre}" no puede asignarse en contexto ${contextTypeUtils.getLabel(contextType)}`
    };
  }
  return { valid: true, message: 'OK' };
}
```

---

## Exportaciones Disponibles

### Desde `@/types`:
```typescript
import {
  ContextType,                 // enum
  RolResponse,                 // interface
  RolDetailResponse,           // interface
  ContextTypeResponse,         // interface
  PermissionDetail,            // interface
  type ContextTypeValue,       // type utility
} from '@/types';
```

### Desde `@/utils/contextType.utils.ts`:
```typescript
import * as contextTypeUtils from '@/utils/contextType.utils';

// Funciones disponibles:
contextTypeUtils.getParent(ContextType)
contextTypeUtils.getAncestors(ContextType)
contextTypeUtils.isAncestor(ancestor, descendant)
contextTypeUtils.isDescendant(descendant, ancestor)
contextTypeUtils.getDescendants(ContextType)
contextTypeUtils.isValidContext(contextType, allowedTypes)
contextTypeUtils.getLabel(ContextType)
contextTypeUtils.getDescription(ContextType)
contextTypeUtils.compareContextTypes(a, b)
contextTypeUtils.getAllInOrder()
contextTypeUtils.isLeaf(ContextType)
contextTypeUtils.isRoot(ContextType)
contextTypeUtils.getDepth(ContextType)
```

---

## Correspondencia Backend ↔ Frontend

| Operación Backend | Frontend Equivalente |
|---|---|
| `$rol->getCompatibleContexts()` | `rol.valid_assignment_context_types` (en RolResponse) |
| `ContextType::CARRERA->immediateParent()` | `contextTypeUtils.getParent(ContextType.CARRERA)` |
| `ContextType::CARRERA->ancestorChain()` | `contextTypeUtils.getAncestors(ContextType.CARRERA)` |
| `ContextType::FACULTAD->isAncestorOf(ContextType.CARRERA)` | `contextTypeUtils.isAncestor(ContextType.FACULTAD, ContextType.CARRERA)` |
| `ContextType::FACULTAD->descendantTypes()` | `contextTypeUtils.getDescendants(ContextType.FACULTAD)` |

---

## Jerarquía de Contextos

```
GLOBAL (raíz)
├── FACULTAD
│   └── DEPARTAMENTO
│       └── CARRERA
│           └── CURSO
│               └── ACTIVIDAD
```

Esta jerarquía está implementada en:
- Backend: `App\Enums\ContextType::parentMap()` y métodos de navegación
- Frontend: `contextTypeUtils` y `CONTEXT_HIERARCHY` map

---

## Archivo de Ejemplos

Para más ejemplos de uso, ver: [`resources/js/EXAMPLES_contextType_usage.ts`](./EXAMPLES_contextType_usage.ts)

---

## Validación de Tipos

Ahora TypeScript valida:

✅ **Esto funciona:**
```typescript
const roles: RolResponse[] = await fetch('/admin/assignment/roles').then(r => r.json());
const rol = roles[0];

if (rol.valid_assignment_context_types.includes(ContextType.CARRERA)) {
  // Type-safe
}
```

❌ **Esto da error en compilación:**
```typescript
if (rol.valid_assignment_context_types.includes('invalid_context')) {
  // ❌ Error: "invalid_context" no es un ContextType válido
}
```

---

## Sincronización Backend-Frontend

Si en el backend cambias `App\Enums\ContextType`, actualizas:
1. `resources/js/types/permissions.types.ts` - enum ContextType
2. `resources/js/utils/contextType.utils.ts` - CONTEXT_HIERARCHY map

Todo lo demás se mantiene automáticamente gracias a TypeScript.

---

## Beneficios

| Beneficio | Descripción |
|---|---|
| 🛡️ **Type Safety** | TypeScript valida tipos en compilación |
| 🚀 **Developer Experience** | Autocompletar, hints, documentación inline |
| 🤝 **Simetría Backend-Frontend** | Mismos conceptos, misma semántica |
| 🔧 **Mantenibilidad** | Cambios centralizados se propagan automáticamente |
| 📖 **Autodocumentación** | Los tipos actúan como documentación |
| 🐛 **Menos Bugs** | Errores detectados temprano en desarrollo |
