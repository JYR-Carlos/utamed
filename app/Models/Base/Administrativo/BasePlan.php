<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BasePlan extends Model implements HasContext
{
    use SoftDeletes;
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    const DELETED_AT = 'fecha_eliminacion';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';
    protected $connection = 'pgsql';
    protected $table = 'Plan';
    protected $primaryKey = 'id_plan';
    public $incrementing = true;

    protected $fillable = [
        'agno',
        'version_plan',
        'creditos_sct_totales'
    ];


    // Relaciones

    public function carrera()
    {
        return $this->belongsTo(\App\Models\Administrativo\Carrera::class, 'id_carrera', 'id_carrera');
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
            'Asignacion_Plan',
            'id_plan',
            'id_asignatura'
        )
            ->withPivot('agno_planificado', 'semestre_planificado', 'tipo_ramo');
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
