<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
    protected $table = 'utamed.carrera';
    protected $primaryKey = 'id_carrera';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'id_facultad',
        'id_departamento',
    ];

    /**
     * Relación: Una carrera pertenece a una facultad
     */
    public function facultad()
    {
        return $this->belongsTo(Facultad::class, 'id_facultad', 'id_facultad');
    }

    /**
     * Relación: Una carrera pertenece a un departamento
     */
    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'id_departamento', 'id_departamento');
    }

    /**
     * Relación: Una carrera tiene muchas asignaturas
     */
    public function asignaturas()
    {
        return $this->hasMany(Asignatura::class, 'id_carrera', 'id_carrera');
    }

    /**
     * Relación: Una carrera tiene muchos estudiantes
     */
    public function estudiantes()
    {
        return $this->hasMany(Estudiante::class, 'id_carrera', 'id_carrera');
    }
}
