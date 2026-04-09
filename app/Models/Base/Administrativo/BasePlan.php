<?php

namespace App\Models\Base\Administrativo;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Contracts\HasOwnedContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BasePlan extends CustomBaseModel implements HasOwnedContext
{
    use SoftDeletes;
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    const DELETED_AT = 'fecha_eliminacion';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';
    protected $connection = 'pgsql';
    protected $table = 'plan';
    protected $primaryKey = 'id_plan';
    public $incrementing = true;

    protected $fillable = [
        'agno_plan',
        'version_plan',
        'creditos_sct_totales',
        'id_carrera'
    ];


    // Relaciones

    public function carrera()
    {
        $instance = new \App\Models\Administrativo\Carrera();
        return new BelongsTo($instance->newQuery(), $this, 'id_carrera', 'id_carrera', 'carrera');
    }

    // Relaciones inversas

    public function asignacionPlanes()
    {
        return $this->hasMany(\App\Models\Administrativo\AsignacionPlan::class, 'id_plan', 'id_plan');
    }

    // Relaciones muchos-a-muchos

    public function asignaturas()
    {
        return $this->belongsToMany(
            \App\Models\Administrativo\Asignatura::class,
            'asignacion_plan',
            'id_plan',
            'id_asignatura'
        )
            ->withPivot('id_asignacion_plan', 'agno_planificado', 'semestre_planificado', 'tipo_ramo', 'id_plan', 'id_asignatura');
    }

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Path: carrera
     */
    public function scopeWhereContextHierarchy($query, array $contextIds)
    {
        if (empty($contextIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('carrera', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
    }
}
