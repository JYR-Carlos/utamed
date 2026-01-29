<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BaseAsignacionPlan;

/**
 * Modelo AsignacionPlan
 * 
 * Extiende de BaseAsignacionPlan (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class AsignacionPlan extends BaseAsignacionPlan
{
    /**
     * Boot method to add model events
     */
    protected static function boot()
    {
        parent::boot();

        // When a new assignment is created, update plan credits
        static::created(function ($asignacion) {
            $asignacion->updatePlanCredits();
        });

        // When an assignment is updated, update plan credits
        static::updated(function ($asignacion) {
            $asignacion->updatePlanCredits();

            // If id_plan changed, update both old and new plan
            if ($asignacion->isDirty('id_plan')) {
                $oldPlanId = $asignacion->getOriginal('id_plan');
                if ($oldPlan = Plan::find($oldPlanId)) {
                    $oldPlan->update([
                        'creditos_sct_totales' => $oldPlan->calculateTotalCredits()
                    ]);
                }
            }
        });

        // When an assignment is deleted, update plan credits
        static::deleted(function ($asignacion) {
            $asignacion->updatePlanCredits();
        });

        // When an assignment is restored, update plan credits
        static::restored(function ($asignacion) {
            $asignacion->updatePlanCredits();
        });
    }

    /**
     * Update the plan's total credits
     */
    protected function updatePlanCredits()
    {
        if ($this->plan) {
            $this->plan->update([
                'creditos_sct_totales' => $this->plan->calculateTotalCredits()
            ]);
        }
    }
}