<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BasePrograma;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modelo Programa
 * 
 * Extiende de BasePrograma (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Programa extends BasePrograma
{
    use HasFactory;
    public function getRouteKeyName()
    {
        return 'id_programa';
    }

    protected $primaryKey = 'id_programa';
    public $incrementing = true;

    protected $table = 'Programa';

    protected $fillable = [
        'id_curso',
        'es_plantilla',
        'version_programa',
        'unc_programa',
        'es_actual',
        'fecha_creacion',
        'creado_por'
    ];

    /**
     * Fix for double quoting issue with Compoships.
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


    // Relaciones adicionales

    /**
     * Override newHasMany to use custom HasMany with proper PostgreSQL quoting
     */
    protected function newHasMany(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $parent, $foreignKey, $localKey)
    {
        return new \App\Extensions\Compoships\HasMany($query, $parent, $foreignKey, $localKey);
    }

    // Accessors/Mutators
    // etc.
}