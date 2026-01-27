# Modelo VwPermisosUsuario

## Ubicación
- **Namespace**: `App\Models\Usuario` (extendido desde Base)
- **Archivo Base**: `app/Models/Base/Usuario/BaseVwPermisosUsuario.php`
- **Tabla/Vista**: `vw_permisos_usuario`

## Propósito
Representa una **vista de base de datos** que consolida todos los permisos de un usuario, tanto los asignados por roles como los permisos especiales.

## Objetivo
Facilitar consultas de permisos de usuarios sin necesidad de realizar múltiples joins. Proporciona una vista optimizada para verificación de permisos en el sistema.

## Estructura de Datos

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | Integer | Identificador único del registro |
| `id_usuario` | Integer | ID del usuario |
| `id_contexto` | Integer | ID del contexto |
| `slug` | String | Slug del permiso |
| `tipo_asignacion` | String | Tipo (por rol o especial) |
| `esta_activo` | Boolean | Indica si está activo |

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo permisos activos

### Ejemplo de Uso
```php
// Obtener todos los permisos de un usuario
$permisos = VwPermisosUsuario::where('id_usuario', $userId)->get();

// Verificar si usuario tiene permiso específico
$tienePermiso = VwPermisosUsuario::where('id_usuario', $userId)
    ->where('slug', 'crear-curso')
    ->where('id_contexto', $contextoId)
    ->exists();

// Obtener permisos por contexto
$permisosContexto = VwPermisosUsuario::where('id_usuario', $userId)
    ->where('id_contexto', $contextoId)
    ->get();
```

## Notas Importantes
- Este es un modelo de solo lectura (vista de base de datos)
- No se deben realizar operaciones de escritura
- Consolida permisos de roles y permisos especiales
- Optimizado para verificación rápida de permisos
- El modelo base es auto-generado y no debe editarse directamente
