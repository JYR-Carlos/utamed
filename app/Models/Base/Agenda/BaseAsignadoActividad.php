<?php

namespace App\Models\Base\Agenda;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignadoActividad extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Asignado_Actividad';
    protected $primaryKey = ['grupo', 'id_actividad', 'id_estudiante'];
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'nota_individual',
        'diferencia_decimas'
    ];

    // Relaciones

    public function actividadAsignada()
    {
        return $this->belongsTo(\App\Models\Agenda\ActividadAsignada::class, 'grupo', 'grupo');
    }

    public function estudiante()
    {
        return $this->belongsTo(\App\Models\Usuario\Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

}