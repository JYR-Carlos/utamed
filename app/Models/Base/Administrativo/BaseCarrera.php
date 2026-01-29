<?php

namespace App\Models\Base\Administrativo;

use Awobaz\Compoships\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseCarrera extends Model
{
    use SoftDeletes;
    protected $connection = 'pgsql';
    protected $table = 'utamed.Carrera';
    protected $primaryKey = 'id_carrera';
    public $incrementing = true;
    const DELETED_AT = 'fecha_eliminacion';

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';

    protected $fillable = [
        'nombre',
        'jornada',
        'sede',
        'modalidad',
        'id_departamento',
        'id_facultad'
    ];

    // Relaciones

    public function departamento()
    {
        return $this->belongsTo(\App\Models\Administrativo\Departamento::class, 'id_departamento', 'id_departamento');
    }

    // Relaciones inversas

    public function planes()
    {
        return $this->hasMany(\App\Models\Administrativo\Plan::class, 'id_carrera', 'id_carrera');
    }

    public function estudiantes()
    {
        return $this->hasMany(\App\Models\Usuario\Estudiante::class, 'id_carrera', 'id_carrera');
    }

}