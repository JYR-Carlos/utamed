# Modelo UsuarioRolAsignación

## Ubicación
- **Namespace**: `App\Models\Usuario` (extendido desde Base)
- **Archivo Base**: `app/Models/Base/Usuario/BaseUsuarioRolAsignación.php`
- **Tabla**: `Usuario_Rol_Asignación`

## Propósito
Representa la **asignación de un rol a un usuario** en un contexto específico, con vigencia temporal y trazabilidad de quién realizó la asignación.

## Objetivo
Gestionar las asignaciones de roles a usuarios, permitiendo roles temporales, contextuales y con auditoría de quién otorgó el rol.

## Estructura de Datos

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_usuario` | Integer | ID del usuario que recibe el rol |
| `id_rol` | Integer | ID del rol asignado |
| `id_contexto` | Integer | ID del contexto donde aplica |
| `asignado_por` | Integer | ID del usuario que asignó el rol |
| `fecha_inicio` | Date | Fecha de inicio de vigencia |
| `fecha_fin` | Date | Fecha de fin de vigencia |
| `duracion` | Integer | Duración en días |
| `esta_activo` | Boolean | Indica si está activo |
| `fecha_creacion` | Timestamp | Fecha de creación |

## Relaciones

### Relaciones Directas (belongsTo)
- **`usuario()`**: Usuario que recibe el rol
- **`contexto()`**: Contexto donde aplica
- **`rol()`**: Rol asignado
- **`asignadoPor()`**: Usuario que realizó la asignación

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo asignaciones activas

### Ejemplo de Uso
```php
// Asignar rol a usuario
$asignacion = new UsuarioRolAsignación();
$asignacion->id_usuario = $userId;
$asignacion->id_rol = $rolId;
$asignacion->id_contexto = $contextoId;
$asignacion->asignado_por = $adminId;
$asignacion->fecha_inicio = now();
$asignacion->fecha_fin = now()->addMonths(6);
$asignacion->save();

// Obtener roles vigentes de un usuario
$rolesVigentes = UsuarioRolAsignación::where('id_usuario', $userId)
    ->where('fecha_inicio', '<=', now())
    ->where('fecha_fin', '>=', now())
    ->with('rol')
    ->get();
```

## Notas Importantes
- Permite roles temporales con vigencia definida
- Incluye auditoría de quién asignó el rol
- Los roles son contextuales (pueden variar por facultad, curso, etc.)
- El modelo base es auto-generado y no debe editarse directamente
