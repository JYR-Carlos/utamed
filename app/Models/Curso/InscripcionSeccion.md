# Modelo InscripcionSeccion

## Ubicación
- **Namespace**: `App\Models\Curso`
- **Archivo**: `app/Models/Curso/InscripcionSeccion.php`
- **Clase Base**: `App\Models\Base\Curso\BaseInscripcionSeccion`

## Propósito
Representa la **inscripción de un estudiante a una sección específica** de un curso. Permite que estudiantes se asignen a grupos particulares de cátedra, taller o laboratorio.

## Objetivo
Gestionar las inscripciones de estudiantes a secciones específicas dentro de un curso, permitiendo el seguimiento de notas por sección y asistencia (en desarrollo).

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Inscripcion_Seccion`
- **Clave Primaria**: Compuesta por `id_estudiante`, `id_seccion` e `id_curso`
- **Conexión**: PostgreSQL (`pgsql`)
- **Timestamps**: Deshabilitados

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_estudiante` | Integer | ID del estudiante |
| `id_seccion` | Integer | ID de la sección |
| `id_curso` | Integer | ID del curso |
| `nota_seccion` | Decimal | Nota obtenida en la sección |
| `AQUI AGREGAR ASISTENCIA` | Mixed | Campo para asistencia (en desarrollo) |
| `esta_activo` | Boolean | Indica si la inscripción está activa |

## Relaciones

### Relaciones Directas (belongsTo)
- **`estudiante()`**: Pertenece a un estudiante específico
- **`seccion()`**: Pertenece a una sección específica (relación compuesta)

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo las inscripciones activas

### Ejemplo de Uso
```php
// Obtener inscripción con estudiante y sección
$inscripcion = InscripcionSeccion::with(['estudiante', 'seccion'])->find($id);

// Obtener todos los estudiantes de una sección
$estudiantes = InscripcionSeccion::where('id_seccion', $seccionId)
    ->where('id_curso', $cursoId)
    ->with('estudiante')
    ->get();

// Obtener secciones de un estudiante en un curso
$secciones = InscripcionSeccion::where('id_estudiante', $estudianteId)
    ->where('id_curso', $cursoId)
    ->get();

// Actualizar nota de sección
$inscripcion = InscripcionSeccion::find($id);
$inscripcion->nota_seccion = 6.5;
$inscripcion->save();
```

## Notas Importantes
- La clave primaria es compuesta por `id_estudiante`, `id_seccion` e `id_curso`
- Un estudiante puede estar inscrito en múltiples secciones del mismo curso (ej: cátedra + taller)
- El campo de asistencia está en desarrollo (indicado por el nombre temporal del campo)
- La `nota_seccion` puede ser parcial o final dependiendo del tipo de sección
- El modelo base es auto-generado y no debe editarse directamente
