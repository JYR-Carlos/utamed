<?php

namespace App\Services\Student;

use App\Enums\DB\TipoActividad;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Curso;
use App\Models\Usuario\Estudiante;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Actividades del estudiante en todos sus cursos: lo que está por vencer o ya
 * venció sin entrega (Inicio y Mis cursos) y las fechas límite del semestre
 * (vista «Semestre» del calendario de la ficha del curso).
 *
 * Sólo mira cursos con inscripción `INSCRITO` y actividades visibles, igual
 * que `Student\CourseController::show` y `Student\ActivityController::show`.
 */
class ResumenActividadesEstudiante
{
    /** Ventana de «próximas a vencer». No se muestra en la UI. */
    public const DIAS_PROXIMAS = 7;

    /** Sin período, cuántos días atrás se siguen mostrando las vencidas sin entrega. */
    public const DIAS_VENCIDAS_SIN_PERIODO = 14;

    /**
     * Actividades sin entregar cuyo plazo real (fecha límite + holgura de la
     * actividad + holgura personal del grupo) cae entre ahora y los próximos
     * 7 días, más las que ya vencieron sin entrega (`es_vencida`).
     *
     * Con período (semestre y año), las vencidas se muestran todas, es decir,
     * hasta que termina el semestre: el alumno tiene que verlas para escribirle
     * a su docente o apelar. Sin período, sólo las de los últimos 14 días.
     *
     * `plazo_hasta` sólo viene cuando hay holgura: es la fecha que el alumno
     * tiene que mirar, ya calculada, en vez de la fecha límite nominal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function proximasAVencer(Estudiante $estudiante, ?int $semestre = null, ?int $agno = null): array
    {
        $delPeriodo = $semestre !== null && $agno !== null;
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
                    'es_grupal'    => (bool) $actividad->es_grupal,
                    'fecha_limite' => $actividad->fecha_limite->format('Y-m-d'),
                    'plazo_hasta'  => $holgura > 0 ? $plazo->format('Y-m-d') : null,
                    'es_vencida'   => $esVencida,
                    '_plazo'       => $plazo,
                ];
            })
            ->filter(function (array $item) use ($ahora, $tope, $delPeriodo) {
                if ($item['es_vencida']) {
                    return $delPeriodo
                        || $item['_plazo']->greaterThanOrEqualTo($ahora->copy()->subDays(self::DIAS_VENCIDAS_SIN_PERIODO));
                }

                return $item['_plazo']->between($ahora, $tope);
            })
            ->sortBy(function (array $item) {
                return ($item['es_vencida'] ? '0' : '1') . '_' . $item['_plazo']->timestamp;
            })
            ->map(fn (array $item) => Arr::except($item, '_plazo'))
            ->values()
            ->all();
    }

    /**
     * Fechas límite del semestre: las actividades visibles con plazo de todos
     * los cursos inscritos del período, con el mismo formato que la ficha del
     * curso (`paraAlumno`) más el curso al que pertenecen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calendarioDelPeriodo(Estudiante $estudiante, ?int $semestre, ?int $agno): array
    {
        $componentes = $this->componentesDelEstudiante($estudiante, $semestre, $agno);

        if ($componentes->isEmpty()) {
            return [];
        }

        $actividades = Actividad::whereIn('id_componente', $componentes->keys())
            ->where('visible', true)
            ->whereNotNull('fecha_limite')
            ->with('componente.tipoComponente')
            ->orderBy('fecha_limite')
            ->orderBy('id_actividad')
            ->get();

        return $this->paraAlumno($estudiante, $actividades)
            ->map(function (array $item, int $i) use ($actividades, $componentes) {
                $curso = $componentes->get($actividades[$i]->id_componente);

                return [
                    ...$item,
                    'id_curso'   => (int) $curso->id_curso,
                    'curso'      => $curso->cod_asignatura ?: $curso->cod_curso,
                    'asignatura' => $curso->asignatura,
                ];
            })
            ->all();
    }

    /**
     * Las actividades visibles de un curso, ordenadas por plazo, tal como las
     * ve el alumno (ver paraAlumno). Son las de sus componentes; si no figura
     * inscrito en ninguno, las de todo el curso. La usan la ficha del curso y
     * la página de una actividad («Lo que sigue en el curso»).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function delCurso(Estudiante $estudiante, Curso $curso): Collection
    {
        $componenteIds = DB::table('curso.inscripcion_componente as ic')
            ->join('curso.componente as c', 'c.id_componente', '=', 'ic.id_componente')
            ->where('ic.id_estudiante', $estudiante->id_estudiante)
            ->where('c.id_curso', $curso->id_curso)
            ->pluck('ic.id_componente');

        if ($componenteIds->isEmpty()) {
            $componenteIds = DB::table('curso.componente')
                ->where('id_curso', $curso->id_curso)
                ->pluck('id_componente');
        }

        $actividades = Actividad::whereIn('id_componente', $componenteIds)
            ->where('visible', true)
            ->with(['componente.tipoComponente', 'unidad'])
            ->orderBy('fecha_limite', 'asc')
            ->orderBy('id_actividad')
            ->get();

        return $this->paraAlumno($estudiante, $actividades);
    }

    /**
     * Las actividades tal como las ve el alumno: tipo, plazo y el estado de su
     * grupo (entregada, evaluada). Lo comparten la ficha del curso y el
     * calendario del semestre.
     *
     * Entregas y evaluaciones se consultan en lote, no por actividad.
     *
     * @param  Collection<int, Actividad>  $actividades  con `componente.tipoComponente` cargado
     * @return Collection<int, array<string, mixed>> en el mismo orden que `$actividades`
     */
    public function paraAlumno(Estudiante $estudiante, Collection $actividades): Collection
    {
        $grupos = $this->gruposDelEstudiante($estudiante, $actividades->pluck('id_actividad'));
        $idsGrupos = $grupos->pluck('id_actividad_asignada_grupo');

        // Último envío vigente de cada grupo.
        $ultimoEnvio = $idsGrupos->isEmpty()
            ? collect()
            : Agenda::entregasVigentes()
                ->whereIn('id_actividad_asignada_grupo', $idsGrupos)
                ->select('id_actividad_asignada_grupo', DB::raw('MAX(fecha_envio) as max_fecha_envio'))
                ->groupBy('id_actividad_asignada_grupo')
                ->pluck('max_fecha_envio', 'id_actividad_asignada_grupo');

        // Grupos evaluados por nota individual o por evaluación en la agenda;
        // la nota grupal se mira en cada grupo (mismo criterio que yaFueEvaluado).
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

        // La rúbrica es cosa de las sumativas (la misma regla de la página de la actividad).
        $conRubrica = Rubrica::whereIn('id_actividad', $actividades->pluck('id_actividad'))
            ->distinct()
            ->pluck('id_actividad')
            ->flip();

        return $actividades
            ->map(function (Actividad $actividad) use ($grupos, $ultimoEnvio, $evaluados, $conRubrica) {
                $grupo = $grupos->get($actividad->id_actividad);
                $fechaEnvio = $grupo ? $ultimoEnvio->get($grupo->id_actividad_asignada_grupo) : null;

                return [
                    'id_actividad'    => $actividad->id_actividad,
                    'nombre'          => $actividad->nombre,
                    'es_sumativa'     => $actividad->tipo_actividad === TipoActividad::SUMATIVA,
                    // Enciende el ícono que abre la rúbrica desde la lista.
                    'con_rubrica'     => $actividad->tipo_actividad === TipoActividad::SUMATIVA
                        && $conRubrica->has($actividad->id_actividad),
                    'id_componente'   => $actividad->id_componente,
                    // «Cómo se evalúa» cuenta las actividades de cada componente.
                    'componente_tipo' => $actividad->componente?->tipoComponente?->tipo,
                    'con_entrega'     => $actividad->tipo_entrega !== 'SIN_ENTREGA',
                    'es_grupal'       => (bool) $actividad->es_grupal,
                    'max_integrantes' => $actividad->max_integrantes ?? 1,
                    'fecha_limite'    => $actividad->fecha_limite?->format('Y-m-d'),
                    'visible'         => (bool) $actividad->visible,
                    'estado'          => $grupo ? $grupo->calcularEstadoGrupo($actividad) : $actividad->calcularEstadoBase(),
                    'ya_entregada'    => $fechaEnvio !== null,
                    // Día del último envío vigente; la tarjeta dice «Entregada el …».
                    'fecha_entrega'   => $fechaEnvio ? Carbon::parse($fechaEnvio)->format('Y-m-d') : null,
                    'ya_evaluada'     => $grupo !== null
                        && ($grupo->nota !== null || $evaluados->has($grupo->id_actividad_asignada_grupo)),
                ];
            })
            ->values();
    }

    /**
     * Componentes del alumno en sus cursos inscritos, con los datos del curso
     * para rotular cada ítem. Si en un curso todavía no hay inscripción por
     * componente, se toman todos sus componentes (mismo criterio que
     * `Student\CourseController::show`).
     *
     * @return Collection<int, object> id_componente => {id_curso, cod_curso, cod_asignatura, asignatura}
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

        $cursos = $queryCursos->get(['c.id_curso', 'c.cod_curso', 'a.cod_asignatura', 'a.nombre as asignatura'])
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
     * Grupos a los que pertenece el alumno en esas actividades.
     *
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
