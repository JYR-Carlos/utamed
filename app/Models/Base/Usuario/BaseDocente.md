# Modelo Docente

## Ubicación
- **Namespace**: `App\Models\Usuario` (extendido desde Base)
- **Archivo Base**: `app/Models/Base/Usuario/BaseDocente.php`
- **Tabla**: `Docente`

## Propósito
Representa un **docente o profesor** del sistema educativo. Extiende la información de un usuario con datos específicos de su rol académico.

## Objetivo
Gestionar la información específica de los docentes, incluyendo su formación académica, cargo y relación con las secciones que imparten.

## Estructura de Datos

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_docente` | Integer | Identificador único del docente |
| `grado` | String | Grado académico (Dr., Mg., etc.) |
| `titulo` | String | Título profesional |
| `cargo` | String | Cargo institucional |
| `id_usuario` | Integer | ID del usuario asociado |
| `esta_activo` | Boolean | Indica si el docente está activo |

## Relaciones

### Relaciones Directas (belongsTo)
- **`usuario()`**: Pertenece a un usuario específico

### Relaciones Inversas (hasMany)
- **`secciones()`**: Tiene múltiples secciones asignadas

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo docentes activos

### Ejemplo de Uso
```php
// Obtener docente con sus secciones
$docente = Docente::with('secciones')->find($id);

// Obtener información del usuario
$docente = Docente::with('usuario')->find($id);

// Obtener docentes con grado de Doctor
$doctores = Docente::where('grado', 'Dr.')->get();

// Obtener secciones que imparte un docente
$secciones = Docente::find($id)->secciones;
```

## Notas Importantes
- Un docente está vinculado a un usuario del sistema
- Puede impartir múltiples secciones de diferentes cursos
- El grado académico y título son importantes para reportes y asignaciones
- El modelo base es auto-generado y no debe editarse directamente
