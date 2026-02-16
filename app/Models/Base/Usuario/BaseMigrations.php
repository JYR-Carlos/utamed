<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseMigrations extends Model
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'migrations';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'migration',
        'batch'
    ];


}
