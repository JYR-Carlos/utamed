<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Acota el tamaño de página que llega por query string.
 *
 * Sin tope, `?per_page=1000000` convierte cualquier listado paginado en una
 * descarga completa de la tabla (y en un vector de agotamiento de memoria).
 */
trait LimitsPageSize
{
    /** Tope duro de filas por página para todos los listados. */
    private const MAX_PAGE_SIZE = 100;

    /** Tamaño de página por defecto. */
    private const DEFAULT_PAGE_SIZE = 15;

    /**
     * Tamaño de página saneado: entero dentro de [1, MAX_PAGE_SIZE].
     *
     * `integer()` devuelve 0 para valores no numéricos o ausentes tras el
     * default, de ahí el max(1, …).
     */
    protected function perPage(Request $request, int $default = self::DEFAULT_PAGE_SIZE): int
    {
        $requested = $request->integer('per_page', $default);

        return max(1, min($requested, self::MAX_PAGE_SIZE));
    }
}
