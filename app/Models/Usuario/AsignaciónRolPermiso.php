<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseAsignaciónRolPermiso;

/**
 * Modelo AsignaciónRolPermiso
 */
class AsignaciónRolPermiso extends BaseAsignaciónRolPermiso
{
    use \App\Traits\HasCompositeKey;

    protected $casts = [
        'puede_delegar_permisos' => 'boolean',
    ];

    // public $incrementing = false; // Inherited
    // protected $primaryKey = null; // Inherited

    protected $fillable = [
        'id_rol',
        'id_permiso',
        'puede_delegar_permisos'
    ];

    public function getRouteKeyName()
    {
        return 'id_rol';
    }
}