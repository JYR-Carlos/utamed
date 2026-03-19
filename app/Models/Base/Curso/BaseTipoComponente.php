<?php

namespace App\Models\Base\Curso;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseTipoComponente extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'tipo_componente';
    protected $primaryKey = 'id_tipo_componente';
    public $incrementing = true;

    protected $fillable = [
        'tipo'
    ];


    // Relaciones

    // Relaciones inversas

    public function componentes()
    {
        return $this->hasMany(\App\Models\Curso\Componente::class, 'id_tipo_componente', 'id_tipo_componente');
    }

}
