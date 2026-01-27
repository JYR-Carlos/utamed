# Modelo EstadoActividad

## Ubicación
- **Namespace**: `App\Models\Agenda`
- **Archivo**: `app/Models/Agenda/EstadoActividad.php`
- **Clase Base**: `App\Models\Base\Agenda\BaseEstadoActividad`

## Propósito
Representa los **estados posibles de una actividad** en su ciclo de vida (pendiente, en progreso, entregada, calificada, atrasada, etc.).

## Objetivo
Gestionar el catálogo de estados que puede tener una actividad asignada, permitiendo el seguimiento del progreso y estado de entrega de las actividades académicas.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Estado_Actividad`
- **Clave Primaria**: `id_estado` (auto-incremental)
- **Conexión**: PostgreSQL (`pgsql`)
- **Timestamps**: Deshabilitados

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_estado` | Integer | Identificador único del estado |
| `titulo` | String | Nombre del estado (ej: "Pendiente", "Entregada") |
| `descripcion` | Text | Descripción detallada del estado |
| `esta_activo` | Boolean | Indica si el estado está activo |

## Relaciones

### Relaciones Inversas (hasMany)
- **`actividadAsignadas()`**: Tiene múltiples actividades asignadas con este estado

### Relaciones Muchos a Muchos (belongsToMany)
- **`actividades()`**: Se relaciona con actividades a través de `Actividad_Asignada`
  - **Campos adicionales en pivote**: `grupo`, `nota`

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo los estados activos

### Ejemplo de Uso
```php
// Obtener todos los estados disponibles
$estados = EstadoActividad::active()->get();

// Obtener actividades con un estado específico
$estado = EstadoActividad::find($estadoId);
$actividades = $estado->actividadAsignadas;

// Buscar estado por título
$estadoPendiente = EstadoActividad::where('titulo', 'Pendiente')->first();

// Obtener actividades entregadas
$estadoEntregada = EstadoActividad::where('titulo', 'Entregada')->first();
$actividadesEntregadas = $estadoEntregada->actividades;
```

## Notas Importantes
- Actúa como catálogo de estados para el flujo de trabajo de actividades
- Estados típicos pueden incluir: Pendiente, En Progreso, Entregada, Calificada, Atrasada, etc.
- Permite rastrear el ciclo de vida completo de una actividad
- El modelo base es auto-generado y no debe editarse directamente
