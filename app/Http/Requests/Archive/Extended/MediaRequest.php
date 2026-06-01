<?php

namespace App\Http\Requests\Archive\Extended;

use App\Http\Requests\Archive\BaseArchiveRequest;

use Illuminate\Validation\Rule;

/**
 * MediaRequest
 * 
 * Validación para uploads de archivos multimedia (superset).
 * 
 * Combina videos, imágenes, audio y otros formatos multimedia.
 * Especialmente útil para galerías, portfolios, o contenido creativo.
 * 
 * Tipos permitidos: MP4, WebM, AVI, MOV, MKV, MP3, WAV, FLAC, PNG, JPG, 
 *                   JPEG, WebP, GIF, BMP, SVG, TIFF, PSD, AI, RAW, etc.
 * Tamaño máximo: 1GB (configurable via FILES_MEDIA_MAX_SIZE)
 * 
 * Uso:
 * ```php
 * class StoreActivityMediaController {
 *     public function store(MediaRequest $request) {
 *         $file = $request->getFile();
 *         $mediaType = $request->input('tipo_media');
 *         // ...
 *     }
 * }
 * ```
 */
class MediaRequest extends BaseArchiveRequest
{
    protected string $fileType = 'media';

    /**
     * Reglas adicionales específicas para multimedia.
     */
    protected function additionalRules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'tipo_media' => [
                'nullable',
                Rule::in(['video', 'audio', 'image', 'vector', 'raw_art']),
            ],
            'etiquetas' => 'nullable|array|max:20',
            'etiquetas.*' => 'string|max:50',
            'duracion_segundos' => 'nullable|integer|min:0|max:86400', // 24 horas máx
            'resolucion' => 'nullable|string|max:50', // ej: "1920x1080"
            'fps' => 'nullable|integer|min:1|max:120', // Para videos
        ];
    }

    /**
     * Mensajes personalizados para multimedia.
     */
    protected function customMessages(): array
    {
        return [
            'titulo.required' => 'El título es requerido.',
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 2000 caracteres.',
            'tipo_media.in' => 'El tipo de media no es válido.',
            'etiquetas.max' => 'No se pueden agregar más de 20 etiquetas.',
            'etiquetas.*.max' => 'Cada etiqueta no puede exceder 50 caracteres.',
            'duracion_segundos.min' => 'La duración debe ser 0 o positiva.',
            'duracion_segundos.max' => 'La duración no puede exceder 24 horas.',
            'fps.min' => 'Los FPS deben ser al menos 1.',
            'fps.max' => 'Los FPS no pueden exceder 120.',
        ];
    }
}
