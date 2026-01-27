<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BasePlan extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Plan';
    protected $primaryKey = 'id_plan';
    public $incrementing = true;

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_carrera',
        'agno',
        'version',
        'creditos_sct_totales'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
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

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}