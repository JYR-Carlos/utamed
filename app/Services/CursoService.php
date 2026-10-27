<?php

namespace App\Services;

use App\Models\Curso\Curso;
use App\Models\Curso\Componente;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\TipoComponente;
use App\Models\Curso\Programa;
use App\Models\Curso\Bibliografia;
use App\Models\Curso\Unidad;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\Rubrica;
use App\Enums\DB\EstadoRubrica;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\IntranetService;
use App\Services\SyllabusStructure;
use App\Support\LetraGrupo;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CursoService
{
    /**
     * Create a new course.
     *
     * @param array $data
     * @return Curso
     * @throws \Exception
     */
    public function create(array $data): Curso
    {
        return DB::transaction(function () use ($data) {
            // Find the AsignacionPlan record
            $asignacionPlan = $this->findAsignacionPlan(
                $data['id_asignatura'],
                $data['id_plan']
            );

            // El código del curso es interno de UTAMED: si no viene, se asigna el
            // siguiente correlativo (el de la Intranet es por componente, no por curso).
            $codCurso = !empty($data['cod_curso']) ? (int) $data['cod_curso'] : $this->siguienteCodCurso();

            $agnoReal = $data['agno_real'] ?? null;
            $semestreReal = $data['semestre_real'] ?? 2;

            // Docentes de cada componente (clave: id_tipo_componente, valor: lista de
            // id_docente; se acepta también un id suelto). Colegiado se calcula al
            // final con Curso::recalcularColegiado(): dos o más docentes en un mismo
            // componente, no un docente distinto por componente.
            $docentesPorComponente = $this->normalizarDocentesPorComponente($data['docentes_por_componente'] ?? []);

            // Letra de grupo (indice_grupo): si viene explícita desde el wizard
            // (selección manual), se respeta y valida (ver StoreCursoRequest);
            // si no, se calcula la próxima disponible para ese periodo.
            $indiceGrupo = !empty($data['indice_grupo'])
                ? (int) $data['indice_grupo']
                : $this->nextIndiceGrupo($asignacionPlan->id_asignacion_plan, $agnoReal, $semestreReal);

            $letra = LetraGrupo::fromIndice($indiceGrupo);
            $nombreSugerido = $asignacionPlan->asignatura?->nombre
                ? "{$asignacionPlan->asignatura->nombre} - Grupo {$letra}"
                : '';
            $nombreFinal = !empty($data['nombre']) ? trim($data['nombre']) : $nombreSugerido;

            // Prepare curso data
            $cursoData = [
                'cod_curso'            => $codCurso,
                'nombre'               => $nombreFinal,
                'fecha_inicio'         => $data['fecha_inicio'] ?? now()->format('Y-m-d'),
                'agno_real'            => $agnoReal,
                'semestre_real'        => $semestreReal,
                'id_asignacion_plan'   => $asignacionPlan->id_asignacion_plan,
                'indice_grupo'         => $indiceGrupo,
                'id_docente_titular'   => $data['id_docente_sugerido'],
                'es_colegiado'         => false,
            ];

            // Set fecha_fin to 6 months after fecha_inicio
            $cursoData['fecha_fin'] = $this->calculateFechaFin($cursoData['fecha_inicio']);

            // id_contexto lo asigna el trigger tr_curso_pre_insert (con tipo y padre en el
            // árbol de contextos); refresh() trae ese valor al modelo.
            $curso = Curso::create($cursoData);
            $curso->refresh();

            // Asignar rol 'Docente Titular' al docente en el contexto del curso SIEMPRE,
            // independientemente de si imparte clases directamente en el componente.
            if (!empty($data['id_docente_sugerido'])) {
                $this->assignDocenteRolCurso($data['id_docente_sugerido'], $curso->id_contexto, true);
            }

            // Autorrecuperar CUR_CODIGO de Intranet por componente
            $curCodigosInput = $data['cur_codigos_por_componente'] ?? [];
            $curCodigosIntranet = [];
            $intranetEstado = 'sin_consultar';
            $intranetError = null;

            try {
                $letra = LetraGrupo::fromIndice($indiceGrupo);
                $componentesIntranet = app(IntranetService::class)->resolverComponentesDesdeParametros(
                    idAsignatura: (int) $asignacionPlan->id_asignatura,
                    idPlan: (int) $asignacionPlan->id_plan,
                    agno: $agnoReal ?? (int) now()->year,
                    semestre: $semestreReal ?? 1,
                    letraGrupo: $letra
                );

                foreach ($componentesIntranet as $compData) {
                    $tc = app(IntranetService::class)->resolverTipoComponentePorCodigoIntranet($compData->curso_tipo_asig->value);
                    if ($tc && $compData->cur_codigo) {
                        $curCodigosIntranet[$tc->id_tipo_componente] = (int) $compData->cur_codigo;
                    }
                }
                $intranetEstado = $componentesIntranet->isNotEmpty() ? 'conectado' : 'sin_oferta';
            } catch (\Throwable $e) {
                $intranetEstado = 'error';
                $intranetError = $e->getMessage();
                Log::warning('[CursoService@crear] Intranet no disponible para autorrecuperar CUR_CODIGO: ' . $e->getMessage());
            }

            $resolverCurCodigo = function (int $idTipo) use ($curCodigosInput, $curCodigosIntranet): ?int {
                return !empty($curCodigosInput[$idTipo])
                    ? (int) $curCodigosInput[$idTipo]
                    : ($curCodigosIntranet[$idTipo] ?? null);
            };

            // Find the chosen component type
            $tipoComponente = TipoComponente::find($data['id_tipo_componente_principal']);

            $generaActaPorComponente = $data['genera_acta_por_componente'] ?? [];
            $pctAprobPorComponente = $data['porcentaje_aprobacion_por_componente'] ?? [];
            $pctAsistPorComponente = $data['porcentaje_asistencia_por_componente'] ?? [];
            $aprobObligPorComponente = $data['aprobacion_obligatoria_por_componente'] ?? [];

            if ($tipoComponente) {
                $idPrinc = $tipoComponente->id_tipo_componente;
                $curCodigoPrincipal = $resolverCurCodigo($idPrinc);

                // id_contexto lo asigna el trigger tr_componente_pre_insert.
                $componente = Componente::create([
                    'id_curso'                          => $curso->id_curso,
                    'id_tipo_componente'                => $idPrinc,
                    'genera_acta'                       => $generaActaPorComponente[$idPrinc] ?? true,
                    'aprobacion_obligatoria'            => $aprobObligPorComponente[$idPrinc] ?? ($data['aprobacion_obligatoria'] ?? false),
                    'porcentaje_aprobacion'             => $pctAprobPorComponente[$idPrinc] ?? ($data['porcentaje_aprobacion'] ?? 60.00),
                    'porcentaje_asistencia_obligatoria' => $pctAsistPorComponente[$idPrinc] ?? ($data['porcentaje_asistencia_obligatoria'] ?? 75.00),
                    'intranet_cur_codigo'               => $curCodigoPrincipal,
                ])->refresh();

                // El jefe SIEMPRE tiene acceso administrativo (via id_docente_titular),
                // independientemente de si dicta clases en algún componente.
                $this->asignarDocentesComponente(
                    $curso,
                    $componente,
                    $docentesPorComponente[$tipoComponente->id_tipo_componente] ?? []
                );

                // Crear componentes adicionales (distintos al principal)
                $tiposAdicionales = array_filter(
                    $data['tipos_componente_ids'] ?? [],
                    fn($id) => (int) $id !== (int) $tipoComponente->id_tipo_componente
                );

                foreach ($tiposAdicionales as $tipoId) {
                    $tipoAdicional = TipoComponente::find((int) $tipoId);
                    if (!$tipoAdicional) {
                        continue;
                    }

                    $idAdic = $tipoAdicional->id_tipo_componente;
                    $curCodigoAdicional = $resolverCurCodigo($idAdic);

                    $componenteAdicional = Componente::create([
                        'id_curso'                          => $curso->id_curso,
                        'id_tipo_componente'                => $idAdic,
                        'genera_acta'                       => $generaActaPorComponente[$idAdic] ?? false,
                        'aprobacion_obligatoria'            => $aprobObligPorComponente[$idAdic] ?? ($data['aprobacion_obligatoria'] ?? false),
                        'porcentaje_aprobacion'             => $pctAprobPorComponente[$idAdic] ?? ($data['porcentaje_aprobacion'] ?? 60.00),
                        'porcentaje_asistencia_obligatoria' => $pctAsistPorComponente[$idAdic] ?? ($data['porcentaje_asistencia_obligatoria'] ?? 75.00),
                        'intranet_cur_codigo'               => $curCodigoAdicional,
                    ])->refresh();

                    $this->asignarDocentesComponente(
                        $curso,
                        $componenteAdicional,
                        $docentesPorComponente[$tipoAdicional->id_tipo_componente] ?? []
                    );
                }
            }

            $curso->recalcularColegiado();
            $curso->intranet_estado = $intranetEstado;
            $curso->intranet_error = $intranetError;

            return $curso;
        });
    }

    /**
     * Copy an existing course preserving structure, components, teachers and activities.
     * Activity dates are reset to null. Students and responses are NOT copied.
     *
     * @param Curso $cursoPadre The source course
     * @param array $data 'fecha_inicio' (Y-m-d string) y, opcional, 'cod_curso' (si no viene se asigna el siguiente)
     * @param Usuario|null $usuario Usuario que realiza la copia
     * @return Curso
     */
    public function copiar(Curso $cursoPadre, array $data, ?Usuario $usuario = null): Curso
    {
        return DB::transaction(function () use ($cursoPadre, $data, $usuario) {
            $cursoPadre->loadMissing([
                'asignacionPlan.asignatura',
                'unidades',
                'componentes.tipoComponente',
                'componentes.docenteComponentes',
                'componentes.actividades.rubricas',
                'programas',
            ]);

            $codCurso = !empty($data['cod_curso']) ? (int) $data['cod_curso'] : $this->siguienteCodCurso();
            $fechaInicio = $data['fecha_inicio'];
            $agnoReal = isset($data['agno_real']) ? (int) $data['agno_real'] : $cursoPadre->agno_real;
            $semestreReal = isset($data['semestre_real']) ? (int) $data['semestre_real'] : $cursoPadre->semestre_real;

            $indiceGrupo = $this->nextIndiceGrupo($cursoPadre->id_asignacion_plan, $agnoReal, $semestreReal);
            $letra = LetraGrupo::fromIndice($indiceGrupo);

            $nombreSugerido = preg_match('/\s*-\s*Grupo\s+[A-Za-z0-9]+$/i', $cursoPadre->nombre)
                ? preg_replace('/\s*-\s*Grupo\s+[A-Za-z0-9]+$/i', " - Grupo {$letra}", $cursoPadre->nombre)
                : ($cursoPadre->asignacionPlan?->asignatura?->nombre
                    ? "{$cursoPadre->asignacionPlan->asignatura->nombre} - Grupo {$letra}"
                    : "{$cursoPadre->nombre} - Grupo {$letra}");

            $nuevoNombre = !empty($data['nombre']) ? trim($data['nombre']) : $nombreSugerido;

            $nuevoCurso = Curso::create([
                'cod_curso'           => $codCurso,
                'nombre'              => $nuevoNombre,
                'fecha_inicio'        => $fechaInicio,
                'fecha_fin'           => $this->calculateFechaFin($fechaInicio),
                'agno_real'           => $agnoReal,
                'semestre_real'       => $semestreReal,
                'id_asignacion_plan'  => $cursoPadre->id_asignacion_plan,
                'id_docente_titular'  => $cursoPadre->id_docente_titular,
                'es_colegiado'        => $cursoPadre->es_colegiado,
                'indice_grupo'        => $indiceGrupo,
                'id_curso_padre'      => $cursoPadre->id_curso,
            ]);

            // id_contexto lo asigna el trigger tr_curso_pre_insert; refresh() lo trae.
            $nuevoCurso->refresh();

            // Las unidades pertenecen al curso y toda actividad exige una
            // (id_unidad NOT NULL): se copian y cada actividad se reasigna a
            // la copia de su unidad.
            $unidadNueva = [];
            foreach ($cursoPadre->unidades as $unidad) {
                $unidadNueva[$unidad->id_unidad] = \App\Models\Curso\Unidad::create([
                    'num_unidad'  => $unidad->num_unidad,
                    'nombre'      => $unidad->nombre,
                    'descripcion' => $unidad->descripcion,
                    'id_curso'    => $nuevoCurso->id_curso,
                ])->id_unidad;
            }

            // Assign titular role in the new course context
            if ($cursoPadre->id_docente_titular) {
                $this->assignDocenteRolCurso(
                    $cursoPadre->id_docente_titular,
                    $nuevoCurso->id_contexto,
                    true
                );
            }

            $asignatura = $cursoPadre->asignacionPlan?->asignatura;
            $ofertaIntranetDestino = null;
            $curCodigosIntranetDestino = [];

            foreach ($cursoPadre->componentes as $comp) {
                $tipo = $comp->tipoComponente;
                if ($tipo && $asignatura) {
                    $nombreNormalizado = str_replace(
                        ['Á', 'É', 'Í', 'Ó', 'Ú'],
                        ['A', 'E', 'I', 'O', 'U'],
                        mb_strtoupper(trim($tipo->tipo ?? ''))
                    );

                    $horas = match ($nombreNormalizado) {
                        'CATEDRA'     => (float) ($asignatura->horas_catedra ?? 0),
                        'TALLER'      => (float) ($asignatura->horas_taller ?? 0),
                        'LABORATORIO' => (float) ($asignatura->horas_laboratorio ?? 0),
                        default       => 0.0,
                    };

                    if ($horas <= 0) {
                        if ($ofertaIntranetDestino === null) {
                            $ofertaIntranetDestino = [];
                            try {
                                /** @var IntranetService $intranetService */
                                $intranetService = app(IntranetService::class);
                                $compsIntranet = $intranetService->resolverComponentesDesdeParametros(
                                    (int) $cursoPadre->asignacionPlan->id_asignatura,
                                    (int) $cursoPadre->asignacionPlan->id_plan,
                                    (int) $agnoReal,
                                    (int) $semestreReal,
                                    $letra
                                );
                                foreach ($compsIntranet as $c) {
                                    match ($c->curso_tipo_asig->value) {
                                        'C' => $ofertaIntranetDestino['CATEDRA'] = true,
                                        'T' => $ofertaIntranetDestino['TALLER'] = true,
                                        'L' => $ofertaIntranetDestino['LABORATORIO'] = true,
                                        default => null,
                                    };
                                    $tc = $intranetService->resolverTipoComponentePorCodigoIntranet($c->curso_tipo_asig->value);
                                    if ($tc && $c->cur_codigo) {
                                        $curCodigosIntranetDestino[$tc->id_tipo_componente] = (int) $c->cur_codigo;
                                    }
                                }
                                $ofertaIntranetDestino = array_keys($ofertaIntranetDestino);
                            } catch (\Throwable $e) {
                                Log::warning('[CursoService@copiar] No fue posible verificar oferta de Intranet para grupo destino: ' . $e->getMessage());
                                $ofertaIntranetDestino = [];
                            }
                        }

                        if (!in_array($nombreNormalizado, $ofertaIntranetDestino, true)) {
                            Log::warning("[CursoService@copiar] Omitiendo componente '{$tipo->tipo}' al copiar curso #{$cursoPadre->id_curso} al grupo {$letra}: tiene 0 horas en plan y no figura en la oferta de Intranet.");
                            continue;
                        }
                    }
                }

                // id_contexto lo asigna el trigger tr_componente_pre_insert.
                $nuevoComp = Componente::create([
                    'id_curso'                          => $nuevoCurso->id_curso,
                    'id_tipo_componente'                => $comp->id_tipo_componente,
                    'genera_acta'                       => $comp->genera_acta,
                    'aprobacion_obligatoria'            => $comp->aprobacion_obligatoria,
                    'porcentaje_aprobacion'             => $comp->porcentaje_aprobacion,
                    'porcentaje_asistencia_obligatoria' => $comp->porcentaje_asistencia_obligatoria,
                    'intranet_cur_codigo'               => $curCodigosIntranetDestino[$comp->id_tipo_componente] ?? null,
                ])->refresh();

                foreach ($comp->docenteComponentes as $dc) {
                    DocenteComponente::create([
                        'id_componente' => $nuevoComp->id_componente,
                        'id_docente'    => $dc->id_docente,
                        'es_titular'    => $dc->es_titular,
                    ]);
                }

                foreach ($comp->actividades as $act) {
                    $nuevaAct = Actividad::create([
                        'nombre'          => $act->nombre,
                        'fecha_limite'    => null,
                        'visible'         => $act->visible,
                        'ponderacion'     => $act->ponderacion,
                        'exigencia'       => $act->exigencia,
                        'tipo_actividad'  => $act->tipo_actividad instanceof \App\Enums\DB\TipoActividad
                            ? $act->tipo_actividad->value
                            : $act->tipo_actividad,
                        'tipo_entrega'    => $act->tipo_entrega,
                        'es_grupal'       => $act->es_grupal,
                        'max_integrantes' => $act->max_integrantes,
                        'es_plantilla'    => false,
                        'id_componente'   => $nuevoComp->id_componente,
                        'id_unidad'       => $unidadNueva[$act->id_unidad] ?? null,
                    ]);

                    // Clonar las rúbricas analíticas asociadas manteniendo criterios y ponderaciones
                    foreach ($act->rubricas as $rubrica) {
                        Rubrica::create([
                            'rubrica'        => $rubrica->rubrica,
                            'estado_rubrica' => EstadoRubrica::POSTULADA,
                            'id_actividad'   => $nuevaAct->id_actividad,
                        ]);
                    }
                }
            }

            // Clonar syllabus vigente adaptado al nuevo curso y período si el curso padre tiene programa
            if ($cursoPadre->programas()->exists()) {
                $this->copiarSyllabus($cursoPadre, $nuevoCurso, $usuario);
            }

            Log::info('[CursoService@copiar] Curso copiado', [
                'id_curso_padre' => $cursoPadre->id_curso,
                'id_curso_nuevo' => $nuevoCurso->id_curso,
                'cod_curso'      => $codCurso,
            ]);

            return $nuevoCurso;
        });
    }

    /**
     * Update an existing course.
     *
     * AUDIT: Tracks professor jefe changes and synchronizes access revocation
     *
     * @param Curso $curso
     * @param array $data
     * @return Curso
     * @throws \Exception
     */
    public function update(Curso $curso, array $data): Curso
    {
        return DB::transaction(function () use ($curso, $data) {
            // Find the AsignacionPlan record
            $asignacionPlan = $this->findAsignacionPlan(
                $data['id_asignatura'],
                $data['id_plan']
            );

            // Store old docente_titular to sync with components
            $oldDocenteTitularId = $curso->id_docente_titular;

            // Prepare update data
            $updateData = [
                'cod_curso'          => $data['cod_curso'],
                'nombre'             => $data['nombre'] ?? '',
                'fecha_inicio'       => $data['fecha_inicio'] ?? $curso->fecha_inicio,
                'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
                'indice_grupo'       => $data['indice_grupo'] ?? $curso->indice_grupo,
                'agno_real'          => $data['agno_real'] ?? $curso->agno_real,
                'semestre_real'      => $data['semestre_real'] ?? $curso->semestre_real,
            ];

            $newDocenteTitularId = null;
            if (!empty($data['id_docente_sugerido'])) {
                $newDocenteTitularId = $data['id_docente_sugerido'];
                $updateData['id_docente_titular'] = $newDocenteTitularId;
            }

            // Recalculate fecha_fin if fecha_inicio changed
            if ($updateData['fecha_inicio'] !== $curso->fecha_inicio) {
                $updateData['fecha_fin'] = $this->calculateFechaFin($updateData['fecha_inicio']);
            }

            $curso->update($updateData);

            // Sync docente_titular roles in components if changed
            if ($newDocenteTitularId !== null && $newDocenteTitularId !== $oldDocenteTitularId) {
                $this->syncDocenteTitularInComponents($curso, $oldDocenteTitularId, $newDocenteTitularId);

                // syncDocenteTitularInComponents solo sincroniza curso.docente_componente
                // (es_titular por componente); sin esto, el nuevo titular nunca recibe el
                // rol RBAC "Docente Titular" en el contexto del curso (sólo se asigna al
                // crear el curso), y por lo tanto no tiene el permiso
                // cursos/programas:agregar para crear el syllabus.
                $idContexto = (int) ($curso->id_contexto ?: $curso->fresh()?->id_contexto);
                if ($idContexto > 0) {
                    $this->assignDocenteRolCurso($newDocenteTitularId, $idContexto, true);
                }
            }

            return $curso;
        });
    }

    /**
     * Find AsignacionPlan by asignatura and plan.
     *
     * @param int $idAsignatura
     * @param int $idPlan
     * @return AsignacionPlan
     * @throws \Exception
     */
    private function findAsignacionPlan(int $idAsignatura, int $idPlan): AsignacionPlan
    {
        $asignacionPlan = AsignacionPlan::where('id_asignatura', $idAsignatura)
            ->where('id_plan', $idPlan)
            ->whereNull('fecha_eliminacion')
            ->first();

        if (!$asignacionPlan) {
            throw new \Exception('No se encontró la asignación del plan.');
        }

        return $asignacionPlan;
    }

    /**
     * Asigna el rol de docente en el contexto del curso según su lugar en el componente:
     *   - id_docente === curso.id_docente_titular        → 'Docente Titular'
     *   - titular del componente (es_titular) sin ser DT → 'Docente Componente'
     *   - docente adicional del componente (colegiado)   → 'Docente Componente Colegiado'
     *
     * Al ascender se revocan los roles inferiores previos; nunca se degrada un rol
     * superior. Vive aquí (y no sólo en ComponenteController) para que el wizard
     * de creación asigne los mismos roles que «Editar componente».
     */
    public function asignarRolDocenteComponente(
        int $idDocente,
        int $idContextoCurso,
        bool $esTitularComponente,
        ?int $idDocenteTitularCurso = null,
    ): void {
        try {
            $docente = Docente::find($idDocente);
            if (!$docente || !$docente->id_usuario) {
                Log::warning('No se pudo asignar rol Docente: docente no encontrado.', [
                    'id_docente' => $idDocente,
                ]);
                return;
            }

            $actorId = Auth::id() ?? $docente->id_usuario;

            // ── Determinar el rol objetivo ─────────────────────────────────
            $esDtCurso = $idDocenteTitularCurso !== null && $idDocente === $idDocenteTitularCurso;

            $rolNombre = match (true) {
                $esDtCurso             => 'Docente Titular',
                $esTitularComponente   => 'Docente Componente',
                default                => 'Docente Componente Colegiado',
            };

            // ── Jerarquía: no degradar si ya tiene un rol superior ─────────
            // Orden descendente: Docente Titular > Docente Componente > Docente Componente Colegiado
            $jerarquia = ['Docente Titular', 'Docente Componente', 'Docente Componente Colegiado'];
            $nivelObjetivo = array_search($rolNombre, $jerarquia);

            $rolesActuales = Rol::whereIn('nombre', $jerarquia)->pluck('id_rol', 'nombre');

            foreach ($jerarquia as $idx => $nombreSuperior) {
                if ($idx >= $nivelObjetivo) {
                    break;
                }
                $idRolSuperior = $rolesActuales[$nombreSuperior] ?? null;
                if ($idRolSuperior) {
                    $tieneSuperior = UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                        ->where('id_contexto', $idContextoCurso)
                        ->where('id_rol', $idRolSuperior)
                        ->where('esta_activo', true)
                        ->where('fue_eliminado', false)
                        ->exists();
                    if ($tieneSuperior) {
                        return; // Ya tiene un rol superior; no degradar
                    }
                }
            }

            // ── Revocar roles inferiores antes de asignar el objetivo ──────
            foreach ($jerarquia as $idx => $nombreInferior) {
                if ($idx <= $nivelObjetivo) {
                    continue;
                }
                $idRolInferior = $rolesActuales[$nombreInferior] ?? null;
                if ($idRolInferior) {
                    UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                        ->where('id_contexto', $idContextoCurso)
                        ->where('id_rol', $idRolInferior)
                        ->where('esta_activo', true)
                        ->where('fue_eliminado', false)
                        ->update([
                            'esta_activo'    => false,
                            'fue_eliminado'  => true,
                            'fecha_fin_real' => Carbon::now(),
                            'eliminado_por'  => $actorId,
                        ]);
                }
            }

            // ── Asignar rol objetivo si no lo tiene aún ────────────────────
            $rol = Rol::where('nombre', $rolNombre)->first();
            if (!$rol) {
                Log::error("Rol '{$rolNombre}' no existe en la base de datos.");
                return;
            }

            $already = UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                ->where('id_contexto', $idContextoCurso)
                ->where('id_rol', $rol->id_rol)
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->exists();

            if (!$already) {
                $now = Carbon::now();
                UsuarioRolAsignacion::create([
                    'id_usuario'                => $docente->id_usuario,
                    'id_rol'                    => $rol->id_rol,
                    'id_contexto'               => $idContextoCurso,
                    'asignado_por'              => $actorId,
                    'fecha_inicio_planificada'  => $now,
                    'fecha_fin_planificada'     => $now->copy()->addYears(100),
                    'fecha_fin_real'            => null,
                    'fue_eliminado'             => false,
                    'esta_activo'               => true,
                    'creado_por'                => $actorId,
                ]);
                Log::info("Rol '{$rolNombre}' asignado en contexto de curso", [
                    'id_usuario'        => $docente->id_usuario,
                    'id_contexto_curso' => $idContextoCurso,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error asignando rol Docente en curso: ' . $e->getMessage(), [
                'id_docente'        => $idDocente,
                'id_contexto_curso' => $idContextoCurso,
            ]);
        }
    }

    /**
     * @param array<int|string, int|string|array<int, int|string>|null> $porComponente
     * @return array<int, list<int>> id_tipo_componente => ids de docente, sin repetir
     */
    private function normalizarDocentesPorComponente(array $porComponente): array
    {
        $normalizado = [];
        foreach ($porComponente as $idTipo => $docentes) {
            $ids = collect(is_array($docentes) ? $docentes : [$docentes])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            if ($ids !== []) {
                $normalizado[(int) $idTipo] = $ids;
            }
        }

        return $normalizado;
    }

    /**
     * Mismo criterio que ComponenteController::addDocente(): el primero de la
     * lista queda como titular del componente y los siguientes lo acompañan
     * (colegiado). Cada uno recibe su rol en el contexto del curso: sin él, un
     * docente de componente distinto del titular no podía entrar al curso.
     *
     * @param list<int> $idsDocente
     */
    private function asignarDocentesComponente(Curso $curso, Componente $componente, array $idsDocente): void
    {
        foreach ($idsDocente as $i => $idDocente) {
            DocenteComponente::create([
                'id_componente' => $componente->id_componente,
                'id_docente'    => $idDocente,
                'es_titular'    => $i === 0,
            ]);

            $this->asignarRolDocenteComponente($idDocente, $curso->id_contexto, $i === 0, $curso->id_docente_titular);
        }
    }

    /**
     * Siguiente código interno de curso: el mayor existente + 1, contando también
     * los cursos eliminados, porque uq_cod_curso no mira fecha_eliminacion.
     *
     * Debe llamarse dentro de la transacción que inserta el curso: el candado
     * consultivo serializa las creaciones simultáneas hasta el COMMIT, para que
     * dos de ellas no lean el mismo máximo.
     */
    public function siguienteCodCurso(): int
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['curso.cod_curso']);

        return (int) (DB::table('curso.curso')->max('cod_curso') ?? 0) + 1;
    }

    /**
     * Calcula el próximo indice_grupo (letra) disponible para un periodo dado.
     * Null-safe: agno_real/semestre_real nulos se tratan como su propio scope
     * (no como "cualquier periodo"), igual que la comparación SQL original.
     *
     * @param int $idAsignacionPlan
     * @param int|null $agnoReal
     * @param int|null $semestreReal
     * @return int
     */
    public function nextIndiceGrupo(int $idAsignacionPlan, ?int $agnoReal, ?int $semestreReal): int
    {
        $query = Curso::where('id_asignacion_plan', $idAsignacionPlan)
            ->where('es_plantilla', false)
            ->whereNull('fecha_eliminacion');

        $agnoReal === null ? $query->whereNull('agno_real') : $query->where('agno_real', $agnoReal);
        $semestreReal === null ? $query->whereNull('semestre_real') : $query->where('semestre_real', $semestreReal);

        $tomados = $query->pluck('indice_grupo')->all();
        $tomadosSet = array_flip($tomados);

        $candidato = 1;
        while (isset($tomadosSet[$candidato])) {
            $candidato++;
        }

        return $candidato;
    }

    /**
     * Calculate fecha_fin based on fecha_inicio.
     *
     * @param string $fechaInicio
     * @return string
     */
    private function calculateFechaFin(string $fechaInicio): string
    {
        $fecha = new \DateTime($fechaInicio);
        $fecha->modify('+6 months');
        return $fecha->format('Y-m-d');
    }

    /**
     * Synchronize docente_titular roles when professor jefe changes.
     * 
     * AUDIT LOG: Tracks all changes to professor jefe assignments
     * 
     * Removes old professor from titular roles in all course components
     * and assigns the new professor as titular to all components.
     * This ensures that when a course's titular professor changes, their
     * access is revoked from all course components immediately.
     *
     * @param Curso $curso
     * @param int $oldDocenteId
     * @param int $newDocenteId
     * @return void
     */
    private function syncDocenteTitularInComponents(Curso $curso, int $oldDocenteId, int $newDocenteId): void
    {
        // Get all components of this course
        $componentes = Componente::where('id_curso', $curso->id_curso)->get();
        
        $logData = [
            'evento' => 'CAMBIO_PROFESOR_JEFE',
            'id_curso' => $curso->id_curso,
            'cod_curso' => $curso->cod_curso,
            'id_docente_anterior' => $oldDocenteId,
            'id_docente_nuevo' => $newDocenteId,
            'total_componentes' => $componentes->count(),
            'timestamp' => now()->toIso8601String(),
            'detalles' => []
        ];

        foreach ($componentes as $componente) {
            $componenteLog = [
                'id_componente' => $componente->id_componente,
                'tipo_componente' => $componente->tipoComponente?->nombre ?? 'N/A',
                'acciones' => []
            ];

            // Remove old docente from titular roles in this component
            $removidoCount = DocenteComponente::where('id_componente', $componente->id_componente)
                ->where('id_docente', $oldDocenteId)
                ->where('es_titular', true)
                ->delete();
            
            if ($removidoCount > 0) {
                $componenteLog['acciones'][] = "REMOVIDO: Docente {$oldDocenteId} como titular (registros eliminados: {$removidoCount})";
            }

            // Add new docente as titular in this component (if not already present)
            $existingDocenteComponente = DocenteComponente::where('id_componente', $componente->id_componente)
                ->where('id_docente', $newDocenteId)
                ->first();

            if (!$existingDocenteComponente) {
                // New docente not yet in component, add as titular
                DocenteComponente::create([
                    'id_componente' => $componente->id_componente,
                    'id_docente'    => $newDocenteId,
                    'es_titular'    => true,
                ]);
                $componenteLog['acciones'][] = "ASIGNADO: Docente {$newDocenteId} como titular (registro creado)";
            } else {
                // New docente already in component, just mark as titular
                $existingDocenteComponente->update(['es_titular' => true]);
                $componenteLog['acciones'][] = "ACTUALIZADO: Docente {$newDocenteId} marcado como titular";
            }

            $logData['detalles'][] = $componenteLog;
        }

        // Log the synchronization event
        Log::channel('seguridad')->info('Sincronización de profesor jefe completada', $logData);
    }

    /**
     * Asigna el rol correcto al usuario en el contexto del curso según si es titular o componente.
     *
     * @param  int   $idDocente        ID del docente
     * @param  int   $idContextoCurso  ID del contexto del curso
     * @param  bool  $esTitular        Si se asigna como titular (true) o componente (false)
     * @return void
     */
    private function assignDocenteRolCurso(int $idDocente, int $idContextoCurso, bool $esTitular): void
    {
        try {
            $docente = Docente::with('usuario')->find($idDocente);
            if (!$docente || !$docente->id_usuario) {
                Log::warning('CursoService: No se pudo asignar rol Docente: docente no encontrado.', [
                    'id_docente' => $idDocente,
                ]);
                return;
            }

            $usuarioActivo = $docente->usuario
                && $docente->usuario instanceof Usuario
                && $docente->usuario->esta_activo;

            if (!$usuarioActivo) {
                Log::warning('CursoService: No se pudo asignar rol Docente: usuario asociado inexistente o inactivo.', [
                    'id_docente' => $idDocente,
                    'id_usuario' => $docente->id_usuario,
                ]);
                return;
            }

            $actorId = Auth::id() ?? $docente->id_usuario;
            $rolNombre = $esTitular ? 'Docente Titular' : 'Docente Componente';

            if ($esTitular) {
                // Revocar rol Docente Componente previo si existe
                $rolComponente = Rol::where('nombre', 'Docente Componente')->first();
                if ($rolComponente) {
                    UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                        ->where('id_contexto', $idContextoCurso)
                        ->where('id_rol', $rolComponente->id_rol)
                        ->where('esta_activo', true)
                        ->where('fue_eliminado', false)
                        ->update([
                            'esta_activo'    => false,
                            'fue_eliminado'  => true,
                            'fecha_fin_real' => Carbon::now(),
                            'eliminado_por'  => $actorId,
                        ]);
                }
            } else {
                // No degradar si ya tiene Docente Titular
                $rolTitular = Rol::where('nombre', 'Docente Titular')->first();
                if ($rolTitular) {
                    $hasTitular = UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                        ->where('id_contexto', $idContextoCurso)
                        ->where('id_rol', $rolTitular->id_rol)
                        ->where('esta_activo', true)
                        ->where('fue_eliminado', false)
                        ->exists();
                    if ($hasTitular) {
                        return;
                    }
                }
            }

            $rol = Rol::firstOrCreate(
                ['nombre' => $rolNombre],
                ['creado_por' => $actorId]
            );

            $already = UsuarioRolAsignacion::where('id_usuario', $docente->id_usuario)
                ->where('id_contexto', $idContextoCurso)
                ->where('id_rol', $rol->id_rol)
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->exists();

            if (!$already) {
                $now = Carbon::now();
                UsuarioRolAsignacion::create([
                    'id_usuario'               => $docente->id_usuario,
                    'id_rol'                   => $rol->id_rol,
                    'id_contexto'              => $idContextoCurso,
                    'asignado_por'             => $actorId,
                    'fecha_inicio_planificada' => $now,
                    'fecha_fin_planificada'    => $now->copy()->addYears(100),
                    'fecha_fin_real'           => null,
                    'fue_eliminado'            => false,
                    'esta_activo'              => true,
                    'creado_por'               => $actorId,
                ]);
                Log::info("CursoService: Rol '{$rolNombre}' asignado en curso", [
                    'id_usuario'        => $docente->id_usuario,
                    'id_contexto_curso' => $idContextoCurso,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('CursoService: Error asignando rol Docente en curso: ' . $e->getMessage(), [
                'id_docente'        => $idDocente,
                'id_contexto_curso' => $idContextoCurso,
            ]);
        }
    }

    /**
     * Copia el syllabus (programa) vigente de un curso a otro que compartan la misma asignatura.
     * Opcionalmente replica las actividades evaluativas y rúbricas definidas en el curso origen.
     *
     * @param Curso $cursoOrigen Curso con el programa fuente
     * @param Curso $cursoDestino Curso receptor del nuevo programa
     * @param Usuario|null $usuario Usuario que realiza la copia (o Auth::user())
     * @param Programa|null $programaOrigen Programa específico a copiar (si es null, usa el actual o último)
     * @param bool $copiarActividades Si es true, replica actividades evaluativas y rúbricas a componentes homólogos
     * @return Programa
     * @throws \InvalidArgumentException
     */
    public function copiarSyllabus(
        Curso $cursoOrigen,
        Curso $cursoDestino,
        ?Usuario $usuario = null,
        ?Programa $programaOrigen = null,
        bool $copiarActividades = true
    ): Programa {
        return DB::transaction(function () use ($cursoOrigen, $cursoDestino, $usuario, $programaOrigen, $copiarActividades) {
            $cursoOrigen->loadMissing('asignacionPlan.asignatura');
            $cursoDestino->loadMissing('asignacionPlan.asignatura');

            $idAsigOrigen = $cursoOrigen->asignacionPlan?->id_asignatura;
            $idAsigDestino = $cursoDestino->asignacionPlan?->id_asignatura;

            if (!$idAsigOrigen || !$idAsigDestino || (int) $idAsigOrigen !== (int) $idAsigDestino) {
                throw new \InvalidArgumentException('Los cursos deben compartir la misma asignatura para copiar el syllabus.');
            }

            $programaFuente = $programaOrigen ?? $cursoOrigen->programas()->where('es_actual', true)->first()
                ?? $cursoOrigen->programas()->latest('id_programa')->first();

            if (!$programaFuente) {
                throw new \InvalidArgumentException('El curso de origen no posee un programa o syllabus para copiar.');
            }

            // Desmarcar programa actual previo en el curso destino
            Programa::where('id_curso', $cursoDestino->id_curso)
                ->where('es_actual', true)
                ->update(['es_actual' => false]);

            $maxVersion = (int) Programa::where('id_curso', $cursoDestino->id_curso)->max('version_programa');
            $nuevaVersion = $maxVersion + 1;

            $usuarioId = $usuario?->id_usuario ?? Auth::id() ?? $cursoDestino->id_docente_titular ?? $cursoOrigen->id_docente_titular;

            $tipoSyllabus = $programaFuente->getTipoSyllabus() ?? 'BASICO';

            // Construir metadata limpia adaptada exclusivamente al curso destino (docente principal, id_curso, codigo, agno, semestre)
            $shellDestino = (new SyllabusStructure($cursoDestino))
                ->withAsignatura()
                ->withSecciones()
                ->withCategoriaFromString($cursoDestino->asignacionPlan?->tipo_ramo ?? 'OBLIGATORIO')
                ->withTipoSyllabus($tipoSyllabus)
                ->build();

            // Las secciones académicas se copian íntegramente desde el programa origen
            $secciones = $programaFuente->data_syllabus['secciones'] ?? $shellDestino['secciones'];

            // Sincronizar unidades al curso destino para preservar integridad relacional y de bibliografías
            $unidadMap = [];
            if (!empty($secciones['VI']['contenido']['unidades'])) {
                foreach ($secciones['VI']['contenido']['unidades'] as $u) {
                    $num = $u['numero'] ?? null;
                    $titulo = trim($u['titulo'] ?? '');
                    if ($num !== null) {
                        $uDest = Unidad::firstOrCreate(
                            ['id_curso' => $cursoDestino->id_curso, 'num_unidad' => (int) $num],
                            ['nombre' => $titulo ?: "Unidad {$num}", 'descripcion' => $u['contenidos_items'][0]['item'] ?? null]
                        );
                        $unidadMap[(int) $num] = $uDest->id_unidad;
                    }
                }
            }

            // Asegurar sincronización de unidades existentes en base de datos para el curso origen
            $unidadesOrigen = Unidad::where('id_curso', $cursoOrigen->id_curso)->get();
            foreach ($unidadesOrigen as $uOrig) {
                if (!isset($unidadMap[$uOrig->num_unidad])) {
                    $uDest = Unidad::firstOrCreate(
                        ['id_curso' => $cursoDestino->id_curso, 'num_unidad' => $uOrig->num_unidad],
                        ['nombre' => $uOrig->nombre, 'descripcion' => $uOrig->descripcion]
                    );
                    $unidadMap[$uOrig->num_unidad] = $uDest->id_unidad;
                }
            }

            $nuevoDataSyllabus = [
                'metadata' => $shellDestino['metadata'],
                'secciones' => $secciones,
                'timestamp' => now()->toIso8601String(),
            ];

            // El nuevo programa se inicializa como BORRADOR (no hereda aprobaciones ni firmas)
            $nuevoPrograma = Programa::create([
                'id_curso'           => $cursoDestino->id_curso,
                'version_programa'   => $nuevaVersion,
                'estado'             => 'BORRADOR',
                'data_syllabus'      => $nuevoDataSyllabus,
                'creado_por'         => $usuarioId,
                'revisado_por'       => null,
                'es_actual'          => true,
                'fecha_creacion'     => now(),
                'fecha_modificacion' => null,
                'fecha_entrega'      => null,
            ]);

            // Copiar bibliografías (tabla curso.bibliografia) remapeando id_unidad
            $programaFuente->loadMissing('bibliografias');
            $nuevasBibliografiasSyllabus = [];

            if ($programaFuente->bibliografias->isNotEmpty()) {
                foreach ($programaFuente->bibliografias as $bib) {
                    $numUnidadOrigen = $bib->id_unidad ? $bib->unidad?->num_unidad : null;
                    $nuevoIdUnidad = $numUnidadOrigen !== null ? ($unidadMap[$numUnidadOrigen] ?? null) : null;
                    $uuidBib = (string) Str::uuid();

                    $nuevaBib = Bibliografia::create([
                        'uuid_bibliografia'   => $uuidBib,
                        'id_programa'         => $nuevoPrograma->id_programa,
                        'id_unidad'           => $nuevoIdUnidad,
                        'titulo'              => $bib->titulo,
                        'autor'               => $bib->autor,
                        'cita'                => $bib->cita,
                        'editorial'           => $bib->editorial,
                        'agno'                => $bib->agno,
                        'url'                 => $bib->url,
                        'uuid_archivo'        => $bib->uuid_archivo,
                        'es_bibliografia_uta' => $bib->es_bibliografia_uta,
                        'agregado_por'        => $usuarioId,
                    ]);

                    $nuevasBibliografiasSyllabus[] = [
                        'id_bibliografia'     => $uuidBib,
                        'titulo'              => $nuevaBib->titulo,
                        'autor'               => $nuevaBib->autor,
                        'cita'                => $nuevaBib->cita,
                        'editorial'           => $nuevaBib->editorial,
                        'anio'                => $nuevaBib->agno,
                        'es_bibliografia_uta' => $nuevaBib->es_bibliografia_uta,
                        'url'                 => $nuevaBib->url,
                        'uuid_archivo'        => $nuevaBib->uuid_archivo,
                        'id_unidad'           => $numUnidadOrigen,
                    ];
                }
            } elseif (!empty($secciones['VIII']['contenido']['bibliografias'])) {
                foreach ($secciones['VIII']['contenido']['bibliografias'] as $bibData) {
                    if (empty($bibData['titulo'])) {
                        continue;
                    }
                    $numUnidad = isset($bibData['id_unidad']) && is_numeric($bibData['id_unidad']) ? (int) $bibData['id_unidad'] : null;
                    $nuevoIdUnidad = $numUnidad !== null ? ($unidadMap[$numUnidad] ?? null) : null;
                    $uuidBib = (string) Str::uuid();

                    $nuevaBib = Bibliografia::create([
                        'uuid_bibliografia'   => $uuidBib,
                        'id_programa'         => $nuevoPrograma->id_programa,
                        'id_unidad'           => $nuevoIdUnidad,
                        'titulo'              => $bibData['titulo'],
                        'autor'               => !empty($bibData['autor']) ? $bibData['autor'] : 'Desconocido',
                        'cita'                => $bibData['cita'] ?? null,
                        'editorial'           => $bibData['editorial'] ?? null,
                        'agno'                => $bibData['anio'] ?? date('Y'),
                        'url'                 => $bibData['url'] ?? null,
                        'uuid_archivo'        => $bibData['uuid_archivo'] ?? null,
                        'es_bibliografia_uta' => $bibData['es_bibliografia_uta'] ?? false,
                        'agregado_por'        => $usuarioId,
                    ]);

                    $nuevasBibliografiasSyllabus[] = [
                        'id_bibliografia'     => $uuidBib,
                        'titulo'              => $nuevaBib->titulo,
                        'autor'               => $nuevaBib->autor,
                        'cita'                => $nuevaBib->cita,
                        'editorial'           => $nuevaBib->editorial,
                        'anio'                => $nuevaBib->agno,
                        'es_bibliografia_uta' => $nuevaBib->es_bibliografia_uta,
                        'url'                 => $nuevaBib->url,
                        'uuid_archivo'        => $nuevaBib->uuid_archivo,
                        'id_unidad'           => $numUnidad,
                    ];
                }
            }

            // Sincronizar los nuevos IDs de bibliografía en la sección VIII del JSON
            if (!empty($nuevasBibliografiasSyllabus) && isset($nuevoDataSyllabus['secciones']['VIII']['contenido'])) {
                $nuevoDataSyllabus['secciones']['VIII']['contenido']['bibliografias'] = $nuevasBibliografiasSyllabus;
                $nuevoPrograma->update(['data_syllabus' => $nuevoDataSyllabus]);
                $nuevoPrograma->refresh();
            }

            // Replicar actividades del curso origen si $copiarActividades es true
            if ($copiarActividades) {
                $this->replicarActividadesEntreCursos($cursoOrigen, $cursoDestino, $unidadMap);
            }

            Log::info('[CursoService@copiarSyllabus] Syllabus copiado exitosamente', [
                'id_curso_origen'   => $cursoOrigen->id_curso,
                'id_curso_destino'  => $cursoDestino->id_curso,
                'id_programa_nuevo' => $nuevoPrograma->id_programa,
                'version'           => $nuevaVersion,
                'con_actividades'   => $copiarActividades,
            ]);

            return $nuevoPrograma;
        });
    }

    /**
     * Replica actividades evaluativas y rúbricas analíticas desde un curso origen hacia los componentes homólogos
     * de un curso destino que comparte la misma asignatura.
     *
     * @param Curso $cursoOrigen Curso fuente con las actividades
     * @param Curso $cursoDestino Curso receptor con componentes homólogos
     * @param array<int, int> $unidadMap Mapeo opcional de num_unidad a id_unidad destino
     * @return int Cantidad de actividades clonadas
     */
    public function replicarActividadesEntreCursos(Curso $cursoOrigen, Curso $cursoDestino, array $unidadMap = []): int
    {
        $cursoOrigen->loadMissing(['componentes.actividades.rubricas', 'componentes.actividades.unidad']);
        $cursoDestino->loadMissing(['componentes', 'unidades']);

        // Si no se proporcionó $unidadMap, armarlo a partir de las unidades de destino
        if (empty($unidadMap)) {
            $unidadMap = $cursoDestino->unidades->pluck('id_unidad', 'num_unidad')->all();
        }

        $componentesDestino = $cursoDestino->componentes->keyBy('id_tipo_componente');
        if ($componentesDestino->isEmpty()) {
            return 0;
        }

        // Obtener nombres de actividades ya existentes en el destino para evitar duplicados (idempotencia)
        $actividadesDestinoNombres = Actividad::whereIn('id_componente', $componentesDestino->pluck('id_componente'))
            ->pluck('nombre')
            ->map(fn($n) => mb_strtolower(trim((string) $n)))
            ->flip();

        $gruposService = new \App\Services\Agenda\GrupoIndividualService();
        $creadas = 0;

        foreach ($cursoOrigen->componentes as $compOrigen) {
            $compDest = $componentesDestino->get($compOrigen->id_tipo_componente);
            if (!$compDest) {
                continue;
            }

            foreach ($compOrigen->actividades as $actOrigen) {
                $nombreNorm = mb_strtolower(trim((string) $actOrigen->nombre));
                if ($actividadesDestinoNombres->has($nombreNorm)) {
                    continue; // Ya existe en el curso destino
                }

                $numUnidadOrigen = $actOrigen->unidad?->num_unidad;
                $nuevoIdUnidad = $numUnidadOrigen !== null ? ($unidadMap[(int) $numUnidadOrigen] ?? null) : null;

                // Fallback a la primera unidad del destino si no hay coincidencia exacta de número
                if ($nuevoIdUnidad === null && $cursoDestino->unidades->isNotEmpty()) {
                    $nuevoIdUnidad = $cursoDestino->unidades->first()->id_unidad;
                }

                $copiaAct = Actividad::create([
                    'nombre'          => $actOrigen->nombre,
                    'fecha_limite'    => null, // Regla de negocio: fechas en blanco
                    'visible'         => false, // Llega oculta para que el docente configure plazos y publique
                    'ponderacion'     => $actOrigen->ponderacion,
                    'exigencia'       => $actOrigen->exigencia,
                    'tipo_actividad'  => $actOrigen->tipo_actividad instanceof \App\Enums\DB\TipoActividad
                        ? $actOrigen->tipo_actividad->value
                        : $actOrigen->tipo_actividad,
                    'tipo_entrega'    => $actOrigen->tipo_entrega,
                    'es_grupal'       => $actOrigen->es_grupal,
                    'max_integrantes' => $actOrigen->max_integrantes,
                    'es_plantilla'    => false,
                    'id_componente'   => $compDest->id_componente,
                    'id_unidad'       => $nuevoIdUnidad,
                    'uuid_archivo_enunciado' => $actOrigen->uuid_archivo_enunciado,
                ]);

                // Duplicar rúbricas analíticas en estado POSTULADA
                foreach ($actOrigen->rubricas as $rubrica) {
                    Rubrica::create([
                        'rubrica'        => $rubrica->rubrica,
                        'estado_rubrica' => EstadoRubrica::POSTULADA,
                        'id_actividad'   => $copiaAct->id_actividad,
                    ]);
                }

                if (!$copiaAct->es_grupal) {
                    $gruposService->asegurarGruposDelCurso($cursoDestino, $copiaAct);
                }

                $actividadesDestinoNombres->put($nombreNorm, true);
                $creadas++;
            }
        }

        return $creadas;
    }
}
