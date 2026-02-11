<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;
use App\Contracts\HasContext;
use App\Traits\ContextAware;
use App\Traits\QueryScopes\FiltersContextScope;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseRol extends Model implements HasContext
{
    use Compoships;
    use ContextAware;
    use FiltersContextScope;
    public $timestamps = false;
    protected $connection = 'pgsql';
    protected $table = 'Rol';
    protected $primaryKey = 'id_rol';
    public $incrementing = true;

    protected $fillable = [
        'nombre',
        'creado_por'
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

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'creado_por', 'id_usuario');
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
            'id_usuario'
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
