<?php

namespace App\Models\Base\Agenda;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseEstadoRubrica extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'estado_rubrica';
    protected $primaryKey = 'id_estado_rubrica';
    public $incrementing = true;

    protected $fillable = [
        'titulo',
        'descripcion'
    ];

    // Relaciones

    // Relaciones inversas

    public function rubricas()
    {
        return $this->hasMany(\App\Models\Agenda\Rubrica::class, 'id_estado_rubrica', 'id_estado_rubrica');
    }

}
