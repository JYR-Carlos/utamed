<?php

namespace App\Models\Base\Agenda;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseTipoRegistroAgenda extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'tipo_registro_agenda';
    protected $primaryKey = 'id_tipo_registro_agenda';
    public $incrementing = true;

    protected $fillable = [
        'tipo'
    ];

    // Relaciones

    // Relaciones inversas

    public function agendas()
    {
        return $this->hasMany(\App\Models\Agenda\Agenda::class, 'id_tipo_registro_agenda', 'id_tipo_registro_agenda');
    }

}
