# Modelo Permiso

## Ubicación
- **Namespace**: `App\Models\Usuario` (extendido desde Base)
- **Archivo Base**: `app/Models/Base/Usuario/BasePermiso.php`
- **Tabla**: `Permiso`

## Propósito
Representa un **permiso del sistema** que define una acción o capacidad específica que puede ser otorgada a usuarios o roles.

## Objetivo
Gestionar el catálogo de permisos disponibles en el sistema, permitiendo un control granular de acceso a funcionalidades. Base del sistema de autorización.

## Estructura de Datos

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_permiso` | Integer | Identificador único del permiso |
| `slug` | String | Identificador único en formato slug (ej: 'crear-curso') |
| `nombre` | String | Nombre descriptivo del permiso |
| `descripcion` | Text | Descripción detallada del permiso |
| `esta_activo` | Boolean | Indica si el permiso está activo |

## Relaciones

### Relaciones Inversas (hasMany)
- **`asignaciónRolPermisos()`**: Asignaciones a roles
- **`usuarioPermisoEspeciales()`**: Asignaciones especiales a usuarios

### Relaciones Muchos a Muchos (belongsToMany)
- **`roles()`**: Roles que tienen este permiso
- **`usuariosUPE()`**: Usuarios con este permiso especial
- **`contextosUPE()`**: Contextos donde se aplica el permiso

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo permisos activos

### Ejemplo de Uso
```php
// Obtener permiso por slug
$permiso = Permiso::where('slug', 'crear-curso')->first();

// Obtener roles que tienen un permiso
$roles = Permiso::find($id)->roles;

// Obtener usuarios con permiso especial
$usuarios = Permiso::find($id)->usuariosUPE;

// Listar todos los permisos
$permisos = Permiso::active()->get();
```

## Notas Importantes
- El `slug` es único y se usa para verificar permisos en código
- Los permisos pueden asignarse a roles o directamente a usuarios
- Los permisos especiales permiten excepciones temporales
- El sistema soporta permisos contextuales (por facultad, curso, etc.)
- El modelo base es auto-generado y no debe editarse directamente
