<?php

namespace App\Models\Base\Agenda;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseEstadoActividad extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Estado_Actividad';
    protected $primaryKey = 'id_estado';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'descripcion'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
    ];

    // Relaciones

    // Relaciones inversas

    public function actividadAsignadas()
    {
        return $this->hasMany(\App\Models\Agenda\ActividadAsignada::class, 'id_estado', 'id_estado');
    }

    // Relaciones muchos-a-muchos

    public function actividades()
    {
        return $this->belongsToMany(
            \App\Models\Agenda\Actividad::class,
            '\"utamed.Agenda\".\"Actividad_Asignada\"',
            'id_estado',
            'id_actividad'
        )
        ->withPivot('grupo', 'nota');
    }

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}