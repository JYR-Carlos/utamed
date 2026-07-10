<?php

namespace App\Services\Agenda;

use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;

/**
 * Las actividades grupales crean su(s) actividad_asignada_grupo manualmente
 * (DocenteActivityController::crearGrupo), pero las individuales no pasan por
 * ese flujo: cada estudiante necesita su propio grupo de 1 integrante para que
 * agenda, evaluación y nota tengan dónde colgarse (agenda.id_actividad_asignada_grupo
 * es NOT NULL). Este servicio centraliza esa creación, idempotente, para poder
 * llamarla tanto al crear la actividad como de forma perezosa (estudiante nuevo,
 * o actividades individuales creadas antes de este fix).
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

        $grupo = ActividadAsignadaGrupo::create([
            'id_actividad'              => $actividad->id_actividad,
            'estado_actividad_asignada' => 'PLANIFICADA',
        ]);

        IntegranteGrupo::create([
            'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
            'id_estudiante'               => $idEstudiante,
        ]);

        return $grupo;
    }

    /**
     * Crea los grupos individuales faltantes para todos los estudiantes
     * inscritos en el curso. No hace nada si la actividad es grupal.
     */
    public function asegurarGruposDelCurso(Curso $curso, Actividad $actividad): void
    {
        if ($actividad->es_grupal) {
            return;
        }

        $idsEstudiantes = InscripcionCurso::where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->pluck('id_estudiante');

        foreach ($idsEstudiantes as $idEstudiante) {
            $this->asegurarGrupo($actividad, $idEstudiante);
        }
    }
}
