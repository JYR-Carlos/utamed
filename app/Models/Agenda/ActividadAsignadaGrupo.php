<?php

namespace App\Models\Agenda;

use App\Models\Base\Agenda\BaseActividadAsignadaGrupo;

/**
 * Modelo ActividadAsignadaGrupo
 * 
 * Extiende de BaseActividadAsignadaGrupo (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class ActividadAsignadaGrupo extends BaseActividadAsignadaGrupo
{
    /**
     * Obtiene todos los integrantes del grupo con sus detalles de estudiante
     */
    public function miembros()
    {
        return $this->hasMany(IntegranteGrupo::class, 'id_actividad_asignada_grupo', 'id_actividad_asignada_grupo');
    }

    /**
     * Obtiene todos los mensajes/entregas del grupo a través de agenda
     */
    public function entregas()
    {
        return $this->hasMany(Agenda::class, 'id_actividad_asignada_grupo', 'id_actividad_asignada_grupo');
    }

    /**
     * Obtiene los miembros con sus datos de estudiante cargados
     */
    public function getMiembrosConDetalles()
    {
        return $this->miembros()
            ->with('estudiante.usuario')
            ->get()
            ->map(function ($miembro) {
                return [
                    'id_asignado_actividad' => $miembro->id_asignado_actividad,
                    'id_estudiante' => $miembro->id_estudiante,
                    'nota_individual' => $miembro->nota_individual,
                    'diferencia_decimas' => $miembro->diferencia_decimas,
                    'nombre_completo' => trim(
                        ($miembro->estudiante?->usuario?->nombre1 ?? '') . ' ' .
                        ($miembro->estudiante?->usuario?->nombre2 ?? '') . ' ' .
                        ($miembro->estudiante?->usuario?->apellido1 ?? '') . ' ' .
                        ($miembro->estudiante?->usuario?->apellido2 ?? '')
                    ),
                    'rut' => $miembro->estudiante?->usuario?->rut,
                ];
            });
    }

    /**
     * Deriva el estado específico de la actividad para este grupo,
     * sumando la holgura base de la actividad y la holgura personal del grupo.
     * 
     * Reglas:
     * - No visible + Pre-Fin (+holgura total)  => PLANIFICADA
     * - Visible    + Pre-Fin (+holgura total)  => ACTIVA
     * - Visible    + Post-Fin (+holgura total) => CERRADA
     * - No visible + Post-Fin (+holgura total) => NO VISIBLE
     */
    public function calcularEstadoGrupo(?Actividad $actividad = null): string
    {
        $act = $actividad ?? $this->actividad;
        
        $holguraBase = (int) ($act?->nro_dias_adicionales_para_bloqueo ?? 0);
        $holguraPersonal = (int) ($this->nro_dias_adicionales_para_bloqueo_personal ?? 0);
        $holguraTotal = $holguraBase + $holguraPersonal;

        if ($act?->fecha_limite) {
            $fechaFinConHolgura = \Carbon\Carbon::parse($act->fecha_limite)->endOfDay()->addDays($holguraTotal);
            $esPostFin = \Carbon\Carbon::now()->greaterThan($fechaFinConHolgura);
        } else {
            $esPostFin = false;
        }

        $esVisible = (bool) $act?->getAttribute('visible');

        if (!$esVisible) {
            return $esPostFin ? 'NO VISIBLE' : 'PLANIFICADA';
        }

        return $esPostFin ? 'CERRADA' : 'ACTIVA';
    }
}