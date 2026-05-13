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
        return $this->hasMany(IntegranteGrupo::class, 'grupo', 'grupo');
    }

    /**
     * Obtiene todos los mensajes/entregas del grupo a través de agenda
     */
    public function entregas()
    {
        return $this->hasMany(Agenda::class, 'grupo', 'grupo');
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
}