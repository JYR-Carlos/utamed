# Plan de Implementación - Módulo de Programas (Syllabus)

## Análisis del Contenido Modificado

He analizado el programa que modificaste. Los cambios realizados reflejan mejor la realidad universitaria chilena:

### Cambios Clave Identificados

| Sección | Original | Modificado | Implicación |
|---------|----------|-----------|------------|
| **IV. Competencias** | 5 específicas + 5 genéricas detalladas | 1 específica + 1 genérica + subcompetencias | Estructura jerárquica simplificada |
| **V. Diagnóstica** | 5 evaluaciones completas | 1 tipo simple ("Preguntas exploratorias") | Flexibilidad para completar después |
| **VII. Planificación** | Descripción pedagógica detallada | Solo titulares y tipo de estrategia | Información mínima por componente |
| **IX. Aspectos Admvos** | Tabla de 6 evaluaciones | Tabla de 3 componentes + ponderación optativa | Estructura modular por tipo de actividad |

---

## Propuesta de Implementación

### 1. Arquitectura de Base de Datos

#### Tabla Principal: `administrativo.programa`

```sql
CREATE TABLE administrativo.programa (
    id_programa SMALLSERIAL PRIMARY KEY,
    id_curso SMALLINT NOT NULL REFERENCES curso.curso(id_curso),
    id_asignatura SMALLINT NOT NULL REFERENCES administrativo.asignatura(id_asignatura),
    
    -- Estado del programa
    estado VARCHAR(20) NOT NULL DEFAULT 'BORRADOR' 
        CHECK (estado IN ('BORRADOR', 'PENDIENTE_APROBACION', 'APROBADO', 'VIGENTE', 'ARCHIVADO')),
    
    -- Versioning
    numero_version SMALLINT DEFAULT 1,
    es_actual BOOLEAN DEFAULT true,
    
    -- Contenido
    data_syllabus JSONB NOT NULL,
    
    -- Auditoría
    creado_por SMALLINT REFERENCES usuario.usuario(id_usuario),
    modificado_por SMALLINT REFERENCES usuario.usuario(id_usuario),
    aprobado_por SMALLINT REFERENCES usuario.usuario(id_usuario),
    
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion TIMESTAMP,
    fecha_eliminacion TIMESTAMP,
    
    UNIQUE(id_curso, numero_version)
);
```

### 2. Estructura JSONB Optimizada

Basada en el programa modificado:

```json
{
  "metadata": {
    "asignatura": {
      "id_asignatura": 1,
      "nombre": "Edición de Video y Montaje Audiovisual",
      "codigo": "AUD-301",
      "creditos_sct": 5,
      "categoria": "Obligatorio"
    },
    "curso": {
      "id_curso": 1,
      "codigo": "AUD-301-001",
      "año_academico": 2025,
      "semestre": 2,
      "docente_principal": {
        "id_docente": 5,
        "nombre": "Prof. García"
      }
    },
    "horas": {
      "catedra": 2,
      "taller": 3,
      "laboratorio": 2
    }
  },
  "secciones": [
    {
      "id": "I",
      "nombre": "Identificación de la Asignatura",
      "orden": 1,
      "tipo": "identificacion",
      "contenido": {
        "nombre_asignatura": "Edición de Video y Montaje Audiovisual",
        "codigo": "AUD-301",
        "creditos_sct": 5,
        "horas": { "catedra": 2, "taller": 3, "laboratorio": 2 },
        "categoria": "Obligatorio",
        "año_academico": 2025,
        "semestre": 2
      }
    },
    {
      "id": "II",
      "nombre": "Presentación, Descripción y Propósito Formativo",
      "orden": 2,
      "tipo": "texto",
      "contenido": {
        "texto": "Este curso introduce a los estudiantes..."
      }
    },
    {
      "id": "III",
      "nombre": "Estándares de la Profesión",
      "orden": 3,
      "tipo": "texto",
      "contenido": {
        "texto": "Los profesionales del montaje audiovisual..."
      }
    },
    {
      "id": "IV",
      "nombre": "Áreas, Competencias Específicas y Competencias Genéricas",
      "orden": 4,
      "tipo": "competencias",
      "contenido": {
        "competencias_especificas": [
          {
            "id": 1,
            "titulo": "Uso de Herramientas Tecnológicas de la Información y de la Comunicación",
            "descripcion": null,
            "orden": 1
          }
        ],
        "competencias_genericas": [
          {
            "id": 2,
            "titulo": "Construir productos multimedia, aplicando conocimientos teóricos prácticos...",
            "descripcion": null,
            "orden": 1
          }
        ],
        "subcompetencias": [
          {
            "id": 3,
            "titulo": "Producir material audiovisual, integrando herramientas computacionales...",
            "descripcion": null,
            "orden": 1
          }
        ]
      }
    },
    {
      "id": "V",
      "nombre": "Evaluación Diagnóstica para Determinar los Aprendizajes Previos",
      "orden": 5,
      "tipo": "lista",
      "contenido": {
        "items": [
          {
            "id": 1,
            "titulo": "Preguntas exploratorias",
            "descripcion": "Preguntas dirigidas a alumnos y alumnas",
            "orden": 1
          }
        ]
      }
    },
    {
      "id": "VI",
      "nombre": "Unidades y Contenidos de Aprendizaje",
      "orden": 6,
      "tipo": "unidades",
      "contenido": {
        "unidades": [
          {
            "numero": 1,
            "titulo": "Fundamentos de Edición Digital y Flujos de Trabajo",
            "contenidos_items": [
              { "item": "Historia y evolución del montaje audiovisual", "orden": 1 },
              { "item": "Conceptos de edición linear y no-linear", "orden": 2 }
            ],
            "orden": 1
          }
        ]
      }
    },
    {
      "id": "VII",
      "nombre": "Planificación de la Enseñanza",
      "orden": 7,
      "tipo": "planificacion",
      "contenido": {
        "resultados_aprendizaje": {
          "titulo": "Resultados de Aprendizaje",
          "items": [
            { "unidad": 1, "resultado": "Identificar y clasificar diferentes técnicas...", "orden": 1 }
          ]
        },
        "metodologia": {
          "titulo": "Metodología",
          "tipo_estrategia": "Taller práctico con sesiones teóricas y laboratorio"
        },
        "evaluacion": {
          "titulo": "Evaluación",
          "tipo_evaluacion": "Rúbrica analítica por competencia"
        }
      }
    },
    {
      "id": "VIII",
      "nombre": "Recursos para el Aprendizaje",
      "orden": 8,
      "tipo": "recursos",
      "contenido": {
        "recursos": [
          {
            "id": 1,
            "descripcion": "Dancyger, Ken. (2018). The Technique of Film and Video Editing",
            "autor": "Ken Dancyger",
            "año": 2018,
            "tipo": "Libro",
            "isbn": "978-1138922464",
            "url": null,
            "ubicacion": "Biblioteca UTA",
            "orden": 1
          }
        ]
      }
    },
    {
      "id": "IX",
      "nombre": "Aspectos Administrativos y Evaluación",
      "orden": 9,
      "tipo": "administrativa",
      "contenido": {
        "descripcion": "Esta sección detalla los requisitos administrativos y la evaluación de la asignatura conforme a los estándares de la institución.",
        "ponderacion_optativa": {
          "porcentaje": 20,
          "descripcion": "Prueba Optativa"
        },
        "tabla_componentes": [
          {
            "componente": "Laboratorio",
            "genera_acta": true,
            "porcentaje": 20,
            "aprobacion_obligatoria": true,
            "asistencia_obligatoria_porcentaje": 80,
            "orden": 1
          },
          {
            "componente": "Taller",
            "genera_acta": false,
            "porcentaje": 20,
            "aprobacion_obligatoria": false,
            "asistencia_obligatoria_porcentaje": null,
            "orden": 2
          },
          {
            "componente": "Cátedra",
            "genera_acta": true,
            "porcentaje": 60,
            "aprobacion_obligatoria": true,
            "asistencia_obligatoria_porcentaje": 100,
            "orden": 3
          }
        ],
        "normativa": "Este curso de 4 horas pedagógicas semanales tendrá como mínimo 3 actividades formativas y 3 actividades sumativas..."
      }
    }
  ],
  "timestamp": "2025-02-25T12:00:00Z"
}
```

---

### 3. Modelos Eloquent

#### Modelo `Programa` (Existente - a Optimizar)

```php
<?php

namespace App\Models\Administrativo;

use Illuminate\Database\Eloquent\SoftDeletes;

class Programa extends \App\Models\Base\Administrativo\BasePrograma
{
    use SoftDeletes;

    protected $casts = [
        'data_syllabus' => 'json',
        'fecha_creacion' => 'datetime',
        'fecha_modificacion' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'fecha_eliminacion' => 'datetime',
        'es_actual' => 'boolean',
    ];

    // Relaciones
    public function curso()
    {
        return $this->belongsTo(Curso::class, 'id_curso', 'id_curso');
    }

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class, 'id_asignatura', 'id_asignatura');
    }

    public function creadoPor()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'creado_por', 'id_usuario');
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'aprobado_por', 'id_usuario');
    }

    // Métodos de acceso a secciones
    public function getSeccion($id)
    {
        $secciones = $this->data_syllabus['secciones'] ?? [];
        return collect($secciones)->firstWhere('id', $id);
    }

    public function getAllSecciones()
    {
        return $this->data_syllabus['secciones'] ?? [];
    }

    public function getMetadata()
    {
        return $this->data_syllabus['metadata'] ?? [];
    }

    // Validaciones rápidas
    public function isComplete(): bool
    {
        $secciones = $this->getAllSecciones();
        return count($secciones) === 9;
    }

    public function cantidadSecciones(): int
    {
        return count($this->getAllSecciones());
    }
}
```

#### Modelo `SyllabusBuilder` (Nuevo - Service Builder)

```php
<?php

namespace App\Services;

use App\Models\Administrativo\Programa;
use App\Models\Administrativo\Curso;
use App\Models\Administrativo\Asignatura;

class SyllabusBuilder
{
    private $data = [];

    public function __construct(Curso $curso, Asignatura $asignatura)
    {
        $this->data = $this->initializeStructure($curso, $asignatura);
    }

    private function initializeStructure(Curso $curso, Asignatura $asignatura): array
    {
        return [
            'metadata' => [
                'asignatura' => [
                    'id_asignatura' => $asignatura->id_asignatura,
                    'nombre' => $asignatura->nombre,
                    'codigo' => $asignatura->cod_asignatura,
                    'creditos_sct' => $asignatura->creditos_sct,
                    'categoria' => $this->getCategoria($asignatura),
                ],
                'curso' => [
                    'id_curso' => $curso->id_curso,
                    'codigo' => $curso->cod_curso,
                    'año_academico' => $curso->año_academico,
                    'semestre' => $curso->semestre,
                ],
                'horas' => $this->extractHoras($asignatura),
            ],
            'secciones' => [],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function addSeccion($id, $nombre, $orden, $tipo, $contenido)
    {
        $this->data['secciones'][] = [
            'id' => $id,
            'nombre' => $nombre,
            'orden' => $orden,
            'tipo' => $tipo,
            'contenido' => $contenido,
        ];
        return $this;
    }

    public function build(): array
    {
        return $this->data;
    }

    public function persist(Usuario $usuario): Programa
    {
        $programa = Programa::create([
            'id_curso' => $this->data['metadata']['curso']['id_curso'],
            'id_asignatura' => $this->data['metadata']['asignatura']['id_asignatura'],
            'data_syllabus' => $this->data,
            'estado' => 'BORRADOR',
            'creado_por' => $usuario->id_usuario,
        ]);

        return $programa;
    }

    // Helpers
    private function getCategoria(Asignatura $asignatura): string
    {
        // Obtener categoría desde plan si existe, sino "Obligatorio"
        return $asignatura->categoria ?? 'Obligatorio';
    }

    private function extractHoras(Asignatura $asignatura): array
    {
        return [
            'catedra' => $asignatura->horas_catedra ?? 0,
            'taller' => $asignatura->horas_taller ?? 0,
            'laboratorio' => $asignatura->horas_laboratorio ?? 0,
        ];
    }
}
```

---

### 4. Controlador - Flujo de Trabajo

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\Administrativo\Programa;
use App\Models\Administrativo\Curso;
use App\Models\Administrativo\Asignatura;
use App\Services\SyllabusBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgramaController extends Controller
{
    // Mostrar lista de programas del curso
    public function index(Curso $curso)
    {
        $programas = $curso->programas()
            ->where('es_actual', true)
            ->orderByDesc('numero_version')
            ->get();

        return Inertia::render('admin/Programas/Index', [
            'curso' => $curso,
            'programas' => $programas,
        ]);
    }

    // Ver programa completo
    public function show(Programa $programa)
    {
        $this->authorize('view', $programa);
        
        return Inertia::render('admin/Programas/Show', [
            'programa' => $programa,
        ]);
    }

    // Editar/Crear programa
    public function edit(Programa $programa)
    {
        $this->authorize('update', $programa);

        return Inertia::render('admin/Programas/Edit', [
            'programa' => $programa,
            'secciones' => $programa->getAllSecciones(),
        ]);
    }

    // Guardar sección específica
    public function updateSeccion(Request $request, Programa $programa, string $seccionId)
    {
        $this->authorize('update', $programa);

        $validated = $request->validate([
            'contenido' => 'required|array',
        ]);

        $data = $programa->data_syllabus;
        $secciones = $data['secciones'] ?? [];

        // Actualizar sección
        foreach ($secciones as &$seccion) {
            if ($seccion['id'] === $seccionId) {
                $seccion['contenido'] = $validated['contenido'];
                break;
            }
        }

        $data['secciones'] = $secciones;
        $data['timestamp'] = now()->toIso8601String();

        $programa->update([
            'data_syllabus' => $data,
            'estado' => 'BORRADOR',
        ]);

        return back()->with('success', 'Sección actualizada.');
    }

    // Enviar a aprobación
    public function submitForApproval(Programa $programa)
    {
        $this->authorize('update', $programa);

        if (!$programa->isComplete()) {
            return back()->with('error', 'El programa debe tener las 9 secciones completas.');
        }

        $programa->update(['estado' => 'PENDIENTE_APROBACION']);

        return back()->with('success', 'Programa enviado a aprobación.');
    }

    // Crear nuevo programa (duplicar versión anterior)
    public function createNewVersion(Curso $curso)
    {
        $this->authorize('create', Programa::class);

        $programaActual = $curso->programas()
            ->where('es_actual', true)
            ->first();

        if (!$programaActual) {
            return back()->with('error', 'No hay programa anterior para duplicar.');
        }

        // Crear nueva versión
        $nuevoPrograma = $programaActual->replicate();
        $nuevoPrograma->numero_version = $programaActual->numero_version + 1;
        $nuevoPrograma->es_actual = false;
        $nuevoPrograma->estado = 'BORRADOR';
        $nuevoPrograma->creado_por = Auth::id();
        $nuevoPrograma->save();

        $programaActual->update(['es_actual' => false]);
        $nuevoPrograma->update(['es_actual' => true]);

        return redirect()->route('programa.edit', $nuevoPrograma)
            ->with('success', 'Nueva versión creada.');
    }
}
```

---

### 5. Rutas

```php
Route::middleware(['auth', 'verified'])->group(function () {
    // Programas
    Route::prefix('admin/cursos/{curso}/programas')->group(function () {
        Route::get('/', [ProgramaController::class, 'index'])->name('programa.index');
        Route::get('{programa}', [ProgramaController::class, 'show'])->name('programa.show');
        Route::get('{programa}/edit', [ProgramaController::class, 'edit'])->name('programa.edit');
        
        // Actualizar secciones individuales
        Route::patch('{programa}/secciones/{seccionId}', [ProgramaController::class, 'updateSeccion'])
            ->name('programa.updateSeccion');
        
        // Workflow
        Route::post('{programa}/submit-approval', [ProgramaController::class, 'submitForApproval'])
            ->name('programa.submitForApproval');
        Route::post('create-version', [ProgramaController::class, 'createNewVersion'])
            ->name('programa.createNewVersion');
    });
});
```

---

### 6. Flujo de Trabajo Propuesto

```
┌─────────────────────────────────────────────────────────────┐
│                 CICLO DE VIDA DEL PROGRAMA                 │
└─────────────────────────────────────────────────────────────┘

1. CREAR PROGRAMA
   └─ Seleccionar Curso + Asignatura
   └─ Generar estructura JSONB con valores por defecto
   └─ Estado: BORRADOR

2. EDITAR SECCIONES (Una a una)
   ├─ Sección I: Identificación (Auto-rellenada)
   ├─ Sección II: Presentación (Texto)
   ├─ Sección III: Estándares (Texto)
   ├─ Sección IV: Competencias (Jerarquía)
   ├─ Sección V: Evaluación Diagnóstica (Lista)
   ├─ Sección VI: Unidades (Lista estructurada)
   ├─ Sección VII: Planificación (Subsecciones)
   ├─ Sección VIII: Recursos (Lista con referencias)
   └─ Sección IX: Aspectos Administrativos (Tablas)

3. VALIDAR COMPLETUD
   └─ Verificar 9 secciones presentes
   └─ Verificar contenido mínimo por sección

4. ENVIAR A APROBACIÓN
   └─ Estado: PENDIENTE_APROBACION
   └─ Notificar a Director Carrera/Jefe Departamento

5. APROBACIÓN
   └─ Estado: APROBADO
   └─ Registrar aprobador y fecha

6. PUBLICAR
   └─ Estado: VIGENTE
   └─ Disponible para estudiantes

7. NUEVA VERSIÓN
   └─ Crear versión N+1 duplicando versión anterior
   └─ Marcar versión anterior como no actual (es_actual = false)
   └─ Comenzar ciclo nuevamente
```

---

### 7. Consideraciones de Implementación

#### **Validaciones Críticas**

```php
class SyllabusValidator
{
    const REGLAS_VALIDACION = [
        'I' => ['campos_obligatorios' => true, 'longitud_minima' => 0],
        'II' => ['campos_obligatorios' => true, 'longitud_minima' => 100],
        'III' => ['campos_obligatorios' => true, 'longitud_minima' => 100],
        'IV' => ['campos_obligatorios' => true, 'items_minimos' => 1],
        'V' => ['campos_obligatorios' => true, 'items_minimos' => 1],
        'VI' => ['campos_obligatorios' => true, 'items_minimos' => 1],
        'VII' => ['campos_obligatorios' => true, 'subsecciones' => ['Resultados', 'Metodología', 'Evaluación']],
        'VIII' => ['campos_obligatorios' => true, 'items_minimos' => 2],
        'IX' => ['campos_obligatorios' => true, 'tablas' => ['componentes' => true]],
    ];
}
```

#### **Permisos (Policies)**

```php
class ProgramaPolicy
{
    public function create(Usuario $user)
    {
        return $user->tienePermiso('crear_programa');
    }

    public function update(Usuario $user, Programa $programa)
    {
        return $programa->estado === 'BORRADOR' 
            && $user->id_usuario === $programa->creado_por;
    }

    public function approve(Usuario $user, Programa $programa)
    {
        return ($user->docente && $user->esDirectorCarrera()) 
            && $programa->estado === 'PENDIENTE_APROBACION';
    }
}
```

---

## Resumen de la Implementación

| Componente | Implementación |
|-----------|----------------|
| **BD** | Tabla `programa` con `data_syllabus` JSONB |
| **Modelos** | `Programa` (optimizado), `SyllabusBuilder` (nuevo) |
| **Controlador** | `ProgramaController` con 7 acciones clave |
| **Vistas** | 4 vistas Inertia (Index, Show, Edit, Secciones) |
| **Validaciones** | Validador de 9 secciones con reglas específicas |
| **Workflow** | 7 estados del programa (Borrador → Vigente → Archivado) |
| **Permisos** | Policy restrictiva por estado y rol |

¿Qué te parece esta arquitectura? ¿Hay ajustes o aclaraciones que necesites?
