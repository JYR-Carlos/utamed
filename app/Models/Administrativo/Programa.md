# Modelo Programa

## Ubicación
- **Namespace**: `App\Models\Administrativo`
- **Archivo**: `app/Models/Administrativo/Programa.php`
- **Clase Base**: `App\Models\Base\Administrativo\BasePrograma`

## Propósito
Representa el **programa de una asignatura**, que es el documento que define los contenidos, objetivos, metodología y evaluación de un curso específico.

## Objetivo
Gestionar los programas académicos asociados a cursos, permitiendo que docentes definan y documenten la planificación de sus asignaturas. Vincula el contenido académico con cursos específicos y sus responsables.

## Estructura de Datos

### Tabla de Base de Datos
- **Tabla**: `Programa`
- **Clave Primaria**: Compuesta por `id_curso` y `es_plantilla`
- **Conexión**: PostgreSQL (`pgsql`)

### Atributos Principales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id_curso` | Integer | ID del curso asociado |
| `es_plantilla` | Boolean | Indica si es una plantilla reutilizable |
| `id_usuario` | Integer | ID del docente responsable del programa |
| `esta_activo` | Boolean | Indica si el programa está activo |

## Relaciones

### Relaciones Directas (belongsTo)
- **`curso()`**: Un programa pertenece a un curso específico (relación compuesta)
- **`usuario()`**: Un programa tiene un docente responsable

## Funcionalidades

### Scopes Disponibles
- **`active()`**: Filtra solo los programas activos

### Ejemplo de Uso
```php
// Obtener programa con su curso
$programa = Programa::with('curso')->find($id);

// Obtener programa con el docente responsable
$programa = Programa::with('usuario')->find($id);

// Obtener todos los programas de un docente
$programas = Programa::where('id_usuario', $docenteId)->get();

// Obtener solo plantillas de programas
$plantillas = Programa::where('es_plantilla', true)->get();
```

## Notas Importantes
- La clave primaria es compuesta por `id_curso` y `es_plantilla`
- El campo `es_plantilla` permite crear programas reutilizables
- Cada programa está asociado a un usuario (docente) responsable
- El modelo base es auto-generado y no debe editarse directamente
