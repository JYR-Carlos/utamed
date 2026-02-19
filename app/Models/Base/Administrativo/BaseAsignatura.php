<?php

namespace App\Models\Base\Administrativo;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignatura extends CustomBaseModel implements HasContext
{
    use SoftDeletes;
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    const DELETED_AT = 'fecha_eliminacion';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';
    protected $connection = 'pgsql';
    protected $table = 'asignatura';
    protected $primaryKey = 'id_asignatura';
    public $incrementing = true;

    protected $fillable = [
        'cod_asignatura',
        'nombre',
        'descripcion',
        'creditos_sct',
        'horas_catedra',
        'horas_taller',
        'horas_laboratorio',
        'horas_dirigidas',
        'horas_autonomas'
    ];


    // Relaciones

    // Relaciones inversas

    public function asignacionPlanes()
    {
        return $this->hasMany(\App\Models\Administrativo\AsignacionPlan::class, 'id_asignatura', 'id_asignatura');
    }

    // Relaciones muchos-a-muchos

    public function planes()
    {
        return $this->belongsToMany(
            \App\Models\Administrativo\Plan::class,
            'asignacion_plan',
            'id_asignatura',
            'id_plan'
        )
            ->withPivot('id_asignacion_plan', 'agno_planificado', 'semestre_planificado', 'tipo_ramo');
    }

}
