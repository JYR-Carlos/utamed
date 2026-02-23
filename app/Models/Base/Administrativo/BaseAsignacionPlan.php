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
abstract class BaseAsignacionPlan extends CustomBaseModel implements HasOwnedContext
{
    use SoftDeletes;
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    const DELETED_AT = 'fecha_eliminacion';
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'asignacion_plan';
    protected $primaryKey = 'id_asignacion_plan';
    public $incrementing = true;

    protected $fillable = [
        'agno_planificado',
        'semestre_planificado',
        'tipo_ramo',
        'id_plan',
        'id_asignatura'
    ];


    // Relaciones

    public function asignatura()
    {
        $instance = new \App\Models\Administrativo\Asignatura();
        return new BelongsTo($instance->newQuery(), $this, 'id_asignatura', 'id_asignatura', 'asignatura');
    }

    public function plan()
    {
        $instance = new \App\Models\Administrativo\Plan();
        return new BelongsTo($instance->newQuery(), $this, 'id_plan', 'id_plan', 'plan');
    }

    // Relaciones inversas

    public function cursos()
    {
        return $this->hasMany(\App\Models\Curso\Curso::class, 'id_asignacion_plan', 'id_asignacion_plan');
    }

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Path: plan
     */
    public function scopeWhereContextHierarchy($query, array $contextIds)
    {
        if (empty($contextIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('plan', function ($q) use ($contextIds) {
                $q->whereHas('carrera', function ($q) use ($contextIds) {
                $q->whereIn('id_contexto', $contextIds);
            });
            });
    }
}
