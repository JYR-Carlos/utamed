<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    protected $table = 'utamed.estudiante';
    protected $primaryKey = 'id_estudiante';
    public $timestamps = false;

    protected $fillable = [
        'rut',
        'nombre_completo',
        'agno_ingreso',
        'id_carrera',
    ];

    /**
     * Relación: Un estudiante pertenece a una carrera
     */
    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'id_carrera', 'id_carrera');
    }

    /**
     * Relación: Un estudiante tiene un usuario (1:1)
     */
    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'id_usuario', 'id_estudiante');
    }

    /**
     * Relación: Un estudiante tiene muchas inscripciones
     */
    public function inscripciones()
    {
        return $this->hasMany(Inscribe::class, 'id_estudiante', 'id_estudiante');
    }

    /**
     * Relación: Un estudiante tiene muchas asignaciones de actividades
     */
    public function asignacionesActividades()
    {
        return $this->hasMany(AsignadoActividad::class, 'id_estudiante', 'id_estudiante');
    }
}
