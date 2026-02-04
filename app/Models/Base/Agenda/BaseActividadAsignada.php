<?php

namespace App\Models\Base\Agenda;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class BaseActividadAsignada extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'Actividad_Asignada';
    protected $primaryKey = ['grupo', 'id_actividad'];
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'nota',
        'id_estado'
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

    public function actividad()
    {
        return $this->belongsTo(\App\Models\Agenda\Actividad::class, 'id_actividad', 'id_actividad');
    }

    public function estadoActividad()
    {
        return $this->belongsTo(\App\Models\Agenda\EstadoActividad::class, 'id_estado', 'id_estado');
    }

    // Relaciones inversas

    public function asignadoActividades()
    {
        return $this->hasMany(\App\Models\Agenda\AsignadoActividad::class, ['grupo', 'id_actividad'], ['grupo', 'id_actividad']);
    }

    // Relaciones muchos-a-muchos

    public function estudiantesAsignados()
    {
        return $this->belongsToMany(
            \App\Models\Usuario\Estudiante::class,
            'Asignado_Actividad',
            'grupo,id_actividad',
            'id_estudiante'
        )
        ->withPivot('nota_individual', 'diferencia_decimas');
    }

}