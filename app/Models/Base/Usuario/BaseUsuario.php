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

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_modificacion';

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

    public function programas()
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

    public function usuarioPermisoEspeciales()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'id_usuario_recipiente', 'id_usuario');
    }

    public function usuarioPermisoEspeciales1()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'id_usuario_asignador', 'id_usuario');
    }

    public function usuarioRolAsignacciones()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'id_usuario_recipiente', 'id_usuario');
    }

    public function usuarioRolAsignacciones1()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'id_usuario_asignador', 'id_usuario');
    }

    // Relaciones muchos-a-muchos

    public function permisosUPE()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Permiso::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_usuario_recipiente',
            'id_permiso'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function contextosUPE()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_usuario_recipiente',
            'id_contexto'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosUPE()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_usuario_recipiente',
            'id_usuario_asignador'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function contextosURA()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_usuario_recipiente',
            'id_contexto'
        )
        ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function rolesURA()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Rol::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_usuario_recipiente',
            'id_rol'
        )
        ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function usuariosURA()
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