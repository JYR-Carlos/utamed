<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseUsuarioRolAsignación;

/**
 * Modelo UsuarioRolAsignación
 */
class UsuarioRolAsignación extends BaseUsuarioRolAsignación
{

    use \App\Traits\HasCompositeKey;

    protected $casts = [
        'esta_activo' => 'boolean',
        'fue_eliminado' => 'boolean',
    ];

    // public $incrementing = false; // Inherited from Base
    // protected $primaryKey = null; // Removed to inherit from Base array

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
        'esta_activo',
        'fecha_creacion',
        'fecha_modificacion'
    ];

    public function getRouteKeyName()
    {
        return 'id_usuario_recipiente';
    }
}