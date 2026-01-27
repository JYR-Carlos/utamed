# Modelo TipoSeccion

## Ubicación
- **Namespace**: `App\Models\Curso`
- **Archivo**: `app/Models/Curso/TipoSeccion.php`
- **Clase Base**: `App\Models\Base\Curso\BaseTipoSeccion`

## Propósito
Representa el **catálogo de tipos de secciones** que pueden existir en un curso (cátedra, taller, laboratorio, ayudantía, etc.).

## Objetivo
Gestionar el catálogo de tipos de secciones disponibles en el sistema, permitiendo clasificar y organizar las diferentes modalidades de clases que componen un curso.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Tipo_Seccion`
- **Clave Primaria**: `id_tipo_seccion` (auto-incremental)
- **Conexión**: PostgreSQL (`pgsql`)
- **Timestamps**: Deshabilitados

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_tipo_seccion` | Integer | Identificador único del tipo |
| `tipo` | String | Nombre del tipo (Cátedra, Taller, Laboratorio, etc.) |
| `esta_activo` | Boolean | Indica si el tipo está activo |

## Relaciones

### Relaciones Inversas (hasMany)
- **`secciones()`**: Un tipo puede tener múltiples secciones

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo los tipos activos

### Ejemplo de Uso
```php
// Obtener todos los tipos de sección
$tipos = TipoSeccion::active()->get();

// Obtener tipo específico
$catedra = TipoSeccion::where('tipo', 'Cátedra')->first();

// Obtener todas las secciones de un tipo
$tipo = TipoSeccion::find($id);
$secciones = $tipo->secciones;

// Crear nuevo tipo de sección
$tipo = new TipoSeccion();
$tipo->tipo = 'Ayudantía';
$tipo->save();
```

## Notas Importantes
- Actúa como catálogo de tipos de secciones
- Tipos comunes incluyen: Cátedra, Taller, Laboratorio, Ayudantía, Seminario
- Permite clasificar las diferentes modalidades de enseñanza
- El modelo base es auto-generado y no debe editarse directamente
