# Modelo UsuarioPermisoEspecial

## Ubicación
- **Namespace**: `App\Models\Usuario` (extendido desde Base)
- **Archivo Base**: `app/Models/Base/Usuario/BaseUsuarioPermisoEspecial.php`
- **Tabla**: `Usuario_Permiso_Especial`

## Propósito
Representa un **permiso especial asignado directamente a un usuario** en un contexto específico, con vigencia temporal.

## Objetivo
Gestionar permisos excepcionales otorgados a usuarios fuera de los roles estándar, permitiendo permisos temporales y específicos por contexto.

## Estructura de Datos

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_usuario` | Integer | ID del usuario |
| `id_permiso` | Integer | ID del permiso |
| `id_contexto` | Integer | ID del contexto |
| `fecha_inicio` | Date | Fecha de inicio de vigencia |
| `fecha_fin` | Date | Fecha de fin de vigencia |
| `esta_permitido` | Boolean | Si está permitido o denegado |
| `duracion_dias` | Integer | Duración en días |
| `puede_delegar` | Boolean | Si puede delegar el permiso |
| `esta_activo` | Boolean | Indica si está activo |
| `fecha_creacion` | Timestamp | Fecha de creación |

## Relaciones

### Relaciones Directas (belongsTo)
- **`usuario()`**: Pertenece a un usuario
- **`permiso()`**: Pertenece a un permiso
- **`contexto()`**: Pertenece a un contexto

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo permisos activos

### Ejemplo de Uso
```php
// Asignar permiso especial temporal
$permisoEspecial = new UsuarioPermisoEspecial();
$permisoEspecial->id_usuario = $userId;
$permisoEspecial->id_permiso = $permisoId;
$permisoEspecial->id_contexto = $contextoId;
$permisoEspecial->fecha_inicio = now();
$permisoEspecial->fecha_fin = now()->addDays(30);
$permisoEspecial->esta_permitido = true;
$permisoEspecial->save();

// Verificar permisos vigentes
$permisosVigentes = UsuarioPermisoEspecial::where('id_usuario', $userId)
    ->where('fecha_inicio', '<=', now())
    ->where('fecha_fin', '>=', now())
    ->get();
```

## Notas Importantes
- Permite permisos temporales y excepcionales
- El campo `esta_permitido` permite denegar permisos explícitamente
- Los permisos tienen vigencia temporal definida
- Pueden ser delegables a otros usuarios
- El modelo base es auto-generado y no debe editarse directamente
