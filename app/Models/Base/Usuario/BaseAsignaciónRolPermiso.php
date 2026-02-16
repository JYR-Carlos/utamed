<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignaciónRolPermiso extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Asignación_Rol_Permiso';
    protected $primaryKey = ['id_rol', 'id_permiso'];
    public $incrementing = false;

    protected $fillable = [
        'puede_delegar_permisos',
        'id_rol',
        'id_permiso'
    ];


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
