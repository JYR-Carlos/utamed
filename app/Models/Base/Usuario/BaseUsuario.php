<?php

namespace App\Models\Base\Usuario;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseUsuario extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Usuario';
    protected $primaryKey = 'id_usuario';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'username',
        'passhash',
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

    public function programas()
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

    public function usuarioRolAsignacciones()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'id_usuario', 'id_usuario');
    }

    public function usuarioRolAsignacciones1()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'creado_por', 'id_usuario');
    }

    public function usuarioRolAsignacciones2()
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

    public function usuariosQueRecibenMisPermisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Permiso_Especial',
            'id_usuario_asignador',
            'id_usuario_recipiente'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueAsignanMisPermisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Permiso_Especial',
            'id_usuario_recipiente',
            'id_usuario_asignador'
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

    public function usuariosQueRecibenMisRoles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Rol_Asignación',
            'id_usuario_asignador',
            'id_usuario_recipiente'
        )
        ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueAsignanMisRoles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Rol_Asignación',
            'id_usuario_recipiente',
            'id_usuario_asignador'
        )
        ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

}