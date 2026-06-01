<?php

namespace App\Http\Requests\Archive\Extended;

use App\Http\Requests\Archive\BaseArchiveRequest;

/**
 * WordDocumentRequest
 * 
 * Validación para uploads de documentos de texto.
 * 
 * Tipos permitidos: DOC, DOCX, DOCM, DOT, DOTX, DOTM, RTF, ODT, TXT
 * Tamaño máximo: 50MB (configurable via FILES_WORD_DOCUMENT_MAX_SIZE)
 * 
 * Soporta formatos de Microsoft Word, OpenDocument y RTF.
 * 
 * Uso:
 * ```php
 * class StoreActivityDocumentController {
 *     public function store(WordDocumentRequest $request) {
 *         $file = $request->getFile();
 *         // ...
 *     }
 * }
 * ```
 */
class WordDocumentRequest extends BaseArchiveRequest
{
    protected string $fileType = 'word_document';

    /**
     * Reglas adicionales específicas para documentos de texto.
     */
    protected function additionalRules(): array
    {
        return [
            'titulo' => 'nullable|string|max:255',
            'autor' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Mensajes personalizados para documentos de texto.
     */
    protected function customMessages(): array
    {
        return [
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'autor.max' => 'El autor no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 1000 caracteres.',
        ];
    }
}
