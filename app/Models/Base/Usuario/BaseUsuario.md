# Modelo Usuario

## Ubicación
- **Namespace**: `App\Models\Usuario` (extendido desde Base)
- **Archivo Base**: `app/Models/Base/Usuario/BaseUsuario.php`
- **Tabla**: `Usuario`

## Propósito
Representa un **usuario del sistema**, que es la entidad base para todas las personas que interactúan con la plataforma educativa.

## Objetivo
Gestionar la información básica de todos los usuarios del sistema, incluyendo credenciales de acceso, datos personales y relaciones con roles específicos (docente, estudiante). Actúa como base para el sistema de autenticación y autorización.

## Estructura de Datos

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_usuario` | Integer | Identificador único del usuario |
| `username` | String | Nombre de usuario para login |
| `passhash` | String | Hash de la contraseña |
| `email` | String | Correo electrónico |
| `nombre1` | String | Primer nombre |
| `nombre2` | String | Segundo nombre (opcional) |
| `apellido1` | String | Primer apellido |
| `apellido2` | String | Segundo apellido (opcional) |
| `rut` | String | RUT (identificador nacional chileno) |
| `esta_activo` | Boolean | Indica si el usuario está activo |
| `fecha_creacion` | Timestamp | Fecha de creación del registro |

## Relaciones

### Relaciones Uno a Uno (hasOne)
- **`programa()`**: Puede tener un programa asociado
- **`docente()`**: Puede ser un docente
- **`estudiante()`**: Puede ser un estudiante

### Relaciones Inversas (hasMany)
- **`rolesCreados()`**: Roles que ha creado
- **`permisosEspecialesAsignados()`**: Permisos especiales asignados
- **`asignacionesRolRecibidas()`**: Roles que le han sido asignados
- **`asignacionesRolRealizadas()`**: Roles que ha asignado a otros

### Relaciones Muchos a Muchos (belongsToMany)
- **`permisosUPE()`**: Permisos especiales a través de `Usuario_Permiso_Especial`
- **`contextosUPE()`**: Contextos con permisos especiales
- **`contextosURA()`**: Contextos con roles asignados
- **`rolesURA()`**: Roles asignados en contextos
- **`usuariosURA()`**: Usuarios que le han asignado roles

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo usuarios activos

### Ejemplo de Uso
```php
// Obtener usuario con sus roles
$usuario = Usuario::with('rolesURA')->find($id);

// Verificar si es docente
$usuario = Usuario::find($id);
if ($usuario->docente) {
    // Es docente
}

// Obtener permisos especiales de un usuario
$permisos = Usuario::find($id)->permisosUPE;

// Buscar por RUT
$usuario = Usuario::where('rut', '12345678-9')->first();
```

## Notas Importantes
- Un usuario puede tener múltiples roles (docente, estudiante, ambos)
- El sistema de permisos se gestiona a través de roles y permisos especiales
- Los contextos permiten permisos granulares por facultad, curso, etc.
- El modelo base es auto-generado y no debe editarse directamente
