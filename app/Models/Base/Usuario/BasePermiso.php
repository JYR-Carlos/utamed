<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BasePermiso extends Model
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Permiso';
    protected $primaryKey = 'id_permiso';
    public $incrementing = true;

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
            'Asignación_Rol_Permiso',
            'id_permiso',
            'id_rol'
        )
            ->withPivot('puede_delegar_permisos');
    }

    public function usuariosConPermisoEspecial()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Permiso_Especial',
            'id_permiso',
            'id_usuario'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function contextosConEstePermiso()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            'Usuario_Permiso_Especial',
            'id_permiso',
            'id_contexto'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

}
