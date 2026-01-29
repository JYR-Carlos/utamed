<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;

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
        'email',
        'nombre1',
        'nombre2',
        'apellido1',
        'apellido2',
        'rut'
    ];

    // Relaciones

    // Relaciones inversas

    public function programasCreados()
    {
        return $this->hasMany(\App\Models\Administrativo\Programa::class, 'id_usuario_autor', 'id_usuario');
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
        return $this->hasMany(\App\Models\Usuario\Rol::class, 'id_usuario_autor', 'id_usuario');
    }

    public function permisosEspecialesRecibidos()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'id_usuario_recipiente', 'id_usuario');
    }

    public function permisosEspecialesAsignados()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'id_usuario_asignador', 'id_usuario');
    }

    public function asignacionesRolRecibidas()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'id_usuario_recipiente', 'id_usuario');
    }

    public function asignacionesRolRealizadas()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'id_usuario_asignador', 'id_usuario');
    }

    // Relaciones muchos-a-muchos

    public function permisosEspeciales()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Permiso::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_usuario_recipiente',
            'id_permiso'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function contextosConPermisoEspecial()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_usuario_recipiente',
            'id_contexto'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueRecibenMisPermisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_usuario_asignador',
            'id_usuario_recipiente'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosQueAsignanMisPermisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_usuario_recipiente',
            'id_usuario_asignador'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function contextosConRolAsignado()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_usuario_recipiente',
            'id_contexto'
        )
        ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function rolesAsignados()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Rol::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_usuario_recipiente',
            'id_rol'
        )
        ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueRecibenMisRoles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_usuario_asignador',
            'id_usuario_recipiente'
        )
        ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosQueAsignanMisRoles()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_usuario_recipiente',
            'id_usuario_asignador'
        )
        ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

}