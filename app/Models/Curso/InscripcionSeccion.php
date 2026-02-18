<?php

namespace App\Models\Curso;

use App\Extensions\Compoships\BelongsTo as CompoBelongsTo;
use App\Models\Base\Curso\BaseInscripcionSeccion;

/**
 * Modelo InscripcionSeccion
 * 
 * Extiende de BaseInscripcionSeccion (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class InscripcionSeccion extends BaseInscripcionSeccion
{
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
        return 'id_estudiante';
    }

    /**
     * Override seccion() to use the custom BelongsTo extension that correctly
     * quotes table names for PostgreSQL composite-key eager loading.
     *
     * Compoships' built-in belongsTo generates: (Seccion.id_seccion, Seccion.id_curso) IN (...)
     * PostgreSQL requires quoted identifiers:   ("Seccion"."id_seccion", "Seccion"."id_curso") IN (...)
     */
    public function seccion()
    {
        $instance = new Seccion();

        return new CompoBelongsTo(
            $instance->newQuery(),
            $this,
            ['id_seccion', 'id_curso'],
            ['id_seccion', 'id_curso'],
            'seccion'
        );
    }

    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}
