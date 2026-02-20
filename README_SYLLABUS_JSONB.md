# Estructura Base JSONB para `data_syllabus` - Implementación Completa

## 📋 Resumen

Se ha construido una **estructura completa base para rellenar el campo JSONB `data_syllabus`** de la tabla `programa`. Esta estructura incluye:

1. ✅ **Información de asignatura** (desde tablas `curso`, `asignacion_plan`, `asignatura`)
2. ✅ **Información de curso y secciones** (desde tabla `curso` y `seccion`)
3. ✅ **Información de categoría** (OBLIGATORIO, ELECTIVO, NIVELACION, COMPLEMENTARIA) - **Esto era lo que faltaba**
4. ✅ **Estructura de 6 secciones estándar** (Descripción, Competencias, Resultados, Contenidos, Metodología, Evaluación)

## 🏗️ Archivos Creados

### 1. **Data Builder** (`app/Data/SyllabusStructure.php`)
Clase responsable de construir la estructura JSONB a partir de los datos de la base de datos.

```php
// Uso simple:
$estructura = SyllabusStructure::for($curso);

// Uso avanzado con personalización:
$syllabus = (new SyllabusStructure($curso))
    ->withAsignatura()
    ->withSecciones()
    ->withCategoriaFromString('OBLIGATORIO')
    ->build();
```

**Métodos principales:**
- `withAsignatura()` - Carga datos de asignatura
- `withSecciones()` - Carga secciones del curso
- `withCategoria(array)` - Define categoría personalizada
- `withCategoriaFromString(string)` - Define desde string estándar
- `build()` - Construye la estructura completa
- `buildSecciones()` - Solo secciones sin metadata
- `toJson()` - Exporta a JSON formateado

### 2. **Service Layer** (`app/Services/ProgramaService.php`)
Servicio de lógica de negocio para gestionar Programas con JSONB.

```php
// Generar programa  
$programa = ProgramaService::generateProgramaWithSyllabus($curso);

// Actualizar sección
ProgramaService::updateSeccion($programa, 2, [
    ['texto_contenido' => 'Competencia 1', 'orden_item' => 1]
]);

// Agregar contenido
ProgramaService::addContentToSeccion($programa, 5, 'Nueva metodología');

// Cambiar estado
ProgramaService::changeStatus($programa, 'APROBADO');

// Obtener datos
$metadata = ProgramaService::getMetadata($programa);
$secciones = ProgramaService::getSecciones($programa);
```

**Métodos disponibles:**
- `generateProgramaWithSyllabus()` - Crea nuevo programa con JSONB
- `updateSyllabusContent()` - Actualiza contenido total
- `getSyllabusStructure()` - Obtiene estructura completa
- `getSecciones()` - Obtiene solo secciones
- `getMetadata()` - Obtiene solo metadata
- `updateSeccion()` - Actualiza una sección
- `addContentToSeccion()` - Agrega contenido a sección
- `changeStatus()` - Cambia estado (ABIERTO|EN_REVISION|APROBADO|PUBLICADO)
- `export()` - Exporta en formato legible

### 3. **Modelo Mejorado** (`app/Models/Administrativo/Programa.php`)
Modelo Programa actualizado con casts y métodos helper.

```php
// Casts automáticos JSONB → Array
protected $casts = [
    'data_syllabus' => 'json',
];

// Métodos helper:
$programa->getSyllabusStructure()  // array completo
$programa->getMetadata()           // metadata
$programa->getSecciones()          // secciones
$programa->getAsignatura()         // info asignatura
$programa->getCategoria()          // categoria (LO NUEVO)
$programa->isOpen()                // boolean
$programa->isApproved()            // boolean
```

### 4. **Documentación Técnica**

#### `docs/SYLLABUS_JSONB_STRUCTURE.md`
- Estructura detallada del JSONB
- Especificación de cada campo
- Ejemplos de queries PostgreSQL
- Validaciones recomendadas

#### `docs/ARCHITECTURE_PROGRAMA_JSONB.md`
- Diagramas de flujo
- Relaciones de base de datos
- Responsabilidades de clases
- Ventajas de la arquitectura

### 5. **Ejemplos** (`app/Data/SYLLABUS_EXAMPLE.php`)
Archivo con 10 ejemplos de uso práctico:
1. Generar programa completo
2. Usar estructura sin guardar
3. Actualizar contenidos de sección
4. Agregar contenido a sección
5. Cambiar estado del programa
6. Obtener datos del programa
7. Usar en controlador
8. Verificar migraciones
9. Casting automático en modelo
10. Queries PostgreSQL útiles

### 6. **Migración** (`database/migrations/2025_02_20_create_programa_jsonb_indexes.php`)
Migración para crear índices JSONB optimizados:
- Índice por categoría
- Índice por código de asignatura
- Índice por nombre de asignatura

### 7. **Tests** (`tests/Unit/Services/ProgramaServiceTest.php`)
Suite completa de tests unitarios:
- Generación de programa con estructura
- Validación de metadata
- Validación de 6 secciones
- Información de horas
- Información de categoría
- Actualización de secciones
- Agregar contenido
- Cambio de estado
- Versionado
- Exportación

## 📊 Estructura JSONB Generada

```json
{
  "metadata": {
    "asignatura": {
      "id_asignatura": 1,
      "nombre": "Programación I",
      "codigo": "INF101",
      "descripcion": "...",
      "creditos_sct": 6,
      "horas": {
        "catedra": 3,
        "taller": 2,
        "laboratorio": 1,
        "dirigidas": 0,
        "autonomas": 8
      }
    },
    "curso": {
      "id_curso": 1,
      "codigo": "INF101-001",
      "año_academico": 2025,
      "semestre": 1,
      "es_plantilla": false,
      "seccion_count": 2,
      "docente_principal": {
        "id_docente": 5,
        "nombre": "Dr. Juan Pérez",
        "titulo": "Ingeniero en Computación",
        "grado": "Magíster",
        "cargo": "Profesor"
      }
    },
    "categoria": {
      "tipo": "OBLIGATORIO",
      "descripcion": "Asignatura obligatoria del plan de estudios"
    }
  },
  "secciones": [
    {
      "nombre_seccion": "Descripción de la Asignatura",
      "numeral_romano": "I",
      "orden": 1,
      "contenidos": [...]
    },
    ...
  ],
  "timestamp": "2025-02-20T10:30:00Z"
}
```

## 🚀 Cómo Usar

### En un Controlador
```php
use App\Services\ProgramaService;
use App\Models\Curso\Curso;

public function store(Curso $curso)
{
    // Generar programa con estructura completa
    $programa = ProgramaService::generateProgramaWithSyllabus($curso);
    
    return response()->json([
        'success' => true,
        'programa' => ProgramaService::export($programa)
    ]);
}
```

### Actualizar Contenidos
```php
// Agregar información a la sección "Metodología"
ProgramaService::addContentToSeccion(
    $programa,
    5, // orden de sección Metodología
    'Methodology: Constructivist approach with practical focus'
);

// Cambiar a estado EN_REVISION
ProgramaService::changeStatus($programa, 'EN_REVISION');
```

### Consultar Datos
```php
$asignatura = $programa->getAsignatura();
$categoria = $programa->getCategoria(); // ← NUEVO
$docente = $programa->getCursoData()['docente_principal'];

if ($programa->isApproved()) {
    // Hacer algo cuando está aprobado
}
```

## 🔑 Lo Más Importante: La Categoría

**Antes**: No había forma de saber si una asignatura era obligatoria, electiva, nivelación, etc.

**Ahora**: Almacenado en `data_syllabus['metadata']['categoria']` con:
- `tipo`: OBLIGATORIO | ELECTIVO | NIVELACION | COMPLEMENTARIA
- `descripcion`: Descripción legible

Se obtiene del campo `tipo_ramo` de la tabla `administrativo.asignacion_plan` durante la generación.

## 🔄 Próximos Pasos

1. **Integración con Controllers**
   - Actualizar `ProgramaController.php` para usar `ProgramaService`
   - Adaptar `Admin\ProgramaController.php` igual

2. **Ejecutar Migración**
   ```bash
   php artisan migrate
   ```

3. **Ejecutar Tests**
   ```bash
   php artisan test tests/Unit/Services/ProgramaServiceTest.php
   ```

4. **Actualizar Vistas**
   - Usar `programa.getCategoria()` en formularios
   - Mostrar categoría en syllabus viewer

5. **Búsqueda Avanzada** (Opcional)
   - Implementar queries por categoría
   - Búsqueda de texto en secciones

## 📝 Notas

- El campo `data_syllabus` es completamente nullable (opcional)
- Cada programa tiene su propia copia de datos (sin referencias)
- La estructura es versionable (cada nuevo programa es v+1)
- Ideal para auditoría y historial

## 📦 Archivos Creados

```
✅ app/Data/SyllabusStructure.php
✅ app/Services/ProgramaService.php
✅ app/Models/Administrativo/Programa.php (actualizado)
✅ app/Data/SYLLABUS_EXAMPLE.php
✅ database/migrations/2025_02_20_create_programa_jsonb_indexes.php
✅ tests/Unit/Services/ProgramaServiceTest.php
✅ docs/SYLLABUS_JSONB_STRUCTURE.md
✅ docs/ARCHITECTURE_PROGRAMA_JSONB.md
```
