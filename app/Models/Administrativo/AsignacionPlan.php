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
    use \App\Traits\HasCompositeKey;

    /**
     * Attributes that should be mutated to dates
     */
    protected $dates = [
        'fecha_creacion',
        'fecha_eliminacion'
    ];

    /**
     * Wayfinder/Eloquent workaround: return a single key name instead of the composite array
     * to prevent Reflection errors in Wayfinder.
     */
    public function getRouteKeyName()
    {
        return 'id_asignatura'; // Or 'id_plan', either works as a placeholder for Wayfinder
    }

    /**
     * Boot method to add model events
     */
    protected static function boot()
    {
        parent::boot();

        // Ensure fecha_creacion is set when creating
        static::creating(function ($asignacion) {
            if (!$asignacion->fecha_creacion) {
                $asignacion->fecha_creacion = now();
            }
        });

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

    /**
     * Fix: Override BaseAsignacionPlan quoting to prevent double-escaping backslashes.
     * Uses standard double quotes for PostgreSQL.
     */
    public function qualifyColumn($column)
    {
        // Remove plain quotes from column
        $column = str_replace(['"', "'"], '', $column);

        // Handle table name separately
        $table = $this->getTable();
        // Remove quotes from table name if present
        $table = str_replace(['"', "'"], '', $table);

        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            return '"' . $parts[0] . '"."' . $parts[1] . '"';
        }

        return '"' . $table . '"."' . $column . '"';
    }

    public function getQualifiedKeyName()
    {
        return $this->qualifyColumn($this->getKeyName());
    }
}
