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
abstract class BaseUsuario extends CustomBaseModel implements HasContext
{
    use SoftDeletes;
    use Compoships;
    use GlobalContextAware;
    use FiltersContextScope;
    const DELETED_AT = 'fecha_eliminacion';
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    public $incrementing = true;

    protected $fillable = [
        'username',
        'passhash',
        'email',
        'rut',
        'nombre1',
        'nombre2',
        'apellido1',
        'apellido2',
        'esta_activo',
        'fecha_verificacion_email',
        'token_recuerdame_sesion'
    ];


    // Relaciones

    // Relaciones inversas

    public function programas()
    {
        return $this->hasMany(\App\Models\Administrativo\Programa::class, 'creado_por', 'id_usuario');
    }

    public function programas1()
    {
        return $this->hasMany(\App\Models\Administrativo\Programa::class, 'revisado_por', 'id_usuario');
    }

    public function docente()
    {
        return $this->hasOne(\App\Models\Usuario\Docente::class, 'id_usuario', 'id_usuario');
    }

    public function estudiante()
    {
        return $this->hasOne(\App\Models\Usuario\Estudiante::class, 'id_usuario', 'id_usuario');
    }

    public function roles()
    {
        return $this->hasMany(\App\Models\Usuario\Rol::class, 'creado_por', 'id_usuario');
    }

    public function usuarioPermisoEspeciales()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'id_usuario', 'id_usuario');
    }

    public function usuarioPermisoEspeciales1()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'creado_por', 'id_usuario');
    }

    public function usuarioPermisoEspeciales2()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'eliminado_por', 'id_usuario');
    }

    public function usuarioRolAsignaciones()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignacion::class, 'id_usuario', 'id_usuario');
    }

    public function usuarioRolAsignaciones1()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignacion::class, 'creado_por', 'id_usuario');
    }

    public function usuarioRolAsignaciones2()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignacion::class, 'eliminado_por', 'id_usuario');
    }

    // Relaciones muchos-a-muchos

    public function permisosEspeciales()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Permiso::class,
            'usuario_permiso_especial',
            'id_usuario',
            'id_permiso'
        )
            ->withPivot('id_upe', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function contextosConPermisoEspecial()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            'usuario_permiso_especial',
            'id_usuario',
            'id_contexto'
        )
            ->withPivot('id_upe', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueAsignanMisPermisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_permiso_especial',
            'id_usuario',
            'creado_por'
        )
            ->withPivot('id_upe', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueBorranMisPermisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_permiso_especial',
            'id_usuario',
            'eliminado_por'
        )
            ->withPivot('id_upe', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueRecibenMisPermisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_permiso_especial',
            'creado_por',
            'id_usuario'
        )
            ->withPivot('id_upe', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueBorranMisPermisosAsignados()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_permiso_especial',
            'creado_por',
            'eliminado_por'
        )
            ->withPivot('id_upe', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueBorranMisPermisosRecibidos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_permiso_especial',
            'eliminado_por',
            'id_usuario'
        )
            ->withPivot('id_upe', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosALosQueBorroSusPermisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_permiso_especial',
            'eliminado_por',
            'creado_por'
        )
            ->withPivot('id_upe', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function contextosConRolAsignado()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            'usuario_rol_asignacion',
            'id_usuario',
            'id_contexto'
        )
            ->withPivot('id_ura', 'asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function rolesAsignados()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Rol::class,
            'usuario_rol_asignacion',
            'id_usuario',
            'id_rol'
        )
            ->withPivot('id_ura', 'asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueAsignanMisRoles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_rol_asignacion',
            'id_usuario',
            'creado_por'
        )
            ->withPivot('id_ura', 'asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueBorranMisRoles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_rol_asignacion',
            'id_usuario',
            'eliminado_por'
        )
            ->withPivot('id_ura', 'asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueRecibenMisRoles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_rol_asignacion',
            'creado_por',
            'id_usuario'
        )
            ->withPivot('id_ura', 'asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueBorranMisRolesAsignados()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_rol_asignacion',
            'creado_por',
            'eliminado_por'
        )
            ->withPivot('id_ura', 'asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueBorranMisRolesRecibidos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_rol_asignacion',
            'eliminado_por',
            'id_usuario'
        )
            ->withPivot('id_ura', 'asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosALosQueBorroSusRoles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_rol_asignacion',
            'eliminado_por',
            'creado_por'
        )
            ->withPivot('id_ura', 'asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

}
