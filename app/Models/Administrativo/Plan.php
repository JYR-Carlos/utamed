<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BasePlan;

/**
 * Modelo Plan
 * 
 * Extiende de BasePlan (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Plan extends BasePlan
{
    /**
     * Calculate total SCT credits from all assigned asignaturas
     */
    public function calculateTotalCredits()
    {
        return $this->asignacionPlanes()
            ->join('utamed.Asignatura', 'utamed.Asignacion_Plan.id_asignatura', '=', 'utamed.Asignatura.id_asignatura')
            ->whereNull('utamed.Asignatura.fecha_eliminacion')
            ->whereNull('utamed.Asignacion_Plan.fecha_eliminacion')
            ->sum('utamed.Asignatura.creditos_sct') ?? 0;
    }
}