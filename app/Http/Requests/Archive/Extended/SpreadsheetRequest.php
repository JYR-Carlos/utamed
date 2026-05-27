<?php

namespace App\Http\Requests\Archive;

/**
 * SpreadsheetRequest
 * 
 * Validación para uploads de hojas de cálculo.
 * 
 * Tipos permitidos: XLS, XLSX, XLSM, XLT, XLTX, XLTM, ODS, NUMBERS, CSV, TSV
 * Tamaño máximo: 50MB (configurable via FILES_SPREADSHEET_MAX_SIZE)
 * 
 * Soporta Microsoft Excel, OpenDocument Spreadsheet, Apple Numbers, y CSV/TSV.
 * 
 * Uso:
 * ```php
 * class StoreActivitySpreadsheetController {
 *     public function store(SpreadsheetRequest $request) {
 *         $file = $request->getFile();
 *         // ...
 *     }
 * }
 * ```
 */
class SpreadsheetRequest extends BaseArchiveRequest
{
    protected string $fileType = 'spreadsheet';

    /**
     * Reglas adicionales específicas para hojas de cálculo.
     */
    protected function additionalRules(): array
    {
        return [
            'titulo' => 'nullable|string|max:255',
            'autor' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'numero_hojas' => 'nullable|integer|min:1|max:255',
        ];
    }

    /**
     * Mensajes personalizados para hojas de cálculo.
     */
    protected function customMessages(): array
    {
        return [
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'autor.max' => 'El autor no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 1000 caracteres.',
            'numero_hojas.min' => 'Debe tener al menos 1 hoja.',
            'numero_hojas.max' => 'No puede tener más de 255 hojas.',
        ];
    }
}
