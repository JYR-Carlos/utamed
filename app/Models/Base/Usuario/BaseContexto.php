<?php

namespace App\Models\Base\Usuario;

use Awobaz\Compoships\Database\Eloquent\Model;

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

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        $qualified = parent::qualifyColumn($column);
        // Only quote if not already quoted and contains a dot (table.column)
        if (!str_contains($qualified, '\"') && str_contains($qualified, '.')) {
            return '\"' . str_replace('.', '\".\"', $qualified) . '\"';
        }
        return $qualified;
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return '\"' . $this->getTable() . '\".\"' . $this->getKeyName() . '\"';
    }


    // Relaciones

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
            'Usuario_Permiso_Especial',
            'id_contexto',
            'id_usuario'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function permisosEspecialesEnContexto()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Permiso::class,
            'Usuario_Permiso_Especial',
            'id_contexto',
            'id_permiso'
        )
        ->withPivot('fecha_inicio_planificada', 'fecha_fin_planificada', 'esta_permitido', 'puede_delegar', 'fecha_fin_real', 'fue_borrado', 'esta_activo');
    }

    public function usuariosConRolEnContexto()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Usuario::class,
            'Usuario_Rol_Asignación',
            'id_contexto',
            'id_usuario'
        )
        ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

    public function rolesEnContexto()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Rol::class,
            'Usuario_Rol_Asignación',
            'id_contexto',
            'id_rol'
        )
        ->withPivot('asignado_por', 'fecha_inicio_planificada', 'fecha_fin_planificada', 'fecha_fin_real', 'fue_eliminado', 'esta_activo');
    }

}