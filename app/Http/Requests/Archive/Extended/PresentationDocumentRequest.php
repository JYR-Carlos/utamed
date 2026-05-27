<?php

namespace App\Http\Requests\Archive;

/**
 * PresentationDocumentRequest
 * 
 * Validación para uploads de presentaciones.
 * 
 * Tipos permitidos: PPT, PPTX, PPTM, POT, POTX, POTM, ODP, KEY
 * Tamaño máximo: 100MB (configurable via FILES_PRESENTATION_MAX_SIZE)
 * 
 * Soporta Microsoft PowerPoint, OpenDocument Presentation y Keynote.
 * 
 * Uso:
 * ```php
 * class StoreActivityPresentationController {
 *     public function store(PresentationDocumentRequest $request) {
 *         $file = $request->getFile();
 *         // ...
 *     }
 * }
 * ```
 */
class PresentationDocumentRequest extends BaseArchiveRequest
{
    protected string $fileType = 'presentation';

    /**
     * Reglas adicionales específicas para presentaciones.
     */
    protected function additionalRules(): array
    {
        return [
            'titulo' => 'nullable|string|max:255',
            'autor' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'numero_diapositivas' => 'nullable|integer|min:1|max:500',
        ];
    }

    /**
     * Mensajes personalizados para presentaciones.
     */
    protected function customMessages(): array
    {
        return [
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'autor.max' => 'El autor no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 1000 caracteres.',
            'numero_diapositivas.min' => 'Debe tener al menos 1 diapositiva.',
            'numero_diapositivas.max' => 'No puede tener más de 500 diapositivas.',
        ];
    }
}
