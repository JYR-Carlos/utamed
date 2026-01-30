<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignacionPlan extends Model
{
    use SoftDeletes;
    protected $connection = 'pgsql';
    protected $table = 'utamed.Asignacion_Plan';
    protected $primaryKey = 'id_asignacion';
    public $incrementing = true;
    const DELETED_AT = 'fecha_eliminacion';

      public $timestamps = false;

    protected $fillable = [
        'id_plan',
        'id_asignatura',
        'agno_planificado',
        'semestre_planificado',
        'tipo_ramo'
    ];

    // Relaciones

    public function asignatura()
    {
        return $this->belongsTo(\App\Models\Administrativo\Asignatura::class, 'id_asignatura', 'id_asignatura');
    }

    public function plan()
    {
        return $this->belongsTo(\App\Models\Administrativo\Plan::class, 'id_plan', 'id_plan');
    }

    // Relaciones inversas

    public function cursos()
    {
        return $this->hasMany(\App\Models\Curso\Curso::class, 'id_asignatura', 'id_asignatura');
    }

}