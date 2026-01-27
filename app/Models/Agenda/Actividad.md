# Modelo Actividad

## Ubicación
- **Namespace**: `App\Models\Agenda`
- **Archivo**: `app/Models/Agenda/Actividad.php`
- **Clase Base**: `App\Models\Base\Agenda\BaseActividad`

## Propósito
Representa una **actividad académica** que puede ser asignada a estudiantes dentro de un curso. Incluye tareas, evaluaciones, trabajos, proyectos, etc.

## Objetivo
Gestionar las actividades académicas de los cursos, definiendo sus características (tipo, fecha límite, modalidad de entrega, si es grupal o individual) y su relación con secciones y unidades del curso.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Actividad`
- **Clave Primaria**: `id_actividad` (auto-incremental)
- **Conexión**: PostgreSQL (`pgsql`)
- **Timestamps**: Deshabilitados

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_actividad` | Integer | Identificador único de la actividad |
| `nombre` | String | Nombre de la actividad |
| `fecha_limite` | DateTime | Fecha y hora límite de entrega |
| `visible` | Boolean | Indica si la actividad es visible para estudiantes |
| `tipo_actividad` | String | Tipo de actividad (tarea, evaluación, proyecto, etc.) |
| `tipo_entrega` | String | Modalidad de entrega (online, presencial, etc.) |
| `es_grupal` | Boolean | Indica si la actividad es grupal |
| `max_integrantes` | Integer | Número máximo de integrantes si es grupal |
| `es_plantilla` | Boolean | Indica si es una plantilla reutilizable |
| `id_curso` | Integer | ID del curso al que pertenece |
| `id_seccion` | Integer | ID de la sección específica (opcional) |
| `id_unidad` | Integer | ID de la unidad temática a la que pertenece |
| `esta_activo` | Boolean | Indica si la actividad está activa |

## Relaciones

### Relaciones Directas (belongsTo)
- **`seccion()`**: Pertenece a una sección específica del curso (relación compuesta)
- **`unidad()`**: Pertenece a una unidad temática del curso (relación compuesta)

### Relaciones Inversas (hasMany)
- **`actividadAsignadas()`**: Tiene múltiples asignaciones a grupos/estudiantes

### Relaciones Muchos a Muchos (belongsToMany)
- **`estadoActividades()`**: Se relaciona con estados de actividad a través de `Actividad_Asignada`
  - **Campos adicionales en pivote**: `grupo`, `nota`

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo las actividades activas

### Ejemplo de Uso
```php
// Obtener actividades de un curso
$actividades = Actividad::where('id_curso', $cursoId)->get();

// Obtener actividades visibles y activas
$actividadesVisibles = Actividad::active()
    ->where('visible', true)
    ->get();

// Obtener actividad con su sección y unidad
$actividad = Actividad::with(['seccion', 'unidad'])->find($id);

// Obtener actividades grupales
$actividadesGrupales = Actividad::where('es_grupal', true)->get();

// Obtener actividades próximas a vencer
$proximasVencer = Actividad::where('fecha_limite', '>', now())
    ->where('fecha_limite', '<', now()->addDays(7))
    ->get();

// Obtener plantillas de actividades
$plantillas = Actividad::where('es_plantilla', true)->get();
```

## Notas Importantes
- Las actividades pueden ser individuales o grupales (controlado por `es_grupal`)
- Si es grupal, `max_integrantes` define el tamaño máximo del grupo
- El campo `visible` controla si los estudiantes pueden ver la actividad
- Las plantillas (`es_plantilla`) permiten reutilizar actividades en múltiples cursos
- La relación con sección y unidad es compuesta, incluyendo `id_curso` y `es_plantilla`
- El modelo base es auto-generado y no debe editarse directamente
