<?php

namespace App\Services\Student;

use App\Enums\DB\TipoActividad;
use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Usuario\Estudiante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bloques de actividades del dashboard del estudiante: las notas y
 * retroalimentaciones recientes.
 *
 * Sólo mira cursos con inscripción `INSCRITO` y actividades visibles, igual
 * que `Student\CourseController::show` y `Student\ActivityController::show`.
 */
class ResumenActividadesEstudiante
{
    /** Cuántas notas/retroalimentaciones se listan. */
    public const MAX_RECIENTES = 5;

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
    private function componentesDelEstudiante(Estudiante $estudiante): Collection
    {
        $cursos = DB::table('curso.inscripcion_curso as ic')
            ->join('curso.curso as c', 'c.id_curso', '=', 'ic.id_curso')
            ->leftJoin('administrativo.asignacion_plan as ap', 'ap.id_asignacion_plan', '=', 'c.id_asignacion_plan')
            ->leftJoin('administrativo.asignatura as a', 'a.id_asignatura', '=', 'ap.id_asignatura')
            ->where('ic.id_estudiante', $estudiante->id_estudiante)
            ->where('ic.estado_inscripcion', 'INSCRITO')
            ->get(['c.id_curso', 'c.cod_curso', 'a.cod_asignatura'])
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
}
