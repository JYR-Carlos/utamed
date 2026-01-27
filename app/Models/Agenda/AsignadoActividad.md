# Modelo AsignadoActividad

## Ubicación
- **Namespace**: `App\Models\Agenda`
- **Archivo**: `app/Models/Agenda/AsignadoActividad.php`
- **Clase Base**: `App\Models\Base\Agenda\BaseAsignadoActividad`

## Propósito
Representa la **asignación individual de un estudiante a una actividad grupal**. Vincula estudiantes específicos con grupos de trabajo y almacena sus calificaciones individuales.

## Objetivo
Gestionar la participación individual de estudiantes en actividades grupales, permitiendo calificaciones diferenciadas dentro de un mismo grupo y el seguimiento de contribuciones individuales.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Asignado_Actividad`
- **Clave Primaria**: Compuesta por `grupo`, `id_actividad` e `id_estudiante`
- **Conexión**: PostgreSQL (`pgsql`)
- **Timestamps**: Deshabilitados

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `grupo` | Integer | Identificador del grupo |
| `id_actividad` | Integer | ID de la actividad |
| `id_estudiante` | Integer | ID del estudiante |
| `nota_individual` | Decimal | Nota individual del estudiante |
| `diferencia_decimas` | Decimal | Diferencia en décimas respecto a la nota grupal |
| `esta_activo` | Boolean | Indica si la asignación está activa |

## Relaciones

### Relaciones Directas (belongsTo)
- **`actividadAsignada()`**: Pertenece a una asignación grupal de actividad (relación compuesta)
- **`estudiante()`**: Pertenece a un estudiante específico

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo las asignaciones activas

### Ejemplo de Uso
```php
// Obtener asignación con estudiante y actividad grupal
$asignacion = AsignadoActividad::with(['estudiante', 'actividadAsignada'])->find($id);

// Obtener todos los estudiantes de un grupo en una actividad
$integrantes = AsignadoActividad::where('grupo', $grupoId)
    ->where('id_actividad', $actividadId)
    ->with('estudiante')
    ->get();

// Calcular nota individual con diferencia
$asignacion = AsignadoActividad::find($id);
$notaGrupal = $asignacion->actividadAsignada->nota;
$notaIndividual = $notaGrupal + $asignacion->diferencia_decimas;

// Obtener actividades de un estudiante
$actividades = AsignadoActividad::where('id_estudiante', $estudianteId)->get();
```

## Notas Importantes
- Permite calificaciones diferenciadas dentro de un grupo
- El campo `diferencia_decimas` ajusta la nota individual respecto a la grupal
- La clave primaria es compuesta por `grupo`, `id_actividad` e `id_estudiante`
- Útil para evaluar contribuciones individuales en trabajos grupales
- El modelo base es auto-generado y no debe editarse directamente
