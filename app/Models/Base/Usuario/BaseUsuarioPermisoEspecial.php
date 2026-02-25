<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseUsuarioPermisoEspecial extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'usuario_permiso_especial';
    protected $primaryKey = 'id_upe';
    public $incrementing = true;

    protected $fillable = [
        'fecha_inicio_planificada',
        'fecha_fin_planificada',
        'esta_permitido',
        'puede_delegar',
        'fecha_fin_real',
        'fue_borrado',
        'id_permiso',
        'id_contexto',
        'id_usuario',
        'esta_activo',
        'creado_por',
        'eliminado_por'
    ];


    // Relaciones

    public function receptor()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'id_usuario', 'id_usuario', 'receptor');
    }

    public function permiso()
    {
        $instance = new \App\Models\Usuario\Permiso();
        return new BelongsTo($instance->newQuery(), $this, 'id_permiso', 'id_permiso', 'permiso');
    }

    public function contexto()
    {
        $instance = new \App\Models\Usuario\Contexto();
        return new BelongsTo($instance->newQuery(), $this, 'id_contexto', 'id_contexto', 'contexto');
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
