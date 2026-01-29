<?php

namespace App\Models\Base\Usuario;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseVwPermisosUsuario extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'utamed.vw_permisos_usuario';
    protected $primaryKey = 'id';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'slug',
        'tipo_asignacion'
    ];

}