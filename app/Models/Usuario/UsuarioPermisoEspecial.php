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

    /**
     * Set the keys for a save update query.
     * Use composite keys since this table doesn't have a single PK.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = ['id_usuario_recipiente', 'id_contexto', 'id_permiso', 'id_usuario_asignador'];

        foreach ($keys as $key) {
            $query->where($key, '=', $this->getAttribute($key));
        }

        return $query;
    }
}