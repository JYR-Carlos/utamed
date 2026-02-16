<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseMigrations extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'migrations';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'id',
        'migration',
        'batch'
    ];


}
