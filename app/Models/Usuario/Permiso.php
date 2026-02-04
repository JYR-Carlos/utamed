<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BasePermiso;

/**
 * Modelo Permiso
 * 
 * Extiende de BasePermiso (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Permiso extends BasePermiso
{

    protected $fillable = ['slug', 'nombre', 'descripcion', 'modulo'];
    protected $table = 'Permiso';
    protected $primaryKey = 'id_permiso';
    /**
     * Override base relationship to use correct pivot table name.
     */
    public function roles()
    {
        return $this->belongsToMany(
            Rol::class,
            'Asignación_Rol_Permiso',
            'id_permiso',
            'id_rol'
        )->withPivot('puede_delegar_permisos');
    }
}