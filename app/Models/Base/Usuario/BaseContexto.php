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

    public function usuariosConPermisoEspecialEnContexto()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_contexto',
            'id_usuario_recipiente'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function permisosEspecialesEnContexto()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Permiso::class,
            '\"utamed.Usuario\".\"Usuario_Permiso_Especial\"',
            'id_contexto',
            'id_permiso'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosConRolEnContexto()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_contexto',
            'id_usuario_recipiente'
        )
        ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function rolesEnContexto()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Rol::class,
            '\"utamed.Usuario\".\"Usuario_Rol_Asignación\"',
            'id_contexto',
            'id_rol'
        )
        ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

}