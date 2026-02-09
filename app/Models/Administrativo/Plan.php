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
    protected $table = 'Plan';
    protected $fillable = ['id_carrera', 'agno', 'version_plan', 'id_contexto'];
    /**
     * Calculate total SCT credits from all assigned asignaturas
     */
    public function calculateTotalCredits()
    {
        return $this->asignacionPlanes()
            ->join('Asignatura', 'Asignacion_Plan.id_asignatura', '=', 'Asignatura.id_asignatura')
            ->whereNull('Asignatura.fecha_eliminacion')
            ->whereNull('Asignacion_Plan.fecha_eliminacion')
            ->sum('Asignatura.creditos_sct') ?? 0;
    }


    /**
     * Fix: Override BasePlan quoting to prevent double-escaping backslashes.
     * Uses standard double quotes for PostgreSQL.
     */
    public function qualifyColumn($column)
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    public function getQualifiedKeyName()
    {
        return $this->qualifyColumn($this->getKeyName());
    }
}