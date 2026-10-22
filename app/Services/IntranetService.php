<?php

namespace App\Services;

use App\DTOs\External\ComponenteCursoData;
use App\DTOs\External\ComponenteDetectada;
use App\DTOs\External\InscripcionData;
use App\DTOs\External\ResultadoInscripcionAutomatica;
use App\DTOs\External\ResultadoPreviewComponentes;
use App\DTOs\External\ResultadoSincronizacionComponentes;
use App\DTOs\External\ResultadoSincronizacionInscripciones;
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

        if (!$curso->id_contexto) {
            $curso->refresh();
        }

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
                advertencias: ["No fue posible consultar la Intranet para inscribir alumnos: {$e->getMessage()}"],
                estudiantes_en_intranet: [],
                lectura_completa: false
            );
        }

        $totalProcesados = 0;
        $inscritosExitosamente = 0;
        $alumnosCreados = 0;
        $yaInscritos = 0;
        $errores = [];
        $advertencias = [];
        $componentesReporte = [];

        // Foto de quién figura en la Intranet, para que la sincronización sepa a
        // quién retirar sin volver a consultar Oracle. `lecturaCompleta` se apaga
        // en cuanto una componente no se puede leer o no tiene equivalente en
        // UTAMED: la foto queda con huecos y ya no sirve para dar de baja.
        $estudiantesEnIntranet = [];
        $lecturaCompleta = true;

        // Alumnos inscritos al curso en esta corrida, y alumnos ya contados como
        // inscritos (uno que figura en Cátedra y Taller es un alumno, no dos).
        $nuevosEnCurso = [];
        $contados = [];

        try {
            DB::statement("SET utamed.skip_componente_trigger = 'on'");
        } catch (\Throwable) {
        }

        try {
            foreach ($componentesIntranet as $compIntranet) {
                $compUtamed = $this->mapearComponenteIntranetAUtamed($compIntranet, $curso);
                if (!$compUtamed) {
                    // Antes: Log::info("Skip silencioso...") y se seguía sin dejar rastro
                    // visible para el usuario. Ahora queda como advertencia reportada.
                    $advertencias[] = "La componente {$compIntranet->curso_tipo_asig->value} (acta {$compIntranet->cur_codigo}) "
                        . "no tiene equivalente configurado en UTAMED para el curso #{$curso->id_curso}; no se inscribió a sus alumnos.";
                    $lecturaCompleta = false;
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
                    $lecturaCompleta = false;
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

                        // Figura en la Intranet, esté ya inscrito o no: es lo que
                        // distingue «sigue en el ramo» de «lo botó».
                        $estudiantesEnIntranet[$estudiante->id_estudiante] = $estudiante->id_estudiante;

                        // 1. Inscripción a Nivel Curso
                        $yaInscritoCurso = InscripcionCurso::where('id_curso', $curso->id_curso)
                            ->where('id_estudiante', $estudiante->id_estudiante)
                            ->exists();

                        if (!$yaInscritoCurso) {
                            // El INS_ID de la Intranet es el folio de inscripción del alumno en
                            // el periodo y se repite en todas sus asignaturas, pero
                            // cod_inscripcion_uta es UNIQUE: guardarlo siempre hacía fallar la
                            // inscripción del alumno en su segundo curso del semestre. Se guarda
                            // sólo si está libre; el INS_ID queda siempre en la componente
                            // (cod_inscripcion_curso_uta), que es donde corresponde.
                            $codInscripcion = (string) $inscripcionData->ins_id;
                            $codLibre = !InscripcionCurso::where('cod_inscripcion_uta', $codInscripcion)->exists();

                            $this->inscripcionCursoService->create([
                                'id_curso'            => $curso->id_curso,
                                'id_estudiante'       => $estudiante->id_estudiante,
                                'cod_inscripcion_uta' => $codLibre ? $codInscripcion : null,
                                'fecha_inscripcion'   => now()->toDateString(),
                                'estado_inscripcion'  => 'INSCRITO',
                                'num_intento'         => 1,
                            ]);

                            // tr_inscribir_componente_curso_automaticamente ya no autoinscribe en
                            // componentes en inscripciones de Intranet; la inscripción a nivel de componente
                            // se realiza explícitamente abajo según el acta oficial.
                            $nuevosEnCurso[$estudiante->id_estudiante] = true;
                        }

                        // Asegurar asignación del rol Estudiante en el contexto del Curso
                        if ($curso->id_contexto) {
                            $this->estudianteService->asignarRolEnContexto($estudiante, $curso->id_contexto);
                        }


                        // 2. Inscripción a Nivel Componente
                        $inscripcionCompExistente = InscripcionComponente::where('id_componente', $compUtamed->id_componente)
                            ->where('id_estudiante', $estudiante->id_estudiante)
                            ->first();

                        if ($inscripcionCompExistente) {
                            if (!$inscripcionCompExistente->cod_inscripcion_curso_uta) {
                                $inscripcionCompExistente->update([
                                    'cod_inscripcion_curso_uta' => $inscripcionData->ins_id,
                                ]);
                            }

                            if (isset($nuevosEnCurso[$estudiante->id_estudiante])) {
                                $inscritosComponenteCount++;
                                if (!isset($contados[$estudiante->id_estudiante])) {
                                    $contados[$estudiante->id_estudiante] = true;
                                    $inscritosExitosamente++;
                                }
                            } else {
                                $yaInscritos++;
                            }
                        } else {
                            InscripcionComponente::create([
                                'id_componente'             => $compUtamed->id_componente,
                                'id_estudiante'             => $estudiante->id_estudiante,
                                'cod_inscripcion_curso_uta' => $inscripcionData->ins_id,
                            ]);

                            $inscritosComponenteCount++;
                            if (!isset($contados[$estudiante->id_estudiante])) {
                                $contados[$estudiante->id_estudiante] = true;
                                $inscritosExitosamente++;
                            }
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
                        // Un alumno que falló es un alumno del que no sabemos si
                        // sigue o no: la foto ya no está completa.
                        $lecturaCompleta = false;
                    }
                }

                $componentesReporte[] = [
                    'cur_codigo' => $compIntranet->cur_codigo,
                    'tipo'       => $compIntranet->curso_tipo_asig->value,
                    'grupo'      => $compIntranet->curso_grupo_asig,
                    'inscritos'  => $inscritosComponenteCount,
                ];
            }
        } finally {
            try {
                DB::statement("SET utamed.skip_componente_trigger = 'off'");
            } catch (\Throwable) {
            }
        }

        return new ResultadoInscripcionAutomatica(
            total_procesados: $totalProcesados,
            inscritos_exitosamente: $inscritosExitosamente,
            alumnos_creados: $alumnosCreados,
            ya_inscritos: $yaInscritos,
            errores: $errores,
            componentes_procesadas: $componentesReporte,
            advertencias: $advertencias,
            estudiantes_en_intranet: array_values($estudiantesEnIntranet),
            lectura_completa: $lecturaCompleta
        );
    }

    /**
     * Sincroniza el roster de un curso contra la Intranet en las dos direcciones:
     * inscribe a quien aparece y retira a quien dejó de aparecer (ramo botado).
     *
     * POR QUÉ EN DOS PASOS Y NO UNO: la mitad que inscribe ya existía y se sigue
     * usando sola desde el botón de inscripción automática. Aquí se reutiliza tal
     * cual y se le agrega lo que falta, en vez de escribir una segunda versión de
     * la misma lógica que podría divergir.
     *
     * NADIE SE BORRA. Retirar es mover `estado_inscripcion` a RETIRADO: la fila
     * queda, con su código de inscripción, su intento y su promedio parcial. Es
     * además reversible —si el alumno vuelve a figurar, la siguiente corrida lo
     * reactiva— y esa reversibilidad es lo que hace que retirar de más no sea
     * un daño permanente.
     *
     * NO SE RETIRA CON DATOS PARCIALES. Si una componente no se pudo leer o no
     * tiene equivalente en UTAMED, la foto de la Intranet tiene huecos: los
     * alumnos de esa componente parecerían haber botado el ramo. En ese caso la
     * mitad que inscribe corre igual —agregar de menos no hace daño— y la que
     * retira se salta entera, avisando por qué.
     */
    public function sincronizarInscripciones(Curso $curso): ResultadoSincronizacionInscripciones
    {
        $resultadoInscripcion = $this->inscribirAutomaticamente($curso);

        $enIntranet = $resultadoInscripcion->estudiantes_en_intranet;
        $advertencias = [];

        if (!$resultadoInscripcion->lectura_completa) {
            $advertencias[] = 'No se retiró a nadie: la lectura de la Intranet quedó incompleta '
                . '(revisa las advertencias de arriba) y dar de baja con datos parciales retiraría '
                . 'alumnos que sí están inscritos.';

            return new ResultadoSincronizacionInscripciones(
                inscripcion: $resultadoInscripcion,
                retiro_aplicado: false,
                advertencias: $advertencias
            );
        }

        // Reactivar primero: si un alumno volvió a figurar, su fila RETIRADA de
        // una corrida anterior tiene que volver a INSCRITO antes de que el paso
        // siguiente mire quién está activo.
        $reactivados = [];
        if ($enIntranet !== []) {
            $porReactivar = InscripcionCurso::with('estudiante.usuario')
                ->where('id_curso', $curso->id_curso)
                ->whereIn('id_estudiante', $enIntranet)
                ->whereIn('estado_inscripcion', ['RETIRADO', 'ANULADO'])
                ->get();

            foreach ($porReactivar as $inscripcion) {
                $this->inscripcionCursoService->reEnroll($inscripcion);
                $reactivados[] = $this->identificarAlumno($inscripcion);
            }
        }

        // Retirar a los que siguen activos en UTAMED pero ya no figuran en la
        // Intranet. `whereNotIn` con lista vacía no filtra nada, así que el caso
        // «la Intranet no devolvió a nadie» retira el curso completo, que es lo
        // correcto: el acta quedó sin alumnos.
        $porRetirar = InscripcionCurso::with('estudiante.usuario')
            ->where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->when($enIntranet !== [], fn ($q) => $q->whereNotIn('id_estudiante', $enIntranet))
            ->get();

        $retirados = [];
        foreach ($porRetirar as $inscripcion) {
            $this->inscripcionCursoService->marcarRetirada($inscripcion);
            $retirados[] = $this->identificarAlumno($inscripcion);
        }

        // Nivel componente: se informa, no se toca. `inscripcion_componente` no
        // tiene columna de estado, así que la única forma de «retirar» ahí sería
        // borrar la fila, y con ella el vínculo del alumno con sus evaluaciones
        // de esa componente. Mientras no exista esa columna, el número sirve para
        // que quien administra sepa que la discrepancia está ahí.
        $componentesSinRespaldo = InscripcionComponente::query()
            ->whereIn('id_componente', $curso->componentes->pluck('id_componente'))
            ->when($enIntranet !== [], fn ($q) => $q->whereNotIn('id_estudiante', $enIntranet))
            ->count();

        if ($componentesSinRespaldo > 0) {
            $advertencias[] = "{$componentesSinRespaldo} inscripción(es) de componente quedaron sin "
                . 'respaldo en la Intranet. No se modifican: esa tabla todavía no registra estado y '
                . 'borrarlas perdería el historial de evaluación de la componente.';
        }

        return new ResultadoSincronizacionInscripciones(
            inscripcion: $resultadoInscripcion,
            retirados: $retirados,
            reactivados: $reactivados,
            componentes_sin_respaldo: $componentesSinRespaldo,
            retiro_aplicado: true,
            advertencias: $advertencias
        );
    }

    /**
     * Datos mínimos para que el reporte diga a quién se retiró o reactivó. Un
     * id_estudiante no le sirve a nadie para revisar si la baja fue correcta.
     *
     * @return array{rut: string|null, nombre: string|null}
     */
    protected function identificarAlumno(InscripcionCurso $inscripcion): array
    {
        $usuario = $inscripcion->estudiante?->usuario;

        return [
            'rut'    => $usuario?->rut,
            'nombre' => $usuario
                ? trim("{$usuario->nombre1} {$usuario->apellido1} {$usuario->apellido2}")
                : null,
        ];
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
        string $letraGrupo,
        ?Curso $curso = null
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

        $componentesSobrantes = [];
        if ($curso !== null) {
            $curso->loadMissing(['componentes.tipoComponente']);

            $tiposIntranet = [];
            $curCodigosIntranet = [];
            if ($componentesIntranet->isNotEmpty()) {
                foreach ($componentesIntranet as $compIntranet) {
                    $curCodigosIntranet[] = (int) $compIntranet->cur_codigo;
                    $tc = $this->resolverTipoComponentePorCodigoIntranet($compIntranet->curso_tipo_asig->value);
                    if ($tc) {
                        $tiposIntranet[] = $tc->id_tipo_componente;
                    }
                }
            }

            foreach ($curso->componentes as $compLocal) {
                $curCodigoLocal = $compLocal->intranet_cur_codigo;
                $coincideCurCodigo = $curCodigoLocal && in_array((int) $curCodigoLocal, $curCodigosIntranet, true);
                $coincideTipo = in_array($compLocal->id_tipo_componente, $tiposIntranet, true);

                if (!$coincideCurCodigo && !$coincideTipo) {
                    $componentesSobrantes[] = [
                        'id_componente'      => $compLocal->id_componente,
                        'id_tipo_componente' => $compLocal->id_tipo_componente,
                        'tipo'               => $compLocal->tipoComponente?->tipo ?? 'Desconocida',
                        'cod_uta'            => $curCodigoLocal,
                    ];
                }
            }

            if (!empty($componentesSobrantes)) {
                $nombresSobrantes = implode(', ', array_column($componentesSobrantes, 'tipo'));
                $advertencias[] = "El curso posee componente(s) locales ({$nombresSobrantes}) que no figuran en la oferta oficial de Intranet para este período y grupo.";
            }
        }

        $idPrincipal = collect($componentes)
            ->sortBy(fn(ComponenteDetectada $c) => TipoComponente::PRIORIDAD[$this->normalizarNombreTipo($c->tipo)] ?? 99)
            ->first()?->id_tipo_componente;

        return new ResultadoPreviewComponentes(
            origen: $origen,
            componentes: $componentes,
            id_tipo_componente_principal: $idPrincipal,
            advertencias: $advertencias,
            componentes_sobrantes: $componentesSobrantes
        );
    }

    /**
     * Grupos que la Intranet oferta para una asignatura en un periodo, cada uno
     * con sus componentes y su CUR_CODIGO. Es lo que el wizard ofrece para
     * autocompletar letra y componentes. No escribe nada.
     *
     * Si la Intranet no responde se devuelve `disponible = false` con el motivo,
     * para que el wizard lo diga en vez de mostrar «no hay oferta».
     *
     * @return array{disponible: bool, grupos: list<array{letra: string, indice_grupo: int|null, componentes: list<array{id_tipo_componente: int|null, tipo: string, cur_codigo: int}>}>, advertencias: list<string>}
     */
    public function ofertaIntranet(int $idAsignatura, int $idPlan, int $agno, int $semestre): array
    {
        try {
            $componentes = $this->resolverComponentesDesdeParametros($idAsignatura, $idPlan, $agno, $semestre, '');
        } catch (\Throwable $e) {
            Log::warning('[IntranetService@ofertaIntranet] Intranet no disponible: ' . $e->getMessage(), [
                'id_asignatura' => $idAsignatura,
                'id_plan'       => $idPlan,
                'agno'          => $agno,
                'semestre'      => $semestre,
                'exception'     => get_class($e),
            ]);

            return [
                'disponible'   => false,
                'grupos'       => [],
                'advertencias' => ['No fue posible consultar la Intranet: ' . $e->getMessage()],
            ];
        }

        $grupos = $componentes
            ->groupBy(fn (ComponenteCursoData $c) => $c->curso_grupo_asig)
            ->map(function (Collection $comps, string $letra) {
                return [
                    'letra'        => $letra,
                    'indice_grupo' => LetraGrupo::toIndice($letra),
                    'componentes'  => $comps
                        ->map(function (ComponenteCursoData $c) {
                            $tipo = $this->resolverTipoComponentePorCodigoIntranet($c->curso_tipo_asig->value);
                            return [
                                'id_tipo_componente' => $tipo?->id_tipo_componente,
                                'tipo'               => $tipo?->tipo ?? $c->curso_tipo_asig->value,
                                'cur_codigo'         => $c->cur_codigo,
                            ];
                        })
                        ->sortBy(fn ($c) => TipoComponente::PRIORIDAD[$this->normalizarNombreTipo($c['tipo'])] ?? 99)
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy(fn ($g) => $g['indice_grupo'] ?? PHP_INT_MAX)
            ->values()
            ->all();

        return [
            'disponible'   => true,
            'grupos'       => $grupos,
            'advertencias' => [],
        ];
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
        $componentesSobrantes = [];
        $asignacionPlan = $curso->asignacionPlan;
        if ($asignacionPlan) {
            try {
                $letra = $curso->letra_grupo ?: LetraGrupo::fromIndice($curso->indice_grupo);
                $preview = $this->previsualizarComponentes(
                    $asignacionPlan->id_asignatura,
                    $asignacionPlan->id_plan,
                    $curso->agno_real ?? (int) now()->year,
                    $curso->semestre_real ?? 1,
                    $letra,
                    $curso
                );
                $origen = $preview->origen;
                $componentesSobrantes = $preview->componentes_sobrantes;
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

            $curCodigo = null;
            if (isset($preview) && !empty($preview->componentes)) {
                $compDetectada = collect($preview->componentes)
                    ->firstWhere('id_tipo_componente', $tipoComponente->id_tipo_componente);
                $curCodigo = $compDetectada?->cur_codigo;
            }

            $yaExiste = $curso->componentes->firstWhere('id_tipo_componente', $tipoComponente->id_tipo_componente);
            if ($yaExiste) {
                if ($yaExiste->intranet_cur_codigo === null && $curCodigo !== null) {
                    $yaExiste->update(['intranet_cur_codigo' => $curCodigo]);
                }
                $existentes[] = $tipoComponente->tipo;
                continue;
            }

            // id_contexto lo asigna el trigger tr_componente_pre_insert.
            Componente::create([
                'id_curso'                          => $curso->id_curso,
                'id_tipo_componente'                => $tipoComponente->id_tipo_componente,
                'genera_acta'                       => true,
                'aprobacion_obligatoria'            => false,
                'porcentaje_aprobacion'             => 60.00,
                'porcentaje_asistencia_obligatoria' => 75.00,
                'intranet_cur_codigo'               => $curCodigo,
            ]);

            $creadas[] = $tipoComponente->tipo;
        }

        $resultadoInscripcion = null;
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
            advertencias: $advertencias,
            inscripcion: $resultadoInscripcion,
            componentes_sobrantes: $componentesSobrantes
        );
    }

    /**
     * Mapea un ComponenteCursoData de la Intranet a su Componente equivalente
     * YA CREADA en el curso (usado al inscribir alumnos, cuando las
     * componentes ya deberían existir gracias al preview + sincronización).
     */
    protected function mapearComponenteIntranetAUtamed(ComponenteCursoData $compIntranet, Curso $curso): ?Componente
    {
        // 1. Coincidencia directa por intranet_cur_codigo si ya fue grabado
        if ($compIntranet->cur_codigo) {
            $match = $curso->componentes->first(
                fn($c) => $c->intranet_cur_codigo !== null && (int)$c->intranet_cur_codigo === (int)$compIntranet->cur_codigo
            );
            if ($match) {
                return $match;
            }
        }

        // 2. Coincidencia por tipología normalizada y backfill de intranet_cur_codigo
        $nombresBuscados = self::TIPO_INTRANET_A_NOMBRES[$compIntranet->curso_tipo_asig->value] ?? [];

        foreach ($curso->componentes as $comp) {
            $nombreTipo = $this->normalizarNombreTipo($comp->tipoComponente?->tipo ?? '');
            if (in_array($nombreTipo, $nombresBuscados, true)) {
                if ($comp->intranet_cur_codigo === null && $compIntranet->cur_codigo !== null) {
                    $comp->update(['intranet_cur_codigo' => $compIntranet->cur_codigo]);
                }
                return $comp;
            }
        }

        return null;
    }

    /**
     * Busca en el catálogo UTAMED el TipoComponente que corresponde a un
     * código de tipo de Intranet (C/T/L).
     */
    public function resolverTipoComponentePorCodigoIntranet(string $codigoIntranet): ?TipoComponente
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
