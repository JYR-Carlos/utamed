<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseVwPermisosUsuario extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'vw_permisos_usuario';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id_usuario',
        'id_contexto',
        'id_permiso',
        'slug',
        'esta_permitido',
        'tipo_asignacion',
        'puede_delegar'
    ];


}
