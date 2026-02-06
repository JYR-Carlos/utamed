<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseUsuarioPermisoEspecial;

/**
 * Modelo UsuarioPermisoEspecial
 */
class UsuarioPermisoEspecial extends BaseUsuarioPermisoEspecial
{

    use \App\Traits\HasCompositeKey;

    protected $casts = [
        'esta_activo' => 'boolean',
        'esta_permitido' => 'boolean',
        'puede_delegar' => 'boolean',
        'fue_borrado' => 'boolean',
    ];

    // public $incrementing = false; // Inherited
    // protected $primaryKey = null; // Inherited

    protected $fillable = [
        'id_usuario_recipiente',
        'id_contexto',
        'id_permiso',
        'id_usuario_asignador',
        'fecha_inicio_planificada',
        'fecha_fin_planificada',
        'esta_permitido',
        'puede_delegar',
        'fecha_fin_real',
        'fue_borrado',
        'esta_activo',
        'fecha_creacion',
        'fecha_modificacion'
    ];

    /**
     * Fix for double quoting issue in BaseUsuarioPermisoEspecial.
     * Reverts to standard Eloquent behavior.
     */
    public function qualifyColumn($column)
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    public function getQualifiedKeyName()
    {
        return $this->qualifyColumn($this->getKeyName());
    }

    public function getRouteKeyName()
    {
        return 'id_usuario_recipiente';
    }
}