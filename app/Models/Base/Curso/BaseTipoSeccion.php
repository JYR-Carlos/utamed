<?php

namespace App\Models\Base\Curso;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseTipoSeccion extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Tipo_Seccion';
    protected $primaryKey = 'id_tipo_seccion';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'tipo'
    ];

    // Overrides removed to fix double quoting issue


    // Relaciones

    // Relaciones inversas

    public function secciones()
    {
        return $this->hasMany(\App\Models\Curso\Seccion::class, 'id_tipo_seccion', 'id_tipo_seccion');
    }

}