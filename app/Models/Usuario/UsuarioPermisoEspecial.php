<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseUsuarioPermisoEspecial;

/**
 * Modelo UsuarioPermisoEspecial
 */
class UsuarioPermisoEspecial extends BaseUsuarioPermisoEspecial
{
    protected $casts = [
        'esta_activo' => 'boolean',
        'esta_permitido' => 'boolean',
        'puede_delegar' => 'boolean',
        'fue_borrado' => 'boolean',
    ];

    public $incrementing = false;
    protected $primaryKey = null; // Composite key

    protected $fillable = [
        'id_usuario_recipiente',
        'id_permiso',
        'id_contexto',
        'id_usuario_asignador',
        'fecha_inicio_planificada',
        'fecha_fin_planificada',
        'esta_permitido',
        'puede_delegar',
        'fecha_fin_real',
        'fue_borrado',
        'esta_activo'
    ];
}