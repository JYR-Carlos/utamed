# Modelo AsignaciónRolPermiso

## Ubicación
- **Namespace**: `App\Models\Usuario` (extendido desde Base)
- **Archivo Base**: `app/Models/Base/Usuario/BaseAsignaciónRolPermiso.php`
- **Tabla**: `Asignación_Rol_Permiso`

## Propósito
Representa la **asignación de un permiso a un rol**. Actúa como tabla pivote entre roles y permisos.

## Objetivo
Gestionar qué permisos tiene cada rol, incluyendo la capacidad de delegar esos permisos a otros usuarios.

## Estructura de Datos

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_rol` | Integer | ID del rol |
| `id_permiso` | Integer | ID del permiso |
| `puede_delegar_permisos` | Boolean | Si puede delegar este permiso |
| `esta_activo` | Boolean | Indica si la asignación está activa |

## Relaciones

### Relaciones Directas (belongsTo)
- **`rol()`**: Pertenece a un rol específico
- **`permiso()`**: Pertenece a un permiso específico

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo asignaciones activas

### Ejemplo de Uso
```php
// Asignar permiso a rol con delegación
$asignacion = new AsignaciónRolPermiso();
$asignacion->id_rol = $rolId;
$asignacion->id_permiso = $permisoId;
$asignacion->puede_delegar_permisos = true;
$asignacion->save();

// Obtener permisos de un rol
$permisos = AsignaciónRolPermiso::where('id_rol', $rolId)->get();
```

## Notas Importantes
- Define qué permisos tiene cada rol
- El campo `puede_delegar_permisos` controla la delegación
- El modelo base es auto-generado y no debe editarse directamente
