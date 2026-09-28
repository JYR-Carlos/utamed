<?php

namespace App\Models\Base\Agenda;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseLecturaAgenda extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'lectura_agenda';
    protected $primaryKey = 'id_lectura_agenda';
    public $incrementing = true;

    protected $fillable = [
        'id_agenda',
        'id_usuario_lector',
        'fecha_lectura'
    ];

    // Relaciones

    public function agenda()
    {
        $instance = new \App\Models\Agenda\Agenda();
        return new BelongsTo($instance->newQuery(), $this, 'id_agenda', 'id_agenda', 'agenda');
    }

    public function usuario()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'id_usuario_lector', 'id_usuario', 'usuario');
    }

}
