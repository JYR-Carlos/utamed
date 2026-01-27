<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseContexto extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Contexto';
    protected $primaryKey = 'id_contexto';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'contexto_display'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
    ];

    // Relaciones

    // Relaciones inversas

    public function facultad()
    {
        return $this->hasOne(\App\Models\Administrativo\Facultad::class, 'id_contexto', 'id_contexto');
    }

    public function curso()
    {
        return $this->hasOne(\App\Models\Curso\Curso::class, 'id_contexto', 'id_contexto');
    }

    public function tipoContextos()
    {
        return $this->hasMany(\App\Models\Usuario\TipoContexto::class, 'id_contexto', 'id_contexto');
    }

    public function usuarioPermisoEspeciales()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'id_contexto', 'id_contexto');
    }

    public function usuarioRolAsignacciones()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'id_contexto', 'id_contexto');
    }

    // Relaciones muchos-a-muchos

    public function usuariosUPE()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_contexto',
            'id_usuario'
        )
        ->withPivot('fecha_inicio', 'fecha_fin', 'esta_permitido', 'duracion_dias', 'puede_delegar');
    }

    public function permisosUPE()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Permiso::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_contexto',
            'id_permiso'
        )
        ->withPivot('fecha_inicio', 'fecha_fin', 'esta_permitido', 'duracion_dias', 'puede_delegar');
    }

    public function usuariosURA()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_contexto',
            'id_usuario'
        )
        ->withPivot('fecha_inicio', 'fecha_fin', 'duracion');
    }

    public function rolesURA()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Rol::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_contexto',
            'id_rol'
        )
        ->withPivot('fecha_inicio', 'fecha_fin', 'duracion');
    }

    public function usuariosURA1()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_contexto',
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