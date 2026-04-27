<?php

namespace App\Models\Base\Administrativo;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseVwUsuariosCompleto extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'vw_usuarios_completo';

    protected $casts = [
        'esta_activo' => 'boolean'
    ];


}
