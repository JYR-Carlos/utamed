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

    /**
     * Override qualifyColumn to ensure correct quoting for PostgreSQL case sensitivity
     */
    public function qualifyColumn($column)
    {
        // Check if the column is already qualified with the table name
        if (str_contains($column, '.')) {
            // Split into table and column
            [$table, $col] = explode('.', $column, 2);

            // If table matches our table or alias, ensure it's quoted if column is not
            if ($table === $this->getTable() || $table === 'Programa') {
                // If table is not quoted, quote it
                // We assume if it contains Quotes it is handled
                if (!str_contains($table, '"')) {
                    $table = '"' . $table . '"';
                }

                // If col is not quoted, quote it (optional but safe)
                if (!str_contains($col, '"') && $col !== '*') {
                    $col = '"' . $col . '"';
                }

                return "$table.$col";
            }
        }

        return '"' . $this->getTable() . '"."' . $column . '"';
    }

    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}