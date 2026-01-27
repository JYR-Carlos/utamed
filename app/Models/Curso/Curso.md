# Modelo Curso

## Ubicación
- **Namespace**: `App\Models\Curso`
- **Archivo**: `app/Models/Curso/Curso.php`
- **Clase Base**: `App\Models\Base\Curso\BaseCurso`

## Propósito
Representa una **instancia específica de un curso** en un período académico determinado. Es la materialización de una asignatura en un semestre y año concreto.

## Objetivo
Gestionar las instancias de cursos que se dictan en cada período académico, vinculando asignaturas del plan de estudios con contextos específicos, permitiendo inscripciones de estudiantes y organización en secciones.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Curso`
- **Clave Primaria**: `id_curso` (auto-incremental)
- **Conexión**: PostgreSQL (`pgsql`)
- **Timestamps**: Deshabilitados

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_curso` | Integer | Identificador único del curso |
| `cod_curso` | String | Código del curso |
| `nombre` | String | Nombre del curso |
| `grupo_indice` | Integer | Índice del grupo |
| `fecha_inicio` | Date | Fecha de inicio del curso |
| `agno_real` | Integer | Año académico en que se dicta |
| `semestre_real` | Integer | Semestre en que se dicta (1 o 2) |
| `estado_interno` | String | Estado interno del curso |
| `estado_acta` | String | Estado del acta de notas |
| `es_plantilla` | Boolean | Indica si es una plantilla reutilizable |
| `id_contexto` | Integer | ID del contexto asociado |
| `id_asignatura` | Integer | ID de la asignatura base |
| `id_plan` | Integer | ID del plan de estudios |
| `grupo_letra` | String | Letra identificadora del grupo |
| `esta_activo` | Boolean | Indica si el curso está activo |

## Relaciones

### Relaciones Directas (belongsTo)
- **`contexto()`**: Pertenece a un contexto específico (para permisos y organización)
- **`asignacionPlan()`**: Está basado en una asignación de plan (relación compuesta con asignatura y plan)

### Relaciones Inversas (hasMany)
- **`programas()`**: Un curso puede tener múltiples programas
- **`inscripcionCursos()`**: Tiene múltiples inscripciones de estudiantes
- **`secciones()`**: Se divide en múltiples secciones
- **`unidades()`**: Se organiza en múltiples unidades temáticas

### Relaciones Muchos a Muchos (belongsToMany)
- **`estudiantes()`**: Se relaciona con estudiantes a través de `Inscripcion_Curso`
  - **Campos adicionales en pivote**: `cod_inscripcion_uta`, `num_intento`, `fecha_inscripcion`, `estado_inscripcion`, `promedio_parcial`

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo los cursos activos

### Ejemplo de Uso
```php
// Obtener curso con sus secciones y estudiantes
$curso = Curso::with(['secciones', 'estudiantes'])->find($id);

// Obtener cursos del semestre actual
$cursosActuales = Curso::where('agno_real', 2024)
    ->where('semestre_real', 1)
    ->get();

// Obtener curso con su asignatura y plan
$curso = Curso::with('asignacionPlan.asignatura', 'asignacionPlan.plan')->find($id);

// Obtener estudiantes inscritos con su información de inscripción
$estudiantes = Curso::find($id)->estudiantes()
    ->withPivot('estado_inscripcion', 'promedio_parcial')
    ->get();

// Obtener unidades del curso
$unidades = Curso::find($id)->unidades;

// Filtrar plantillas de cursos
$plantillas = Curso::where('es_plantilla', true)->get();
```

## Notas Importantes
- Un curso es la instancia concreta de una asignatura en un período específico
- El campo `es_plantilla` permite crear cursos reutilizables como base para otros
- La relación con `asignacionPlan` vincula el curso con la malla curricular
- Los estudiantes se inscriben al curso y luego a secciones específicas
- El `contexto` permite gestionar permisos y roles a nivel de curso
- El modelo base es auto-generado y no debe editarse directamente
