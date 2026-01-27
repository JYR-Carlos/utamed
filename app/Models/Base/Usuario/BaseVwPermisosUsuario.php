<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseVwPermisosUsuario extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'vw_permisos_usuario';
    protected $primaryKey = 'id';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_contexto',
        'slug',
        'tipo_asignacion'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
    ];

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}