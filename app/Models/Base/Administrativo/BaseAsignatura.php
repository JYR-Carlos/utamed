<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignatura extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Asignatura';
    protected $primaryKey = 'id_asignatura';
    public $incrementing = true;

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

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

    protected $casts = [
        'esta_activo' => 'boolean',
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
            '\"utamed.Administrativo\".\"Asignacion_Plan\"',
            'id_asignatura',
            'id_plan'
        )
        ->withPivot('agno_planificado', 'semestre_planificado', 'tipo_ramo');
    }

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}