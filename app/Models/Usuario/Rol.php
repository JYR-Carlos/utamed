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

    /**
     * Override base relationship to use correct pivot table name.
     */
    public function permisos()
    {
        return $this->belongsToMany(
            Permiso::class,
            'utamed.Asignación_Rol_Permiso',
            'id_rol',
            'id_permiso'
        )->withPivot('puede_delegar_permisos');
    }
}