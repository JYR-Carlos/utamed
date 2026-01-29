<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseUsuarioRolAsignación;

/**
 * Modelo UsuarioRolAsignación
 */
class UsuarioRolAsignación extends BaseUsuarioRolAsignación
{
    protected $casts = [
        'esta_activo' => 'boolean',
        'fue_eliminado' => 'boolean',
    ];

    public $incrementing = false;
    protected $primaryKey = null; // Composite key handled manually if needed

    protected $fillable = [
        'id_usuario_recipiente',
        'id_contexto',
        'id_rol',
        'id_usuario_asignador',
        'asignado_por',
        'fecha_inicio_planificada',
        'fecha_fin_planificada',
        'fecha_fin_real',
        'fue_eliminado',
        'esta_activo'
    ];
}