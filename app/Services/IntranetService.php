<?php

namespace App\Services;

use App\DTOs\External\ComponenteCursoData;
use App\DTOs\External\InscripcionData;
use App\DTOs\External\ResultadoInscripcionAutomatica;
use App\Enums\External\TipoAsignatura;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IntranetService
{
    public function __construct(
        protected EstudianteService $estudianteService,
        protected InscripcionCursoService $inscripcionCursoService
    ) {}

    /**
     * Inscribe automáticamente los estudiantes inscritos en la Intranet (Oracle)
     * al curso y a sus componentes correspondientes en UTAMED.
     */
    public function inscribirAutomaticamente(Curso $curso): ResultadoInscripcionAutomatica
    {
        $curso->loadMissing([
            'asignacionPlan.plan.carrera',
            'asignacionPlan.asignatura',
            'componentes.tipoComponente',
        ]);

        $carrera = $curso->asignacionPlan?->plan?->carrera;
        if (!$carrera) {
            throw new \InvalidArgumentException("El curso #{$curso->id_curso} no tiene una carrera asociada en su asignación de plan.");
        }

        $componentesIntranet = $this->resolverComponentesIntranet($curso);

        $totalProcesados = 0;
        $inscritosExitosamente = 0;
        $alumnosCreados = 0;
        $yaInscritos = 0;
        $errores = [];
        $componentesReporte = [];

        foreach ($componentesIntranet as $compIntranet) {
            $compUtamed = $this->mapearComponenteIntranetAUtamed($compIntranet, $curso);
            if (!$compUtamed) {
                // Skip silencioso si la componente de la Intranet no tiene equivalente en UTAMED
                Log::info("Skip silencioso: No existe componente UTAMED para tipo {$compIntranet->curso_tipo_asig->value} en curso #{$curso->id_curso}");
                continue;
            }

            /** @var Collection<int, InscripcionData> $inscripcionesIntranet */
            $oracleService = app('OracleDataService');
            $inscripcionesIntranet = $oracleService->traer_ins_id([$compIntranet->cur_codigo]);

            $inscritosComponenteCount = 0;

            foreach ($inscripcionesIntranet as $inscripcionData) {
                $totalProcesados++;

                try {
                    $estudianteExistente = $this->estudianteService->buscarPorRut($inscripcionData->alum_rut);
                    $estudiante = $this->estudianteService->obtenerOCrearDesdeIntranet($inscripcionData->alum_rut, $carrera);

                    if (!$estudiante) {
                        $errores[] = [
                            'rut'    => $inscripcionData->alum_rut,
                            'motivo' => "No se encontraron datos del alumno en la Intranet.",
                        ];
                        continue;
                    }

                    if (!$estudianteExistente) {
                        $alumnosCreados++;
                    }

                    // 1. Inscripción a Nivel Curso
                    $yaInscritoCurso = InscripcionCurso::where('id_curso', $curso->id_curso)
                        ->where('id_estudiante', $estudiante->id_estudiante)
                        ->exists();

                    if (!$yaInscritoCurso) {
                        $this->inscripcionCursoService->create([
                            'id_curso'            => $curso->id_curso,
                            'id_estudiante'       => $estudiante->id_estudiante,
                            'cod_inscripcion_uta' => (string)$inscripcionData->ins_id,
                            'fecha_inscripcion'   => now()->toDateString(),
                            'estado_inscripcion'  => 'INSCRITO',
                            'num_intento'         => 1,
                        ]);
                    }

                    // Asegurar asignación del rol Estudiante en el contexto del Curso
                    $this->estudianteService->asignarRolEnContexto($estudiante, $curso->id_contexto);


                    // 2. Inscripción a Nivel Componente
                    $inscripcionCompExistente = InscripcionComponente::where('id_componente', $compUtamed->id_componente)
                        ->where('id_estudiante', $estudiante->id_estudiante)
                        ->first();

                    if ($inscripcionCompExistente) {
                        $yaInscritos++;
                        if (!$inscripcionCompExistente->cod_inscripcion_curso_uta) {
                            $inscripcionCompExistente->update([
                                'cod_inscripcion_curso_uta' => $inscripcionData->ins_id,
                            ]);
                        }
                    } else {
                        InscripcionComponente::create([
                            'id_componente'             => $compUtamed->id_componente,
                            'id_estudiante'             => $estudiante->id_estudiante,
                            'cod_inscripcion_curso_uta' => $inscripcionData->ins_id,
                        ]);

                        $inscritosExitosamente++;
                        $inscritosComponenteCount++;
                    }

                    // Asignar el rol Estudiante en el contexto de la componente
                    $this->estudianteService->asignarRolEnContexto($estudiante, $compUtamed->id_contexto);

                } catch (\Exception $e) {
                    Log::error("Error al inscribir alumno RUT {$inscripcionData->alum_rut}: " . $e->getMessage());
                    $errores[] = [
                        'rut'    => $inscripcionData->alum_rut,
                        'motivo' => $e->getMessage(),
                    ];
                }
            }

            $componentesReporte[] = [
                'cur_codigo' => $compIntranet->cur_codigo,
                'tipo'       => $compIntranet->curso_tipo_asig->value,
                'grupo'      => $compIntranet->curso_grupo_asig,
                'inscritos'  => $inscritosComponenteCount,
            ];
        }

        return new ResultadoInscripcionAutomatica(
            total_procesados: $totalProcesados,
            inscritos_exitosamente: $inscritosExitosamente,
            alumnos_creados: $alumnosCreados,
            ya_inscritos: $yaInscritos,
            errores: $errores,
            componentes_procesadas: $componentesReporte
        );
    }

    /**
     * Resuelve los códigos de componente (CUR_CODIGO) desde la Intranet.
     *
     * @return Collection<int, ComponenteCursoData>
     */
    public function resolverComponentesIntranet(Curso $curso): Collection
    {
        $asignacionPlan = $curso->asignacionPlan;
        if (!$asignacionPlan) {
            return collect();
        }

        $semestre = $curso->semestre_real ?? 1;
        $agno = $curso->agno_real ?? (int)now()->year;

        $carreraCod = (int)($asignacionPlan->plan?->carrera?->cod_carrera ?? $asignacionPlan->plan?->carrera?->id_carrera ?? 0);
        $planCod = (int)($asignacionPlan->plan?->agno_plan ?? $asignacionPlan->plan?->cod_plan ?? $asignacionPlan->plan?->id_plan ?? 0);
        $asigCodigo = (string)($asignacionPlan->asignatura?->cod_asignatura ?? '');
        $grupoAsig = $curso->letra_grupo ?? null;

        $oracleService = app('OracleDataService');

        return $oracleService->traer_cur_codigos(
            semestre: $semestre,
            agno: $agno,
            carreraCod: $carreraCod,
            planCod: $planCod,
            asigCodigo: $asigCodigo,
            grupoAsig: $grupoAsig
        );
    }

    /**
     * Mapea un ComponenteCursoData de la Intranet a su Componente equivalente en UTAMED.
     */
    protected function mapearComponenteIntranetAUtamed(ComponenteCursoData $compIntranet, Curso $curso): ?Componente
    {
        $tipoMap = [
            TipoAsignatura::Catedra->value     => ['CATEDRA', 'CÁTEDRA'],
            TipoAsignatura::Taller->value      => ['TALLER'],
            TipoAsignatura::Laboratorio->value => ['LABORATORIO'],
        ];

        $tiposBuscados = $tipoMap[$compIntranet->curso_tipo_asig->value] ?? [];

        foreach ($curso->componentes as $comp) {
            $nombreTipo = mb_strtoupper(trim($comp->tipoComponente?->tipo ?? ''));
            if (in_array($nombreTipo, $tiposBuscados, true)) {
                return $comp;
            }
        }

        return null;
    }
}