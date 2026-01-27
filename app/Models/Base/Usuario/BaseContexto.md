# Modelo Contexto

## Ubicación
- **Namespace**: `App\Models\Usuario` (extendido desde Base)
- **Archivo Base**: `app/Models/Base/Usuario/BaseContexto.php`
- **Tabla**: `Contexto`

## Propósito
Representa un **contexto de permisos** que define el alcance donde se aplican roles y permisos (facultad, curso, global, etc.).

## Objetivo
Gestionar los contextos que permiten aplicar permisos y roles de forma granular, permitiendo que un usuario tenga diferentes capacidades en diferentes ámbitos del sistema.

## Estructura de Datos

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_contexto` | Integer | Identificador único del contexto |
| `contexto_display` | String | Nombre descriptivo del contexto |
| `esta_activo` | Boolean | Indica si el contexto está activo |

## Relaciones

### Relaciones Uno a Uno (hasOne)
- **`facultad()`**: Puede estar asociado a una facultad
- **`curso()`**: Puede estar asociado a un curso

### Relaciones Inversas (hasMany)
- **`tipoContextos()`**: Tipos de contexto asociados
- **`usuarioPermisoEspeciales()`**: Permisos especiales en este contexto
- **`usuarioRolAsignacciones()`**: Asignaciones de roles en este contexto

### Relaciones Muchos a Muchos (belongsToMany)
- **`usuariosUPE()`**: Usuarios con permisos especiales
- **`permisosUPE()`**: Permisos especiales aplicados
- **`usuariosURA()`**: Usuarios con roles asignados
- **`rolesURA()`**: Roles aplicados en este contexto

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo contextos activos

### Ejemplo de Uso
```php
// Obtener contexto de una facultad
$contexto = Contexto::whereHas('facultad')->find($id);

// Obtener usuarios con roles en un contexto
$usuarios = Contexto::find($id)->usuariosURA;

// Obtener permisos aplicados en un contexto
$permisos = Contexto::find($id)->permisosUPE;

// Verificar si un contexto es de curso
$contexto = Contexto::find($id);
if ($contexto->curso) {
    // Es contexto de curso
}
```

## Notas Importantes
- Los contextos permiten permisos granulares por ámbito
- Un contexto puede ser global, de facultad, de curso, etc.
- Los usuarios pueden tener diferentes roles en diferentes contextos
- Fundamental para el sistema de autorización multinivel
- El modelo base es auto-generado y no debe editarse directamente
