<?php

namespace App\Models\Base\Auditoria;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseProgramaHistorial extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'programa_historial';
    protected $primaryKey = 'id_log';
    public $incrementing = true;

    protected $fillable = [
        'accion',
        'estado_anterior',
        'estado_nuevo',
        'observaciones',
        'fecha_accion',
        'id_programa',
        'id_usuario'
    ];

    // Relaciones

    public function programa()
    {
        $instance = new \App\Models\Curso\Programa();
        return new BelongsTo($instance->newQuery(), $this, 'id_programa', 'id_programa', 'programa');
    }

    public function usuario()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'id_usuario', 'id_usuario', 'usuario');
    }

}
