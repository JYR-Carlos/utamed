<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BasePlan extends Model
{
    use SoftDeletes;
    protected $connection = 'pgsql';
    protected $table = 'utamed.Plan';
    protected $primaryKey = 'id_plan';
    public $incrementing = true;
    const DELETED_AT = 'fecha_eliminacion';

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';

    protected $fillable = [
        'id_carrera',
        'agno',
        'version',
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
            '\"utamed.Administrativo\".\"Asignacion_Plan\"',
            'id_plan',
            'id_asignatura'
        )
        ->withPivot('agno_planificado', 'semestre_planificado', 'tipo_ramo');
    }

}