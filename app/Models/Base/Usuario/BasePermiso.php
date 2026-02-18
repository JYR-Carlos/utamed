<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BasePermiso extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'permiso';
    protected $primaryKey = 'id_permiso';
    public $incrementing = true;

    protected $fillable = [
        'slug',
        'nombre',
        'descripcion'
    ];


    // Relaciones

    // Relaciones inversas

    public function asignacionRolPermisos()
    {
        return $this->hasMany(\App\Models\Usuario\AsignacionRolPermiso::class, 'id_permiso', 'id_permiso');
    }

    public function usuarioPermisoEspeciales()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'id_permiso', 'id_permiso');
    }

    // Relaciones muchos-a-muchos

    public function roles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Rol::class,
            'asignacion_rol_permiso',
            'id_permiso',
            'id_rol'
        )
            ->withPivot('puede_delegar_permisos');
    }

    public function usuariosConPermisoEspecial()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_permiso_especial',
            'id_permiso',
            'id_usuario'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function contextosConEstePermiso()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            'usuario_permiso_especial',
            'id_permiso',
            'id_contexto'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

}
