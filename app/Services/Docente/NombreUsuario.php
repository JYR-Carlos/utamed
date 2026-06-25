<?php

namespace App\Services\Docente;

use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Helper centralizado para la construcción del "nombre completo" de un usuario
 * dentro de consultas crudas (DB::raw).
 *
 * Es la contraparte SQL del accessor Eloquent Usuario::getNombreCompletoAttribute().
 * Mantener el formato canónico (nombre1 + nombre2 + apellido1 + apellido2, con los
 * segundos opcionales y espacios colapsados) en un solo lugar evita las ~8 copias
 * del mismo TRIM(CONCAT(...)) repartidas por los controladores docentes.
 */
final class NombreUsuario
{
    /**
     * Devuelve una expresión SQL TRIM(CONCAT(...)) para el nombre completo.
     *
     * @param  string  $alias  Alias de la tabla usuario en la consulta (p.ej. 'u').
     * @param  string  $as     Nombre de la columna resultante.
     */
    public static function sqlConcat(string $alias = 'u', string $as = 'nombre'): Expression
    {
        return DB::raw(
            "TRIM(CONCAT({$alias}.nombre1,' ',COALESCE({$alias}.nombre2,''),' ',{$alias}.apellido1,' ',COALESCE({$alias}.apellido2,''))) as {$as}"
        );
    }
}
