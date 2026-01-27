# Modelo Rol

## Ubicación
- **Namespace**: `App\Models\Usuario` (extendido desde Base)
- **Archivo Base**: `app/Models/Base/Usuario/BaseRol.php`
- **Tabla**: `Rol`

## Propósito
Representa un **rol del sistema** que agrupa permisos y puede ser asignado a usuarios en contextos específicos.

## Objetivo
Gestionar los roles del sistema de autorización, permitiendo agrupar permisos y asignarlos a usuarios de forma organizada. Implementa RBAC (Role-Based Access Control).

## Estructura de Datos

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_rol` | Integer | Identificador único del rol |
| `nombre` | String | Nombre del rol |
| `id_usuario_autor` | Integer | ID del usuario que creó el rol |
| `esta_activo` | Boolean | Indica si el rol está activo |

## Relaciones

### Relaciones Directas (belongsTo)
- **`usuario()`**: Creado por un usuario específico

### Relaciones Inversas (hasMany)
- **`asignaciónRolPermisos()`**: Permisos asignados al rol
- **`usuarioRolAsignacciones()`**: Asignaciones del rol a usuarios

### Relaciones Muchos a Muchos (belongsToMany)
- **`permisos()`**: Permisos asociados al rol
- **`usuariosURA()`**: Usuarios que tienen este rol
- **`contextosURA()`**: Contextos donde se usa este rol

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo roles activos

### Ejemplo de Uso
```php
// Obtener rol con sus permisos
$rol = Rol::with('permisos')->find($id);

// Obtener usuarios con un rol específico
$usuarios = Rol::find($id)->usuariosURA;

// Crear nuevo rol
$rol = new Rol();
$rol->nombre = 'Coordinador';
$rol->id_usuario_autor = $userId;
$rol->save();

// Asignar permisos a un rol
$rol->permisos()->attach($permisoId, ['puede_delegar_permisos' => true]);
```

## Notas Importantes
- Los roles agrupan permisos para facilitar la gestión
- Pueden ser asignados a usuarios en contextos específicos
- El sistema permite delegación de permisos
- Cada rol tiene un autor que lo creó
- El modelo base es auto-generado y no debe editarse directamente
