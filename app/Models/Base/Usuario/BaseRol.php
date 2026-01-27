<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseRol extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Rol';
    protected $primaryKey = 'id_rol';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'id_usuario_autor'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
    ];

    // Relaciones

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario_autor', 'id_usuario');
    }

    // Relaciones inversas

    public function asignaciónRolPermisos()
    {
        return $this->hasMany(\App\Models\Usuario\AsignaciónRolPermiso::class, 'id_rol', 'id_rol');
    }

    public function usuarioRolAsignacciones()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignación::class, 'id_rol', 'id_rol');
    }

    // Relaciones muchos-a-muchos

    public function permisos()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Permiso::class,
            '\"utamed.Usuario\".\"Asignación_Rol_Permiso\"',
            'id_rol',
            'id_permiso'
        )
        ->withPivot('puede_delegar_permisos');
    }

    public function usuariosURA()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_rol',
            'id_usuario'
        )
        ->withPivot('fecha_inicio', 'fecha_fin', 'duracion');
    }

    public function contextosURA()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_rol',
            'id_contexto'
        )
        ->withPivot('fecha_inicio', 'fecha_fin', 'duracion');
    }

    public function usuariosURA1()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_rol',
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