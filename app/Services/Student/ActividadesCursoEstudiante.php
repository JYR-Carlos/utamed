<?php

namespace App\Services\Student;

use App\Enums\DB\TipoActividad;
use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\Evaluacion;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Usuario\Estudiante;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Servicio de Dominio para la consulta de actividades evaluativas, rúbricas
 * y descarga segura de enunciados para estudiantes en la API móvil.
 */
class ActividadesCursoEstudiante
{
    /**
     * Valida pertenencia de la actividad al curso y visibilidad para el estudiante.
     */
    public function validarAccesoActividad(Curso $curso, Actividad $actividad): void
    {
        $actividad->loadMissing('componente');

        if ((int) $actividad->componente?->id_curso !== (int) $curso->id_curso) {
            abort(404, 'Actividad no encontrada en este curso.');
        }

        if (! $actividad->visible) {
            abort(403, 'Esta actividad no está disponible.');
        }
    }

    /**
     * Resuelve el listado de actividades del curso para el estudiante,
     * filtrado por componentes y con estados calculados.
     *
     * @return array<string, mixed>
     */
    public function listarActividades(Curso $curso, Estudiante $estudiante): array
    {
        // 1. Obtener componentes asignados o fallback a todos los del curso
        $componentes = $this->componentesRelevantes($curso, $estudiante);
        $idsComponentes = $componentes->pluck('id_componente')->all();

        if (empty($idsComponentes)) {
            return [
                'curso' => [
                    'id_curso' => (int) $curso->id_curso,
                    'nombre' => $curso->nombre,
                    'cod_curso' => $curso->cod_curso,
                ],
                'totales' => [
                    'total' => 0,
                    'sumativas' => 0,
                    'formativas' => 0,
                    'pendientes_entrega' => 0,
                ],
                'actividades' => [],
            ];
        }

        // 2. Consultar actividades visibles de los componentes
        $actividades = Actividad::whereIn('id_componente', $idsComponentes)
            ->where('visible', true)
            ->with(['componente.tipoComponente', 'unidad', 'rubricas'])
            ->orderBy('fecha_limite', 'asc')
            ->orderBy('id_actividad', 'asc')
            ->get();

        if ($actividades->isEmpty()) {
            return [
                'curso' => [
                    'id_curso' => (int) $curso->id_curso,
                    'nombre' => $curso->nombre,
                    'cod_curso' => $curso->cod_curso,
                ],
                'totales' => [
                    'total' => 0,
                    'sumativas' => 0,
                    'formativas' => 0,
                    'pendientes_entrega' => 0,
                ],
                'actividades' => [],
            ];
        }

        // 3. Consultar grupos del estudiante para estas actividades
        $idsActividades = $actividades->pluck('id_actividad');
        $grupos = ActividadAsignadaGrupo::whereIn('id_actividad', $idsActividades)
            ->whereHas('miembros', fn ($q) => $q->where('id_estudiante', $estudiante->id_estudiante))
            ->get()
            ->keyBy('id_actividad');

        $idsGrupos = $grupos->pluck('id_actividad_asignada_grupo')->filter();

        // 4. Último envío de entrega vigente por grupo
        $ultimosEnvios = $idsGrupos->isEmpty()
            ? collect()
            : Agenda::entregasVigentes()
                ->whereIn('id_actividad_asignada_grupo', $idsGrupos)
                ->select('id_actividad_asignada_grupo', DB::raw('MAX(fecha_envio) as max_fecha_envio'))
                ->groupBy('id_actividad_asignada_grupo')
                ->pluck('max_fecha_envio', 'id_actividad_asignada_grupo');

        // 5. Grupos evaluados
        $evaluados = $idsGrupos->isEmpty()
            ? collect()
            : IntegranteGrupo::whereIn('id_actividad_asignada_grupo', $idsGrupos)
                ->whereNotNull('nota_individual')
                ->pluck('id_actividad_asignada_grupo')
                ->merge(Agenda::whereIn('id_actividad_asignada_grupo', $idsGrupos)
                    ->whereHas('evaluacion')
                    ->pluck('id_actividad_asignada_grupo'))
                ->unique()
                ->flip();

        $totalSumativas = 0;
        $totalFormativas = 0;
        $totalPendientes = 0;

        $items = $actividades->map(function (Actividad $act) use ($grupos, $ultimosEnvios, $evaluados, &$totalSumativas, &$totalFormativas, &$totalPendientes) {
            $esSumativa = $act->tipo_actividad === TipoActividad::SUMATIVA;
            if ($esSumativa) {
                $totalSumativas++;
            } else {
                $totalFormativas++;
            }

            $conEntrega = strtolower((string) $act->tipo_entrega) !== 'sin entrega' && (string) $act->tipo_entrega !== 'SIN_ENTREGA';

            $grupo = $grupos->get($act->id_actividad);
            $fechaEnvio = $grupo ? $ultimosEnvios->get($grupo->id_actividad_asignada_grupo) : null;
            $yaEntregada = $fechaEnvio !== null;
            $yaEvaluada = $grupo !== null && ($grupo->nota !== null || $evaluados->has($grupo->id_actividad_asignada_grupo));

            // Estado de la actividad para el grupo o base
            $estado = $grupo ? $grupo->calcularEstadoGrupo($act) : $act->calcularEstadoBase();

            // Cálculo pendiente de entrega
            if ($conEntrega && $estado !== 'CERRADA' && ! $yaEntregada && ! $yaEvaluada) {
                $totalPendientes++;
            }

            $tieneRubrica = $act->rubricas->isNotEmpty();
            $tieneEnunciado = $act->uuid_archivo_enunciado !== null;

            return [
                'id_actividad' => (int) $act->id_actividad,
                'nombre' => $act->nombre,
                'descripcion' => $act->descripcion ?? '',
                'tipo_actividad' => $act->tipo_actividad instanceof TipoActividad ? $act->tipo_actividad->value : (string) $act->tipo_actividad,
                'es_sumativa' => $esSumativa,
                'tipo_entrega' => (string) $act->tipo_entrega,
                'con_entrega' => $conEntrega,
                'es_grupal' => (bool) $act->es_grupal,
                'max_integrantes' => $act->max_integrantes ? (int) $act->max_integrantes : 1,
                'ponderacion' => $act->ponderacion !== null ? (float) $act->ponderacion : null,
                'exigencia' => $act->exigencia !== null ? (float) $act->exigencia : null,
                'fecha_limite' => $act->fecha_limite?->format('Y-m-d'),
                'estado' => $estado,
                'componente' => [
                    'id_componente' => (int) $act->id_componente,
                    'tipo' => $act->componente?->tipoComponente?->tipo ?? 'Componente',
                ],
                'unidad' => $act->unidad ? [
                    'id_unidad' => (int) $act->unidad->id_unidad,
                    'nombre' => $act->unidad->nombre,
                ] : null,
                'tiene_enunciado' => $tieneEnunciado,
                'tiene_rubrica' => $tieneRubrica,
                'grupo' => $grupo ? [
                    'id_actividad_asignada_grupo' => (int) $grupo->id_actividad_asignada_grupo,
                    'nombre_grupo' => $grupo->nombre_grupo,
                    'estado' => $grupo->estado instanceof \BackedEnum ? $grupo->estado->value : (string) $grupo->estado,
                    'ya_entregada' => $yaEntregada,
                    'fecha_entrega' => $fechaEnvio ? Carbon::parse($fechaEnvio)->format('Y-m-d') : null,
                    'ya_evaluada' => $yaEvaluada,
                ] : null,
            ];
        })->values()->all();

        return [
            'curso' => [
                'id_curso' => (int) $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
            ],
            'totales' => [
                'total' => count($items),
                'sumativas' => $totalSumativas,
                'formativas' => $totalFormativas,
                'pendientes_entrega' => $totalPendientes,
            ],
            'actividades' => $items,
        ];
    }

    /**
     * Obtiene el detalle completo de la actividad: datos, equipo docente,
     * integrantes del grupo, holguras calculadas, rúbrica asignada y archivo enunciado.
     *
     * @return array<string, mixed>
     */
    public function obtenerDetalle(Curso $curso, Actividad $actividad, Estudiante $estudiante): array
    {
        $this->validarAccesoActividad($curso, $actividad);

        $actividad->loadMissing([
            'componente.tipoComponente',
            'componente.docenteComponentes.docente.usuario',
            'unidad',
            'archivo',
        ]);

        // 1. Docentes del componente
        $equipoDocente = ($actividad->componente?->docenteComponentes ?? collect())
            ->filter(fn ($dc) => $dc->docente?->usuario !== null)
            ->sortBy(fn ($dc) => ! $dc->es_titular)
            ->map(fn ($dc) => [
                'nombre' => trim("{$dc->docente->usuario->nombre1} {$dc->docente->usuario->apellido1}"),
                'es_titular' => (bool) $dc->es_titular,
            ])
            ->values()
            ->all();

        // 2. Grupo asignado e integrantes
        $integranteGrupo = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
            ->whereHas('actividadAsignadaGrupo', fn ($q) => $q->where('id_actividad', $actividad->id_actividad))
            ->with(['actividadAsignadaGrupo.miembros.estudiante.usuario'])
            ->first();

        $grupo = $integranteGrupo?->actividadAsignadaGrupo;

        $datosGrupo = null;
        $diasHolguraPersonal = 0;

        if ($grupo) {
            $diasHolguraPersonal = (int) ($grupo->nro_dias_adicionales_para_bloqueo_personal ?? 0);
            $integrantes = $grupo->miembros->map(function (IntegranteGrupo $miembro) use ($estudiante) {
                $u = $miembro->estudiante?->usuario;
                $esActual = (int) $miembro->id_estudiante === (int) $estudiante->id_estudiante;

                return [
                    'id_estudiante' => (int) $miembro->id_estudiante,
                    'nombre_completo' => $u ? $u->nombre_completo : '',
                    'rut' => $u?->rut,
                    'email' => $u?->email,
                    'es_usuario_actual' => $esActual,
                ];
            })
            ->sortBy(fn ($i) => [! $i['es_usuario_actual'], $i['nombre_completo']])
            ->values()
            ->all();

            $datosGrupo = [
                'id_actividad_asignada_grupo' => (int) $grupo->id_actividad_asignada_grupo,
                'nombre_grupo' => $grupo->nombre_grupo,
                'estado' => $grupo->estado instanceof \BackedEnum ? $grupo->estado->value : (string) $grupo->estado,
                'es_individual' => ! (bool) $actividad->es_grupal,
                'integrantes' => $integrantes,
            ];
        }

        // 3. Fechas y holguras
        $diasHolguraBase = (int) ($actividad->nro_dias_adicionales_para_bloqueo ?? 0);
        $holguraTotal = $diasHolguraBase + $diasHolguraPersonal;
        $plazoFinalConHolgura = null;
        if ($actividad->fecha_limite) {
            $plazoFinalConHolgura = Carbon::parse($actividad->fecha_limite)
                ->addDays($holguraTotal)
                ->format('Y-m-d');
        }

        $estado = $grupo ? $grupo->calcularEstadoGrupo($actividad) : $actividad->calcularEstadoBase();

        // 4. Rúbrica oficial asignada
        $rubricaModel = Rubrica::where('id_actividad', $actividad->id_actividad)
            ->orderByDesc('id_rubrica')
            ->first();

        $datosRubrica = null;
        if ($rubricaModel) {
            $estadoRubrica = $rubricaModel->estado_rubrica instanceof \BackedEnum
                ? $rubricaModel->estado_rubrica->value
                : (string) $rubricaModel->estado_rubrica;

            $datosRubrica = [
                'id_rubrica' => (int) $rubricaModel->id_rubrica,
                'estado_rubrica' => $estadoRubrica,
                'datos' => $rubricaModel->rubrica,
            ];
        }

        // 5. Archivo Enunciado
        $archivoEnunciado = null;
        if ($actividad->uuid_archivo_enunciado && $actividad->archivo) {
            $archivoEnunciado = [
                'uuid_archivo' => (string) $actividad->archivo->uuid_archivo,
                'nombre_original' => $actividad->archivo->nombre_original,
                'mime_type' => $actividad->archivo->mime_type,
                'peso_bytes' => $actividad->archivo->peso_bytes ? (int) $actividad->archivo->peso_bytes : null,
                'url_descarga' => route('api.v1.estudiante.cursos.actividades.enunciado', [
                    'curso' => $curso->id_curso,
                    'actividad' => $actividad->id_actividad,
                ]),
            ];
        }

        $esSumativa = $actividad->tipo_actividad === TipoActividad::SUMATIVA;
        $entregaObligatoria = strtolower((string) $actividad->tipo_entrega) !== 'sin entrega' && (string) $actividad->tipo_entrega !== 'SIN_ENTREGA';

        return [
            'actividad' => [
                'id_actividad' => (int) $actividad->id_actividad,
                'nombre' => $actividad->nombre,
                'descripcion' => $actividad->descripcion ?? '',
                'tipo_actividad' => $actividad->tipo_actividad instanceof TipoActividad ? $actividad->tipo_actividad->value : (string) $actividad->tipo_actividad,
                'es_sumativa' => $esSumativa,
                'tipo_entrega' => (string) $actividad->tipo_entrega,
                'entrega_obligatoria' => $entregaObligatoria,
                'es_grupal' => (bool) $actividad->es_grupal,
                'max_integrantes' => $actividad->max_integrantes ? (int) $actividad->max_integrantes : 1,
                'ponderacion' => $actividad->ponderacion !== null ? (float) $actividad->ponderacion : null,
                'exigencia' => $actividad->exigencia !== null ? (float) $actividad->exigencia : null,
                'fecha_limite' => $actividad->fecha_limite?->format('Y-m-d'),
                'dias_holgura_base' => $diasHolguraBase,
                'dias_holgura_personal' => $diasHolguraPersonal,
                'plazo_final_con_holgura' => $plazoFinalConHolgura,
                'estado' => $estado,
                'componente' => [
                    'id_componente' => (int) $actividad->id_componente,
                    'tipo' => $actividad->componente?->tipoComponente?->tipo ?? 'Componente',
                ],
                'unidad' => $actividad->unidad ? [
                    'id_unidad' => (int) $actividad->unidad->id_unidad,
                    'nombre' => $actividad->unidad->nombre,
                ] : null,
                'equipo_docente' => $equipoDocente,
            ],
            'grupo' => $datosGrupo,
            'rubrica' => $datosRubrica,
            'archivo_enunciado' => $archivoEnunciado,
        ];
    }

    /**
     * Valida y sirve la descarga segura binaria del archivo de enunciado oficial.
     */
    public function descargarEnunciado(Curso $curso, Actividad $actividad, bool $enLinea = false): BinaryFileResponse
    {
        $this->validarAccesoActividad($curso, $actividad);

        $actividad->loadMissing('archivo');

        if (! $actividad->uuid_archivo_enunciado || ! $actividad->archivo) {
            abort(404, 'Esta actividad no tiene enunciado asociado.');
        }

        $disk = config('files.storage.disk', 'local_archives');
        $rutaFisica = $actividad->archivo->ruta_fisica;

        if (! Storage::disk($disk)->exists($rutaFisica)) {
            abort(404, 'El archivo de enunciado no existe en el servidor.');
        }

        $rutaAbsoluta = Storage::disk($disk)->path($rutaFisica);

        if ($enLinea && $actividad->archivo->esVisualizableEnNavegador()) {
            return response()->file($rutaAbsoluta, [
                'Content-Type' => $actividad->archivo->mime_type,
                'Content-Disposition' => 'inline; filename="' . $actividad->archivo->nombre_original . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return $actividad->archivo->respuestaHttp($rutaAbsoluta, $enLinea);
    }

    /**
     * Consulta las calificaciones, ajuste por décimas, feedback docente,
     * rúbrica evaluada y entrega vinculada de una actividad calificada.
     *
     * @return array<string, mixed>
     */
    public function obtenerEvaluacion(Curso $curso, Actividad $actividad, Estudiante $estudiante): array
    {
        $this->validarAccesoActividad($curso, $actividad);

        // 1. Obtener el grupo asignado al estudiante
        $integranteGrupo = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
            ->whereHas('actividadAsignadaGrupo', fn ($q) => $q->where('id_actividad', $actividad->id_actividad))
            ->with(['actividadAsignadaGrupo'])
            ->first();

        if (! $integranteGrupo || ! $integranteGrupo->actividadAsignadaGrupo) {
            abort(404, 'No tienes un grupo asignado para esta actividad.');
        }

        $grupo = $integranteGrupo->actividadAsignadaGrupo;

        // 2. Buscar mensajes de evaluación en agenda para este grupo
        $agendaEvaluacion = Agenda::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
            ->where('tipo_mensaje', TipoMensaje::EVALUACIÓN)
            ->whereHas('evaluacion')
            ->with(['evaluacion.rubrica', 'evaluacion.usuario', 'archivo'])
            ->orderByDesc('id_agenda')
            ->first();

        if (! $agendaEvaluacion || ! $agendaEvaluacion->evaluacion) {
            abort(404, 'Esta actividad aún no ha sido evaluada.');
        }

        $evaluacion = $agendaEvaluacion->evaluacion;

        // 3. Calificaciones y décimas
        $notaGrupal = $grupo->nota !== null ? (float) $grupo->nota : null;
        $diferenciaDecimas = (float) ($integranteGrupo->diferencia_decimas ?? 0.0);

        $notaIndividual = null;
        $esAprobatoria = true;
        $esSumativa = $actividad->tipo_actividad === TipoActividad::SUMATIVA;

        if ($esSumativa && $notaGrupal !== null) {
            $notaCalc = round(max(1.0, min(7.0, $notaGrupal + $diferenciaDecimas)), 1);
            $notaIndividual = $integranteGrupo->nota_individual !== null
                ? (float) $integranteGrupo->nota_individual
                : $notaCalc;
            $esAprobatoria = $notaIndividual >= 4.0;
        }

        $evaluacionCualitativa = ! $esSumativa ? $evaluacion->evaluacion_obtenida : null;
        if (! $esSumativa && $evaluacionCualitativa !== null) {
            $esAprobatoria = strtolower($evaluacionCualitativa) !== 'malo' && strtolower($evaluacionCualitativa) !== 'insuficiente';
        }

        // 4. Feedback docente
        $docenteEvaluador = null;
        if ($evaluacion->usuario) {
            $docenteEvaluador = trim("{$evaluacion->usuario->nombre1} {$evaluacion->usuario->apellido1}");
        }

        $fechaEvaluacion = $evaluacion->fecha_evaluacion
            ? Carbon::parse($evaluacion->fecha_evaluacion)->format('Y-m-d H:i:s')
            : ($agendaEvaluacion->fecha_envio ? Carbon::parse($agendaEvaluacion->fecha_envio)->format('Y-m-d H:i:s') : null);

        // 5. Desglose de Rúbrica
        $desgloseRubrica = $this->desglosarRubricaEvaluada(
            $evaluacion->rubrica,
            $evaluacion->resultado,
            $evaluacion->puntaje_obtenido !== null ? (float) $evaluacion->puntaje_obtenido : null
        );

        // 6. Entrega evaluada asociada
        $entregaEvaluada = null;
        if ($agendaEvaluacion->uuid_archivo_subido && $agendaEvaluacion->archivo) {
            $archivoEntrega = $agendaEvaluacion->archivo;
            $entregaEvaluada = [
                'id_agenda' => (int) $agendaEvaluacion->id_agenda,
                'uuid_archivo' => (string) $archivoEntrega->uuid_archivo,
                'nombre_original' => $archivoEntrega->nombre_original,
                'mime_type' => $archivoEntrega->mime_type,
                'peso_bytes' => $archivoEntrega->peso_bytes ? (int) $archivoEntrega->peso_bytes : null,
                'fecha_envio' => $agendaEvaluacion->fecha_envio ? Carbon::parse($agendaEvaluacion->fecha_envio)->format('Y-m-d H:i:s') : null,
                'url_descarga' => route('api.v1.estudiante.cursos.actividades.entregas.descargar', [
                    'curso' => $curso->id_curso,
                    'actividad' => $actividad->id_actividad,
                    'agenda' => $agendaEvaluacion->id_agenda,
                ]),
            ];
        }

        $puntajeObtenido = $evaluacion->puntaje_obtenido !== null ? (float) $evaluacion->puntaje_obtenido : null;
        $puntajeMaximo = $desgloseRubrica ? $desgloseRubrica['puntaje_maximo'] : null;
        $porcentajeLogro = $desgloseRubrica ? $desgloseRubrica['porcentaje_logro'] : null;

        return [
            'curso' => [
                'id_curso' => (int) $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
            ],
            'actividad' => [
                'id_actividad' => (int) $actividad->id_actividad,
                'nombre' => $actividad->nombre,
                'tipo_actividad' => $actividad->tipo_actividad instanceof TipoActividad ? $actividad->tipo_actividad->value : (string) $actividad->tipo_actividad,
                'es_sumativa' => $esSumativa,
                'es_grupal' => (bool) $actividad->es_grupal,
                'ponderacion' => $actividad->ponderacion !== null ? (float) $actividad->ponderacion : null,
                'exigencia' => $actividad->exigencia !== null ? (float) $actividad->exigencia : null,
            ],
            'grupo' => [
                'id_actividad_asignada_grupo' => (int) $grupo->id_actividad_asignada_grupo,
                'nombre_grupo' => $grupo->nombre_grupo,
                'es_individual' => ! (bool) $actividad->es_grupal,
            ],
            'calificacion' => [
                'nota_grupal' => $notaGrupal,
                'diferencia_decimas' => $diferenciaDecimas,
                'nota_individual' => $notaIndividual,
                'es_aprobatoria' => $esAprobatoria,
                'evaluacion_cualitativa' => $evaluacionCualitativa,
                'puntaje_obtenido' => $puntajeObtenido,
                'puntaje_maximo' => $puntajeMaximo,
                'porcentaje_logro' => $porcentajeLogro,
            ],
            'feedback' => [
                'id_evaluacion' => (int) $evaluacion->id_evaluacion,
                'fecha_evaluacion' => $fechaEvaluacion,
                'docente_evaluador' => $docenteEvaluador,
                'retroalimentacion' => $agendaEvaluacion->mensaje ?? '',
            ],
            'rubrica' => $desgloseRubrica,
            'entrega_evaluada' => $entregaEvaluada,
        ];
    }

    /**
     * Valida y sirve la descarga segura binaria del archivo entregado y calificado.
     */
    public function descargarEntrega(Curso $curso, Actividad $actividad, Agenda $agenda, Estudiante $estudiante, bool $enLinea = false): BinaryFileResponse
    {
        $this->validarAccesoActividad($curso, $actividad);

        $agenda->loadMissing(['actividadAsignadaGrupo', 'archivo']);

        if ((int) $agenda->actividadAsignadaGrupo?->id_actividad !== (int) $actividad->id_actividad) {
            abort(404, 'Entrega no encontrada en esta actividad.');
        }

        // Validar que el estudiante pertenezca al grupo de la entrega (Anti-IDOR)
        $esMiembro = IntegranteGrupo::where('id_actividad_asignada_grupo', $agenda->id_actividad_asignada_grupo)
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->exists();

        if (! $esMiembro) {
            abort(403, 'Esta entrega no te pertenece.');
        }

        if (! $agenda->uuid_archivo_subido || ! $agenda->archivo) {
            abort(404, 'No hay archivo asociado a esta entrega.');
        }

        $disk = config('files.storage.disk', 'local_archives');
        $rutaFisica = $agenda->archivo->ruta_fisica;

        if (! Storage::disk($disk)->exists($rutaFisica)) {
            abort(404, 'El archivo no existe en el servidor.');
        }

        $rutaAbsoluta = Storage::disk($disk)->path($rutaFisica);

        if ($enLinea && $agenda->archivo->esVisualizableEnNavegador()) {
            return response()->file($rutaAbsoluta, [
                'Content-Type' => $agenda->archivo->mime_type,
                'Content-Disposition' => 'inline; filename="' . $agenda->archivo->nombre_original . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return $agenda->archivo->respuestaHttp($rutaAbsoluta, $enLinea);
    }

    /**
     * Helper para desglosar la matriz de la rúbrica evaluada cruzando la definición con la selección docente.
     *
     * @param  array<string, mixed>|null  $resultado
     * @return array<string, mixed>|null
     */
    private function desglosarRubricaEvaluada(?Rubrica $rubrica, ?array $resultado, ?float $puntajeObtenido): ?array
    {
        if (! $rubrica || empty($rubrica->rubrica)) {
            return null;
        }

        $datos = $rubrica->rubrica;
        $nivelesRaw = $datos['niveles'] ?? [];
        if (empty($nivelesRaw)) {
            return null;
        }

        $criteriosEvaluados = [];
        $puntajeMaximoTotal = 0.0;
        $puntajeObtenidoCalculado = 0.0;

        foreach ($nivelesRaw as $index => $nivel) {
            $idCriterio = (string) ($nivel['id_nivel'] ?? $nivel['id'] ?? "criterio_{$index}");
            $nombre = $nivel['nombre'] ?? '';
            $descripcion = $nivel['descripcion'] ?? '';
            $ponderacion = isset($nivel['ponderacion']) ? (float) $nivel['ponderacion'] : null;

            $escalasRaw = $nivel['escalas'] ?? [];
            $escalasProcesadas = [];
            $escalaSeleccionadaProcesada = null;
            $puntajeMaximoCriterio = 0.0;

            // id de escala seleccionada por el docente para este nivel
            $idEscalaSeleccionada = $resultado[$idCriterio] ?? $resultado[(string) ($index + 1)] ?? null;

            foreach ($escalasRaw as $eIndex => $esc) {
                $idEscala = (string) ($esc['id_escala'] ?? $esc['id'] ?? "escala_{$eIndex}");
                $nombreNivel = $esc['nombre_nivel'] ?? $esc['nombre'] ?? '';
                $puntos = (float) ($esc['puntos'] ?? 0.0);
                $criterioTexto = $esc['criterio'] ?? $esc['descripcion'] ?? '';
                $estaSeleccionada = ($idEscalaSeleccionada !== null && (string) $idEscalaSeleccionada === $idEscala);

                if ($puntos > $puntajeMaximoCriterio) {
                    $puntajeMaximoCriterio = $puntos;
                }

                $escalasProcesadas[] = [
                    'id_escala' => $idEscala,
                    'nombre_nivel' => $nombreNivel,
                    'puntos' => $puntos,
                    'criterio' => $criterioTexto,
                    'seleccionada' => $estaSeleccionada,
                ];

                if ($estaSeleccionada) {
                    $escalaSeleccionadaProcesada = [
                        'id_escala' => $idEscala,
                        'nombre_nivel' => $nombreNivel,
                        'puntos' => $puntos,
                        'criterio' => $criterioTexto,
                    ];
                }
            }

            $puntosObtenidosCriterio = $escalaSeleccionadaProcesada ? $escalaSeleccionadaProcesada['puntos'] : 0.0;
            $puntajeMaximoTotal += $puntajeMaximoCriterio;
            $puntajeObtenidoCalculado += $puntosObtenidosCriterio;

            $criteriosEvaluados[] = [
                'id_criterio' => $idCriterio,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'ponderacion' => $ponderacion,
                'puntaje_maximo' => $puntajeMaximoCriterio,
                'puntaje_obtenido' => $puntosObtenidosCriterio,
                'escala_seleccionada' => $escalaSeleccionadaProcesada,
                'escalas' => $escalasProcesadas,
            ];
        }

        $puntajeFinal = $puntajeObtenido !== null ? $puntajeObtenido : $puntajeObtenidoCalculado;
        $porcentajeLogro = $puntajeMaximoTotal > 0
            ? round(($puntajeFinal / $puntajeMaximoTotal) * 100, 1)
            : 0.0;

        $estadoRubrica = $rubrica->estado_rubrica instanceof \BackedEnum
            ? $rubrica->estado_rubrica->value
            : (string) $rubrica->estado_rubrica;

        return [
            'id_rubrica' => (int) $rubrica->id_rubrica,
            'estado_rubrica' => $estadoRubrica,
            'total_criterios' => count($criteriosEvaluados),
            'puntaje_maximo' => $puntajeMaximoTotal,
            'puntaje_obtenido' => $puntajeFinal,
            'porcentaje_logro' => $porcentajeLogro,
            'criterios_evaluados' => $criteriosEvaluados,
        ];
    }

    /**
     * Componentes relevantes para el alumno en el curso (asignados o todos del curso).
     */
    private function componentesRelevantes(Curso $curso, Estudiante $estudiante): Collection
    {
        $todos = Componente::where('id_curso', $curso->id_curso)
            ->with(['tipoComponente', 'inscripcionComponentes' => fn ($q) => $q->where('id_estudiante', $estudiante->id_estudiante)])
            ->get();

        $propios = $todos->filter(fn ($c) => $c->inscripcionComponentes->isNotEmpty());

        return $propios->isNotEmpty() ? $propios : $todos;
    }
}
