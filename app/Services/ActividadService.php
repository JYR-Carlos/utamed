<?php

namespace App\Services;

use App\Enums\DB\TipoActividad;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;
use App\Services\Agenda\GrupoIndividualService;
use Illuminate\Support\Facades\DB;

class ActividadService
{
    /**
     * Copia los datos de una actividad hacia un curso hermano (misma asignatura).
     *
     * Mapea componente por id_tipo_componente y unidad por num_unidad.
     * No copia grupos grupales, entregas ni evaluaciones — solo la definición, enunciado y rúbrica.
     */
    public function copiarA(Actividad $actividad, Componente $componenteDestino, Unidad $unidadDestino, Curso $curso): Actividad
    {
        return DB::transaction(function () use ($actividad, $componenteDestino, $unidadDestino, $curso) {
            $tipoActividadValor = $actividad->tipo_actividad instanceof TipoActividad
                ? $actividad->tipo_actividad->value
                : $actividad->tipo_actividad;

            $nueva = Actividad::create([
                'nombre' => $actividad->nombre,
                'fecha_limite' => $actividad->fecha_limite,
                'ponderacion' => $actividad->ponderacion,
                'exigencia' => $actividad->exigencia,
                'tipo_actividad' => $tipoActividadValor,
                'tipo_entrega' => $actividad->tipo_entrega,
                'visible' => false,
                'es_grupal' => $actividad->es_grupal,
                'max_integrantes' => $actividad->max_integrantes,
                'es_plantilla' => false,
                'nro_dias_adicionales_para_bloqueo' => $actividad->nro_dias_adicionales_para_bloqueo,
                'id_componente' => $componenteDestino->id_componente,
                'id_unidad' => $unidadDestino->id_unidad,
                'uuid_archivo_enunciado' => $actividad->uuid_archivo_enunciado,
            ]);

            // Duplicar Rúbrica si el origen tiene una
            $rubricaOrigen = Rubrica::where('id_actividad', $actividad->id_actividad)->first();
            if ($rubricaOrigen) {
                Rubrica::create([
                    'rubrica' => $rubricaOrigen->rubrica,
                    'estado_rubrica' => $rubricaOrigen->estado_rubrica,
                    'id_actividad' => $nueva->id_actividad,
                ]);
            }

            // Las actividades individuales necesitan grupos automáticos.
            if (!$nueva->es_grupal) {
                (new GrupoIndividualService)->asegurarGruposDelCurso($curso, $nueva);
            }

            return $nueva;
        });
    }
}
