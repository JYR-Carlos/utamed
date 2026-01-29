<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseAsignaciónRolPermiso;

/**
 * Modelo AsignaciónRolPermiso
 */
class AsignaciónRolPermiso extends BaseAsignaciónRolPermiso
{
    protected $casts = [
        'puede_delegar_permisos' => 'boolean',
    ];

    public $incrementing = false;
    protected $primaryKey = null; // Composite key

    protected $fillable = [
        'id_rol',
        'id_permiso',
        'puede_delegar_permisos'
    ];
}