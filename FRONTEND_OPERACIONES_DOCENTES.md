# Frontend - Implementación de Operaciones de Programas para Docentes

## Cambios en Componentes

### 1. **Programa.svelte** - Página Principal de Gestión

**Archivo**: `resources/js/pages/docente/Programa.svelte`

#### Nuevos Estados
```typescript
let isLoading = $state(false);
let permissionError = $state<string | null>(null);
```

#### Mejora: handleSave() - Manejo de Permisos
```typescript
function handleSave(event: CustomEvent) {
    const data = event.detail;
    isLoading = true;
    permissionError = null;
    
    router.post(`/docente/cursos/${curso.id_curso}/programa`, {
        secciones: data
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success("Programa guardado correctamente");
            hasUnsavedChanges = false;
            mode = 'view';
            isLoading = false;
        },
        onError: (errors) => {
            isLoading = false;
            // Detecta errores 403 (permiso denegado)
            if (errorMessage.includes("403") || errorMessage.includes("permiso")) {
                permissionError = "No tienes permiso para crear/editar...";
                toast.error(permissionError);
            }
        }
    });
}
```

**Cambios**:
- ✅ Muestra estado de carga (`isLoading`)
- ✅ Detecta errores 403 de autorización
- ✅ Muestra mensaje de error específico en alerta
- ✅ Deshabilita interacción durante operación

#### Mejora: handleDelete() - Manejo de Permisos
```typescript
function handleDelete() {
    isLoading = true;
    permissionError = null;
    
    router.delete(`/docente/cursos/${curso.id_curso}/programa`, {
        onSuccess: () => {
            isLoading = false;
            toast.success("Programa eliminado correctamente");
        },
        onError: (errors) => {
            isLoading = false;
            if (errorMessage.includes("403")) {
                permissionError = "No tienes permiso para eliminar...";
            }
        }
    });
}
```

**Cambios**:
- ✅ Manejo de estado de carga
- ✅ Manejo específico de errores 403

#### Mejora: UI - Alerta de Permisos
```svelte
<!-- Permission Error Alert -->
{#if permissionError}
    <div class="flex gap-3 rounded-lg border border-red-200 bg-red-50 p-4">
        <AlertCircle class="h-5 w-5 text-red-600" />
        <div>
            <p class="text-sm font-medium text-red-900">Acceso Denegado</p>
            <p class="text-sm text-red-700 mt-1">{permissionError}</p>
        </div>
    </div>
{/if}
```

**Cambios**:
- ✅ Muestra alerta visual cuando hay error de permiso
- ✅ Icono AlertCircle para indicar problema
- ✅ Estilos en rojo para claridad

#### Mejora: Botones - Estados de Carga
```svelte
<!-- Botón Editar -->
<Button variant="outline" onclick={switchToEdit} disabled={isLoading}>
    <Edit class="mr-2 size-4" />
    Editar
</Button>

<!-- Botón Eliminar -->
<Button variant="destructive" onclick={handleDelete} disabled={isLoading}>
    {#if isLoading}
        <Loader2 class="mr-2 size-4 animate-spin" />
        Eliminando...
    {:else}
        <Trash2 class="mr-2 size-4" />
        Eliminar
    {/if}
</Button>
```

**Cambios**:
- ✅ Botones deshabilitados durante operación (`disabled={isLoading}`)
- ✅ Indicador de carga (spinner) en Eliminar
- ✅ Texto actualizado a "Eliminando..." durante operación

---

### 2. **SyllabusEditor.svelte** - Editor de Programa

**Archivo**: `resources/js/components/SyllabusEditor.svelte`

#### Nuevo Prop
```typescript
export let isLoading = false;
```

#### Mejora: Botón Save con Estado
```svelte
<div class="sticky bottom-4 flex justify-end">
    <Button size="lg" class="shadow-xl" onclick={save} disabled={isLoading}>
        {#if isLoading}
            <Loader2 size={18} class="mr-2 animate-spin" /> Guardando...
        {:else}
            <Save size={18} class="mr-2" /> Guardar Programa
        {/if}
    </Button>
</div>
```

**Cambios**:
- ✅ Botón deshabilitado durante carga
- ✅ Spinner animado mientras guarda
- ✅ Texto cambia a "Guardando..."

#### Mejora: Botón Agregar Sección
```svelte
<Button 
    variant="secondary" 
    class="w-full border-dashed border-2 py-8" 
    onclick={addSection} 
    disabled={isLoading}
>
    <Plus size={24} class="mr-2" /> Agregar Nueva Sección
</Button>
```

**Cambios**:
- ✅ Deshabilitado cuando está guardando

---

## Flujo de Interacción

### Crear/Editar Programa

```
Usuario hace click en "Editar"
    ↓
Cambiar a modo 'edit' (iableTodo deshabilitado si isLoading=true)
    ↓
Usuario edita contenido en SyllabusEditor
    ↓
Usuario hace click en "Guardar Programa"
    ↓
isLoading = true (botones deshabilitados)
    ↓
POST /docente/cursos/{id}/programa
    ├─ ✅ 201/200 → "Programa guardado" → modo='view', isLoading=false
    └─ ❌ 403 → permissionError="No tienes permiso..." → isLoading=false
```

### Eliminar Programa

```
Usuario hace click en "Eliminar"
    ↓
Confirmación: "¿Estás seguro?"
    ↓
isLoading = true (botón muestra "Eliminando...")
    ↓
DELETE /docente/cursos/{id}/programa
    ├─ ✅ 200 → "Programa eliminado" → isLoading=false
    └─ ❌ 403 → permissionError="No tienes permiso..." → isLoading=false
```

---

## Escenarios de Usuario

### Escenario 1: Docente Autorizado ✅

```
- Docente: Juan (tiene permiso 'cursos/programas:*' en CC201)
- Acción: Editar programa

1. Hace click "Editar" → OK
2. Modifica contenido → OK
3. Hace click "Guardar" → POST
4. Respuesta 200 → "Programa guardado ✓"
5. Vuelve a vista normal → OK
```

**Resultado**: Operación exitosa

### Escenario 2: Docente NO Autorizado ❌

```
- Docente: María (SIN permiso en CC101)
- Acción: Intenta editar programa

1. Va a /cursos/CC101/programa
2. Ve el programa (puede ver, pero no editar)
3. Intenta click "Editar"
4. POST de edición
5. Respuesta 403 Forbidden
6. Alerta roja: "No tienes permiso para crear/editar..."
7. Permanece en modo view
```

**Resultado**: Operación bloqueada con mensaje claro

### Escenario 3: Docente Intenta Eliminar sin Permiso ❌

```
- Docente: Carlos (SIN permiso)
- Acción: Intenta eliminar

1. Click en "Eliminar"
2. Confirmación popup
3. Click "Confirmar"
4. Botón muestra "Eliminando..." (spinner)
5. DELETE request
6. Respuesta 403 Forbidden
7. Alerta roja: "No tienes permiso para eliminar..."
8. Botón vuelve a "Eliminar"
```

**Resultado**: Operación bloqueada

---

## Estados Visuales

### Botones Durante Carga
```
ANTES (isLoading=false):
├─ Editar:    [✎ Editar]
├─ Eliminar:  [🗑 Eliminar]
└─ Guardar:   [💾 Guardar Programa]

DURANTE (isLoading=true):
├─ Editar:    [✎ Editar] (disabled)
├─ Eliminar:  [⟳ Eliminando...] (spinning, disabled)
└─ Guardar:   [⟳ Guardando...] (spinning, disabled)
```

### Alerta de Permiso Denegado
```
┌─────────────────────────────────────────────────────┐
│ ⚠ Acceso Denegado                                   │
│                                                     │
│ No tienes permiso para crear/editar programas en   │
│ este curso. Solo un docente autorizado puede       │
│ hacerlo.                                            │
└─────────────────────────────────────────────────────┘

(Se muestra en color ROJO)
(Desaparece cuando recarga o cambia de página)
```

---

## Iconos Utilizados

| Icono | Uso | Paquete |
|-------|-----|---------|
| `Loader2` | Spinner durante carga | lucide-svelte |
| `AlertCircle` | Alerta de error/permiso | lucide-svelte |
| `Save` | Botón Guardar | lucide-svelte |
| `Trash2` | Botón Eliminar | lucide-svelte |
| `Edit` | Botón Editar | lucide-svelte |
| `Eye` | Ver/Preview | lucide-svelte |

---

## Mejoras de UX

✅ **Feedback Visual**
- Estados de carga claros
- Spinners animados
- Cambio de texto en botones

✅ **Prevención de Errores**
- Botones deshabilitados durante operación
- Previene doble-clicks
- Confirmación antes de eliminar

✅ **Manejo de Errores**
- Detecta errores 403 específicamente
- Mensajes claros de falta de permiso
- Toast notifications + alertas visuales

✅ **Accesibilidad**
- `disabled` en botones durante carga
- Icono + texto en alertas
- Colores significativos (rojo = error)

---

## Próximos Pasos (Opcional)

1. **Notificación de Permiso Faltante**: Mostrar tooltip al hover sobre botón deshabilitado
2. **Historial de Cambios**: Guardar quién y cuándo hizo cambios
3. **Preview**: Antes de guardar, mostrar vista previa del programa
4. **Versioning**: Mostrar versión actual vs. anterior
