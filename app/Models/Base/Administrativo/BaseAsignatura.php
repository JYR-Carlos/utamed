<?php

namespace App\Models\Base\Administrativo;

use Awobaz\Compoships\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignatura extends Model
{
    use SoftDeletes;
    protected $connection = 'pgsql';
    protected $table = 'Asignatura';
    protected $primaryKey = 'id_asignatura';
    public $incrementing = true;
    const DELETED_AT = 'fecha_eliminacion';

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';

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

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
 public function qualifyColumn($column)
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return '"' . $this->getTable() . '"."' . $this->getKeyName() . '"';
    }


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
            'Asignacion_Plan',
            'id_asignatura',
            'id_plan'
        )
        ->withPivot('agno_planificado', 'semestre_planificado', 'tipo_ramo');
    }

}
