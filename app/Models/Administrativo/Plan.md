# Modelo Plan

## Ubicación
- **Namespace**: `App\Models\Administrativo`
- **Archivo**: `app/Models/Administrativo/Plan.php`
- **Clase Base**: `App\Models\Base\Administrativo\BasePlan`

## Propósito
Representa un **plan de estudios** o malla curricular de una carrera universitaria. Define la estructura académica y secuencia de asignaturas que un estudiante debe cursar.

## Objetivo
Gestionar los planes de estudio asociados a las carreras, permitiendo versionar y organizar la estructura curricular. Facilita la asignación de asignaturas con su ubicación temporal (año y semestre) dentro del plan.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Plan`
- **Clave Primaria**: `id_plan` (auto-incremental)
- **Conexión**: PostgreSQL (`pgsql`)

## Relaciones

### Relaciones Directas (belongsTo)
- **`carrera()`**: Un plan pertenece a una carrera específica

### Relaciones Inversas (hasMany)
- **`asignacionPlanes()`**: Un plan tiene múltiples asignaciones de asignaturas
- **`cursos()`**: Un plan puede generar múltiples cursos

### Relaciones Muchos a Muchos (belongsToMany)
- **`asignaturas()`**: Un plan incluye múltiples asignaturas a través de la tabla pivote `Asignacion_Plan`
  - **Campos adicionales en pivote**: `agno_planificado`, `semestre_planificado`, `tipo_ramo`

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo los planes activos

### Ejemplo de Uso
```php
// Obtener plan con todas sus asignaturas
$plan = Plan::with('asignaturas')->find($id);

// Obtener asignaturas de un semestre específico
$plan = Plan::find($id);
$asignaturasSemestre = $plan->asignaturas()
    ->wherePivot('agno_planificado', 1)
    ->wherePivot('semestre_planificado', 1)
    ->get();

// Obtener plan con su carrera
$plan = Plan::with('carrera')->find($id);

// Obtener cursos generados desde el plan
$cursos = Plan::find($id)->cursos;
```

## Notas Importantes
- Cada plan está asociado a una carrera específica
- La tabla pivote `Asignacion_Plan` define en qué año y semestre se debe cursar cada asignatura
- El campo `tipo_ramo` en la pivote puede indicar si es obligatorio, electivo, etc.
- Un plan puede tener múltiples versiones para una misma carrera
- El modelo base es auto-generado y no debe editarse directamente
