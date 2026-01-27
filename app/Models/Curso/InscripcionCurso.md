# Modelo InscripcionCurso

## Ubicación
- **Namespace**: `App\Models\Curso`
- **Archivo**: `app/Models/Curso/InscripcionCurso.php`
- **Clase Base**: `App\Models\Base\Curso\BaseInscripcionCurso`

## Propósito
Representa la **inscripción de un estudiante a un curso**. Actúa como tabla pivote entre estudiantes y cursos, almacenando información del proceso de inscripción y seguimiento académico.

## Objetivo
Gestionar las inscripciones de estudiantes a cursos, permitiendo el seguimiento de intentos, estados de inscripción y promedios parciales durante el desarrollo del curso.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Inscripcion_Curso`
- **Clave Primaria**: Compuesta por `id_curso` e `id_estudiante`
- **Conexión**: PostgreSQL (`pgsql`)
- **Timestamps**: Deshabilitados

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_curso` | Integer | ID del curso |
| `id_estudiante` | Integer | ID del estudiante |
| `cod_inscripcion_uta` | String | Código de inscripción institucional |
| `num_intento` | Integer | Número de intento del estudiante (1, 2, 3...) |
| `fecha_inscripcion` | Date | Fecha en que se realizó la inscripción |
| `estado_inscripcion` | String | Estado actual (inscrito, retirado, etc.) |
| `promedio_parcial` | Decimal | Promedio parcial durante el curso |
| `esta_activo` | Boolean | Indica si la inscripción está activa |

## Relaciones

### Relaciones Directas (belongsTo)
- **`curso()`**: Pertenece a un curso específico
- **`estudiante()`**: Pertenece a un estudiante específico

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo las inscripciones activas

### Ejemplo de Uso
```php
// Obtener inscripción con curso y estudiante
$inscripcion = InscripcionCurso::with(['curso', 'estudiante'])->find($id);

// Obtener todas las inscripciones de un curso
$inscripciones = InscripcionCurso::where('id_curso', $cursoId)->get();

// Obtener inscripciones de un estudiante
$misCursos = InscripcionCurso::where('id_estudiante', $estudianteId)->get();

// Verificar si es primer intento
$esPrimerIntento = InscripcionCurso::where('id_estudiante', $estudianteId)
    ->where('id_curso', $cursoId)
    ->where('num_intento', 1)
    ->exists();

// Obtener estudiantes inscritos activos
$inscritos = InscripcionCurso::where('estado_inscripcion', 'inscrito')
    ->active()
    ->get();
```

## Notas Importantes
- La clave primaria es compuesta por `id_curso` e `id_estudiante`
- El campo `num_intento` permite rastrear repeticiones del curso
- El `estado_inscripcion` puede incluir valores como: inscrito, retirado, aprobado, reprobado
- El `promedio_parcial` se actualiza durante el semestre
- El modelo base es auto-generado y no debe editarse directamente
