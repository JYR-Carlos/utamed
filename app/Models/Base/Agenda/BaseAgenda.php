<?php

namespace App\Models\Base\Agenda;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseAgenda extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'utamed.Agenda';
    protected $primaryKey = 'id';
    public $incrementing = true;

      public $timestamps = false;

    protected $fillable = [
        'wip'
    ];

}