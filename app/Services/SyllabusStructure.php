<?php

namespace App\Services;

use App\Models\Administrativo\Asignatura;
use App\Models\Curso\Curso;
use App\Syllabus\SyllabusAsignatura;
use App\Syllabus\SyllabusCategoria;
use App\Syllabus\SyllabusCurso;
use App\Syllabus\SyllabusDocentePrincipal;
use App\Syllabus\SyllabusHoras;
use App\Syllabus\SyllabusMetadata;
use App\Syllabus\SyllabusTipo;
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
    private ?string $tipoSyllabus = null;

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
     * Define el tipo de syllabus (BASICO|COMPLETO) que se incluirá en metadata.
     */
    public function withTipoSyllabus(string $tipoSyllabus): self
    {
        $this->tipoSyllabus = $tipoSyllabus;
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
        $metadata = new SyllabusMetadata(
            asignatura: $this->buildAsignatura(),
            curso: $this->buildCurso(),
            categoria: !empty($this->categoria) ? SyllabusCategoria::fromArray($this->categoria) : null,
            tipoSyllabus: $this->tipoSyllabus !== null ? SyllabusTipo::from($this->tipoSyllabus) : null,
        );

        return $metadata->toArray();
    }

    /**
     * Prepara información de la asignatura
     */
    private function buildAsignatura(): ?SyllabusAsignatura
    {
        if (!$this->asignatura) {
            return null;
        }

        return new SyllabusAsignatura(
            idAsignatura: $this->asignatura->id_asignatura,
            nombre: $this->asignatura->nombre,
            codigo: $this->asignatura->cod_asignatura,
            descripcion: $this->asignatura->descripcion,
            creditosSct: $this->asignatura->creditos_sct,
            horas: new SyllabusHoras(
                catedra: $this->asignatura->horas_catedra,
                taller: $this->asignatura->horas_taller,
                laboratorio: $this->asignatura->horas_laboratorio,
                dirigidas: $this->asignatura->horas_dirigidas,
                autonomas: $this->asignatura->horas_autonomas,
            ),
        );
    }

    /**
     * Prepara información del curso
     */
    private function buildCurso(): SyllabusCurso
    {
        //dd($this->curso);
        return new SyllabusCurso(
            idCurso: $this->curso->id_curso,
            codigo: (string) $this->curso->cod_curso,
            // Las columnas reales de curso.curso son agno_real/semestre_real; no
            // existen agno_academico ni semestre. Ambas son nullable en el
            // esquema, así que se cae a 0 igual que hace SyllabusCurso::fromArray().
            agnoAcademico: (int) ($this->curso->agno_real ?? 0),
            semestre: (int) ($this->curso->semestre_real ?? 0),
            esPlantilla: $this->curso->es_plantilla ?? false,
            seccionCount: $this->componentes->count(),
            docentePrincipal: $this->buildDocentePrincipal(),
        );
    }

    /**
     * Obtiene información del docente principal desde los componentes
     */
    private function buildDocentePrincipal(): ?SyllabusDocentePrincipal
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

        return new SyllabusDocentePrincipal(
            idDocente: $docente->id_docente,
            nombre: $usuario?->nombre_completo,
            titulo: $docente->titulo,
            grado: $docente->grado,
            cargo: $docente->cargo,
        );
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
