<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Contracts\HasContext;
use App\Traits\GlobalContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseRol extends CustomBaseModel implements HasContext
{
    use SoftDeletes;
    use Compoships;
    use GlobalContextAware;
    use FiltersContextScope;
    const DELETED_AT = 'fecha_eliminacion';
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'rol';
    protected $primaryKey = 'id_rol';
    public $incrementing = true;

    protected $fillable = [
        'nombre',
        'es_administrativo',
        'creado_por'
    ];


    // Relaciones

    public function usuario()
    {
        $instance = new \App\Models\Usuario\Usuario();
        return new BelongsTo($instance->newQuery(), $this, 'creado_por', 'id_usuario', 'usuario');
    }

    // Relaciones inversas

    public function asignacionRolPermisos()
    {
        return $this->hasMany(\App\Models\Usuario\AsignacionRolPermiso::class, 'id_rol', 'id_rol');
    }

    public function usuarioRolAsignaciones()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignacion::class, 'id_rol', 'id_rol');
    }

    // Relaciones muchos-a-muchos

    public function permisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Permiso::class,
            'asignacion_rol_permiso',
            'id_rol',
            'id_permiso'
        )
            ->withPivot('id_asignacion_rol_permiso', 'puede_delegar_permiso', 'id_rol', 'id_permiso');
    }

    public function usuariosConRolAsignado()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_rol_asignacion',
            'id_rol',
            'id_usuario'
        )
            ->withPivot('id_ura', 'asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'id_contexto', 'id_rol', 'id_usuario', 'esta_activo', 'creado_por', 'eliminado_por');
    }

    public function contextosConEsteRol()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            'usuario_rol_asignacion',
            'id_rol',
            'id_contexto'
        )
            ->withPivot('id_ura', 'asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'id_contexto', 'id_rol', 'id_usuario', 'esta_activo', 'creado_por', 'eliminado_por');
    }

}
