<?php

namespace App\Models\Base\Administrativo;

use Awobaz\Compoships\Compoships;
use Awobaz\Compoships\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseDepartamento extends Model
{
    use SoftDeletes;
    use Compoships;  // Importante: maneja composite keys correctamente

    protected $connection = 'pgsql';
    protected $table = 'Departamento';
    protected $primaryKey = ['id_departamento', 'id_facultad'];
    public $incrementing = false;

    const DELETED_AT = 'fecha_eliminacion';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';

    protected $fillable = [
        'nombre',
        'id_contexto'
    ];

    // Relaciones (mantén como están)
    public function facultad()
    {
        return $this->belongsTo(\App\Models\Administrativo\Facultad::class, 'id_facultad', 'id_facultad');
    }

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

    public function carreras()
    {
        return $this->hasMany(\App\Models\Administrativo\Carrera::class, ['id_departamento', 'id_facultad'], ['id_departamento', 'id_facultad']);
    }
}