# Modelo VwUsuariosCompleto

## Ubicación
- **Namespace**: `App\Models\Administrativo`
- **Archivo**: `app/Models/Administrativo/VwUsuariosCompleto.php`
- **Clase Base**: `App\Models\Base\Administrativo\BaseVwUsuariosCompleto`

## Propósito
Representa una **vista de base de datos** que proporciona información completa y consolidada de usuarios del sistema.

## Objetivo
Facilitar consultas de información de usuarios sin necesidad de realizar múltiples joins. Esta vista materializada o view de base de datos proporciona acceso optimizado a datos de usuarios frecuentemente consultados.

## Estructura de Datos

### Tabla de Base de Datos
- **Tipo**: Vista de base de datos
- **Tabla/Vista**: `vw_usuarios_completo` (o similar)
- **Clave Primaria**: `id` (auto-incremental)
- **Conexión**: PostgreSQL (`pgsql`)

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo los registros activos

### Ejemplo de Uso
```php
// Obtener todos los usuarios desde la vista
$usuarios = VwUsuariosCompleto::all();

// Filtrar usuarios activos
$usuariosActivos = VwUsuariosCompleto::active()->get();

// Buscar usuario específico
$usuario = VwUsuariosCompleto::find($id);
```

## Notas Importantes
- Este es un modelo de solo lectura (vista de base de datos)
- No se deben realizar operaciones de escritura (INSERT, UPDATE, DELETE)
- La vista consolida información de múltiples tablas para optimizar consultas
- El modelo base es auto-generado y no debe editarse directamente
- Útil para reportes y consultas que requieren datos agregados de usuarios
