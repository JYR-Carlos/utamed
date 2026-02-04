<?php

namespace App\Models\Base\Usuario;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseUsuarioPermisoEspecial extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Usuario_Permiso_Especial';
    protected $primaryKey = ['id_permiso', 'id_contexto', 'id_usuario_recipiente', 'id_usuario_asignador'];
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'fecha_inicio_planificada',
        'fecha_fin_planificada',
        'esta_permitido',
        'puede_delegar',
        'fecha_fin_real',
        'fue_borrado'
    ];

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        $qualified = parent::qualifyColumn($column);
        // Only quote if not already quoted and contains a dot (table.column)
        if (!str_contains($qualified, '\"') && str_contains($qualified, '.')) {
            return '\"' . str_replace('.', '\".\"', $qualified) . '\"';
        }
        return $qualified;
    }

    /**
     * Override getQualifiedKeyName to ensure correct quoting
     */
    public function getQualifiedKeyName()
    {
        return '\"' . $this->getTable() . '\".\"' . $this->getKeyName() . '\"';
    }


    // Relaciones

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario_recipiente', 'id_usuario');
    }

    public function permiso()
    {
        return $this->belongsTo(\App\Models\Usuario\Permiso::class, 'id_permiso', 'id_permiso');
    }

    public function contexto()
    {
        return $this->belongsTo(\App\Models\Usuario\Contexto::class, 'id_contexto', 'id_contexto');
    }

    public function usuario1()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario_asignador', 'id_usuario');
    }

}