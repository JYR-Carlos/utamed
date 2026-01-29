<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BasePermiso extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Permiso';
    protected $primaryKey = 'id_permiso';
    public $incrementing = true;

      public $timestamps = false;

    protected $fillable = [
        'slug',
        'nombre',
        'descripcion'
    ];

    // Relaciones

    // Relaciones inversas

    public function asignaciónRolPermisos()
    {
        return $this->hasMany(\App\Models\Usuario\AsignaciónRolPermiso::class, 'id_permiso', 'id_permiso');
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
            '\"utamed.Usuario\".\"Asignación_Rol_Permiso\"',
            'id_permiso',
            'id_rol'
        )
        ->withPivot('puede_delegar_permisos');
    }

    public function usuariosConPermisoEspecial()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_permiso',
            'id_usuario_recipiente'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function contextosConEstePermiso()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_permiso',
            'id_contexto'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

}