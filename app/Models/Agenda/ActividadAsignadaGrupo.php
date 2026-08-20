<?php

namespace App\Models\Agenda;

use App\Enums\DB\EstadoActividadAsignada;
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

    /**
     * Persiste en la BD el estado calculado (calcularEstadoGrupo), si difiere
     * del guardado. El registro no queda "vivo" por sí solo -nada recalcula el
     * paso del tiempo-, así que hay que llamar a esto en los puntos donde se
     * lee o se escribe el grupo para que la columna no quede pegada en el
     * valor con el que se creó.
     *
     * 'NO VISIBLE' no es un valor válido de agenda.en_estado_actividad_asignada
     * (sólo PLANIFICADA/ACTIVA/CERRADA), así que en ese caso no se escribe nada
     * y la columna conserva su último estado persistible.
     *
     * @return bool true si el registro se actualizó.
     */
    public function sincronizarEstado(?Actividad $actividad = null): bool
    {
        $estadoCalculado = $this->calcularEstadoGrupo($actividad);

        if (!EstadoActividadAsignada::tryFrom($estadoCalculado)) {
            return false;
        }

        if ($this->estado_actividad_asignada?->value === $estadoCalculado) {
            return false;
        }

        $this->estado_actividad_asignada = $estadoCalculado;
        $this->save();

        return true;
    }
}