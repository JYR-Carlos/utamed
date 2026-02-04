<?php

namespace App\Models\Base\Usuario;

use Awobaz\Compoships\Database\Eloquent\Model;

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

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        // Usamos el comportamiento estándar de Eloquent
        return is_string($column) && str_contains($column, '.')
            ? $column
            : $this->getTable() . '.' . $column;
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return $this->getTable() . '.' . $this->getKeyName();
    }


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
            'Asignación_Rol_Permiso',
            'id_rol',
            'id_permiso'
        )
            ->withPivot('puede_delegar_permisos');
    }

    public function usuariosConRolAsignado()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Rol_Asignación',
            'id_rol',
            'id_usuario_recipiente'
        )
            ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function contextosConEsteRol()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Contexto::class,
            'Usuario_Rol_Asignación',
            'id_rol',
            'id_contexto'
        )
            ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

}