# Modelo Seccion

## Ubicación
- **Namespace**: `App\Models\Curso`
- **Archivo**: `app/Models/Curso/Seccion.php`
- **Clase Base**: `App\Models\Base\Curso\BaseSeccion`

## Propósito
Representa una **sección específica de un curso**, que es un grupo de clases del mismo tipo (cátedra, taller, laboratorio) dictado por un docente.

## Objetivo
Gestionar las diferentes secciones en que se divide un curso, permitiendo organizar estudiantes en grupos más pequeños y asignar docentes específicos a cada tipo de actividad académica.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Seccion`
- **Clave Primaria**: Compuesta por `id_seccion`, `id_curso` y `es_plantilla`
- **Conexión**: PostgreSQL (`pgsql`)
- **Timestamps**: Deshabilitados

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_seccion` | Integer | Identificador de la sección |
| `id_curso` | Integer | ID del curso al que pertenece |
| `es_plantilla` | Boolean | Indica si es una plantilla |
| `id_tipo_seccion` | Integer | Tipo de sección (cátedra, taller, lab) |
| `id_docente` | Integer | ID del docente asignado |
| `esta_activo` | Boolean | Indica si la sección está activa |

## Relaciones

### Relaciones Directas (belongsTo)
- **`tipoSeccion()`**: Pertenece a un tipo de sección
- **`docente()`**: Tiene un docente asignado
- **`curso()`**: Pertenece a un curso (relación compuesta)

### Relaciones Inversas (hasMany)
- **`actividades()`**: Tiene múltiples actividades asignadas
- **`inscripcionSecciones()`**: Tiene múltiples inscripciones de estudiantes

### Relaciones Muchos a Muchos (belongsToMany)
- **`estudiantes()`**: Se relaciona con estudiantes a través de `Inscripcion_Seccion`
  - **Campos adicionales en pivote**: `nota_seccion`, `AQUI AGREGAR ASISTENCIA`

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo las secciones activas

### Ejemplo de Uso
```php
// Obtener sección con docente y estudiantes
$seccion = Seccion::with(['docente', 'estudiantes'])->find($id);

// Obtener todas las secciones de un curso
$secciones = Seccion::where('id_curso', $cursoId)->get();

// Obtener secciones de cátedra
$tipoC = TipoSeccion::where('tipo', 'Cátedra')->first();
$catedras = Seccion::where('id_tipo_seccion', $tipoC->id)->get();

// Obtener actividades de una sección
$actividades = Seccion::find($id)->actividades;
```

## Notas Importantes
- La clave primaria es compuesta por `id_seccion`, `id_curso` y `es_plantilla`
- Cada sección tiene un tipo (cátedra, taller, laboratorio, ayudantía, etc.)
- Un curso puede tener múltiples secciones del mismo tipo
- Los estudiantes se inscriben primero al curso y luego a secciones específicas
- El modelo base es auto-generado y no debe editarse directamente
