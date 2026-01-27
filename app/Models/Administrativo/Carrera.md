# Modelo Carrera

## Ubicación
- **Namespace**: `App\Models\Administrativo`
- **Archivo**: `app/Models/Administrativo/Carrera.php`
- **Clase Base**: `App\Models\Base\Administrativo\BaseCarrera`

## Propósito
Representa una **carrera universitaria** dentro del sistema académico. Una carrera es un programa de estudios que ofrece la universidad en diferentes modalidades, jornadas y sedes.

## Objetivo
Gestionar la información de las carreras académicas, incluyendo su relación con departamentos, facultades y planes de estudio. Permite organizar la estructura académica de la institución y vincular estudiantes con sus respectivas carreras.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Carrera`
- **Clave Primaria**: `id_carrera` (auto-incremental)
- **Conexión**: PostgreSQL (`pgsql`)

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_carrera` | Integer | Identificador único de la carrera |
| `nombre` | String | Nombre de la carrera |
| `jornada` | String | Jornada en que se imparte (diurna, vespertina, etc.) |
| `sede` | String | Sede donde se imparte la carrera |
| `modalidad` | String | Modalidad de la carrera (presencial, online, híbrida) |
| `id_departamento` | Integer | ID del departamento al que pertenece |
| `id_facultad` | Integer | ID de la facultad a la que pertenece |
| `esta_activo` | Boolean | Indica si la carrera está activa |
| `fecha_creacion` | Timestamp | Fecha de creación del registro |

## Relaciones

### Relaciones Directas (belongsTo)
- **`departamento()`**: Pertenece a un Departamento (relación compuesta por `id_departamento` e `id_facultad`)

### Relaciones Inversas (hasMany)
- **`planes()`**: Una carrera puede tener múltiples planes de estudio
- **`estudiantes()`**: Una carrera puede tener múltiples estudiantes inscritos

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo las carreras activas (donde `esta_activo` no es NULL)

### Ejemplo de Uso
```php
// Obtener todas las carreras activas
$carrerasActivas = Carrera::active()->get();

// Obtener una carrera con sus planes
$carrera = Carrera::with('planes')->find($id);

// Obtener estudiantes de una carrera
$estudiantes = Carrera::find($id)->estudiantes;

// Obtener carrera con departamento y facultad
$carrera = Carrera::with('departamento')->find($id);
```

## Notas Importantes
- La relación con `Departamento` es compuesta, utilizando tanto `id_departamento` como `id_facultad`
- El modelo base es auto-generado y no debe editarse directamente
- Las personalizaciones deben agregarse en la clase `Carrera` que extiende de `BaseCarrera`
- El timestamp `updated_at` no está habilitado (solo se usa `created_at`)
