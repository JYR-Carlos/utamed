<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseFacultad extends Model
{
    use SoftDeletes;
    protected $connection = 'pgsql';
    protected $table = 'utamed.Facultad';
    protected $primaryKey = 'id_facultad';
    public $incrementing = true;
    const DELETED_AT = 'fecha_eliminacion';

      const CREATED_AT = 'fecha_creacion';
      const UPDATED_AT = 'fecha_modificacion';

    protected $fillable = ['nombre'];

    // Relaciones

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

    // Relaciones inversas

    public function departamentos()
    {
        return $this->hasMany(\App\Models\Administrativo\Departamento::class, 'id_facultad', 'id_facultad');
    }

}