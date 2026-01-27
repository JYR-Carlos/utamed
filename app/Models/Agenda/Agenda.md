# Modelo Agenda

## Ubicación
- **Namespace**: `App\Models\Agenda`
- **Archivo**: `app/Models/Agenda/Agenda.php`
- **Clase Base**: `App\Models\Base\Agenda\BaseAgenda`

## Propósito
Representa la **agenda general** del sistema. Este modelo está actualmente en desarrollo (WIP - Work In Progress).

## Objetivo
Gestionar la agenda o calendario general del sistema educativo, que podría incluir eventos, fechas importantes, y planificación temporal de actividades académicas.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Agenda`
- **Clave Primaria**: `id` (auto-incremental)
- **Conexión**: PostgreSQL (`pgsql`)
- **Timestamps**: Deshabilitados

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | Integer | Identificador único de la agenda |
| `wip` | Mixed | Campo temporal (Work In Progress) |
| `esta_activo` | Boolean | Indica si el registro está activo |

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo los registros activos

### Ejemplo de Uso
```php
// Obtener todas las agendas activas
$agendas = Agenda::active()->get();

// Obtener agenda específica
$agenda = Agenda::find($id);
```

## Notas Importantes
- Este modelo está en desarrollo (indicado por el campo `wip`)
- No tiene timestamps habilitados
- Actualmente no tiene relaciones definidas
- El modelo base es auto-generado y no debe editarse directamente
- La funcionalidad completa se implementará en futuras versiones
