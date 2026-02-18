<?php

namespace App\Models\Base\Usuario;

use App\Models\BaseModel as CustomBaseModel;
use Awobaz\Compoships\Compoships;
use App\Extensions\Compoships\BelongsTo;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseContexto extends CustomBaseModel
{
    use Compoships;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'contexto';
    protected $primaryKey = 'id_contexto';
    public $incrementing = true;

    protected $fillable = [
        'id_contexto',
        'contexto_display',
        'id_tipo_contexto'
    ];


    // Relaciones

    public function tipoContexto()
    {
        $instance = new \App\Models\Usuario\TipoContexto();
        return new BelongsTo($instance->newQuery(), $this, 'id_tipo_contexto', 'id_tipo_contexto', 'tipoContexto');
    }

    // Relaciones inversas

    public function carrera()
    {
        return $this->hasOne(\App\Models\Administrativo\Carrera::class, 'id_contexto', 'id_contexto');
    }

    public function departamento()
    {
        return $this->hasOne(\App\Models\Administrativo\Departamento::class, 'id_contexto', 'id_contexto');
    }

    public function facultad()
    {
        return $this->hasOne(\App\Models\Administrativo\Facultad::class, 'id_contexto', 'id_contexto');
    }

    public function actividad()
    {
        return $this->hasOne(\App\Models\Agenda\Actividad::class, 'id_contexto', 'id_contexto');
    }

    public function curso()
    {
        return $this->hasOne(\App\Models\Curso\Curso::class, 'id_contexto', 'id_contexto');
    }

    public function usuarioPermisoEspeciales()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioPermisoEspecial::class, 'id_contexto', 'id_contexto');
    }

    public function usuarioRolAsignaciones()
    {
        return $this->hasMany(\App\Models\Usuario\UsuarioRolAsignacion::class, 'id_contexto', 'id_contexto');
    }

    // Relaciones muchos-a-muchos

    public function usuariosConPermisoEspecialEnContexto()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_permiso_especial',
            'id_contexto',
            'id_usuario'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function permisosEspecialesEnContexto()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Permiso::class,
            'usuario_permiso_especial',
            'id_contexto',
            'id_permiso'
        )
            ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosConRolEnContexto()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'usuario_rol_asignacion',
            'id_contexto',
            'id_usuario'
        )
            ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function rolesEnContexto()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Rol::class,
            'usuario_rol_asignacion',
            'id_contexto',
            'id_rol'
        )
            ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

}
