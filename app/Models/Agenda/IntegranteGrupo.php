<?php

namespace App\Models\Agenda;

use App\Models\Base\Agenda\BaseIntegranteGrupo;

/**
 * Modelo IntegranteGrupo
 * 
 * Extiende de BaseIntegranteGrupo (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class IntegranteGrupo extends BaseIntegranteGrupo
{
    /**
     * Obtiene los detalles del estudiante
     */
    public function getDetallesEstudiante()
    {
        return [
            'id_estudiante' => $this->id_estudiante,
            'nombre_completo' => trim(
                ($this->estudiante?->usuario?->nombre1 ?? '') . ' ' .
                ($this->estudiante?->usuario?->nombre2 ?? '') . ' ' .
                ($this->estudiante?->usuario?->apellido1 ?? '') . ' ' .
                ($this->estudiante?->usuario?->apellido2 ?? '')
            ),
            'rut' => $this->estudiante?->usuario?->rut,
            'email' => $this->estudiante?->usuario?->email,
        ];
    }
}