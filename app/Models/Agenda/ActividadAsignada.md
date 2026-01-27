# Modelo ActividadAsignada

## Ubicación
- **Namespace**: `App\Models\Agenda`
- **Archivo**: `app/Models/Agenda/ActividadAsignada.php`
- **Clase Base**: `App\Models\Base\Agenda\BaseActividadAsignada`

## Propósito
Representa la **asignación de una actividad a un grupo** de estudiantes. Actúa como tabla pivote entre actividades y grupos de trabajo, almacenando la nota grupal y el estado de la entrega.

## Objetivo
Gestionar las asignaciones de actividades a grupos de estudiantes, permitiendo el seguimiento del estado de entrega y la calificación grupal. Facilita la organización de trabajos grupales y su evaluación.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Actividad_Asignada`
- **Clave Primaria**: Compuesta por `grupo` e `id_actividad`
- **Conexión**: PostgreSQL (`pgsql`)
- **Timestamps**: Deshabilitados

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `grupo` | Integer | Identificador del grupo (auto-incremental) |
| `id_actividad` | Integer | ID de la actividad asignada |
| `nota` | Decimal | Nota grupal de la actividad |
| `id_estado` | Integer | ID del estado de la actividad |
| `esta_activo` | Boolean | Indica si la asignación está activa |

## Relaciones

### Relaciones Directas (belongsTo)
- **`actividad()`**: Pertenece a una actividad específica
- **`estadoActividad()`**: Tiene un estado asociado (pendiente, entregada, calificada, etc.)

### Relaciones Inversas (hasMany)
- **`asignadoActividades()`**: Tiene múltiples estudiantes asignados al grupo

### Relaciones Muchos a Muchos (belongsToMany)
- **`estudiantes()`**: Se relaciona con estudiantes a través de `Asignado_Actividad`
  - **Campos adicionales en pivote**: `nota_individual`, `diferencia_decimas`

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo las asignaciones activas

### Ejemplo de Uso
```php
// Obtener asignación con actividad y estado
$asignacion = ActividadAsignada::with(['actividad', 'estadoActividad'])->find($grupo);

// Obtener estudiantes de un grupo
$estudiantes = ActividadAsignada::find($grupo)->estudiantes;

// Obtener todas las asignaciones de una actividad
$asignaciones = ActividadAsignada::where('id_actividad', $actividadId)->get();

// Actualizar nota grupal
$asignacion = ActividadAsignada::find($grupo);
$asignacion->nota = 6.5;
$asignacion->save();
```

## Notas Importantes
- La clave primaria es compuesta por `grupo` e `id_actividad`
- Almacena la nota grupal, mientras que las notas individuales están en la tabla pivote con estudiantes
- El estado permite rastrear el progreso de la actividad (pendiente, entregada, revisada, etc.)
- El modelo base es auto-generado y no debe editarse directamente
