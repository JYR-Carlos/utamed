<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asignatura extends Model
{
    protected $table = 'utamed.asignatura';
    protected $primaryKey = 'id_asignatura';
    public $timestamps = false;

    protected $fillable = [
        'cod_asignatura',
        'nombre',
        'id_carrera',
    ];

    /**
     * Relación: Una asignatura pertenece a una carrera
     */
    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'id_carrera', 'id_carrera');
    }

    /**
     * Relación: Una asignatura tiene muchos programas (versiones)
     */
    public function programas()
    {
        return $this->hasMany(Programa::class, 'id_asignatura', 'id_asignatura');
    }

    /**
     * Relación: Una asignatura tiene muchos cursos (instancias)
     */
    public function cursos()
    {
        return $this->hasMany(Curso::class, 'id_asignatura', 'id_asignatura');
    }
}
