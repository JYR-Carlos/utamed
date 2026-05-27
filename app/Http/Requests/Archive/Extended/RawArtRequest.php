<?php

namespace App\Http\Requests\Archive;

use Illuminate\Validation\Rule;

/**
 * RawArtRequest
 * 
 * Validación para uploads de archivos de arte raw (nativos de aplicaciones).
 * 
 * Incluye archivos de diseño (PSD, AI), archivos raw de cámara (CR2, NEF, ARW),
 * y otros formatos de arte digital sin procesar.
 * 
 * Tipos permitidos: PSD, AI, XCF, RAW, CR2, NEF, ARW, DNG, EPS, PDF
 * Tamaño máximo: 1GB (configurable via FILES_RAW_ART_MAX_SIZE)
 * 
 * Especialmente útil para repositorios de activos creativos.
 * 
 * Uso:
 * ```php
 * class StoreActivityRawArtController {
 *     public function store(RawArtRequest $request) {
 *         $file = $request->getFile();
 *         $artType = $request->input('tipo_arte');
 *         // ...
 *     }
 * }
 * ```
 */
class RawArtRequest extends BaseArchiveRequest
{
    protected string $fileType = 'raw_art';

    /**
     * Reglas adicionales específicas para arte raw.
     */
    protected function additionalRules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'tipo_arte' => [
                'nullable',
                Rule::in(['diseño', 'fotografia', 'ilustracion', 'video', 'audio', 'otro']),
            ],
            'software_origen' => [
                'nullable',
                Rule::in(['photoshop', 'illustrator', 'blender', 'maya', 'cinema4d', 
                          'canon', 'nikon', 'sony', 'otro']),
            ],
            'artista' => 'nullable|string|max:255',
            'licencia' => 'nullable|string|max:50',
            'etiquetas' => 'nullable|array|max:20',
            'etiquetas.*' => 'string|max:50',
            'es_composable' => 'nullable|boolean',
            'requiere_complementos' => 'nullable|string|max:500', // Plugins, fonts, etc.
        ];
    }

    /**
     * Mensajes personalizados para arte raw.
     */
    protected function customMessages(): array
    {
        return [
            'titulo.required' => 'El título es requerido.',
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 2000 caracteres.',
            'tipo_arte.in' => 'El tipo de arte no es válido.',
            'software_origen.in' => 'El software de origen no es válido.',
            'artista.max' => 'El nombre del artista no puede exceder 255 caracteres.',
            'licencia.max' => 'La licencia no puede exceder 50 caracteres.',
            'etiquetas.max' => 'No se pueden agregar más de 20 etiquetas.',
            'etiquetas.*.max' => 'Cada etiqueta no puede exceder 50 caracteres.',
            'requiere_complementos.max' => 'La descripción de complementos no puede exceder 500 caracteres.',
        ];
    }

    /**
     * Atributos personalizados para arte raw.
     */
    protected function customAttributes(): array
    {
        return [
            'tipo_arte' => 'tipo de arte',
            'software_origen' => 'software de origen',
            'es_composable' => 'si es composable',
            'requiere_complementos' => 'complementos requeridos',
        ];
    }
}
