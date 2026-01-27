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
    const UPDATED_AT = null;

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

    protected $casts = [
        'esta_activo' => 'boolean',
    ];

    // Relaciones

    // Relaciones inversas

    public function programa()
    {
        return $this->hasOne(\App\Models\Administrativo\Programa::class, 'id_usuario', 'id_usuario');
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

    public function permisosEspecialesAsignados()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'id_usuario', 'id_usuario');
    }

    public function asignacionesRolRecibidas()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'id_usuario', 'id_usuario');
    }

    public function asignacionesRolRealizadas()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'asignado_por', 'id_usuario');
    }

    // Relaciones muchos-a-muchos

    public function permisosUPE()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Permiso::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_usuario',
            'id_permiso'
        )
        ->withPivot('fecha_inicio', 'fecha_fin', 'esta_permitido', 'duracion_dias', 'puede_delegar');
    }

    public function contextosUPE()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_usuario',
            'id_contexto'
        )
        ->withPivot('fecha_inicio', 'fecha_fin', 'esta_permitido', 'duracion_dias', 'puede_delegar');
    }

    public function contextosURA()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_usuario',
            'id_contexto'
        )
        ->withPivot('fecha_inicio', 'fecha_fin', 'duracion');
    }

    public function rolesURA()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Rol::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_usuario',
            'id_rol'
        )
        ->withPivot('fecha_inicio', 'fecha_fin', 'duracion');
    }

    public function usuariosURA()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_usuario',
            'asignado_por'
        )
        ->withPivot('fecha_inicio', 'fecha_fin', 'duracion');
    }

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}