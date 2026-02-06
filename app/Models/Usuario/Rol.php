<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseRol;

/**
 * Modelo Rol
 * 
 * Extiende de BaseRol (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Rol extends BaseRol
{
    protected $fillable = ['nombre', 'id_usuario_autor'];
    protected $table = 'Rol';
    protected $primaryKey = 'id_rol';
    /**
     * Override base relationship to use correct pivot table name.
     */
    public function permisos()
    {
        return $this->belongsToMany(
            Permiso::class,
            'Asignación_Rol_Permiso',
            'id_rol',
            'id_permiso'
        )->withPivot('puede_delegar_permisos');
    }

    /**
     * Fix for double quoting issue in BaseRol.
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
}