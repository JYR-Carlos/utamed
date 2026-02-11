<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseUsuario extends Model implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Usuario';
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
        'fecha_verificacion_email',
        'token_recuerdame_sesion',
        'esta_activo'
    ];

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        $qualified = parent::qualifyColumn($column);
        // Only quote if not already quoted and contains a dot (table.column)
        if (!str_contains($qualified, '\"') && str_contains($qualified, '.')) {
            return '\"' . str_replace('.', '\".\"', $qualified) . '\"';
        }
        return $qualified;
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return '\"' . $this->getTable() . '\".\"' . $this->getKeyName() . '\"';
    }


    // Relaciones

    // Relaciones inversas

    public function programasCreados()
    {
        return $this->hasMany(\App\Models\Administrativo\Programa::class, 'creado_por', 'id_usuario');
    }

    public function docente()
    {
        return $this->hasOne(\App\Models\Usuario\Docente::class, 'id_usuario', 'id_usuario');
    }

    public function estudiante()
    {
        return $this->hasOne(\App\Models\Usuario\Estudiante::class, 'id_usuario', 'id_usuario');
    }

    public function rolesCreados()
    {
        return $this->hasMany(\App\Models\Usuario\Rol::class, 'creado_por', 'id_usuario');
    }

    public function permisosEspecialesRecibidos()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'id_usuario', 'id_usuario');
    }

    public function permisosEspecialesAsignados()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'creado_por', 'id_usuario');
    }

    public function permisosEspecialesEliminados()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'eliminado_por', 'id_usuario');
    }

    public function asignacionesRolRecibidas()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'id_usuario', 'id_usuario');
    }

    public function asignacionesRolRealizadas()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'creado_por', 'id_usuario');
    }

    public function asignacionesRolEliminadas()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'eliminado_por', 'id_usuario');
    }

    // Relaciones muchos-a-muchos

    public function permisosEspeciales()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Permiso::class,
            'Usuario_Permiso_Especial',
            'id_usuario',
            'id_permiso'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function contextosConPermisoEspecial()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            'Usuario_Permiso_Especial',
            'id_usuario',
            'id_contexto'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueAsignanMisPermisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Permiso_Especial',
            'id_usuario',
            'creado_por'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueBorranMisPermisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Permiso_Especial',
            'id_usuario',
            'eliminado_por'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueRecibenMisPermisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Permiso_Especial',
            'creado_por',
            'id_usuario'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueBorranMisPermisosAsignados()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Permiso_Especial',
            'creado_por',
            'eliminado_por'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueBorranMisPermisosRecibidos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Permiso_Especial',
            'eliminado_por',
            'id_usuario'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosALosQueBorroSusPermisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Permiso_Especial',
            'eliminado_por',
            'creado_por'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function contextosConRolAsignado()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            'Usuario_Rol_Asignación',
            'id_usuario',
            'id_contexto'
        )
            ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function rolesAsignados()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Rol::class,
            'Usuario_Rol_Asignación',
            'id_usuario',
            'id_rol'
        )
            ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueAsignanMisRoles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Rol_Asignación',
            'id_usuario',
            'creado_por'
        )
            ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueBorranMisRoles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Rol_Asignación',
            'id_usuario',
            'eliminado_por'
        )
            ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueRecibenMisRoles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Rol_Asignación',
            'creado_por',
            'id_usuario'
        )
            ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueBorranMisRolesAsignados()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Rol_Asignación',
            'creado_por',
            'eliminado_por'
        )
            ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueBorranMisRolesRecibidos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Rol_Asignación',
            'eliminado_por',
            'id_usuario'
        )
            ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosALosQueBorroSusRoles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Rol_Asignación',
            'eliminado_por',
            'creado_por'
        )
            ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

}
