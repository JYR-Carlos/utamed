<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseUsuarioRolAsignacion extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'usuario_rol_asignacion';
    protected $primaryKey = 'id_ura';
    public $incrementing = true;

    protected $fillable = [
        'asignado_por',
        'fecha_inicio_planificada',
        'fecha_fin_planificada',
        'fecha_fin_real',
        'fue_eliminado',
        'id_contexto',
        'id_rol',
        'id_usuario',
        'esta_activo',
        'creado_por',
        'eliminado_por'
    ];

    protected $casts = [
        'fue_eliminado' => 'boolean',
        'esta_activo' => 'boolean'
    ];

    // Relaciones

    public function receptor()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'id_usuario', 'id_usuario', 'receptor');
    }

    public function contexto()
    {
        $instance = new \App\Models\Usuario\Contexto();
        return new BelongsTo($instance->newQuery(), $this, 'id_contexto', 'id_contexto', 'contexto');
    }

    public function rol()
    {
        $instance = new \App\Models\Usuario\Rol();
        return new BelongsTo($instance->newQuery(), $this, 'id_rol', 'id_rol', 'rol');
    }

    public function asignador()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'creado_por', 'id_usuario', 'asignador');
    }

    public function borrador()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'eliminado_por', 'id_usuario', 'borrador');
    }

}
