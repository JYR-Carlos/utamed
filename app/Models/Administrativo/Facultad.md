# Modelo Facultad

## Ubicación
- **Namespace**: `App\Models\Administrativo`
- **Archivo**: `app/Models/Administrativo/Facultad.php`
- **Clase Base**: `App\Models\Base\Administrativo\BaseFacultad`

## Propósito
Representa una **facultad universitaria**, que es la unidad organizacional de más alto nivel en la estructura académica. Agrupa departamentos y carreras relacionadas por área de conocimiento.

## Objetivo
Gestionar la estructura organizacional académica de nivel superior, permitiendo organizar departamentos, carreras y establecer contextos de permisos y roles a nivel de facultad.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Facultad`
- **Clave Primaria**: `id_facultad` (auto-incremental)
- **Conexión**: PostgreSQL (`pgsql`)

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_facultad` | Integer | Identificador único de la facultad |
| `nombre` | String | Nombre de la facultad |
| `id_contexto` | Integer | ID del contexto asociado (para permisos/roles) |
| `esta_activo` | Boolean | Indica si la facultad está activa |

## Relaciones

### Relaciones Directas (belongsTo)
- **`contexto()`**: Una facultad está asociada a un contexto para gestión de permisos

### Relaciones Inversas (hasMany)
- **`departamentos()`**: Una facultad puede tener múltiples departamentos

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo las facultades activas

### Ejemplo de Uso
```php
// Obtener facultad con sus departamentos
$facultad = Facultad::with('departamentos')->find($id);

// Obtener facultad con departamentos y carreras
$facultad = Facultad::with('departamentos.carreras')->find($id);

// Obtener facultad con su contexto de permisos
$facultad = Facultad::with('contexto')->find($id);

// Listar todas las facultades activas
$facultades = Facultad::active()->get();
```

## Notas Importantes
- La facultad es el nivel jerárquico más alto en la estructura académica
- Cada facultad tiene un contexto asociado para la gestión de permisos y roles
- A través de departamentos, una facultad agrupa múltiples carreras
- El modelo base es auto-generado y no debe editarse directamente
