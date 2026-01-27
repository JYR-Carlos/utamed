<?php

namespace App\Models\Base\Usuario;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseUsuarioPermisoEspecial extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Usuario_Permiso_Especial';
    protected $primaryKey = 'id_usuario';
    public $incrementing = true;

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = [
        'fecha_inicio',
        'fecha_fin',
        'esta_permitido',
        'duracion_dias',
        'puede_delegar',
        'id_permiso',
        'id_contexto'
    ];

    protected $casts = [
        'esta_activo' => 'boolean',
    ];

    // Relaciones

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function permiso()
    {
        return $this->belongsTo(\App\Models\Usuario\Permiso::class, 'id_permiso', 'id_permiso');
    }

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

    // Scope para filtrar solo registros activos
    public function scopeActive($query)
    {
        return $query->whereRaw('esta_activo IS NOT NULL');
    }
}