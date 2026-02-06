<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseEstudiante;

/**
 * Modelo Estudiante
 * 
 * Extiende de BaseEstudiante (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Estudiante extends BaseEstudiante
{
    protected $fillable = [
        'rut',
        'nombre_completo',
        'agno_ingreso',
        'id_carrera',
        'id_usuario',
        'id_contexto'
    ];

    /**
     * Fix for double quoting issue in BaseEstudiante.
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