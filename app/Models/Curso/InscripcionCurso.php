<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseInscripcionCurso;
use App\Services\Agenda\GrupoIndividualService;

/**
 * Modelo InscripcionCurso
 *
 * Extiende de BaseInscripcionCurso (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class InscripcionCurso extends BaseInscripcionCurso
{
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