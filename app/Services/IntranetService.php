<?php

namespace App\Services;

use App\DTOs\External\ComponenteCursoData;
use App\DTOs\External\ComponenteDetectada;
use App\DTOs\External\InscripcionData;
use App\DTOs\External\ResultadoInscripcionAutomatica;
use App\DTOs\External\ResultadoPreviewComponentes;
use App\DTOs\External\ResultadoSincronizacionComponentes;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoComponente;
use App\Support\LetraGrupo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IntranetService
{
    /**
     * Único lugar donde vive la correspondencia entre el código de tipo que
     * usa Intranet (C/T/L) y los nombres aceptados en el catálogo UTAMED.
     * Todo lo demás en esta clase reutiliza este mapa en vez de duplicarlo.
     */
    private const TIPO_INTRANET_A_NOMBRES = [
        'C' => ['CATEDRA'],
        'T' => ['TALLER'],
        'L' => ['LABORATORIO'],
    ];

    public function __construct(
        protected EstudianteService $estudianteService,
        protected InscripcionCursoService $inscripcionCursoService,
        protected CursoService $cursoService
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

        try {
            $componentesIntranet = $this->resolverComponentesIntranet($curso);
        } catch (\Throwable $e) {
            Log::warning("[IntranetService@inscribirAutomaticamente] Error al consultar componentes de Intranet: " . $e->getMessage(), [
                'curso_id'  => $curso->id_curso,
                'exception' => get_class($e),
                'file'      => $e->getFile() . ':' . $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return new ResultadoInscripcionAutomatica(
                total_procesados: 0,
                inscritos_exitosamente: 0,
                alumnos_creados: 0,
                ya_inscritos: 0,
                errores: [],
                componentes_procesadas: [],
                advertencias: ["No fue posible consultar la Intranet para inscribir alumnos: {$e->getMessage()}"]
            );
        }

        $totalProcesados = 0;
        $inscritosExitosamente = 0;
        $alumnosCreados = 0;
        $yaInscritos = 0;
        $errores = [];
        $advertencias = [];
        $componentesReporte = [];

        foreach ($componentesIntranet as $compIntranet) {
            $compUtamed = $this->mapearComponenteIntranetAUtamed($compIntranet, $curso);
            if (!$compUtamed) {
                // Antes: Log::info("Skip silencioso...") y se seguía sin dejar rastro
                // visible para el usuario. Ahora queda como advertencia reportada.
                $advertencias[] = "La componente {$compIntranet->curso_tipo_asig->value} (acta {$compIntranet->cur_codigo}) "
                    . "no tiene equivalente configurado en UTAMED para el curso #{$curso->id_curso}; no se inscribió a sus alumnos.";
                continue;
            }

            /** @var Collection<int, InscripcionData> $inscripcionesIntranet */
            try {
                $oracleService = app('OracleDataService');
                $inscripcionesIntranet = $oracleService->traer_ins_id([$compIntranet->cur_codigo]);
            } catch (\Throwable $e) {
                Log::warning("[IntranetService@inscribirAutomaticamente] Error al consultar inscripciones de Intranet para acta {$compIntranet->cur_codigo}: " . $e->getMessage(), [
                    'curso_id'   => $curso->id_curso,
                    'cur_codigo' => $compIntranet->cur_codigo,
                    'exception'  => get_class($e),
                    'file'       => $e->getFile() . ':' . $e->getLine(),
                    'trace'      => $e->getTraceAsString(),
                ]);
                $advertencias[] = "No fue posible consultar inscripciones de Intranet para el acta {$compIntranet->cur_codigo}: {$e->getMessage()}";
                continue;
            }

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

                } catch (\Throwable $e) {
                    Log::error("Error al inscribir alumno RUT {$inscripcionData->alum_rut}: " . $e->getMessage(), [
                        'curso_id'  => $curso->id_curso,
                        'alum_rut'  => $inscripcionData->alum_rut,
                        'ins_id'    => $inscripcionData->ins_id,
                        'exception' => get_class($e),
                        'file'      => $e->getFile() . ':' . $e->getLine(),
                        'trace'     => $e->getTraceAsString(),
                    ]);
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
            componentes_procesadas: $componentesReporte,
            advertencias: $advertencias
        );
    }

    /**
     * Resuelve los códigos de componente (CUR_CODIGO) desde la Intranet para
     * un curso ya guardado. Delega en resolverComponentesDesdeParametros(),
     * tomando los datos sueltos desde el curso persistido.
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
        // `letra_grupo` casi nunca queda guardado (ver LetraGrupo); si no está,
        // se deriva de indice_grupo con la misma regla que ya usa el wizard.
        $letraGrupo = $curso->letra_grupo ?: LetraGrupo::fromIndice($curso->indice_grupo);

        return $this->resolverComponentesDesdeParametros(
            idAsignatura: $asignacionPlan->id_asignatura,
            idPlan: $asignacionPlan->id_plan,
            agno: $agno,
            semestre: $semestre,
            letraGrupo: $letraGrupo
        );
    }

    /**
     * Versión "sin curso": resuelve los CUR_CODIGO desde Intranet a partir de
     * los datos sueltos que la persona ya escribió en el formulario ANTES de
     * guardar el curso (asignatura, plan, año, semestre, paralelo). No
     * requiere que exista un Curso persistido — se usa tanto para el preview
     * del wizard como, internamente, para cursos ya guardados.
     *
     * @return Collection<int, ComponenteCursoData>
     */
    public function resolverComponentesDesdeParametros(
        int $idAsignatura,
        int $idPlan,
        int $agno,
        int $semestre,
        string $letraGrupo
    ): Collection {
        $asignacionPlan = AsignacionPlan::where('id_asignatura', $idAsignatura)
            ->where('id_plan', $idPlan)
            ->whereNull('fecha_eliminacion')
            ->with(['plan', 'asignatura'])
            ->first();

        if (!$asignacionPlan) {
            Log::warning("[IntranetService@resolverComponentesDesdeParametros] No se encontró asignacion_plan activa para id_asignatura={$idAsignatura}, id_plan={$idPlan}");
            return collect();
        }

        $planCod = (int)($asignacionPlan->plan?->agno_plan ?? $asignacionPlan->plan?->cod_plan ?? $asignacionPlan->plan?->id_plan ?? 0);
        $asigCodigo = (string)($asignacionPlan->asignatura?->cod_asignatura ?? '');

        if ($asigCodigo === '') {
            Log::warning("[IntranetService@resolverComponentesDesdeParametros] Código de asignatura vacío para AsignacionPlan #{$asignacionPlan->id_asignacion_plan}");
        }

        $oracleService = app('OracleDataService');

        return $oracleService->traer_cur_codigos(
            semestre: $semestre,
            agno: $agno,
            planCod: $planCod,
            asigCodigo: $asigCodigo,
            grupoAsig: $letraGrupo !== '' ? $letraGrupo : null
        );
    }

    /**
     * "Mirar antes de tocar": arma el reporte completo de qué componentes se
     * detectarían (Intranet primero, Plan de Estudios como respaldo) sin
     * escribir nada en la base de datos. El wizard lo llama antes de crear el
     * curso; sincronizarComponentes() lo reutiliza sólo para saber el origen.
     */
    public function previsualizarComponentes(
        int $idAsignatura,
        int $idPlan,
        int $agno,
        int $semestre,
        string $letraGrupo
    ): ResultadoPreviewComponentes {
        $advertencias = [];

        $asignacionPlan = AsignacionPlan::where('id_asignatura', $idAsignatura)
            ->where('id_plan', $idPlan)
            ->whereNull('fecha_eliminacion')
            ->first();

        // Lo que sugiere el Plan de Estudios (horas de la asignatura), para
        // usarlo de respaldo y también para comparar contra Intranet.
        $asignatura = $asignacionPlan?->asignatura;
        $nombresPlan = [];
        if ($asignatura) {
            if (($asignatura->horas_catedra ?? 0) > 0) $nombresPlan[] = 'CATEDRA';
            if (($asignatura->horas_taller ?? 0) > 0) $nombresPlan[] = 'TALLER';
            if (($asignatura->horas_laboratorio ?? 0) > 0) $nombresPlan[] = 'LABORATORIO';
        }

        $componentesIntranet = collect();
        try {
            $componentesIntranet = $this->resolverComponentesDesdeParametros(
                $idAsignatura, $idPlan, $agno, $semestre, $letraGrupo
            );
        } catch (\Throwable $e) {
            Log::warning('[IntranetService@previsualizarComponentes] Intranet no disponible, se usa el Plan de Estudios como respaldo: ' . $e->getMessage(), [
                'id_asignatura' => $idAsignatura,
                'id_plan'       => $idPlan,
                'agno'          => $agno,
                'semestre'      => $semestre,
                'letra_grupo'   => $letraGrupo,
                'exception'     => get_class($e),
                'file'          => $e->getFile() . ':' . $e->getLine(),
                'trace'         => $e->getTraceAsString(),
            ]);
            $advertencias[] = 'No fue posible consultar la Intranet; se usó el Plan de Estudios (horas de la asignatura) como respaldo.';
        }

        $componentes = [];

        if ($componentesIntranet->isNotEmpty()) {
            $origen = 'INTRANET';
            $nombresIntranet = [];

            foreach ($componentesIntranet as $compIntranet) {
                $tipoComponente = $this->resolverTipoComponentePorCodigoIntranet($compIntranet->curso_tipo_asig->value);
                if (!$tipoComponente) {
                    $advertencias[] = "Intranet reporta una componente de tipo '{$compIntranet->curso_tipo_asig->value}' (acta {$compIntranet->cur_codigo}) "
                        . "que no tiene equivalente en el catálogo de UTAMED.";
                    continue;
                }
                $nombresIntranet[] = $this->normalizarNombreTipo($tipoComponente->tipo);
                $componentes[] = new ComponenteDetectada(
                    id_tipo_componente: $tipoComponente->id_tipo_componente,
                    tipo: $tipoComponente->tipo,
                    origen: 'INTRANET',
                    cur_codigo: $compIntranet->cur_codigo
                );
            }

            // Intranet es la fuente de verdad (decisión del equipo): se usa
            // igual su información, pero si el Plan de Estudios sugería algo
            // distinto queda un aviso visible — la discrepancia no se
            // resuelve en silencio, sólo se decide a favor de Intranet.
            if ($nombresPlan !== [] && $this->difierenConjuntos($nombresPlan, $nombresIntranet)) {
                $advertencias[] = 'El Plan de Estudios sugiere componentes distintas a las informadas por Intranet ('
                    . implode(', ', $nombresPlan) . '); se usó lo que indica Intranet por ser la fuente de verdad.';
            }
        } else {
            $origen = 'PLAN';
            if ($advertencias === []) {
                $advertencias[] = 'Intranet no tiene oferta registrada para este periodo; las componentes se derivaron de las horas del Plan de Estudios.';
            }
            foreach ($nombresPlan as $nombre) {
                $tipoComponente = $this->resolverTipoComponentePorNombre($nombre);
                if (!$tipoComponente) {
                    $advertencias[] = "El Plan de Estudios indica horas de '{$nombre}' pero ese tipo no existe en el catálogo de UTAMED.";
                    continue;
                }
                $componentes[] = new ComponenteDetectada(
                    id_tipo_componente: $tipoComponente->id_tipo_componente,
                    tipo: $tipoComponente->tipo,
                    origen: 'PLAN'
                );
            }
        }

        if ($componentes === []) {
            $advertencias[] = 'No se detectó ninguna componente (ni en Intranet ni en el Plan de Estudios). Debe revisarse manualmente.';
        }

        $idPrincipal = collect($componentes)
            ->sortBy(fn(ComponenteDetectada $c) => TipoComponente::PRIORIDAD[$this->normalizarNombreTipo($c->tipo)] ?? 99)
            ->first()?->id_tipo_componente;

        return new ResultadoPreviewComponentes(
            origen: $origen,
            componentes: $componentes,
            id_tipo_componente_principal: $idPrincipal,
            advertencias: $advertencias
        );
    }

    /**
     * EJECUTA (crea en BD) sólo las componentes que la persona aceptó tras
     * revisar el preview. Es idempotente: si la componente ya existe para el
     * curso, no la duplica y la reporta en `componentes_existentes`.
     *
     * @param array<int, int> $idsTipoComponenteAceptados
     */
    public function sincronizarComponentes(
        Curso $curso,
        array $idsTipoComponenteAceptados,
        bool $inscribirAlumnos = false
    ): ResultadoSincronizacionComponentes {
        $curso->loadMissing(['asignacionPlan.plan.carrera', 'asignacionPlan.asignatura', 'componentes.tipoComponente']);

        $origen = 'PLAN';
        $asignacionPlan = $curso->asignacionPlan;
        if ($asignacionPlan) {
            try {
                $letra = $curso->letra_grupo ?: LetraGrupo::fromIndice($curso->indice_grupo);
                $preview = $this->previsualizarComponentes(
                    $asignacionPlan->id_asignatura,
                    $asignacionPlan->id_plan,
                    $curso->agno_real ?? (int) now()->year,
                    $curso->semestre_real ?? 1,
                    $letra
                );
                $origen = $preview->origen;
            } catch (\Throwable $e) {
                Log::warning('[IntranetService@sincronizarComponentes] No se pudo determinar el origen: ' . $e->getMessage(), [
                    'curso_id'  => $curso->id_curso,
                    'exception' => get_class($e),
                    'file'      => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        }

        $creadas = [];
        $existentes = [];
        $advertencias = [];

        foreach ($idsTipoComponenteAceptados as $idTipoComponente) {
            $tipoComponente = TipoComponente::find((int) $idTipoComponente);
            if (!$tipoComponente) {
                $advertencias[] = "Se intentó sincronizar un tipo de componente (#{$idTipoComponente}) que no existe en el catálogo.";
                continue;
            }

            $yaExiste = $curso->componentes->firstWhere('id_tipo_componente', $tipoComponente->id_tipo_componente);
            if ($yaExiste) {
                $existentes[] = $tipoComponente->tipo;
                continue;
            }

            $contexto = $this->cursoService->createOrUpdateContext($curso->cod_curso . '-' . $tipoComponente->id_tipo_componente);

            Componente::create([
                'id_curso'                          => $curso->id_curso,
                'id_tipo_componente'                => $tipoComponente->id_tipo_componente,
                'id_contexto'                        => $contexto->id_contexto,
                'genera_acta'                        => true,
                'aprobacion_obligatoria'             => false,
                'porcentaje_aprobacion'              => 60.00,
                'porcentaje_asistencia_obligatoria'  => 75.00,
            ]);

            $creadas[] = $tipoComponente->tipo;
        }

        if ($inscribirAlumnos) {
            $curso->load(['componentes.tipoComponente']);
            try {
                $resultadoInscripcion = $this->inscribirAutomaticamente($curso);
                $advertencias = array_merge($advertencias, $resultadoInscripcion->advertencias);
            } catch (\Throwable $e) {
                Log::warning('[IntranetService@sincronizarComponentes] Error al inscribir alumnos automáticamente: ' . $e->getMessage(), [
                    'curso_id'  => $curso->id_curso,
                    'exception' => get_class($e),
                    'file'      => $e->getFile() . ':' . $e->getLine(),
                    'trace'     => $e->getTraceAsString(),
                ]);
                $advertencias[] = 'No fue posible inscribir los alumnos desde la Intranet: ' . $e->getMessage();
            }
        }

        return new ResultadoSincronizacionComponentes(
            origen: $origen,
            componentes_creadas: $creadas,
            componentes_existentes: $existentes,
            advertencias: $advertencias
        );
    }

    /**
     * Mapea un ComponenteCursoData de la Intranet a su Componente equivalente
     * YA CREADA en el curso (usado al inscribir alumnos, cuando las
     * componentes ya deberían existir gracias al preview + sincronización).
     */
    protected function mapearComponenteIntranetAUtamed(ComponenteCursoData $compIntranet, Curso $curso): ?Componente
    {
        $nombresBuscados = self::TIPO_INTRANET_A_NOMBRES[$compIntranet->curso_tipo_asig->value] ?? [];

        foreach ($curso->componentes as $comp) {
            $nombreTipo = $this->normalizarNombreTipo($comp->tipoComponente?->tipo ?? '');
            if (in_array($nombreTipo, $nombresBuscados, true)) {
                return $comp;
            }
        }

        return null;
    }

    /**
     * Busca en el catálogo UTAMED el TipoComponente que corresponde a un
     * código de tipo de Intranet (C/T/L).
     */
    protected function resolverTipoComponentePorCodigoIntranet(string $codigoIntranet): ?TipoComponente
    {
        $nombresAceptados = self::TIPO_INTRANET_A_NOMBRES[$codigoIntranet] ?? [];
        if ($nombresAceptados === []) {
            return null;
        }

        return TipoComponente::all()
            ->first(fn(TipoComponente $tc) => in_array($this->normalizarNombreTipo($tc->tipo), $nombresAceptados, true));
    }

    /**
     * Busca en el catálogo UTAMED el TipoComponente por nombre normalizado
     * ('CATEDRA', 'TALLER', 'LABORATORIO'), usado por el fallback del Plan de
     * Estudios.
     */
    protected function resolverTipoComponentePorNombre(string $nombreNormalizado): ?TipoComponente
    {
        return TipoComponente::all()
            ->first(fn(TipoComponente $tc) => $this->normalizarNombreTipo($tc->tipo) === $nombreNormalizado);
    }

    /**
     * Mayúsculas, sin espacios y sin tilde (CÁTEDRA → CATEDRA), para poder
     * comparar nombres de tipo sin importar de qué fuente vinieron.
     */
    protected function normalizarNombreTipo(string $nombre): string
    {
        return str_replace('Á', 'A', mb_strtoupper(trim($nombre)));
    }

    /**
     * True si dos listas de nombres de tipo (ya normalizados o no) no
     * contienen exactamente los mismos elementos.
     *
     * @param array<int, string> $a
     * @param array<int, string> $b
     */
    protected function difierenConjuntos(array $a, array $b): bool
    {
        $a = array_unique(array_map(fn($n) => $this->normalizarNombreTipo($n), $a));
        $b = array_unique(array_map(fn($n) => $this->normalizarNombreTipo($n), $b));
        sort($a);
        sort($b);
        return $a !== $b;
    }
}
