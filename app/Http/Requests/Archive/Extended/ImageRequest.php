<?php

namespace App\Http\Requests\Archive\Extended;

use App\Http\Requests\Archive\BaseArchiveRequest;

/**
 * ImageRequest
 * 
 * Validación para uploads de imágenes (raster y vectoriales).
 * 
 * Tipos permitidos: PNG, JPG, JPEG, WebP, GIF, BMP, SVG, TIFF
 * Tamaño máximo: 50MB (configurable via FILES_IMAGE_MAX_SIZE)
 * 
 * Las imágenes se optimizarán automáticamente a WebP durante el almacenamiento.
 * 
 * Uso:
 * ```php
 * class StoreActivityImageController {
 *     public function store(ImageRequest $request) {
 *         $file = $request->getFile();
 *         // ...
 *     }
 * }
 * ```
 */
class ImageRequest extends BaseArchiveRequest
{
    protected string $fileType = 'image';

    /**
     * Reglas adicionales específicas para imágenes.
     */
    protected function additionalRules(): array
    {
        return [
            'titulo' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'etiquetas' => 'nullable|array|max:10',
            'etiquetas.*' => 'string|max:50',
        ];
    }

    /**
     * Mensajes personalizados para imágenes.
     */
    protected function customMessages(): array
    {
        return [
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 1000 caracteres.',
            'etiquetas.max' => 'No se pueden agregar más de 10 etiquetas.',
            'etiquetas.*.max' => 'Cada etiqueta no puede exceder 50 caracteres.',
        ];
    }
}
