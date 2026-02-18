<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseTipoContexto extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'tipo_contexto';
    protected $primaryKey = 'id_tipo_contexto';
    public $incrementing = true;

    protected $fillable = [
        'categoria',
        'tabla_referenciada'
    ];


    // Relaciones

    // Relaciones inversas

    public function contextos()
    {
        return $this->hasMany(\App\Models\Usuario\Contexto::class, 'id_tipo_contexto', 'id_tipo_contexto');
    }

}
