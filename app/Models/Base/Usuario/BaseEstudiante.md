# Modelo Estudiante

## Ubicación
- **Namespace**: `App\Models\Usuario` (extendido desde Base)
- **Archivo Base**: `app/Models/Base/Usuario/BaseEstudiante.php`
- **Tabla**: `Estudiante`

## Propósito
Representa un **estudiante** del sistema educativo. Extiende la información de un usuario con datos específicos de su vida académica.

## Objetivo
Gestionar la información académica de los estudiantes, incluyendo su carrera, año de ingreso, inscripciones a cursos y participación en actividades.

## Estructura de Datos

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_estudiante` | Integer | Identificador único del estudiante |
| `agno_ingreso` | Integer | Año de ingreso a la universidad |
| `id_carrera` | Integer | ID de la carrera en que está inscrito |
| `id_usuario` | Integer | ID del usuario asociado |
| `esta_activo` | Boolean | Indica si el estudiante está activo |

## Relaciones

### Relaciones Directas (belongsTo)
- **`carrera()`**: Pertenece a una carrera específica
- **`usuario()`**: Pertenece a un usuario específico

### Relaciones Inversas (hasMany)
- **`asignadoActividades()`**: Actividades individuales asignadas
- **`inscripcionCursos()`**: Inscripciones a cursos
- **`inscripcionSecciones()`**: Inscripciones a secciones

### Relaciones Muchos a Muchos (belongsToMany)
- **`actividadAsignadas()`**: Actividades grupales asignadas
- **`cursos()`**: Cursos en los que está inscrito
- **`secciones()`**: Secciones en las que está inscrito

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo estudiantes activos

### Ejemplo de Uso
```php
// Obtener estudiante con sus cursos
$estudiante = Estudiante::with('cursos')->find($id);

// Obtener estudiante con su carrera
$estudiante = Estudiante::with('carrera')->find($id);

// Obtener estudiantes de una generación
$generacion2020 = Estudiante::where('agno_ingreso', 2020)->get();

// Obtener actividades de un estudiante
$actividades = Estudiante::find($id)->asignadoActividades;

// Obtener inscripciones con notas
$cursos = Estudiante::find($id)->cursos()
    ->withPivot('promedio_parcial', 'estado_inscripcion')
    ->get();
```

## Notas Importantes
- Un estudiante está vinculado a un usuario del sistema
- Puede estar inscrito en múltiples cursos y secciones
- El año de ingreso permite identificar cohortes
- Las inscripciones incluyen información de intentos y estados
- El modelo base es auto-generado y no debe editarse directamente
