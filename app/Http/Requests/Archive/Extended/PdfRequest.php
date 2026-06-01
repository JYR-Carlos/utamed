<?php

namespace App\Http\Requests\Archive\Extended;

use App\Http\Requests\Archive\BaseArchiveRequest;

/**
 * PdfRequest
 * 
 * Validación para uploads de archivos PDF.
 * 
 * Tipos permitidos: PDF
 * Tamaño máximo: 100MB (configurable via FILES_PDF_MAX_SIZE)
 * 
 * Uso:
 * ```php
 * class StoreActivityPdfController {
 *     public function store(PdfRequest $request) {
 *         $file = $request->getFile();
 *         // ...
 *     }
 * }
 * ```
 */
class PdfRequest extends BaseArchiveRequest
{
    protected string $fileType = 'pdf';

    /**
     * Reglas adicionales específicas para PDFs.
     */
    protected function additionalRules(): array
    {
        return [
            'titulo' => 'nullable|string|max:255',
            'autor' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'numero_paginas' => 'nullable|integer|min:1|max:5000',
        ];
    }

    /**
     * Mensajes personalizados para PDFs.
     */
    protected function customMessages(): array
    {
        return [
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'autor.max' => 'El autor no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 1000 caracteres.',
            'numero_paginas.min' => 'Debe tener al menos 1 página.',
            'numero_paginas.max' => 'No puede tener más de 5000 páginas.',
        ];
    }
}
