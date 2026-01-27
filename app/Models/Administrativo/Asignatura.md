# Modelo Asignatura

## Ubicación
- **Namespace**: `App\Models\Administrativo`
- **Archivo**: `app/Models/Administrativo/Asignatura.php`
- **Clase Base**: `App\Models\Base\Administrativo\BaseAsignatura`

## Propósito
Representa una **asignatura o materia** del catálogo académico de la universidad. Define las características académicas de una materia que puede ser incluida en diferentes planes de estudio.

## Objetivo
Gestionar el catálogo de asignaturas disponibles en la institución, incluyendo su carga horaria, créditos y descripción. Permite la asignación de asignaturas a diferentes planes de estudio y la creación de cursos basados en estas asignaturas.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Asignatura`
- **Clave Primaria**: `id_asignatura` (auto-incremental)
- **Conexión**: PostgreSQL (`pgsql`)

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_asignatura` | Integer | Identificador único de la asignatura |
| `cod_asignatura` | String | Código único de la asignatura |
| `nombre` | String | Nombre de la asignatura |
| `descripcion` | Text | Descripción detallada de la asignatura |
| `creditos_sct` | Integer | Créditos SCT-Chile de la asignatura |
| `horas_catedra` | Integer | Horas de cátedra teórica |
| `horas_taller` | Integer | Horas de taller práctico |
| `horas_laboratorio` | Integer | Horas de laboratorio |
| `horas_dirigidas` | Integer | Horas de trabajo dirigido |
| `horas_autonomas` | Integer | Horas de trabajo autónomo del estudiante |
| `esta_activo` | Boolean | Indica si la asignatura está activa |
| `fecha_creacion` | Timestamp | Fecha de creación del registro |

## Relaciones

### Relaciones Inversas (hasMany)
- **`asignacionPlanes()`**: Una asignatura puede estar asignada a múltiples planes de estudio

### Relaciones Muchos a Muchos (belongsToMany)
- **`planes()`**: Una asignatura puede pertenecer a múltiples planes de estudio a través de la tabla pivote `Asignacion_Plan`
  - **Campos adicionales en pivote**: `agno_planificado`, `semestre_planificado`, `tipo_ramo`

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo las asignaturas activas

### Ejemplo de Uso
```php
// Obtener todas las asignaturas activas
$asignaturas = Asignatura::active()->get();

// Obtener asignatura con sus planes asociados
$asignatura = Asignatura::with('planes')->find($id);

// Obtener información de asignación a planes
$asignatura = Asignatura::with('asignacionPlanes')->find($id);

// Buscar asignatura por código
$asignatura = Asignatura::where('cod_asignatura', 'MAT101')->first();

// Calcular carga horaria total
$asignatura = Asignatura::find($id);
$horasTotales = $asignatura->horas_catedra + 
                $asignatura->horas_taller + 
                $asignatura->horas_laboratorio;
```

## Notas Importantes
- El sistema de créditos utiliza el estándar SCT-Chile (Sistema de Créditos Transferibles)
- La carga horaria se divide en diferentes tipos de actividades académicas
- Las horas autónomas representan el trabajo independiente esperado del estudiante
- El modelo base es auto-generado y no debe editarse directamente
- Las personalizaciones deben agregarse en la clase `Asignatura` que extiende de `BaseAsignatura`
