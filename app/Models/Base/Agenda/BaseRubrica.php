<?php

namespace App\Models\Base\Agenda;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use App\Enums\DB\EstadoRubrica;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseRubrica extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    public EstadoRubrica $estadoRubrica;

    protected $connection = 'pgsql';
    protected $table = 'rubrica';
    protected $primaryKey = 'id_rubrica';
    public $incrementing = true;

    protected $fillable = [
        'rubrica',
        'estado_rubrica'
    ];

    protected $casts = [
        'rubrica' => 'array',
        'estado_rubrica' => EstadoRubrica::class
    ];

    // Relaciones

    // Relaciones inversas

    public function evaluaciones()
    {
        return $this->hasMany(\App\Models\Agenda\Evaluacion::class, 'id_rubrica', 'id_rubrica');
    }

}
