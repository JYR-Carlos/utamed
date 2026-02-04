<?php

namespace App\Models\Base\Administrativo;

use Awobaz\Compoships\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseDepartamento extends Model
{
    use SoftDeletes;
    protected $connection = 'pgsql';
    protected $table = 'Departamento';
    protected $primaryKey = ['id_departamento', 'id_facultad'];
    public $incrementing = false;
    const DELETED_AT = 'fecha_eliminacion';

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';

    protected $fillable = [
        'nombre',
        'id_facultad'
    ];

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        return is_string($column) && str_contains($column, '.')
            ? $column
            : $this->getTable() . '.' . $column;
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return $this->getTable() . '.' . $this->getKeyName();
    }


    // Relaciones

    public function facultad()
    {
        return $this->belongsTo(\App\Models\Administrativo\Facultad::class, 'id_facultad', 'id_facultad');
    }

    // Relaciones inversas

    public function carreras()
    {
        return $this->hasMany(\App\Models\Administrativo\Carrera::class, 'id_departamento', 'id_departamento');
    }

}