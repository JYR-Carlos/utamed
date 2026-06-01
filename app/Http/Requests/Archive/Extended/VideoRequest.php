<?php

namespace App\Http\Requests\Archive\Extended;

use App\Http\Requests\Archive\BaseArchiveRequest;

/**
 * VideoRequest
 * 
 * Validación para uploads de videos.
 * 
 * Tipos permitidos: MP4, WebM, AVI, MOV, MKV, FLV, WMV, M4V
 * Tamaño máximo: 500MB (configurable via FILES_VIDEO_MAX_SIZE)
 * 
 * Uso:
 * ```php
 * class StoreActivityVideoController {
 *     public function store(VideoRequest $request) {
 *         $file = $request->getFile();
 *         // ...
 *     }
 * }
 * ```
 */
class VideoRequest extends BaseArchiveRequest
{
    protected string $fileType = 'video';

    /**
     * Reglas adicionales específicas para videos.
     */
    protected function additionalRules(): array
    {
        return [
            'titulo' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Mensajes personalizados para videos.
     */
    protected function customMessages(): array
    {
        return [
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 1000 caracteres.',
        ];
    }
}
