# Modelo Departamento

## Ubicación
- **Namespace**: `App\Models\Administrativo`
- **Archivo**: `app/Models/Administrativo/Departamento.php`
- **Clase Base**: `App\Models\Base\Administrativo\BaseDepartamento`

## Propósito
Representa un **departamento académico** dentro de una facultad universitaria. Los departamentos son unidades organizacionales que agrupan carreras y recursos académicos relacionados.

## Objetivo
Gestionar la estructura organizacional académica de nivel intermedio entre facultades y carreras. Permite organizar y administrar las carreras que pertenecen a un mismo departamento dentro de una facultad.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Departamento`
- **Clave Primaria**: Compuesta por `id_departamento` e `id_facultad`
- **Conexión**: PostgreSQL (`pgsql`)

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_departamento` | Integer | Identificador del departamento |
| `id_facultad` | Integer | ID de la facultad a la que pertenece |
| `nombre` | String | Nombre del departamento |
| `esta_activo` | Boolean | Indica si el departamento está activo |

## Relaciones

### Relaciones Directas (belongsTo)
- **`facultad()`**: Un departamento pertenece a una facultad

### Relaciones Inversas (hasMany)
- **`carreras()`**: Un departamento puede tener múltiples carreras

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo los departamentos activos

### Ejemplo de Uso
```php
// Obtener departamento con sus carreras
$departamento = Departamento::with('carreras')->find($id);

// Obtener departamento con su facultad
$departamento = Departamento::with('facultad')->find($id);

// Obtener todos los departamentos activos de una facultad
$departamentos = Departamento::active()
    ->where('id_facultad', $facultadId)
    ->get();
```

## Notas Importantes
- La clave primaria es compuesta (requiere tanto `id_departamento` como `id_facultad`)
- Un departamento siempre debe estar asociado a una facultad
- La relación con carreras también utiliza la clave compuesta
- El modelo base es auto-generado y no debe editarse directamente
