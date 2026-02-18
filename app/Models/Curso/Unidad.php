<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseUnidad;
use App\Traits\HasCompositeKey;

/**
 * Modelo Unidad
 * 
 * Extiende de BaseUnidad (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Unidad extends BaseUnidad
{
    use HasCompositeKey;

    protected $fillable = [
        'id_curso',
        'es_plantilla',
        'num_unidad',
        'nombre',
        'descripcion'
    ];

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

    public function getRouteKeyName()
    {
        return 'id_unidad';
    }

    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}
