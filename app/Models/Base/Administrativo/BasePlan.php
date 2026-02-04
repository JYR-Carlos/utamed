<?php

namespace App\Models\Base\Administrativo;

use Awobaz\Compoships\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BasePlan extends Model
{
    use SoftDeletes;
    protected $connection = 'pgsql';
    protected $table = 'Plan';
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

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        return is_string($column) && str_contains($column, '.')
            ? $column
            : $this->getTable() . '.' . $column;
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return $this->getTable() . '.' . $this->getKeyName();
    }


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

}