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
     * Override asignaturas to include soft delete check on pivot table.
     */
    public function asignaturas()
    {
        return parent::asignaturas()->wherePivotNull('fecha_eliminacion');
    }


    public function calculateTotalCredits()
    {
        return $this->creditos_sct_totales ?? 0;
    }
}