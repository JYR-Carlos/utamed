<?php

namespace App\Models\Base\Agenda;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseRubrica extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'rubrica';
    protected $primaryKey = 'id_rubrica';
    public $incrementing = true;

    protected $fillable = [
        'rubrica',
        'id_estado_rubrica'
    ];

    protected $casts = [
        'rubrica' => 'array'
    ];

    // Relaciones

    public function estadoRubrica()
    {
        $instance = new \App\Models\Agenda\EstadoRubrica();
        return new BelongsTo($instance->newQuery(), $this, 'id_estado_rubrica', 'id_estado_rubrica', 'estadoRubrica');
    }

    // Relaciones inversas

    public function evaluaciones()
    {
        return $this->hasMany(\App\Models\Agenda\Evaluacion::class, 'id_rubrica', 'id_rubrica');
    }

}
