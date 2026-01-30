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

    /**
     * Set the keys for a save update query.
     * Use composite keys since this table doesn't have a single PK.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = ['id_usuario_recipiente', 'id_contexto', 'id_rol', 'id_usuario_asignador'];

        foreach ($keys as $key) {
            $query->where($key, '=', $this->getAttribute($key));
        }

        return $query;
    }
}