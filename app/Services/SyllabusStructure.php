<?php

namespace App\Services;

use App\Models\Administrativo\Asignatura;
use App\Models\Curso\Curso;
use Illuminate\Support\Collection;

/**
 * SyllabusStructure
 * 
 * Clase que construye la estructura base JSONB para rellenar el campo data_syllabus
 * de la tabla programa, agregando información de asignatura, curso, componentes y categorías.
 * 
 * Estructura esperada:
 * {
 *   "metadata": {
 *     "asignatura": {...},
 *     "curso": {...},
 *     "categoria": {...}
 *   },
 *   "secciones": [...]
 * }
 */
class SyllabusStructure
{
    private Curso $curso;
    private ?Asignatura $asignatura = null;
    private Collection $componentes;
    private array $categoria = [];

    public function __construct(Curso $curso)
    {
        $this->curso = $curso;
        $this->componentes = collect();
    }

    /**
     * Carga la asignatura desde la relación asignacionPlan
     */
    public function withAsignatura(): self
    {
        $this->curso->load('asignacionPlan.asignatura');
        $this->asignatura = $this->curso->asignacionPlan?->asignatura;
        return $this;
    }

    /**
     * Carga los componentes del curso con sus docentes asignados
     */
    public function withSecciones(): self
    {
        $this->curso->load('componentes.docentesAsignados.usuario');
        $this->componentes = $this->curso->componentes ?? collect();
        return $this;
    }

    /**
     * Define la categoría de la asignatura
     */
    public function withCategoria(array $categoria): self
    {
        $this->categoria = $categoria;
        return $this;
    }

    /**
     * Define la categoría desde string (ej: "OBLIGATORIO", "ELECTIVO")
     */
    public function withCategoriaFromString(string $tipo_ramo): self
    {
        $this->categoria = [
            'tipo' => $tipo_ramo,
            'descripcion' => match($tipo_ramo) {
                'OBLIGATORIO' => 'Asignatura obligatoria del plan de estudios',
                'ELECTIVO' => 'Asignatura electiva',
                'NIVELACION' => 'Asignatura de nivelación',
                'COMPLEMENTARIA' => 'Asignatura complementaria',
                default => $tipo_ramo,
            }
        ];
        return $this;
    }

    /**
     * Construye la estructura completa del syllabus
     */
    public function build(): array
    {
        return [
            'metadata' => $this->buildMetadata(),
            'secciones' => $this->buildSecciones(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Construye la metadata con información de asignatura, curso y categoría
     */
    private function buildMetadata(): array
    {
        return [
            'asignatura' => $this->buildAsignatura(),
            'curso' => $this->buildCurso(),
            'categoria' => $this->categoria,
        ];
    }

    /**
     * Prepara información de la asignatura
     */
    private function buildAsignatura(): array
    {
        if (!$this->asignatura) {
            return [];
        }

        return [
            'id_asignatura' => $this->asignatura->id_asignatura,
            'nombre' => $this->asignatura->nombre,
            'codigo' => $this->asignatura->cod_asignatura,
            'descripcion' => $this->asignatura->descripcion,
            'creditos_sct' => $this->asignatura->creditos_sct,
            'horas' => [
                'catedra' => $this->asignatura->horas_catedra,
                'taller' => $this->asignatura->horas_taller,
                'laboratorio' => $this->asignatura->horas_laboratorio,
                'dirigidas' => $this->asignatura->horas_dirigidas,
                'autonomas' => $this->asignatura->horas_autonomas,
            ],
        ];
    }

    /**
     * Prepara información del curso
     */
    private function buildCurso(): array
    {
        return [
            'id_curso' => $this->curso->id_curso,
            'codigo' => $this->curso->cod_curso,
            'año_academico' => $this->curso->agno_academico,
            'semestre' => $this->curso->semestre,
            'es_plantilla' => $this->curso->es_plantilla ?? false,
            'seccion_count' => $this->componentes->count(),
            'docente_principal' => $this->buildDocentePrincipal(),
        ];
    }

    /**
     * Obtiene información del docente principal desde los componentes
     */
    private function buildDocentePrincipal(): ?array
    {
        // Obtener el primer docente titular de los componentes, o el primer docente disponible
        $docenteComponente = $this->componentes
            ->flatMap(fn ($componente) => $componente->docenteComponentes ?? collect())
            ->sortBy(fn ($dc) => [!$dc->es_titular, $dc->id_docente_componente]) // Titular primero, luego por ID
            ->first();

        if (!$docenteComponente || !$docenteComponente->docente) {
            return null;
        }

        $docente = $docenteComponente->docente;
        $usuario = $docente->usuario;

        return [
            'id_docente' => $docente->id_docente,
            'nombre' => $usuario?->nombre_completo,
            'titulo' => $docente->titulo,
            'grado' => $docente->grado,
            'cargo' => $docente->cargo,
        ];
    }

    /**
     * Construye las 6 secciones estándar del syllabus
     */
    private function buildSecciones(): array
    {
        $seccionesBase = [
            [
                'nombre_seccion' => 'Descripción de la Asignatura',
                'numeral_romano' => 'I',
                'orden' => 1,
                'contenidos' => [
                    [
                        'texto_contenido' => $this->asignatura?->descripcion ?? '',
                        'orden_item' => 1,
                    ]
                ]
            ],
            [
                'nombre_seccion' => 'Competencias',
                'numeral_romano' => 'II',
                'orden' => 2,
                'contenidos' => []
            ],
            [
                'nombre_seccion' => 'Resultados de Aprendizaje',
                'numeral_romano' => 'III',
                'orden' => 3,
                'contenidos' => []
            ],
            [
                'nombre_seccion' => 'Contenidos',
                'numeral_romano' => 'IV',
                'orden' => 4,
                'contenidos' => $this->buildContenidosFromUnidades()
            ],
            [
                'nombre_seccion' => 'Metodología',
                'numeral_romano' => 'V',
                'orden' => 5,
                'contenidos' => []
            ],
            [
                'nombre_seccion' => 'Evaluación',
                'numeral_romano' => 'VI',
                'orden' => 6,
                'contenidos' => []
            ],
        ];

        return $seccionesBase;
    }

    /**
     * Construye contenidos a partir de unidades del curso si existen
     */
    private function buildContenidosFromUnidades(): array
    {
        $this->curso->load('unidades');
        
        if (!$this->curso->unidades || $this->curso->unidades->isEmpty()) {
            return [];
        }

        return $this->curso->unidades
            ->sortBy('num_unidad')
            ->values()
            ->map(function ($unidad, $index) {
                return [
                    'texto_contenido' => $unidad->nombre_unidad,
                    'descripcion' => $unidad->descripcion ?? null,
                    'orden_item' => $index + 1,
                    'num_unidad' => $unidad->num_unidad,
                ];
            })
            ->toArray();
    }

    /**
     * Método estático para crear y construir directamente
     */
    public static function for(Curso $curso): array
    {
        return (new self($curso))
            ->withAsignatura()
            ->withSecciones()
            ->withCategoriaFromString($curso->asignacionPlan?->tipo_ramo ?? 'OBLIGATORIO')
            ->build();
    }

    /**
     * Obtiene solo la estructura de secciones sin metadata
     */
    public function buildSectionesOnly(): array
    {
        return $this->buildSecciones();
    }

    /**
     * Convierte la estructura a JSON string
     */
    public function toJson(): string
    {
        return json_encode($this->build(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
