<?php

namespace App\Models\Base\Agenda;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignadoActividad extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Asignado_Actividad';
    protected $primaryKey = 'grupo';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'nota_individual',
        'diferencia_decimas',
        'id_actividad',
        'id_estudiante'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
    ];

    // Relaciones

    public function actividadAsignada()
    {
        return $this->belongsTo(\App\Models\Agenda\ActividadAsignada::class, ['grupo', 'id_actividad'], ['grupo', 'id_actividad']);
    }

    public function estudiante()
    {
        return $this->belongsTo(\App\Models\Usuario\Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}