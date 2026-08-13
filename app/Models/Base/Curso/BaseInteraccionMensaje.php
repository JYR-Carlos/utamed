<?php

namespace App\Models\Base\Curso;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseInteraccionMensaje extends CustomBaseModel
{
    use SoftDeletes;
    use Compoships;
    const DELETED_AT = 'fecha_eliminacion';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;
    protected $connection = 'pgsql';
    protected $table = 'interaccion_mensaje';
    protected $primaryKey = 'id_interaccion_mensaje';
    public $incrementing = true;

    protected $fillable = [
        'id_mensaje',
        'id_usuario_lector'
    ];

    // Relaciones

    public function mensaje()
    {
        $instance = new \App\Models\Curso\Mensaje();
        return new BelongsTo($instance->newQuery(), $this, 'id_mensaje', 'id_mensaje', 'mensaje');
    }

    public function lector()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'id_usuario_lector', 'id_usuario', 'lector');
    }
}
