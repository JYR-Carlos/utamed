<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignacionPlan extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Asignacion_Plan';
    protected $primaryKey = 'id_asignatura';
    public $incrementing = true;

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_plan',
        'agno_planificado',
        'semestre_planificado',
        'tipo_ramo'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
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
        return $this->hasMany(\App\Models\Curso\Curso::class, ['id_asignatura', 'id_plan'], ['id_asignatura', 'id_plan']);
    }

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}