<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignaciónRolPermiso extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'utamed.Asignación_Rol_Permiso';
    protected $primaryKey = 'id_rol';
    public $incrementing = true;

      public $timestamps = false;

    protected $fillable = ['puede_delegar_permisos'];

    // Relaciones

    public function rol()
    {
        return $this->belongsTo(\App\Models\Usuario\Rol::class, 'id_rol', 'id_rol');
    }

    public function permiso()
    {
        return $this->belongsTo(\App\Models\Usuario\Permiso::class, 'id_permiso', 'id_permiso');
    }

}