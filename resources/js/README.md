# Frontend - UTAMed

Documentación completa del frontend de UTAMed, una aplicación de gestión académica construida con tecnologías modernas.

## 📚 Tabla de Contenidos

- [Stack Tecnológico](#-stack-tecnológico)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Convenciones de Código](#-convenciones-de-código)
- [Componentes](#-componentes)
- [Sistema de Tipos](#-sistema-de-tipos)
- [Rutas y Navegación](#-rutas-y-navegación)
- [Guía de Desarrollo](#-guía-de-desarrollo)

## 🚀 Stack Tecnológico

### Core
- **[Svelte 5](https://svelte.dev/)** - Framework reactivo con el nuevo sistema de Runes
- **[TypeScript](https://www.typescriptlang.org/)** - Tipado estático para JavaScript
- **[Inertia.js](https://inertiajs.com/)** - Adaptador SPA para Laravel
- **[Vite](https://vitejs.dev/)** - Build tool y dev server

### UI & Styling
- **[TailwindCSS](https://tailwindcss.com/)** - Framework CSS utility-first
- **[shadcn-svelte](https://www.shadcn-svelte.com/)** - Componentes UI reutilizables
- **[Lucide Icons](https://lucide.dev/)** - Biblioteca de iconos

### Herramientas
- **[Laravel Wayfinder](https://github.com/laravel/wayfinder)** - Generación de rutas type-safe
- **[Ziggy](https://github.com/tighten/ziggy)** - Helper de rutas Laravel para JavaScript

## 📁 Estructura del Proyecto

```
resources/js/
├── app.ts                    # Punto de entrada de la aplicación
├── bootstrap.ts              # Configuración inicial (axios, etc.)
├── ssr.ts                    # Configuración SSR de Inertia
│
├── components/               # Componentes
│   ├── custom/              # Componentes personalizados reutilizables
│   │   ├── admin/          # Componentes del módulo admin
│   │   │   ├── DataTable.svelte
│   │   │   ├── FormModal.svelte
│   │   │   ├── DeleteConfirmation.svelte
│   │   │   ├── CourseTeamModal.svelte
│   │   │   ├── PermissionsModal.svelte
│   │   │   └── index.ts
│   │   │
│   │   ├── layout/         # Componentes de layout
│   │   │   ├── AppShell.svelte
│   │   │   ├── AppHeader.svelte
│   │   │   ├── AppSidebar.svelte
│   │   │   └── ...
│   │   │
│   │   ├── navigation/     # Componentes de navegación
│   │   │   ├── NavMain.svelte
│   │   │   ├── NavUser.svelte
│   │   │   └── ...
│   │   │
│   │   ├── auth/           # Componentes de autenticación
│   │   │   ├── TwoFactorSetupModal.svelte
│   │   │   └── ...
│   │   │
│   │   ├── common/         # Componentes comunes
│   │   │   ├── Heading.svelte
│   │   │   ├── Icon.svelte
│   │   │   └── ...
│   │   │
│   │   └── index.ts        # Barrel export principal
│   │
│   └── ui/                 # Componentes UI de shadcn-svelte
│       ├── button/
│       ├── card/
│       └── ...
│
├── pages/                   # Páginas de Inertia (rutas)
│   ├── admin/              # Páginas del panel administrativo
│   ├── auth/               # Páginas de autenticación
│   ├── dashboards/         # Dashboards por rol
│   ├── docente/            # Páginas del panel docente
│   ├── settings/           # Páginas de configuración
│   └── Dashboard.svelte    # Dashboard principal
│
├── layouts/                # Layouts de página
│   ├── AdminLayout.svelte
│   ├── AuthLayout.svelte
│   └── ...
│
├── hooks/                  # Hooks personalizados (Svelte runes)
│   ├── useAppearance.svelte.ts
│   ├── useInitials.ts
│   ├── is-mobile.svelte.ts
│   └── index.ts
│
├── lib/                    # Utilidades y helpers
│   ├── utils.ts           # Funciones utilitarias generales
│   ├── validators.ts      # Validadores (RUT, email, etc.)
│   ├── two-factor-auth.svelte.ts
│   └── index.ts
│
├── types/                  # Definiciones de tipos TypeScript
│   ├── index.d.ts         # Tipos globales
│   ├── index.ts           # Barrel export de tipos
│   ├── admin.types.ts     # Tipos del módulo admin
│   ├── global.d.ts        # Declaraciones globales
│   └── svelte-runes.d.ts  # Tipos para Svelte 5 runes
│
└── wayfinder/             # Rutas generadas por Wayfinder
    └── index.ts
```

## 📝 Convenciones de Código

### Nomenclatura

#### Archivos
- **Componentes Svelte**: `PascalCase.svelte` (ej: `DataTable.svelte`)
- **TypeScript/JavaScript**: `camelCase.ts` o `kebab-case.ts`
- **Hooks con runes**: `useName.svelte.ts` (ej: `useAppearance.svelte.ts`)
- **Tipos**: `name.types.ts` (ej: `admin.types.ts`)

#### Variables y Funciones
```typescript
// Variables reactivas (Svelte 5 runes)
let count = $state(0);
let user = $state<User | null>(null);

// Props
let { title, description, onSubmit }: Props = $props();

// Funciones
function handleSubmit() { }
const formatDate = (date: string) => { };
```

#### Tipos e Interfaces
```typescript
// Interfaces para entidades
export interface Usuario {
    id_usuario: number;
    username: string;
}

// Tipos para datos de formularios
export interface UsuarioFormData {
    username: string;
    password: string;
}

// Tipos para respuestas paginadas
export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    total: number;
}
```

### Estructura de Componentes

```svelte
<script lang="ts">
    // 1. Imports
    import { router } from '@inertiajs/svelte';
    import type { Usuario } from '@/types/admin.types';
    
    // 2. Props interface
    interface Props {
        usuario: Usuario;
        onUpdate?: (usuario: Usuario) => void;
    }
    
    // 3. Props destructuring
    let { usuario, onUpdate }: Props = $props();
    
    // 4. State
    let isEditing = $state(false);
    let formData = $state({ ...usuario });
    
    // 5. Derived state
    let fullName = $derived(`${usuario.nombre1} ${usuario.apellido1}`);
    
    // 6. Effects
    $effect(() => {
        console.log('Usuario changed:', usuario);
    });
    
    // 7. Functions
    function handleSubmit() {
        // ...
    }
</script>

<!-- 8. Template -->
<div class="container">
    <!-- ... -->
</div>

<!-- 9. Styles (si son necesarios) -->
<style>
    .container {
        /* ... */
    }
</style>
```

## 🧩 Componentes

### Componentes Admin

#### DataTable
Tabla de datos con paginación, búsqueda y acciones.

```svelte
<DataTable 
    data={usuarios} 
    columns={columns} 
    onEdit={handleEdit} 
    onDelete={handleDelete} 
/>
```

#### FormModal
Modal reutilizable para formularios.

```svelte
<FormModal
    bind:isOpen={showModal}
    title="Nuevo Usuario"
    onClose={closeModal}
    onSubmit={handleSubmit}
    isLoading={isLoading}
>
    <!-- Contenido del formulario -->
</FormModal>
```

#### DeleteConfirmation
Diálogo de confirmación para eliminaciones.

```svelte
<DeleteConfirmation
    bind:isOpen={showDeleteDialog}
    title="¿Eliminar Usuario?"
    message="Esta acción no se puede deshacer."
    onConfirm={handleDelete}
    onCancel={closeDialog}
/>
```

Ver [COMPONENTS.md](./COMPONENTS.md) para documentación completa de componentes.

## 🔷 Sistema de Tipos

### Tipos Globales
Definidos en `types/index.d.ts`:
- `User` - Usuario autenticado
- `PageProps<T>` - Props de página Inertia
- `SharedAuth` - Datos de autenticación compartidos

### Tipos Admin
Definidos en `types/admin.types.ts`:
- Entidades: `Facultad`, `Departamento`, `Carrera`, `Asignatura`, etc.
- Form Data: `FacultadFormData`, `CarreraFormData`, etc.
- `PaginatedResponse<T>` - Respuestas paginadas

### Uso de Tipos

```typescript
import type { Usuario, UsuarioFormData } from '@/types/admin.types';
import type { PageProps } from '@/types';

interface Props {
    usuarios: PaginatedResponse<Usuario>;
}

let formData = $state<UsuarioFormData>({
    username: '',
    password: ''
});
```

## 🛣️ Rutas y Navegación

### Inertia Router

```typescript
import { router } from '@inertiajs/svelte';

// Navegación simple
router.visit('/admin/usuarios');

// POST request
router.post('/admin/usuarios', formData, {
    onSuccess: () => console.log('Success'),
    onError: () => console.log('Error')
});

// DELETE request
router.delete(`/admin/usuarios/${id}`);
```

### Wayfinder (cuando esté disponible)

```typescript
import { route } from '@/lib/wayfinder';

// Rutas type-safe
const url = route('admin.usuarios.index');
const editUrl = route('admin.usuarios.edit', { usuario: 1 });
```

## 🛠️ Guía de Desarrollo

### Crear un Nuevo Componente

1. **Crear el archivo** en la carpeta apropiada
2. **Definir la interfaz Props** con TypeScript
3. **Usar Svelte 5 runes** para state y props
4. **Agregar JSDoc** para documentación
5. **Exportar desde index.ts** si es reutilizable

### Crear una Nueva Página

1. **Crear archivo** en `pages/[módulo]/NombrePagina.svelte`
2. **Usar layout apropiado** (AdminLayout, etc.)
3. **Definir PageProps** con los datos que recibe
4. **Implementar la lógica** usando Inertia router

### Agregar Nuevos Tipos

1. **Definir en archivo apropiado** (`admin.types.ts`, etc.)
2. **Exportar la interfaz**
3. **Agregar JSDoc** con descripción
4. **Actualizar barrel export** en `types/index.ts`

### Mejores Prácticas

✅ **DO**
- Usar TypeScript para todo
- Documentar componentes con JSDoc
- Usar Svelte 5 runes (`$state`, `$derived`, `$effect`)
- Mantener componentes pequeños y enfocados
- Reutilizar componentes UI de shadcn-svelte
- Usar TailwindCSS para estilos

❌ **DON'T**
- No usar `any` en TypeScript
- No mezclar lógica de negocio en componentes UI
- No duplicar código - crear utilidades
- No ignorar errores de TypeScript
- No usar estilos inline excesivamente

### Comandos Útiles

```bash
# Desarrollo
npm run dev

# Build
npm run build

# Type checking
npm run type-check

# Linting
npm run lint
```

## 📖 Recursos Adicionales

- [Svelte 5 Documentation](https://svelte.dev/docs/svelte/overview)
- [Inertia.js Svelte Adapter](https://inertiajs.com/client-side-setup#svelte)
- [TailwindCSS Documentation](https://tailwindcss.com/docs)
- [shadcn-svelte Components](https://www.shadcn-svelte.com/docs/components)

---

**Última actualización**: 2026-02-03
