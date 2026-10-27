<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseInscripcionCurso;
use App\Services\Agenda\GrupoIndividualService;
use Illuminate\Support\Facades\DB;

/**
 * Modelo InscripcionCurso
 *
 * Extiende de BaseInscripcionCurso (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class InscripcionCurso extends BaseInscripcionCurso
{
    /**
     * Obtiene el código de inscripción Intranet (INS_ID) a través de la relación
     * del alumno con inscripcion_componente relacionada al curso consultado.
     */
    public function getInsIdAttribute(): ?int
    {
        if ($this->relationLoaded('estudiante') && $this->estudiante?->relationLoaded('inscripcionComponentes')) {
            $ic = $this->estudiante->inscripcionComponentes->first(function ($item) {
                $matchCurso = !$item->relationLoaded('componente') || $item->componente?->id_curso == $this->id_curso;
                return $matchCurso && !empty($item->cod_inscripcion_curso_uta);
            });
            if ($ic && is_numeric($ic->cod_inscripcion_curso_uta) && (int) $ic->cod_inscripcion_curso_uta > 0) {
                return (int) $ic->cod_inscripcion_curso_uta;
            }
        }

        $val = DB::table('curso.inscripcion_componente as ic')
            ->join('curso.componente as c', 'c.id_componente', '=', 'ic.id_componente')
            ->where('ic.id_estudiante', $this->id_estudiante)
            ->where('c.id_curso', $this->id_curso)
            ->whereNotNull('ic.cod_inscripcion_curso_uta')
            ->value('ic.cod_inscripcion_curso_uta');

        if ($val !== null && is_numeric($val) && (int) $val > 0) {
            return (int) $val;
        }

        if ($this->cod_inscripcion_uta !== null && is_numeric($this->cod_inscripcion_uta) && (int) $this->cod_inscripcion_uta > 0) {
            return (int) $this->cod_inscripcion_uta;
        }

        return null;
    }

    /**
     * Al inscribir a un estudiante se le crean sus grupos individuales en las
     * actividades individuales que ya existan en el curso.
     *
     * Esto es lo que permite que las pantallas de evaluación no tengan que
     * repararlo al vuelo en cada carga (B-7): la creación ocurre una vez, aquí,
     * en el momento de la escritura.
     */
    protected static function booted(): void
    {
        static::saved(function (self $inscripcion) {
            if ($inscripcion->estado_inscripcion !== 'INSCRITO') {
                return;
            }

            app(GrupoIndividualService::class)->asegurarGruposParaEstudiante(
                (int) $inscripcion->id_curso,
                (int) $inscripcion->id_estudiante
            );
        });
    }
}