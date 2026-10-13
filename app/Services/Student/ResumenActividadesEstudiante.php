<?php

namespace App\Services\Student;

use App\Enums\DB\TipoActividad;
use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Usuario\Estudiante;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bloques de actividades del dashboard del estudiante: lo que está por vencer
 * y las notas/retroalimentaciones recientes.
 *
 * Sólo mira cursos con inscripción `INSCRITO` y actividades visibles, igual
 * que `Student\CourseController::show` y `Student\ActivityController::show`.
 */
class ResumenActividadesEstudiante
{
    /** Ventana de «próximas a vencer». No se muestra en la UI. */
    public const DIAS_PROXIMAS = 7;

    /** Cuántas notas/retroalimentaciones se listan. */
    public const MAX_RECIENTES = 5;

    /**
     * Actividades cuyo plazo real (fecha límite + holgura de la actividad +
     * holgura personal del grupo) cae entre ahora y los próximos 7 días.
     *
     * `plazo_hasta` sólo viene cuando hay holgura: es la fecha que el alumno
     * tiene que mirar, ya calculada, en vez de la fecha límite nominal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function proximasAVencer(Estudiante $estudiante, ?int $semestre = null, ?int $agno = null): array
    {
        $componentes = $this->componentesDelEstudiante($estudiante, $semestre, $agno);

        if ($componentes->isEmpty()) {
            return [];
        }

        $ahora = Carbon::now();
        $tope = $ahora->copy()->addDays(self::DIAS_PROXIMAS)->endOfDay();

        // El SQL sólo descarta lo que vence después de la ventana; lo que ya
        // venció se filtra abajo, porque la holgura puede extenderlo.
        $actividades = Actividad::whereIn('id_componente', $componentes->keys())
            ->where('visible', true)
            ->whereNotNull('fecha_limite')
            ->whereDate('fecha_limite', '<=', $tope->toDateString())
            ->get();

        if ($actividades->isEmpty()) {
            return [];
        }

        $grupos = $this->gruposDelEstudiante($estudiante, $actividades->pluck('id_actividad'));

        return $actividades
            ->filter(function (Actividad $actividad) use ($grupos) {
                $grupo = $grupos->get($actividad->id_actividad);

                // 1. Descartar actividades sin entrega obligatoria
                if (strtolower($actividad->tipo_entrega ?? '') === 'sin entrega') {
                    return false;
                }

                // 2. Descartar si el grupo ya completó una entrega vigente o ya fue evaluado
                if ($grupo && ($grupo->tieneEntregaVigente() || $grupo->yaFueEvaluado())) {
                    return false;
                }

                return true;
            })
            ->map(function (Actividad $actividad) use ($grupos, $componentes) {
                $grupo = $grupos->get($actividad->id_actividad);
                $holgura = (int) ($actividad->nro_dias_adicionales_para_bloqueo ?? 0)
                    + (int) ($grupo?->nro_dias_adicionales_para_bloqueo_personal ?? 0);
                $plazo = Carbon::parse($actividad->fecha_limite)->endOfDay()->addDays($holgura);
                $curso = $componentes->get($actividad->id_componente);

                $esVencida = $plazo->isPast();

                return [
                    'id_actividad' => $actividad->id_actividad,
                    'id_curso'     => (int) $curso->id_curso,
                    'curso'        => $curso->cod_asignatura ?: $curso->cod_curso,
                    'nombre'       => $actividad->nombre,
                    'es_sumativa'  => $actividad->tipo_actividad === TipoActividad::SUMATIVA,
                    'fecha_limite' => $actividad->fecha_limite->format('Y-m-d'),
                    'plazo_hasta'  => $holgura > 0 ? $plazo->format('Y-m-d') : null,
                    'es_vencida'   => $esVencida,
                    '_plazo'       => $plazo,
                ];
            })
            ->filter(function (array $item) use ($ahora, $tope) {
                // Incluye próximas a vencer o vencidas no entregadas recientes (hasta 14 días atrás)
                return $item['_plazo']->between($ahora, $tope)
                    || ($item['es_vencida'] && $item['_plazo']->greaterThanOrEqualTo($ahora->copy()->subDays(14)));
            })
            ->sortBy(function (array $item) {
                return ($item['es_vencida'] ? '0' : '1') . '_' . $item['_plazo']->timestamp;
            })
            ->map(fn (array $item) => Arr::except($item, '_plazo'))
            ->values()
            ->all();
    }

    /**
     * Últimas notas y retroalimentaciones que el equipo docente dejó en las
     * actividades del alumno, una por actividad (la más reciente).
     *
     * - Evaluación de una sumativa → la nota vigente del alumno (individual si
     *   tiene décimas, si no la grupal).
     * - Evaluación de una formativa → el resultado cualitativo.
     * - Feedback con texto → el comentario.
     *
     * @return array<int, array<string, mixed>>
     */
    public function notasYRetroalimentaciones(Estudiante $estudiante): array
    {
        $componentes = $this->componentesDelEstudiante($estudiante);

        if ($componentes->isEmpty()) {
            return [];
        }

        $integraciones = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
            ->whereHas('actividadAsignadaGrupo.actividad', fn ($q) => $q
                ->whereIn('id_componente', $componentes->keys())
                ->where('visible', true))
            ->get()
            ->keyBy('id_actividad_asignada_grupo');

        if ($integraciones->isEmpty()) {
            return [];
        }

        $agendas = Agenda::whereIn('id_actividad_asignada_grupo', $integraciones->keys())
            ->whereIn('tipo_mensaje', [TipoMensaje::EVALUACIÓN->value, TipoMensaje::FEEDBACK->value])
            ->where('id_usuario_emisor', '!=', $estudiante->id_usuario)
            ->with(['evaluacion', 'actividadAsignadaGrupo.actividad'])
            ->orderByDesc('fecha_envio')
            ->orderByDesc('id_agenda')
            ->get()
            // Un feedback vacío no le dice nada al alumno.
            ->filter(fn (Agenda $a) => $a->tipo_mensaje === TipoMensaje::EVALUACIÓN
                || trim((string) $a->mensaje) !== '')
            ->unique(fn (Agenda $a) => $a->actividadAsignadaGrupo->id_actividad)
            ->take(self::MAX_RECIENTES);

        return $agendas
            ->map(function (Agenda $agenda) use ($integraciones, $componentes) {
                $grupo = $agenda->actividadAsignadaGrupo;
                $actividad = $grupo->actividad;
                $esSumativa = $actividad->tipo_actividad === TipoActividad::SUMATIVA;
                $esEvaluacion = $agenda->tipo_mensaje === TipoMensaje::EVALUACIÓN;
                $integrante = $integraciones->get($grupo->id_actividad_asignada_grupo);
                $nota = $integrante?->nota_individual ?? $grupo->nota;
                $curso = $componentes->get($actividad->id_componente);
                $comentario = trim((string) $agenda->mensaje);

                return [
                    'id_agenda'    => $agenda->id_agenda,
                    'id_actividad' => $actividad->id_actividad,
                    'id_curso'     => (int) $curso->id_curso,
                    'curso'        => $curso->cod_asignatura ?: $curso->cod_curso,
                    'nombre'       => $actividad->nombre,
                    'es_sumativa'  => $esSumativa,
                    'tipo'         => $esEvaluacion ? 'evaluacion' : 'retroalimentacion',
                    'nota'         => $esEvaluacion && $esSumativa && $nota !== null ? (float) $nota : null,
                    'resultado'    => $esEvaluacion && !$esSumativa
                        ? ($agenda->evaluacion?->evaluacion_obtenida ?: null)
                        : null,
                    'comentario'   => $comentario !== '' ? Str::limit($comentario, 140) : null,
                    'fecha'        => (string) $agenda->fecha_envio,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Componentes del alumno en sus cursos inscritos, con los datos del curso
     * para rotular cada ítem. Si en un curso todavía no hay inscripción por
     * componente, se toman todos sus componentes (mismo criterio que
     * `Student\CourseController::show`).
     *
     * @return Collection<int, object> id_componente => {id_curso, cod_curso, cod_asignatura}
     */
    private function componentesDelEstudiante(Estudiante $estudiante, ?int $semestre = null, ?int $agno = null): Collection
    {
        $queryCursos = DB::table('curso.inscripcion_curso as ic')
            ->join('curso.curso as c', 'c.id_curso', '=', 'ic.id_curso')
            ->leftJoin('administrativo.asignacion_plan as ap', 'ap.id_asignacion_plan', '=', 'c.id_asignacion_plan')
            ->leftJoin('administrativo.asignatura as a', 'a.id_asignatura', '=', 'ap.id_asignatura')
            ->where('ic.id_estudiante', $estudiante->id_estudiante)
            ->where('ic.estado_inscripcion', 'INSCRITO');

        if ($semestre !== null) {
            $queryCursos->where('c.semestre_real', $semestre);
        }
        if ($agno !== null) {
            $queryCursos->where('c.agno_real', $agno);
        }

        $cursos = $queryCursos->get(['c.id_curso', 'c.cod_curso', 'a.cod_asignatura'])
            ->keyBy('id_curso');

        if ($cursos->isEmpty()) {
            return collect();
        }

        $componentes = DB::table('curso.componente')
            ->whereIn('id_curso', $cursos->keys())
            ->get(['id_componente', 'id_curso']);

        $inscritos = DB::table('curso.inscripcion_componente')
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->whereIn('id_componente', $componentes->pluck('id_componente'))
            ->pluck('id_componente')
            ->all();

        $cursosConInscripcion = $componentes
            ->whereIn('id_componente', $inscritos)
            ->pluck('id_curso')
            ->unique()
            ->all();

        return $componentes
            ->filter(fn ($c) => in_array($c->id_componente, $inscritos)
                || !in_array($c->id_curso, $cursosConInscripcion))
            ->mapWithKeys(fn ($c) => [(int) $c->id_componente => $cursos->get($c->id_curso)]);
    }

    /**
     * @param  Collection<int, int>  $idsActividad
     * @return Collection<int, ActividadAsignadaGrupo> keyed por id_actividad
     */
    private function gruposDelEstudiante(Estudiante $estudiante, Collection $idsActividad): Collection
    {
        return ActividadAsignadaGrupo::whereIn('id_actividad', $idsActividad)
            ->whereHas('miembros', fn ($q) => $q->where('id_estudiante', $estudiante->id_estudiante))
            ->get()
            ->keyBy('id_actividad');
    }
}
