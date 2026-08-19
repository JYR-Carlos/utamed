<?php

namespace App\Services\Agenda;

use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use Illuminate\Support\Collection;

/**
 * Las actividades grupales crean su(s) actividad_asignada_grupo manualmente
 * (DocenteActivityController::crearGrupo), pero las individuales no pasan por
 * ese flujo: cada estudiante necesita su propio grupo de 1 integrante para que
 * agenda, evaluación y nota tengan dónde colgarse (agenda.id_actividad_asignada_grupo
 * es NOT NULL).
 *
 * **Esto es un camino de escritura, no de lectura.** Se invoca al crear una
 * actividad individual y al inscribir a un estudiante (evento de modelo en
 * InscripcionCurso). Antes se llamaba también al pintar pantallas, lo que metía
 * un bucle de consultas en cada carga; para los datos anteriores a ese cambio
 * está el comando `agenda:backfill-grupos-individuales`.
 */
class GrupoIndividualService
{
    /**
     * Devuelve el grupo individual del estudiante para la actividad, creándolo
     * si todavía no existe.
     */
    public function asegurarGrupo(Actividad $actividad, int $idEstudiante): ActividadAsignadaGrupo
    {
        $integrante = IntegranteGrupo::where('id_estudiante', $idEstudiante)
            ->whereHas('actividadAsignadaGrupo', fn ($q) => $q->where('id_actividad', $actividad->id_actividad))
            ->with('actividadAsignadaGrupo')
            ->first();

        if ($integrante) {
            return $integrante->actividadAsignadaGrupo;
        }

        return $this->crearGrupo($actividad->id_actividad, $idEstudiante);
    }

    /**
     * Crea los grupos individuales faltantes para todos los estudiantes
     * inscritos en el curso. No hace nada si la actividad es grupal.
     *
     * Resuelve en una sola consulta quién ya tiene grupo, en vez de comprobarlo
     * estudiante a estudiante (B-7): en el caso normal —todos lo tienen— el coste
     * total son dos consultas y ninguna escritura.
     */
    public function asegurarGruposDelCurso(Curso $curso, Actividad $actividad): void
    {
        if ($actividad->es_grupal) {
            return;
        }

        $inscritos = $this->estudiantesInscritos($curso->id_curso);

        if ($inscritos->isEmpty()) {
            return;
        }

        $yaTienen = $this->estudiantesConGrupo($actividad->id_actividad);

        foreach ($inscritos->diff($yaTienen) as $idEstudiante) {
            $this->crearGrupo($actividad->id_actividad, (int) $idEstudiante);
        }
    }

    /**
     * Crea los grupos individuales que le falten a un estudiante en todas las
     * actividades individuales del curso.
     *
     * Es la contraparte de {@see asegurarGruposDelCurso()} para el otro lado del
     * problema: el estudiante que se inscribe después de creada la actividad.
     */
    public function asegurarGruposParaEstudiante(int $idCurso, int $idEstudiante): void
    {
        $actividades = Actividad::whereHas('componente', fn ($q) => $q->where('id_curso', $idCurso))
            ->where('es_grupal', false)
            ->pluck('id_actividad');

        if ($actividades->isEmpty()) {
            return;
        }

        $conGrupo = IntegranteGrupo::where('id_estudiante', $idEstudiante)
            ->whereHas('actividadAsignadaGrupo', fn ($q) => $q->whereIn('id_actividad', $actividades))
            ->with('actividadAsignadaGrupo:id_actividad_asignada_grupo,id_actividad')
            ->get()
            ->pluck('actividadAsignadaGrupo.id_actividad');

        foreach ($actividades->diff($conGrupo) as $idActividad) {
            $this->crearGrupo((int) $idActividad, $idEstudiante);
        }
    }

    /**
     * IDs de los estudiantes con inscripción vigente en el curso.
     *
     * @return Collection<int, int>
     */
    private function estudiantesInscritos(int $idCurso): Collection
    {
        return InscripcionCurso::where('id_curso', $idCurso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->pluck('id_estudiante');
    }

    /**
     * IDs de los estudiantes que ya tienen grupo en la actividad.
     *
     * @return Collection<int, int>
     */
    private function estudiantesConGrupo(int $idActividad): Collection
    {
        return IntegranteGrupo::whereHas(
            'actividadAsignadaGrupo',
            fn ($q) => $q->where('id_actividad', $idActividad)
        )->pluck('id_estudiante');
    }

    private function crearGrupo(int $idActividad, int $idEstudiante): ActividadAsignadaGrupo
    {
        $grupo = ActividadAsignadaGrupo::create([
            'id_actividad'              => $idActividad,
            'estado_actividad_asignada' => 'PLANIFICADA',
        ]);

        IntegranteGrupo::create([
            'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
            'id_estudiante'               => $idEstudiante,
        ]);

        return $grupo;
    }
}
