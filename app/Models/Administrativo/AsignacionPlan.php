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
     * Asigna una asignatura a un plan, reactivando la asignación si se había quitado antes.
     *
     * Quitar una asignatura de la malla es un soft delete, pero la restricción
     * uq_plan_asignatura (id_asignatura, id_plan) no mira fecha_eliminacion: un INSERT
     * nuevo choca con la fila borrada. Se restaura esa misma fila, lo que además conserva
     * el id_asignacion_plan al que pueden seguir apuntando cursos anteriores.
     */
    public static function asignarOReactivar(array $datos): self
    {
        $existente = static::withTrashed()
            ->where('id_plan', $datos['id_plan'])
            ->where('id_asignatura', $datos['id_asignatura'])
            ->first();

        if (! $existente) {
            return static::create($datos);
        }

        $existente->fill($datos);
        if ($existente->trashed()) {
            $existente->restore();
        }
        $existente->save();

        return $existente;
    }
}