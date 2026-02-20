# Arquitectura: Estructura JSONB para Programa

## Diagrama de Flujo

```
┌─────────────────────────────────────────────────────────────────┐
│                  TABLA: administrativo.programa                  │
│  ────────────────────────────────────────────────────────────   │
│  - id_programa (PK)                                             │
│  - version_programa                                             │
│  - estado (ABIERTO|EN_REVISION|APROBADO|PUBLICADO)             │
│  - data_syllabus (JSONB) ◄─── NUEVO CAMPO A RELLENAR           │
│  - creado_por (FK Usuario)                                      │
│  - revisado_por (FK Usuario)                                    │
│  - id_curso (FK Curso)                                          │
│  - es_actual (boolean)                                          │
│  - fecha_creacion, fecha_modificacion, fecha_eliminacion        │
└─────────────────────────────────────────────────────────────────┘
                                ▲
                                │
                                │
                 ┌──────────────┴──────────────┐
                 │                             │
              ┌──▼──────────────┐    ┌────────▼─────┐
              │ SyllabusStructure   │ ProgramaService │
              │ (Data Builder)      │ (Logic Layer)   │
              └──────────────┬──────┘    └─────┬───────┘
                    ▲        │                  │
                    │        │                  │
        ┌───────────┼────────┴──────────────────┤
        │           │                           │
        │    ┌──────▼──────────┐       ┌────────▼─────┐
        │    │ Curso.php       │       │ Usuario.php  │
        │    │ Asignatura.php  │       │ Docente.php  │
        │    │ Seccion.php     │       │ Carrera.php  │
        │    └─────────────────┘       └──────────────┘
        │
    From DB Tables
```

## Estructura de Datos

```
data_syllabus (JSONB)
│
├── metadata
│   ├── asignatura
│   │   ├── id_asignatura (int)
│   │   ├── nombre (string)
│   │   ├── codigo (string)
│   │   ├── descripcion (string)
│   │   ├── creditos_sct (int)
│   │   └── horas
│   │       ├── catedra
│   │       ├── taller
│   │       ├── laboratorio
│   │       ├── dirigidas
│   │       └── autonomas
│   │
│   ├── curso
│   │   ├── id_curso (int)
│   │   ├── codigo (string)
│   │   ├── año_academico (int)
│   │   ├── semestre (int)
│   │   ├── es_plantilla (bool)
│   │   ├── seccion_count (int)
│   │   └── docente_principal
│   │       ├── id_docente
│   │       ├── nombre
│   │       ├── titulo
│   │       ├── grado
│   │       └── cargo
│   │
│   └── categoria (NEW - LO QUE ESTABA FALTANDO)
│       ├── tipo (OBLIGATORIO|ELECTIVO|NIVELACION|COMPLEMENTARIA)
│       └── descripcion
│
├── secciones (Array de 6 elementos)
│   ├── [0] Descripción de la Asignatura
│   ├── [1] Competencias
│   ├── [2] Resultados de Aprendizaje
│   ├── [3] Contenidos
│   ├── [4] Metodología
│   └── [5] Evaluación
│       └── De cada sección:
│           ├── nombre_seccion
│           ├── numeral_romano
│           ├── orden
│           └── contenidos[]
│               ├── texto_contenido
│               ├── descripcion (opcional)
│               └── orden_item
│
└── timestamp (ISO8601)
```

## Relaciones de Base de Datos

```
       ┌─────────────────────┐
       │  usuario.usuario    │
       │  ─────────────────  │
       │  - id_usuario (PK)  │
       │  - nombre_completo  │
       └──────────┬──────────┘
                  │
          ┌───────┴────────┬─────────────┐
          │                │             │
    ┌─────▼──────┐  ┌─────▼──────┐  ┌──▼──────────┐
    │  creado_por │  │revisado_por│  │ usuario_rol │
    │     (FK)    │  │    (FK)    │  │  asignacion │
    │             │  │            │  │             │
    └─────┬──────┘  └─────┬──────┘  └──────────────┘
          │                │
          └────────┬───────┘
                   │
         ┌─────────▼──────────────┐
         │ programa               │
         │ ────────────────────   │
         │ - id_programa (PK)     │
         │ - data_syllabus (JSONB)│
         │ - creado_por (FK)      │
         │ - revisado_por (FK)    │
         │ - id_curso (FK)        │
         └────────────┬─────────────┘
                      │
              ┌───────▼────────────┐
              │  curso.curso       │
              │  ────────────────  │
              │  - id_curso (PK)   │
              │  - id_contexto (FK)│
              │  - id_asignacion.. │
              └───────┬────────────┘
                      │
         ┌────────────┼─────────────────┐
         │            │                 │
    ┌────▼──────┐ ┌──▼─────────┐ ┌────▼──────────┐
    │ curso.    │ │ curso.     │ │ administrativo│
    │ seccion   │ │ unidad     │ │ .asignacion.. │
    │ ──────    │ │ ──────     │ │ ──────────    │
    │ - seccion │ │ - unidad   │ │ - asignatura  │
    │ - docente │ │ - temas    │ │ - plan        │
    └───────────┘ └────────────┘ └───────────────┘
```

## Clases Principales

### 1. SyllabusStructure
```
Responsabilidad: Construir la estructura JSONB
┌──────────────────────────────────────────┐
│ SyllabusStructure                        │
├──────────────────────────────────────────┤
│ - curso                                  │
│ - asignatura                             │
│ - secciones                              │
│ - categoria                              │
├──────────────────────────────────────────┤
│ + withAsignatura(): self                 │
│ + withSecciones(): self                  │
│ + withCategoria(array): self             │
│ + withCategoriaFromString(string): self  │
│ + build(): array                         │
│ + toJson(): string                       │
│ + for(Curso): array (static)             │
└──────────────────────────────────────────┘
```

### 2. ProgramaService
```
Responsabilidad: Gestionar ciclo de vida del Programa
┌──────────────────────────────────────────────────┐
│ ProgramaService                                  │
├──────────────────────────────────────────────────┤
│ + generateProgramaWithSyllabus(): Programa       │
│ + updateSyllabusContent(): Programa              │
│ + getSyllabusStructure(): array                  │
│ + getSecciones(): array                          │
│ + getMetadata(): array                           │
│ + updateSeccion(): Programa                      │
│ + addContentToSeccion(): Programa                │
│ + changeStatus(): Programa                       │
│ + export(): array                                │
│ - applySyllabusOverrides(): array (private)      │
│ - deepMerge(): array (private)                   │
└──────────────────────────────────────────────────┘
```

### 3. Programa (Modelo)
```
Responsabilidad: Entidad de base de datos + helpers
┌──────────────────────────────────────────┐
│ Programa extends BasePrograma             │
├──────────────────────────────────────────┤
│ Casts:                                   │
│ - data_syllabus: json                    │
│ - es_actual: boolean                     │
├──────────────────────────────────────────┤
│ + getSyllabusStructure(): array          │
│ + getMetadata(): array                   │
│ + getSecciones(): array                  │
│ + getAsignatura(): array                 │
│ + getCursoData(): array                  │
│ + getCategoria(): array                  │
│ + isOpen(): bool                         │
│ + isUnderReview(): bool                  │
│ + isApproved(): bool                     │
│ + isPublished(): bool                    │
└──────────────────────────────────────────┘
```

## Flujo de Uso

### Generar nuevo programa
```
1. Usuario/Sistema solicita generar programa
   ↓
2. ProgramaService::generateProgramaWithSyllabus(curso)
   ↓
3. SyllabusStructure::for(curso) construye estructura
   ├─ Carga asignatura desde curso.asignacionPlan
   ├─ Carga secciones desde curso
   ├─ Carga categoría desde asignacionPlan.tipo_ramo
   └─ Retorna array JSONB completo
   ↓
4. Crea nuevo Programa con data_syllabus = array
   ↓
5. Retorna Programa guardado
```

### Actualizar contenido
```
1. Usuario invoca ProgramaService::updateSeccion()
   ↓
2. Busca la sección en data_syllabus
   ↓
3. Actualiza contenidos de esa sección
   ↓
4. Guarda el cambio
   ↓
5. Retorna Programa actualizado
```

## Ventajas de esta Arquitectura

| Aspecto | Ventaja |
|---------|---------|
| **Denormalización** | Evita múltiples queries; todo en un documento |
| **Flexibilidad** | Se pueden agregar campos sin migración |
| **Versionado** | Cada programa guarda su estado completo |
| **Integridad** | Transacciones de BD aseguran consistencia |
| **Búsqueda** | Índices GIN permiten queries eficientes |
| **Escalabilidad** | JSONB es más eficiente que documentos grandes |
| **Historial** | metadata mantiene referencia a datos originales |

## Próximos Pasos

- [ ] Integrar SyllabusStructure en ProgramaController
- [ ] Agregar migraciones con índices JSONB
- [ ] Crear validaciones de estructura JSONB
- [ ] Implementar queries por categoría
- [ ] Agregar búsqueda de texto en contenidos
- [ ] Exportar a PDF desde data_syllabus
