<?php

namespace App\Models\Base\Curso;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseTipoSeccion extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Tipo_Seccion';
    protected $primaryKey = 'id_tipo_seccion';
    public $incrementing = true;

    protected $fillable = [
        'id_tipo_seccion',
        'tipo'
    ];


    // Relaciones

    // Relaciones inversas

    public function secciones()
    {
        return $this->hasMany(\App\Models\Curso\Seccion::class, 'id_tipo_seccion', 'id_tipo_seccion');
    }

}
