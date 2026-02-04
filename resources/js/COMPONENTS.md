# Guía de Componentes - UTAMed

Documentación completa de los componentes reutilizables del frontend.

## 📋 Tabla de Contenidos

- [Componentes Admin](#componentes-admin)
- [Componentes de Layout](#componentes-de-layout)
- [Componentes de Navegación](#componentes-de-navegación)
- [Componentes de Autenticación](#componentes-de-autenticación)
- [Componentes UI (shadcn-svelte)](#componentes-ui)

---

## Componentes Admin

### DataTable

Tabla de datos con paginación, búsqueda y acciones CRUD.

**Ubicación**: `components/admin/DataTable.svelte`

**Props**:
```typescript
interface Props {
    data: PaginatedResponse<any>;  // Datos paginados
    columns: Column[];              // Definición de columnas
    onEdit?: (item: any) => void;   // Callback para editar
    onDelete?: (item: any) => void; // Callback para eliminar
}

interface Column {
    key: string;   // Clave del dato a mostrar
    label: string; // Etiqueta de la columna
}
```

**Ejemplo de uso**:
```svelte
<script lang="ts">
    import DataTable from '@/components/admin/DataTable.svelte';
    import type { Asignatura, PaginatedResponse } from '@/types/admin.types';
    
    interface Props {
        asignaturas: PaginatedResponse<Asignatura>;
    }
    
    let { asignaturas }: Props = $props();
    
    const columns = [
        { key: 'cod_asignatura', label: 'Código' },
        { key: 'nombre', label: 'Nombre' },
        { key: 'creditos_sct', label: 'Créditos' }
    ];
    
    function handleEdit(asignatura: Asignatura) {
        // Lógica de edición
    }
    
    function handleDelete(asignatura: Asignatura) {
        // Lógica de eliminación
    }
</script>

<DataTable 
    data={asignaturas} 
    {columns} 
    onEdit={handleEdit} 
    onDelete={handleDelete} 
/>
```

**Características**:
- ✅ Paginación automática
- ✅ Búsqueda integrada
- ✅ Acciones de editar/eliminar
- ✅ Responsive
- ✅ Estilos consistentes

---

### FormModal

Modal reutilizable para formularios con manejo de estado de carga.

**Ubicación**: `components/admin/FormModal.svelte`

**Props**:
```typescript
interface Props {
    isOpen: boolean;           // Estado del modal (bind:isOpen)
    title: string;             // Título del modal
    onClose: () => void;       // Callback al cerrar
    onSubmit: () => void;      // Callback al enviar
    isLoading?: boolean;       // Estado de carga
    submitText?: string;       // Texto del botón (default: "Guardar")
    cancelText?: string;       // Texto de cancelar (default: "Cancelar")
}
```

**Ejemplo de uso**:
```svelte
<script lang="ts">
    import FormModal from '@/components/admin/FormModal.svelte';
    import type { AsignaturaFormData } from '@/types/admin.types';
    
    let showModal = $state(false);
    let isLoading = $state(false);
    let formData = $state<AsignaturaFormData>({
        cod_asignatura: '',
        nombre: '',
        creditos_sct: 0
    });
    
    function handleSubmit() {
        isLoading = true;
        router.post('/admin/asignaturas', formData, {
            onSuccess: () => {
                showModal = false;
                isLoading = false;
            },
            onError: () => {
                isLoading = false;
            }
        });
    }
</script>

<FormModal
    bind:isOpen={showModal}
    title="Nueva Asignatura"
    onClose={() => showModal = false}
    onSubmit={handleSubmit}
    {isLoading}
>
    <div class="form-group">
        <label for="codigo">Código</label>
        <input 
            id="codigo"
            type="text" 
            bind:value={formData.cod_asignatura}
            required
        />
    </div>
    
    <div class="form-group">
        <label for="nombre">Nombre</label>
        <input 
            id="nombre"
            type="text" 
            bind:value={formData.nombre}
            required
        />
    </div>
</FormModal>
```

**Características**:
- ✅ Backdrop con click para cerrar
- ✅ Animaciones suaves
- ✅ Estado de carga en botón
- ✅ Accesibilidad (ESC para cerrar)
- ✅ Responsive

---

### DeleteConfirmation

Diálogo de confirmación para acciones destructivas.

**Ubicación**: `components/admin/DeleteConfirmation.svelte`

**Props**:
```typescript
interface Props {
    isOpen: boolean;           // Estado del diálogo (bind:isOpen)
    title: string;             // Título del diálogo
    message: string;           // Mensaje de confirmación
    onConfirm: () => void;     // Callback al confirmar
    onCancel: () => void;      // Callback al cancelar
    isLoading?: boolean;       // Estado de carga
    confirmText?: string;      // Texto de confirmar (default: "Eliminar")
    cancelText?: string;       // Texto de cancelar (default: "Cancelar")
}
```

**Ejemplo de uso**:
```svelte
<script lang="ts">
    import DeleteConfirmation from '@/components/admin/DeleteConfirmation.svelte';
    import type { Asignatura } from '@/types/admin.types';
    
    let showDeleteDialog = $state(false);
    let isLoading = $state(false);
    let deletingItem = $state<Asignatura | null>(null);
    
    function openDeleteDialog(asignatura: Asignatura) {
        deletingItem = asignatura;
        showDeleteDialog = true;
    }
    
    function handleDelete() {
        if (!deletingItem) return;
        
        isLoading = true;
        router.delete(`/admin/asignaturas/${deletingItem.id_asignatura}`, {
            onSuccess: () => {
                showDeleteDialog = false;
                isLoading = false;
            },
            onError: () => {
                isLoading = false;
            }
        });
    }
</script>

<DeleteConfirmation
    bind:isOpen={showDeleteDialog}
    title="¿Eliminar Asignatura?"
    message="Esta acción no se puede deshacer. Si la asignatura está asignada a planes, no podrá ser eliminada."
    onConfirm={handleDelete}
    onCancel={() => showDeleteDialog = false}
    {isLoading}
/>
```

**Características**:
- ✅ Diseño de advertencia (rojo)
- ✅ Prevención de clicks accidentales
- ✅ Estado de carga
- ✅ Accesibilidad

---

### CourseTeamModal

Modal para gestionar equipos de docentes en cursos.

**Ubicación**: `components/admin/CourseTeamModal.svelte`

**Props**:
```typescript
interface Props {
    isOpen: boolean;
    curso: Curso;
    onClose: () => void;
}
```

**Ejemplo de uso**:
```svelte
<CourseTeamModal
    bind:isOpen={showTeamModal}
    {curso}
    onClose={() => showTeamModal = false}
/>
```

---

### PermissionsModal

Modal para gestionar permisos de usuarios.

**Ubicación**: `components/admin/PermissionsModal.svelte`

**Props**:
```typescript
interface Props {
    isOpen: boolean;
    usuario: Usuario;
    onClose: () => void;
}
```

---

## Componentes de Layout

### AdminLayout

Layout principal para páginas administrativas.

**Ubicación**: `layouts/AdminLayout.svelte`

**Ejemplo de uso**:
```svelte
<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
</script>

<AdminLayout>
    <div class="page-container">
        <h1>Mi Página Admin</h1>
        <!-- Contenido -->
    </div>
</AdminLayout>
```

**Características**:
- ✅ Sidebar con navegación
- ✅ Header con usuario
- ✅ Breadcrumbs automáticos
- ✅ Responsive (mobile menu)

---

### AppShell

Shell principal de la aplicación con sidebar.

**Ubicación**: `components/AppShell.svelte`

**Props**:
```typescript
interface Props {
    // Contenido se pasa como children/slot
}
```

---

### AppHeader

Header de la aplicación con navegación y menú de usuario.

**Ubicación**: `components/AppHeader.svelte`

**Características**:
- ✅ Logo de la aplicación
- ✅ Breadcrumbs
- ✅ Menú de usuario
- ✅ Toggle de tema (claro/oscuro)

---

### AppSidebar

Sidebar de navegación con menú contextual según rol.

**Ubicación**: `components/AppSidebar.svelte`

**Características**:
- ✅ Navegación por rol
- ✅ Iconos de Lucide
- ✅ Estado activo
- ✅ Colapsable en mobile

---

## Componentes de Navegación

### NavMain

Navegación principal del sidebar.

**Ubicación**: `components/NavMain.svelte`

**Props**:
```typescript
interface Props {
    items: NavItem[];
}

interface NavItem {
    title: string;
    href: string;
    icon?: any;
    isActive?: boolean;
}
```

---

### NavUser

Menú de usuario en el sidebar.

**Ubicación**: `components/NavUser.svelte`

**Props**:
```typescript
interface Props {
    user: User;
}
```

---

### Breadcrumbs

Componente de breadcrumbs para navegación.

**Ubicación**: `components/Breadcrumbs.svelte`

**Props**:
```typescript
interface Props {
    items: BreadcrumbItem[];
}

interface BreadcrumbItem {
    title: string;
    href: string;
}
```

---

## Componentes de Autenticación

### TwoFactorSetupModal

Modal para configurar autenticación de dos factores.

**Ubicación**: `components/TwoFactorSetupModal.svelte`

**Características**:
- ✅ Generación de QR
- ✅ Códigos de recuperación
- ✅ Verificación de código

---

### TwoFactorRecoveryCodes

Visualización de códigos de recuperación 2FA.

**Ubicación**: `components/TwoFactorRecoveryCodes.svelte`

---

### DeleteUser

Componente para eliminar cuenta de usuario.

**Ubicación**: `components/DeleteUser.svelte`

**Características**:
- ✅ Confirmación con contraseña
- ✅ Advertencias claras
- ✅ Prevención de eliminación accidental

---

## Componentes UI

Los componentes UI están basados en [shadcn-svelte](https://www.shadcn-svelte.com/) y se encuentran en `components/ui/`.

### Componentes Disponibles

- **Accordion** - Acordeones expandibles
- **Alert** - Alertas y notificaciones
- **Avatar** - Avatares de usuario
- **Badge** - Etiquetas y badges
- **Button** - Botones con variantes
- **Card** - Tarjetas de contenido
- **Checkbox** - Checkboxes
- **Dialog** - Diálogos modales
- **Dropdown Menu** - Menús desplegables
- **Form** - Componentes de formulario
- **Input** - Campos de entrada
- **Label** - Etiquetas de formulario
- **Select** - Selectores
- **Table** - Tablas
- **Tabs** - Pestañas
- **Toast** - Notificaciones toast
- Y muchos más...

**Documentación completa**: [shadcn-svelte Components](https://www.shadcn-svelte.com/docs/components)

---

## Patrones Comunes

### Patrón de Página CRUD

```svelte
<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import DataTable from '@/components/admin/DataTable.svelte';
    import FormModal from '@/components/admin/FormModal.svelte';
    import DeleteConfirmation from '@/components/admin/DeleteConfirmation.svelte';
    import { router } from '@inertiajs/svelte';
    import type { Entity, EntityFormData, PaginatedResponse } from '@/types/admin.types';
    
    interface Props {
        entities: PaginatedResponse<Entity>;
    }
    
    let { entities }: Props = $props();
    
    // State
    let showModal = $state(false);
    let showDeleteDialog = $state(false);
    let isLoading = $state(false);
    let editingEntity = $state<Entity | null>(null);
    let deletingEntity = $state<Entity | null>(null);
    let formData = $state<EntityFormData>({ /* ... */ });
    
    // Columns
    const columns = [
        { key: 'id', label: 'ID' },
        { key: 'name', label: 'Nombre' }
    ];
    
    // Handlers
    function openCreateModal() {
        editingEntity = null;
        formData = { /* reset */ };
        showModal = true;
    }
    
    function openEditModal(entity: Entity) {
        editingEntity = entity;
        formData = { ...entity };
        showModal = true;
    }
    
    function handleSubmit() {
        isLoading = true;
        const url = editingEntity 
            ? `/admin/entities/${editingEntity.id}` 
            : '/admin/entities';
        const method = editingEntity ? 'put' : 'post';
        
        router[method](url, formData, {
            onSuccess: () => {
                showModal = false;
                isLoading = false;
            },
            onError: () => {
                isLoading = false;
            }
        });
    }
    
    function openDeleteDialog(entity: Entity) {
        deletingEntity = entity;
        showDeleteDialog = true;
    }
    
    function handleDelete() {
        if (!deletingEntity) return;
        
        isLoading = true;
        router.delete(`/admin/entities/${deletingEntity.id}`, {
            onSuccess: () => {
                showDeleteDialog = false;
                isLoading = false;
            },
            onError: () => {
                isLoading = false;
            }
        });
    }
</script>

<AdminLayout>
    <div class="page-container">
        <div class="page-header">
            <h1>Entidades</h1>
            <button onclick={openCreateModal}>Nueva Entidad</button>
        </div>
        
        <DataTable 
            data={entities} 
            {columns} 
            onEdit={openEditModal} 
            onDelete={openDeleteDialog} 
        />
    </div>
    
    <FormModal
        bind:isOpen={showModal}
        title={editingEntity ? 'Editar' : 'Crear'}
        onClose={() => showModal = false}
        onSubmit={handleSubmit}
        {isLoading}
    >
        <!-- Form fields -->
    </FormModal>
    
    <DeleteConfirmation
        bind:isOpen={showDeleteDialog}
        title="¿Eliminar?"
        message="Esta acción no se puede deshacer."
        onConfirm={handleDelete}
        onCancel={() => showDeleteDialog = false}
        {isLoading}
    />
</AdminLayout>
```

---

**Última actualización**: 2026-02-03
