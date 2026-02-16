<?php

namespace App\Models\Base\Agenda;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAgenda extends Model
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Agenda';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'wip'
    ];


}
