<?php

namespace App\Models\Base\Agenda;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseEstadoActividad extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Estado_Actividad';
    protected $primaryKey = 'id_estado';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'descripcion'
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

    // Relaciones inversas

    public function actividadAsignadas()
    {
        return $this->hasMany(\App\Models\Agenda\ActividadAsignada::class, 'id_estado', 'id_estado');
    }

    // Relaciones muchos-a-muchos

    public function actividadesConEstado()
    {
        return $this->belongsToMany(
            \App\Models\Agenda\Actividad::class,
            'Actividad_Asignada',
            'id_estado',
            'id_actividad'
        )
        ->withPivot('grupo', 'nota');
    }

}
