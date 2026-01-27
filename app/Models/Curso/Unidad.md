# Modelo Unidad

## Ubicación
- **Namespace**: `App\Models\Curso`
- **Archivo**: `app/Models/Curso/Unidad.php`
- **Clase Base**: `App\Models\Base\Curso\BaseUnidad`

## Propósito
Representa una **unidad temática** dentro de un curso. Las unidades organizan el contenido del curso en bloques temáticos o módulos de aprendizaje.

## Objetivo
Gestionar la organización temática de los cursos, permitiendo estructurar el contenido en unidades lógicas y asociar actividades a cada unidad específica.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Unidad`
- **Clave Primaria**: Compuesta por `id_unidad`, `id_curso` y `es_plantilla`
- **Conexión**: PostgreSQL (`pgsql`)
- **Timestamps**: Deshabilitados

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_unidad` | Integer | Identificador de la unidad |
| `num_unidad` | Integer | Número de orden de la unidad (1, 2, 3...) |
| `nombre` | String | Nombre de la unidad temática |
| `descripcion` | Text | Descripción detallada del contenido |
| `id_curso` | Integer | ID del curso al que pertenece |
| `es_plantilla` | Boolean | Indica si es una plantilla |
| `esta_activo` | Boolean | Indica si la unidad está activa |

## Relaciones

### Relaciones Directas (belongsTo)
- **`curso()`**: Pertenece a un curso específico (relación compuesta)

### Relaciones Inversas (hasMany)
- **`actividades()`**: Una unidad puede tener múltiples actividades asociadas

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo las unidades activas

### Ejemplo de Uso
```php
// Obtener unidades de un curso ordenadas
$unidades = Unidad::where('id_curso', $cursoId)
    ->orderBy('num_unidad')
    ->get();

// Obtener unidad con sus actividades
$unidad = Unidad::with('actividades')->find($id);

// Obtener unidad con su curso
$unidad = Unidad::with('curso')->find($id);

// Crear nueva unidad
$unidad = new Unidad();
$unidad->num_unidad = 1;
$unidad->nombre = 'Introducción a la Programación';
$unidad->descripcion = 'Conceptos básicos...';
$unidad->id_curso = $cursoId;
$unidad->save();
```

## Notas Importantes
- La clave primaria es compuesta por `id_unidad`, `id_curso` y `es_plantilla`
- El `num_unidad` define el orden de las unidades en el curso
- Las actividades se asocian a unidades específicas para mejor organización
- Las plantillas permiten reutilizar estructuras de unidades
- El modelo base es auto-generado y no debe editarse directamente
