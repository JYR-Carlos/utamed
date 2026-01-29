<?php

namespace App\Models\Base\Agenda;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseActividadAsignada extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'utamed.Actividad_Asignada';
    protected $primaryKey = 'grupo';
    public $incrementing = true;

      public $timestamps = false;

    protected $fillable = [
        'nota',
        'id_estado'
    ];

    // Relaciones

    public function actividad()
    {
        return $this->belongsTo(\App\Models\Agenda\Actividad::class, 'id_actividad', 'id_actividad');
    }

    public function estadoActividad()
    {
        return $this->belongsTo(\App\Models\Agenda\EstadoActividad::class, 'id_estado', 'id_estado');
    }

    // Relaciones inversas

    public function asignadoActividades()
    {
        return $this->hasMany(\App\Models\Agenda\AsignadoActividad::class, 'grupo', 'grupo');
    }

    // Relaciones muchos-a-muchos

    public function estudiantesAsignados()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Estudiante::class,
            '\"utamed.Agenda\".\"Asignado_Actividad\"',
            'grupo,id_actividad',
            'id_estudiante'
        )
        ->withPivot('nota_individual', 'diferencia_decimas');
    }

}