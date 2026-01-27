# Modelo AsignacionPlan

## Ubicación
- **Namespace**: `App\Models\Administrativo`
- **Archivo**: `app/Models/Administrativo/AsignacionPlan.php`
- **Clase Base**: `App\Models\Base\Administrativo\BaseAsignacionPlan`

## Propósito
Representa la **asignación de una asignatura a un plan de estudios**. Actúa como tabla pivote entre asignaturas y planes, definiendo cuándo y cómo se cursa cada asignatura dentro de un plan específico.

## Objetivo
Gestionar la relación entre asignaturas y planes de estudio, especificando en qué año y semestre se debe cursar cada asignatura, así como el tipo de ramo (obligatorio, electivo, etc.).

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Asignacion_Plan`
- **Clave Primaria**: Compuesta por `id_asignatura` e `id_plan`
- **Conexión**: PostgreSQL (`pgsql`)

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_asignatura` | Integer | ID de la asignatura asignada |
| `id_plan` | Integer | ID del plan de estudios |
| `agno_planificado` | Integer | Año en que se debe cursar (1, 2, 3, etc.) |
| `semestre_planificado` | Integer | Semestre en que se debe cursar (1 o 2) |
| `tipo_ramo` | String | Tipo de asignatura (obligatorio, electivo, etc.) |
| `esta_activo` | Boolean | Indica si la asignación está activa |

## Relaciones

### Relaciones Directas (belongsTo)
- **`asignatura()`**: Pertenece a una asignatura específica
- **`plan()`**: Pertenece a un plan de estudios específico

### Relaciones Inversas (hasMany)
- **`cursos()`**: Una asignación puede generar múltiples cursos

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo las asignaciones activas

### Ejemplo de Uso
```php
// Obtener asignación con asignatura y plan
$asignacion = AsignacionPlan::with(['asignatura', 'plan'])->find($id);

// Obtener todas las asignaturas de primer año, primer semestre
$asignaciones = AsignacionPlan::where('agno_planificado', 1)
    ->where('semestre_planificado', 1)
    ->get();

// Obtener solo ramos obligatorios de un plan
$obligatorios = AsignacionPlan::where('id_plan', $planId)
    ->where('tipo_ramo', 'obligatorio')
    ->get();

// Obtener cursos generados desde una asignación
$cursos = AsignacionPlan::find($id)->cursos;
```

## Notas Importantes
- Esta tabla pivote enriquecida define la malla curricular de un plan
- La combinación de `agno_planificado` y `semestre_planificado` ubica la asignatura en el tiempo
- El `tipo_ramo` permite clasificar asignaturas (obligatorias, electivas, de formación general, etc.)
- La clave primaria es compuesta por `id_asignatura` e `id_plan`
- El modelo base es auto-generado y no debe editarse directamente
