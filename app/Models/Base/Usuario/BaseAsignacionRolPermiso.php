<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAsignacionRolPermiso extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'asignacion_rol_permiso';
    protected $primaryKey = 'id_asignacion_rol_permiso';
    public $incrementing = true;

    protected $fillable = [
        'puede_delegar_permisos',
        'id_rol',
        'id_permiso'
    ];


    // Relaciones

    public function rol()
    {
        $instance = new \App\Models\Usuario\Rol();
        return new BelongsTo($instance->newQuery(), $this, 'id_rol', 'id_rol', 'rol');
    }

    public function permiso()
    {
        $instance = new \App\Models\Usuario\Permiso();
        return new BelongsTo($instance->newQuery(), $this, 'id_permiso', 'id_permiso', 'permiso');
    }

}
