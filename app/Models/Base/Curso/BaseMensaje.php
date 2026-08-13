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
abstract class BaseMensaje extends CustomBaseModel
{
    use SoftDeletes;
    use Compoships;
    const DELETED_AT = 'fecha_eliminacion';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;
    protected $connection = 'pgsql';
    protected $table = 'mensaje';
    protected $primaryKey = 'id_mensaje';
    public $incrementing = true;

    protected $fillable = [
        'mensaje',
        'tipo_mensaje',
        'tema',
        'id_componente',
        'id_usuario_emisor',
        'id_usuario_receptor'
    ];

    // Relaciones

    public function componente()
    {
        $instance = new \App\Models\Curso\Componente();
        return new BelongsTo($instance->newQuery(), $this, 'id_componente', 'id_componente', 'componente');
    }

    public function emisor()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'id_usuario_emisor', 'id_usuario', 'emisor');
    }

    public function receptor()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'id_usuario_receptor', 'id_usuario', 'receptor');
    }

    // Relaciones inversas

    public function interacciones()
    {
        return $this->hasMany(\App\Models\Curso\InteraccionMensaje::class, 'id_mensaje', 'id_mensaje');
    }
}
