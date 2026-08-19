# Plan de Refactorización Frontend — Monolito Modular

> **Fecha:** Marzo 2026  
> **Alcance:** `resources/js/`  
> **Stack:** Svelte 5 · TypeScript · Inertia.js · TailwindCSS v4 · shadcn-svelte

---

## 1. Diagnóstico del Estado Actual

### 1.1 Problemas Identificados

| #   | Problema                                                                                                       | Severidad | Ubicación              |
| --- | -------------------------------------------------------------------------------------------------------------- | --------- | ---------------------- |
| P1  | Componentes del módulo admin dispersos en `custom/admin/` y al nivel raíz de `components/`                     | Alta      | `components/`          |
| P2  | Dos versiones coexistentes de la misma página (`Programas.svelte` vs `Programas_New.svelte`)                   | Alta      | `pages/admin/`         |
| P3  | `components/admin/` es sólo un alias de `custom/admin/` vía `index.ts`, creando ambigüedad de imports          | Alta      | `components/admin/`    |
| P4  | `admin.types.ts` centraliza todos los tipos de dominio (todas las entidades en un solo archivo)                | Media     | `types/admin.types.ts` |
| P5  | Componentes de módulos distintos (Docente, student) viven bajo rutas inconsistentes (`Docente/` vs `student/`) | Media     | `components/`          |
| P6  | `SyllabusEditor.svelte` y `SyllabusViewer.svelte` al nivel raíz de `components/` (reorganización incompleta)   | Media     | `components/`          |
| P7  | Lógica de negocio mezclada con presentación dentro de páginas grandes (ej. `Cursos.svelte`)                    | Media     | `pages/admin/`         |
| P8  | Sin frontera explícita entre componentes compartidos y componentes de dominio                                  | Alta      | Todo                   |
| P9  | No existe un `index.ts` por módulo que defina la interfaz pública del módulo                                   | Alta      | Todo                   |

### 1.2 Estructura Actual (simplificada)

```
resources/js/
├── pages/
│   ├── admin/          # 16 páginas (algunas muy grandes)
│   ├── docente/        # 8 páginas
│   ├── dashboards/     # 3 dashboards de rol
│   ├── student/
│   ├── ayudante/
│   ├── jefe-carrera/
│   ├── auth/
│   └── settings/
├── components/
│   ├── SyllabusEditor.svelte    ← fuera de lugar
│   ├── SyllabusViewer.svelte    ← fuera de lugar
│   ├── admin/          ← alias de custom/admin (confuso)
│   ├── Docente/        ← capitalizado inconsistente
│   ├── student/        ← minúscula
│   ├── custom/
│   │   ├── admin/      ← componentes reales del módulo admin
│   │   ├── common/     ← compartidos
│   │   ├── layout/     ← shell de la app
│   │   ├── auth/
│   │   ├── dashboard/
│   │   └── navigation/
│   └── ui/             ← shadcn primitivos
├── layouts/
├── types/
│   ├── admin.types.ts  ← monolito de tipos
│   └── index.ts
├── services/
├── utils/
├── hooks/
├── constants/
└── routes/ / wayfinder/
```

---

## 2. Arquitectura Objetivo — Monolito Modular

### 2.1 Principios Rectores

1. **Módulo = unidad de encapsulamiento**: Cada dominio del negocio (admin, docente, etc.) es un módulo con límites claros.
2. **Core compartido sin acoplamiento**: El núcleo (`core/`) provee primitivos sin conocer los módulos.
3. **Páginas como controladores thin**: Las páginas Inertia solo orquestan datos y delegan presentación al módulo.
4. **Reglas de dependencia explícitas y unidireccionales** (ver §2.3).
5. **API pública por módulo**: Cada módulo expone lo que quiere compartir vía `index.ts`; el internals queda privado.
6. **Compatibilidad Inertia preservada**: La carpeta `pages/` y el glob en `app.ts` no se modifican; PHP sigue usando `Inertia::render('admin/Cursos')`.

### 2.2 Estructura Target

```
resources/js/
│
├── core/                              ← Kernel compartido (dominio-agnóstico)
│   ├── ui/                            ←   shadcn-svelte (sin cambios, solo mover)
│   ├── components/                    ←   Componentes reutilizables entre módulos
│   │   ├── layout/                    ←     AppShell, AppSidebar, AppHeader...
│   │   ├── navigation/                ←     Breadcrumbs, NavMain, NavFooter...
│   │   └── shared/                    ←     AlertError, Heading, DatePickerCL...
│   ├── layouts/                       ←   Layouts de aplicación (todos los Xxx.svelte)
│   ├── hooks/                         ←   Hooks globales (is-mobile, useAppearance)
│   ├── utils/                         ←   formatters.ts, contextType.utils.ts
│   ├── types/                         ←   User, PageProps, PaginatedResponse, BreadcrumbItem
│   └── constants/                     ←   Constantes globales
│
├── modules/                           ← Módulos de dominio
│   ├── admin/
│   │   ├── components/                ←   actual custom/admin/ (reorganizado)
│   │   │   ├── DataTable.svelte
│   │   │   ├── FormModal.svelte
│   │   │   ├── CursoWizardModal.svelte
│   │   │   ├── programa/              ←   Programa* agrupados
│   │   │   │   ├── ProgramaDetailView.svelte
│   │   │   │   ├── ProgramaActionButtons.svelte
│   │   │   │   ├── ProgramaStateBadges.svelte
│   │   │   │   └── ProgramaWizardSteps.svelte
│   │   │   ├── syllabus/              ←   Syllabus* agrupados
│   │   │   │   ├── SyllabusModal.svelte
│   │   │   │   ├── SyllabusTypeSelector.svelte
│   │   │   │   ├── SyllabusEditor.svelte
│   │   │   │   └── SyllabusViewer.svelte
│   │   │   └── permissions/
│   │   │       └── permissions-modal/
│   │   ├── types/                     ←   Tipos del dominio admin
│   │   │   ├── index.ts               ←   Barrel export
│   │   │   ├── curso.types.ts
│   │   │   ├── programa.types.ts
│   │   │   ├── plan.types.ts
│   │   │   └── organizacion.types.ts  ←   Facultad, Depto, Carrera
│   │   ├── hooks/                     ←   Hooks específicos del módulo admin
│   │   ├── services/                  ←   Lógica client-side del admin
│   │   └── index.ts                   ←   API pública del módulo
│   │
│   ├── docente/
│   │   ├── components/                ←   actual components/Docente/
│   │   │   └── Sidebar/
│   │   ├── types/
│   │   ├── hooks/
│   │   └── index.ts
│   │
│   ├── estudiante/
│   │   ├── components/                ←   actual components/student/
│   │   │   ├── CourseCard.svelte
│   │   │   ├── CourseSidebar.svelte
│   │   │   ├── AyudantiaCard.svelte
│   │   │   └── ProfileCard.svelte
│   │   ├── types/
│   │   └── index.ts
│   │
│   ├── ayudante/
│   │   ├── components/
│   │   └── index.ts
│   │
│   ├── jefe-carrera/
│   │   ├── components/
│   │   └── index.ts
│   │
│   ├── auth/
│   │   ├── components/                ←   actual custom/auth/
│   │   └── index.ts
│   │
│   └── settings/
│       ├── components/
│       └── index.ts
│
├── pages/                             ← Páginas Inertia — SOLO thin wrappers
│   ├── admin/                         ←   sin cambios de nombre de archivo
│   ├── docente/
│   ├── estudiante/
│   ├── ayudante/
│   ├── jefe-carrera/
│   ├── auth/
│   ├── settings/
│   ├── dashboards/
│   ├── Dashboard.svelte
│   ├── SinRol.svelte
│   └── Welcome.svelte
│
├── app.ts                             ← Sin cambios (glob sigue apuntando a pages/**)
├── bootstrap.ts
├── ssr.ts
└── routes/ / wayfinder/               ← Generados (sin cambios)
```

### 2.3 Reglas de Dependencia

```
┌─────────────────────────────────────────────────────────────┐
│                         pages/**                            │
│          (Inertia thin controllers — orquesta datos)        │
└───────────────────┬─────────────────────┬───────────────────┘
                    │ importa de           │ importa de
                    ↓                     ↓
┌────────────────────────┐    ┌──────────────────────────────┐
│     modules/[domain]/  │    │          core/               │
│  (lógica de dominio)   │ ←  │  (primitivos compartidos)    │
│                        │    │                              │
│  components/           │    │  ui/, components/, layouts/  │
│  types/                │    │  hooks/, utils/, types/      │
│  hooks/, services/     │    │  constants/                  │
└────────────────────────┘    └──────────────────────────────┘
         │                              ↑
         └─────────── importa de ───────┘
```

**Reglas estrictas:**
- `pages/**` → puede importar de `modules/**` y `core/**` ✅
- `modules/**` → puede importar de `core/**` ✅
- `modules/**` → **NO** puede importar de otro `modules/**` ❌
- `core/**` → **NO** puede importar de `modules/**` ❌
- Los tipos cross-módulo se definen en `core/types/` o se pasan como props de Inertia

---

## 3. Plan de Implementación por Fases

### Resumen de Fases

| Fase | Nombre                     | Duración Est. | Riesgo |
| ---- | -------------------------- | ------------- | ------ |
| 0    | Preparación y convenciones | 1 día         | Bajo   |
| 1    | Estructura `core/`         | 2–3 días      | Bajo   |
| 2    | Módulo `admin`             | 3–4 días      | Medio  |
| 3    | Módulos secundarios        | 2–3 días      | Bajo   |
| 4    | Consolidación de tipos     | 1–2 días      | Medio  |
| 5    | Páginas thin wrappers      | 2–3 días      | Medio  |
| 6    | Limpieza y validación      | 1–2 días      | Bajo   |

---

### Fase 0 — Preparación y Convenciones

**Objetivo:** Establecer convenciones claras antes de mover cualquier archivo.

#### Tareas

- [ ] **F0.1** Definir y documentar convenciones de nombrado en `docs/FRONTEND_CONVENTIONS.md`:
  - Archivos de componentes: `PascalCase.svelte`
  - Archivos de tipos: `kebab-case.types.ts`
  - Directorios de módulo: `kebab-case/`
  - Exports: siempre a través de `index.ts` del módulo
  
- [ ] **F0.2** Configurar el alias `@core` en `tsconfig.json` y `vite.config.js`:
  ```ts
  // tsconfig.json
  "@core/*": ["resources/js/core/*"],
  "@modules/*": ["resources/js/modules/*"]
  ```

- [ ] **F0.3** Instalar o configurar ESLint rule para detectar imports cross-módulo (opcional, usar `eslint-plugin-import` boundaries).

- [ ] **F0.4** Crear rama Git `refactor/frontend-modular` y asegurarse que la app compila antes de iniciar.

- [ ] **F0.5** Verificar que `npm run build` y `npm run dev` pasan sin errores en el estado actual.

---

### Fase 1 — Construcción del `core/`

**Objetivo:** Extraer todo lo genérico y compartido a `core/`. Sin romper ningún import existente todavía.

#### Subtareas

**F1.1 — Mover `ui/` a `core/ui/`**
```
components/ui/ → core/ui/
```
- Actualizar alias `@/components/ui/` → `@core/ui/` en todos los archivos
- Nota: shadcn-svelte usa paths configurados en `components.json`; actualizar `components.json`:
  ```json
  { "aliases": { "components": "@core", "ui": "@core/ui" } }
  ```

**F1.2 — Crear `core/components/`**
```
components/custom/common/     → core/components/shared/
components/custom/layout/     → core/components/layout/
components/custom/navigation/ → core/components/navigation/
components/custom/dashboard/  → core/components/dashboard/
```

**F1.3 — Mover layouts**
```
layouts/ → core/layouts/
```
- Actualizar todos los imports de layout en `pages/**`

**F1.4 — Mover hooks, utils, constants**
```
hooks/     → core/hooks/
utils/     → core/utils/
constants/ → core/constants/
```

**F1.5 — Crear `core/types/` con tipos base**
Extraer de `types/index.d.ts` y `types/global.d.ts` los tipos que no son de dominio:
```ts
// core/types/index.ts
export type { User, SharedAuth, PageProps } from './auth.types';
export type { BreadcrumbItem, NavItem } from './navigation.types';
export type { PaginatedResponse } from './pagination.types';
```

**F1.6 — Crear `core/index.ts`** como barrel export del núcleo.

#### Criterio de completitud
- `npm run build` sin errores
- No quedan imports que apunten a las rutas antiguas de los archivos movidos

---

### Fase 2 — Módulo `admin/`

**Objetivo:** Encapsular toda la lógica, componentes y tipos del módulo admin.

#### Subtareas

**F2.1 — Crear estructura de carpetas**
```bash
mkdir -p resources/js/modules/admin/{components/{programa,syllabus,permissions},types,hooks,services}
```

**F2.2 — Migrar componentes de `custom/admin/` a `modules/admin/components/`**

| Origen (custom/admin/)             | Destino (modules/admin/components/)       |
| ---------------------------------- | ----------------------------------------- |
| `DataTable.svelte`                 | `DataTable.svelte`                        |
| `FormModal.svelte`                 | `FormModal.svelte`                        |
| `DeleteConfirmation.svelte`        | `DeleteConfirmation.svelte`               |
| `CourseTeamModal.svelte`           | `CourseTeamModal.svelte`                  |
| `CursoWizardModal.svelte`          | `CursoWizardModal.svelte`                 |
| `MallaSlideOver.svelte`            | `MallaSlideOver.svelte`                   |
| `SyllabusModal.svelte`             | `syllabus/SyllabusModal.svelte`           |
| `SyllabusTypeSelector.svelte`      | `syllabus/SyllabusTypeSelector.svelte`    |
| `components/SyllabusEditor.svelte` | `syllabus/SyllabusEditor.svelte`          |
| `components/SyllabusViewer.svelte` | `syllabus/SyllabusViewer.svelte`          |
| `ProgramaActionButtons.svelte`     | `programa/ProgramaActionButtons.svelte`   |
| `ProgramaDetailView.svelte`        | `programa/ProgramaDetailView.svelte`      |
| `ProgramasListView.svelte`         | `programa/ProgramasListView.svelte`       |
| `ProgramaStateBadges.svelte`       | `programa/ProgramaStateBadges.svelte`     |
| `ProgramaWizardSteps.svelte`       | `programa/ProgramaWizardSteps.svelte`     |
| `CompletenessProgressBar.svelte`   | `programa/CompletenessProgressBar.svelte` |
| `permissions-modal/`               | `permissions/permissions-modal/`          |

**F2.3 — Consolidar `components/admin/` (el alias)**
- Eliminar `components/admin/` (alias/re-export) tras actualizar todos los imports que lo usaban
- Confirmar que ningún archivo fuera de `pages/admin/**` importaba directo de aquí

**F2.4 — Dividir `types/admin.types.ts`**
```
types/admin.types.ts →
  modules/admin/types/curso.types.ts       (Curso, Seccion, Inscripcion)
  modules/admin/types/programa.types.ts    (Programa, Syllabus, EstadoPrograma)
  modules/admin/types/plan.types.ts        (Plan, DetalleMalla, Asignatura)
  modules/admin/types/organizacion.types.ts (Facultad, Departamento, Carrera)
  modules/admin/types/usuario.types.ts     (Usuario, Docente)
  modules/admin/types/index.ts             (barrel export de todos)
```
- Mantener retrocompatibilidad: crear `types/admin.types.ts` → re-export desde `modules/admin/types/` durante la transición

**F2.5 — Crear `modules/admin/index.ts`**
```ts
// API pública del módulo admin
export * from './types';
export { default as DataTable } from './components/DataTable.svelte';
export { default as FormModal } from './components/FormModal.svelte';
// ... solo los componentes que otros módulos (páginas) necesitan
```

**F2.6 — Actualizar imports en `pages/admin/**`**
Reemplazar:
```ts
import DataTable from '@/components/custom/admin/DataTable.svelte';
// o
import DataTable from '@/components/admin/DataTable.svelte';
```
Por:
```ts
import { DataTable } from '@modules/admin';
// o import directo si es página admin
import DataTable from '@modules/admin/components/DataTable.svelte';
```

**F2.7 — Resolver duplicado `Programas.svelte` / `Programas_New.svelte`**
- Verificar que `Programas_New.svelte` es la versión estable actual
- Renombrar: `Programas_New.svelte` → `Programas.svelte` (tras backup/git)
- Eliminar `Programas.svelte` legada
- Actualizar la ruta en `web.php` si apunta por nombre de archivo (verificar `Inertia::render` calls)

**F2.8 — Mover `services/permissionValidator.ts`**
```
services/permissionValidator.ts → modules/admin/services/permissionValidator.ts
```
(O a `core/services/` si se usa en múltiples módulos — verificar imports)

#### Criterio de completitud
- `npx tsc --noEmit` sin errores
- `npm run build` sin errores
- Ningún import en `pages/admin/**` referencia `custom/admin/` o la carpeta raíz `components/`

---

### Fase 3 — Módulos Secundarios

**Objetivo:** Aplicar el mismo patrón a los demás dominios.

#### F3.1 — Módulo `docente/`
```
components/Docente/ → modules/docente/components/
```
- Crear `modules/docente/types/` con tipos específicos del docente
- Crear `modules/docente/index.ts`
- Actualizar imports en `pages/docente/**`

#### F3.2 — Módulo `estudiante/`
```
components/student/ → modules/estudiante/components/
```
- Renombrar de `student` a `estudiante` para consistencia con el idioma del proyecto
- Crear `modules/estudiante/index.ts`
- Actualizar imports en `pages/student/**` (y `pages/dashboards/DashboardAlumno.svelte`)

#### F3.3 — Módulo `ayudante/`
```
# Componentes que existan en pages/ayudante o components próximos
→ modules/ayudante/components/
```

#### F3.4 — Módulo `jefe-carrera/`
```
# Verificar si hay componentes propios o reutilizan admin/docente
→ modules/jefe-carrera/components/ (si aplica)
```

#### F3.5 — Módulo `auth/`
```
components/custom/auth/ → modules/auth/components/
```

#### F3.6 — Módulo `settings/`
```
# Revisar components existentes en pages/settings
→ modules/settings/components/ (si aplica)
```

---

### Fase 4 — Consolidación de Tipos

**Objetivo:** Tipado limpio por módulo, sin monolitos de tipos.

#### Tareas

- [ ] **F4.1** Completar la división de `admin.types.ts` iniciada en F2.4
- [ ] **F4.2** Crear `core/types/auth.types.ts` con `User`, `SharedAuth`
- [ ] **F4.3** Crear `core/types/navigation.types.ts` con `BreadcrumbItem`, `NavItem`
- [ ] **F4.4** Crear `core/types/inertia.types.ts` con `PageProps`, `PaginatedResponse<T>`
- [ ] **F4.5** Actualizar `types/index.ts` para re-exportar desde `core/types/` (compatibilidad hacia atrás)
- [ ] **F4.6** Revisar `types/controllers/` y `types/permissions/` — mover a módulos correspondientes o core
- [ ] **F4.7** Ejecutar `npx tsc --noEmit` para verificar coherencia total de tipos

---

### Fase 5 — Páginas como Thin Wrappers

**Objetivo:** Asegurar que las páginas Inertia no contengan lógica de negocio, solo orquestación.

#### Criterio de "thin wrapper"

Una página Inertia bien formada:
```svelte
<script lang="ts">
  // 1. Sólo importa del módulo y core
  import ModuloLayout from '@core/layouts/AdminLayout.svelte';
  import { DataTable, FormModal } from '@modules/admin';
  import type { Curso } from '@modules/admin';
  
  // 2. Declara sus props (datos del controlador PHP)
  interface Props {
    cursos: PaginatedResponse<Curso>;
    filters: { search?: string };
  }
  let { cursos, filters }: Props = $props();
  
  // 3. Estado UI mínimo (modales abiertos, selección actual)
  let modal = $state<'create' | 'edit' | null>(null);
  let selected = $state<Curso | null>(null);
  
  // 4. Sin lógica de negocio inline — delega a módulo
</script>

<ModuloLayout {breadcrumbs}>
  <DataTable ... />
  <FormModal open={modal === 'edit'} ... />
</ModuloLayout>
```

#### Páginas que requieren refactoring prioritario (> 300 líneas actuales)

| Página                              | Problema Principal                      | Acción                                            |
| ----------------------------------- | --------------------------------------- | ------------------------------------------------- |
| `pages/admin/Cursos.svelte`         | Lógica CRUD + secciones + equipo inline | Extraer a `modules/admin/components/curso/`       |
| `pages/admin/ProgramaEdit.svelte`   | Wizard de múltiples pasos inline        | Componentes ya existen en módulo — limpiar página |
| `pages/docente/CursoDetalle.svelte` | Lógica de actividades inline            | Extraer a `modules/docente/components/`           |
| `pages/admin/DetalleMalla.svelte`   | Side panel + tabla compleja             | Extraer modal a módulo                            |

---

### Fase 6 — Limpieza y Validación

**Objetivo:** Eliminar artefactos obsoletos y validar la arquitectura completa.

#### Tareas

- [ ] **F6.1** Eliminar `components/custom/` (debería estar vacío tras las fases anteriores)
- [ ] **F6.2** Eliminar `components/admin/` (alias obsoleto)
- [ ] **F6.3** Eliminar `components/Docente/` y `components/student/` (migrados a módulos)
- [ ] **F6.4** Verificar que `components/` solo contiene `ui/`, `index.ts`, y quizás el `COMPONENTS.md` actualizado — o mover todo a `core/` y eliminar `components/`
- [ ] **F6.5** Actualizar `README.md` de `resources/js/` con la nueva estructura
- [ ] **F6.6** Actualizar `COMPONENTS.md` con inventario de nueva ubicación
- [ ] **F6.7** Ejecutar `npm run build` en modo producción y verificar bundle sin errores
- [ ] **F6.8** Ejecutar `npx tsc --noEmit` — cero errores
- [ ] **F6.9** Review visual: verificar páginas clave en navegador (admin/Cursos, docente/CursoDetalle, admin/Programas)
- [ ] **F6.10** Revisar `vite.config.js` — confirmar que alias `@/` sigue funcionando o actualizarlo

---

## 4. Consideraciones Técnicas Específicas

### 4.1 Alias de Paths en Vite y TypeScript

Añadir en `vite.config.js`:
```js
resolve: {
  alias: {
    '@': '/resources/js',
    '@core': '/resources/js/core',
    '@modules': '/resources/js/modules',
  }
}
```

Y en `tsconfig.json`:
```json
{
  "compilerOptions": {
    "paths": {
      "@/*": ["resources/js/*"],
      "@core/*": ["resources/js/core/*"],
      "@modules/*": ["resources/js/modules/*"]
    }
  }
}
```

### 4.2 Actualización de `components.json` (shadcn-svelte)

Al mover `ui/` a `core/ui/`:
```json
{
  "aliases": {
    "components": "@core",
    "ui": "@core/ui",
    "utils": "@core/utils",
    "hooks": "@core/hooks"
  }
}
```
> ⚠️ Sin este cambio, `npx shadcn-svelte add <component>` generará archivos en la ruta incorrecta.

### 4.3 Manejo del Glob de Inertia en `app.ts`

El glob actual **no necesita cambios**:
```ts
// app.ts — sin modificar
const pages = import.meta.glob<ResolvedComponent>('./pages/**/*.svelte', { eager: true });
```
Las páginas permanecen en `resources/js/pages/` con los mismos nombres. Solo cambian sus imports internos.

### 4.4 Strategy para `Programas.svelte` Legada

Antes de eliminar `Programas.svelte`:
1. Verificar en `web.php` exactamente qué `Inertia::render()` llama qué página
2. Buscar en PHP: `grep -r "Programas" app/Http/Controllers`
3. Si ambas rutas coexisten temporalmente, son dos rutas de web.php diferentes → consolidar en una
4. Después de consolidar en PHP, eliminar `Programas.svelte` legada y renombrar `Programas_New.svelte`

### 4.5 `permissionValidator.ts` — Decisión de Ubicación

Verificar antes de mover:
```
grep -r "permissionValidator" resources/js/
```
- Si solo `pages/admin/**` lo importa → `modules/admin/services/`
- Si lo importan múltiples módulos → `core/services/` o `core/utils/`

### 4.6 Wayfinder y Rutas Type-Safe

Los archivos en `routes/` y `wayfinder/` son **generados automáticamente**. No mover ni editar. Son agnósticos a la estructura de módulos.

---

## 5. Criterios de Éxito

| Criterio                                                       | Cómo verificar                                      |
| -------------------------------------------------------------- | --------------------------------------------------- |
| `npm run build` pasa sin warnings                              | CI/CD o `npm run build` local                       |
| `npx tsc --noEmit` sin errores                                 | `npm run type-check`                                |
| Ningún import en `modules/A` referencia `modules/B`            | ESLint boundaries o grep manual                     |
| Ningún import en `core/` referencia `modules/`                 | grep: `grep -r "from.*modules/" resources/js/core/` |
| `pages/` solo importa de `modules/`, `core/` y rutas generadas | grep pattern check                                  |
| SyllabusEditor/Viewer no está al nivel raíz de `components/`   | Verificación de estructura de carpetas              |
| No existe `components/admin/` (alias)                          | `ls resources/js/components/`                       |
| No existe `Programas_New.svelte`                               | `ls resources/js/pages/admin/`                      |
| Cada módulo tiene `index.ts`                                   | Verificación de carpetas                            |

---

## 6. Orden de Ejecución Recomendado

```
Rama Git: refactor/frontend-modular (desde main)
│
├── Semana 1: Fases 0 y 1
│   ├── Día 1:  F0 completo + F1.1 (mover ui/)
│   ├── Día 2:  F1.2-F1.4 (components share, layouts, hooks/utils)
│   └── Día 3:  F1.5-F1.6 (core/types) + validación
│
├── Semana 2: Fase 2 (módulo admin)
│   ├── Día 4:  F2.1-F2.3 (crear estructura + migrar componentes)
│   ├── Día 5:  F2.4 (dividir admin.types.ts)
│   └── Día 6:  F2.5-F2.8 + validación
│
└── Semana 3: Fases 3-6
    ├── Día 7:  F3 (módulos secundarios)
    ├── Día 8:  F4 (consolidar tipos) + F5 (thin wrappers)
    └── Día 9:  F6 (limpieza + validación final)
```

> **Estrategia de merge**: Cada fase termina con un commit atómico y verificación de build. Use PRs parciales si trabaja en equipo para hacer review incremental.

---

## 7. Lo que NO cambia

Para acotar el alcance y evitar over-engineering:

- ❌ No se modifica la estructura de `routes/` ni `wayfinder/` (generados)
- ❌ No se cambia el glob resolver de Inertia en `app.ts`
- ❌ No se modifica la estructura de `pages/` (mismos nombres de archivo)
- ❌ No se migra de Svelte 5 a ninguna otra tecnología
- ❌ No se toca el backend (PHP/Laravel)
- ❌ No se cambia el sistema de layouts por rol
- ❌ No se modifica `bootstrap.ts` ni `ssr.ts`
- ❌ No se reescriben componentes (solo se mueven y actualizan sus imports)

---

*Documento generado como parte de la planificación de refactorización — UTAmed 2026*
