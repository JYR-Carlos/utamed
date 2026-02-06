<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BaseAsignatura;

/**
 * Modelo Asignatura
 * 
 * Extiende de BaseAsignatura (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Asignatura extends BaseAsignatura
{
    protected $table = 'Asignatura';
    protected $fillable = [
        'cod_asignatura',
        'nombre',
        'descripcion',
        'creditos_sct',
        'horas_catedra',
        'horas_taller',
        'horas_laboratorio',
        'horas_dirigidas',
        'horas_autonomas',
        'id_contexto'
    ];
    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.

    /**
     * Fix for double quoting issue in BaseAsignatura.
     * Reverts to standard Eloquent behavior.
     */
    public function qualifyColumn($column)
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    public function getQualifiedKeyName()
    {
        return $this->qualifyColumn($this->getKeyName());
    }
}