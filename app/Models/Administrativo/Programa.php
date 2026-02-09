<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BasePrograma;

/**
 * Modelo Programa
 * 
 * Extiende de BasePrograma (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Programa extends BasePrograma
{
    public function getRouteKeyName()
    {
        return 'id_programa';
    }

    protected $fillable = [
        'id_curso',
        'es_plantilla',
        'version',
        'unc_programa',
        'id_usuario_autor',
        'es_actual',
        'fecha_creacion'
    ];



    /**
     * Fix: Override BasePrograma quoting to prevent double-escaping backslashes.
     * Uses standard double quotes for PostgreSQL.
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
    // Accessors/Mutators
    // etc.
}