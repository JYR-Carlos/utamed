<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facultad extends Model
{
    protected $table = 'utamed.facultad';
    protected $primaryKey = 'id_facultad';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    /**
     * Relación: Una facultad tiene muchos departamentos
     */
    public function departamentos()
    {
        return $this->hasMany(Departamento::class, 'id_facultad', 'id_facultad');
    }

    /**
     * Relación: Una facultad tiene muchas carreras
     */
    public function carreras()
    {
        return $this->hasMany(Carrera::class, 'id_facultad', 'id_facultad');
    }
}
