# Estructura JSONB para `data_syllabus`

## Descripción General

El campo `data_syllabus` en la tabla `programa` es un campo JSONB que almacena la estructura completa y metadatos del syllabus de una asignatura. Esta estructura integra información proveniente de:

- **Asignatura**: Datos académicos (código, créditos, horas)
- **Curso**: Información de la oferta del curso (año, semestre, docente)
- **Secciones**: Las secciones dinámicas del programa
- **Categoría**: Clasificación de la asignatura (OBLIGATORIO, ELECTIVO, etc.)

## Estructura Base

```json
{
  "metadata": {
    "asignatura": {...},
    "curso": {...},
    "categoria": {...}
  },
  "secciones": [...],
  "timestamp": "ISO8601"
}
```

## Detalles de Cada Componente

### 1. Metadata

#### Asignatura
```json
{
  "id_asignatura": 1,
  "nombre": "Programación I",
  "codigo": "INF101",
  "descripcion": "Introducción a los fundamentos de programación...",
  "creditos_sct": 6,
  "horas": {
    "catedra": 3,
    "taller": 2,
    "laboratorio": 1,
    "dirigidas": 0,
    "autonomas": 8
  }
}
```

**Campos:**
- `id_asignatura`: FK a `administrativo.asignatura`
- `nombre`: Nombre oficial de la asignatura
- `codigo`: Código único (ej: INF101)
- `descripcion`: Descripción académica
- `creditos_sct`: Créditos SCT-Chile
- `horas`: Desglose de horas por tipo
  - `catedra`: Horas de clase frontal
  - `taller`: Horas de taller teórico-práctico
  - `laboratorio`: Horas en laboratorio
  - `dirigidas`: Horas dirigidas
  - `autonomas`: Horas de estudio autónomo

#### Curso
```json
{
  "id_curso": 1,
  "codigo": "INF101-001",
  "año_academico": 2025,
  "semestre": 1,
  "es_plantilla": false,
  "seccion_count": 2,
  "docente_principal": {
    "id_docente": 5,
    "nombre": "Dr. Juan Pérez García",
    "titulo": "Ingeniero en Computación",
    "grado": "Magíster",
    "cargo": "Profesor"
  }
}
```

**Campos:**
- `id_curso`: FK a `curso.curso`
- `codigo`: Código del curso dentro de la oferta
- `año_academico`: Año de oferta
- `semestre`: Semestre (1 o 2)
- `es_plantilla`: Boolean indicando si es plantilla
- `seccion_count`: Cantidad de secciones ofertadas
- `docente_principal`: Información del docente responsable

#### Categoría (NUEVO - Lo que estaba faltando)
```json
{
  "tipo": "OBLIGATORIO",
  "descripcion": "Asignatura obligatoria del plan de estudios"
}
```

**Campos:**
- `tipo`: Categoría de la asignatura
  - `OBLIGATORIO`: Asignatura mandatoria
  - `ELECTIVO`: Asignatura de libre elección
  - `NIVELACION`: Asignatura de nivelación
  - `COMPLEMENTARIA`: Asignatura complementaria
- `descripcion`: Descripción del tipo de categoría

### 2. Secciones

Estructura estándar de 6 secciones (componible):

```json
[
  {
    "nombre_seccion": "Descripción de la Asignatura",
    "numeral_romano": "I",
    "orden": 1,
    "contenidos": [
      {
        "texto_contenido": "Fundamentos de programación...",
        "orden_item": 1
      }
    ]
  },
  {
    "nombre_seccion": "Competencias",
    "numeral_romano": "II",
    "orden": 2,
    "contenidos": []
  },
  {
    "nombre_seccion": "Resultados de Aprendizaje",
    "numeral_romano": "III",
    "orden": 3,
    "contenidos": []
  },
  {
    "nombre_seccion": "Contenidos",
    "numeral_romano": "IV",
    "orden": 4,
    "contenidos": [
      {
        "texto_contenido": "Unidad 1: Conceptos básicos",
        "descripcion": "Variables, tipos de datos",
        "orden_item": 1,
        "num_unidad": 1
      }
    ]
  },
  {
    "nombre_seccion": "Metodología",
    "numeral_romano": "V",
    "orden": 5,
    "contenidos": []
  },
  {
    "nombre_seccion": "Evaluación",
    "numeral_romano": "VI",
    "orden": 6,
    "contenidos": []
  }
]
```

**Campos de Sección:**
- `nombre_seccion`: Nombre descriptivo
- `numeral_romano`: Numeración en romanos (I, II, III...)
- `orden`: Orden de presentación (1-6)
- `contenidos`: Array de ítems de contenido

**Campos de Contenido:**
- `texto_contenido`: Texto principal del contenido
- `descripcion`: Opcional, descripción adicional
- `orden_item`: Orden dentro de la sección
- `num_unidad`: Opcional, para relacionar con unidades del curso

## Implementación en el Modelo

### 1. Agregar Cast al Modelo Programa

En `app/Models/Administrativo/Programa.php`:

```php
protected $casts = [
    'data_syllabus' => 'json',
    'fecha_creacion' => 'datetime',
    'fecha_modificacion' => 'datetime',
    'fecha_eliminacion' => 'datetime',
];
```

### 2. Agregar Index en Base de Datos

En la migración:

```php
// En up()
$table->jsonb('data_syllabus')->nullable();
$table->index('data_syllabus', 'idx_data_syllabus');

// O con operadores específicos
$table->index(
    DB::raw('(data_syllabus->\'\'metadata\'\'->\'\'asignatura\'\'->\'\'codigo\'\')')
);
```

### 3. Usar SyllabusStructure y ProgramaService

En controladores o en lógica de negocio:

```php
use App\Services\ProgramaService;
use App\Models\Curso\Curso;

// Generar programa completo
$curso = Curso::find(1);
$programa = ProgramaService::generateProgramaWithSyllabus($curso);

// Acceder a datos
$estructura = $programa->data_syllabus;
$asignatura = $estructura['metadata']['asignatura'];
$secciones = $estructura['secciones'];
```

## Queries PostgreSQL útiles

### Buscar por categoría
```sql
SELECT * FROM administrativo.programa
WHERE data_syllabus->'metadata'->'categoria'->>'tipo' = 'OBLIGATORIO';
```

### Buscar por código de asignatura
```sql
SELECT * FROM administrativo.programa
WHERE data_syllabus->'metadata'->'asignatura'->>'codigo' = 'INF101';
```

### Actualizar categoría
```sql
UPDATE administrativo.programa
SET data_syllabus = jsonb_set(
    data_syllabus,
    '{metadata,categoria,tipo}',
    '"ELECTIVO"'
)
WHERE id_programa = 1;
```

### Agregar contenido a sección
```sql
UPDATE administrativo.programa
SET data_syllabus = jsonb_set(
    data_syllabus,
    '{secciones,1,contenidos}',
    data_syllabus->'secciones'->1->'contenidos' || '[{"texto_contenido":"Nuevo contenido","orden_item":3}]'::jsonb
)
WHERE id_programa = 1;
```

## Validación de Estructura

Es recomendable usar Form Requests para validar la estructura JSONB:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id_curso' => 'required|integer|exists:curso.curso,id_curso',
            'data_syllabus.metadata.asignatura.codigo' => 'required|string|max:20',
            'data_syllabus.metadata.categoria.tipo' => 'required|in:OBLIGATORIO,ELECTIVO,NIVELACION,COMPLEMENTARIA',
            'data_syllabus.secciones' => 'required|array|size:6',
            'data_syllabus.secciones.*.nombre_seccion' => 'required|string',
            'data_syllabus.secciones.*.orden' => 'required|integer|between:1,6',
            'data_syllabus.secciones.*.contenidos' => 'array',
            'data_syllabus.secciones.*.contenidos.*.texto_contenido' => 'string',
            'data_syllabus.secciones.*.contenidos.*.orden_item' => 'integer',
        ];
    }
}
```

## Ventajas de esta Estructura

1. **Flexibilidad**: Permite agregar nuevos campos sin migración
2. **Denormalización Controlada**: Evita queries complejas
3. **Histórico**: Cada programa guarda su estado completo
4. **Búsqueda**: Usa índices GIN de PostgreSQL
5. **Integridad**: La metadata se carga una sola vez

## Próximos Pasos

1. ✅ Crear clases `SyllabusStructure` y `ProgramaService`
2. ⏳ Actualizar modelo `Programa` con casts
3. ⏳ Crear migración para agregar índices JSONB
4. ⏳ Integrar en controladores `ProgramaController`
5. ⏳ Crear tests para validar estructura JSONB
