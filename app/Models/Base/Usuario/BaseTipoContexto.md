# Modelo TipoContexto

## Ubicación
- **Namespace**: `App\Models\Usuario` (extendido desde Base)
- **Archivo Base**: `app/Models/Base/Usuario/BaseTipoContexto.php`
- **Tabla**: `Tipo_Contexto`

## Propósito
Representa el **tipo de contexto** que clasifica los contextos según su categoría y tabla de referencia.

## Objetivo
Gestionar la clasificación de contextos, vinculándolos con las tablas del sistema que representan (Facultad, Curso, etc.).

## Estructura de Datos

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_tipo_contexto` | Integer | Identificador único del tipo |
| `categoria` | String | Categoría del contexto (Global, Facultad, Curso, etc.) |
| `tabla_referenciada` | String | Nombre de la tabla asociada |
| `id_contexto` | Integer | ID del contexto asociado |
| `esta_activo` | Boolean | Indica si el tipo está activo |

## Relaciones

### Relaciones Directas (belongsTo)
- **`contexto()`**: Pertenece a un contexto específico

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo tipos activos

### Ejemplo de Uso
```php
// Obtener tipo de contexto
$tipo = TipoContexto::where('categoria', 'Facultad')->first();

// Obtener contexto asociado
$contexto = TipoContexto::find($id)->contexto;
```

## Notas Importantes
- Clasifica los contextos según su alcance
- Vincula contextos con tablas específicas del sistema
- El modelo base es auto-generado y no debe editarse directamente
