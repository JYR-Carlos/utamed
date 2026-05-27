<?php

namespace App\Http\Requests\Archive;

/**
 * CompressedFileRequest
 * 
 * Validación para uploads de archivos comprimidos.
 * 
 * Tipos permitidos: ZIP, RAR, 7Z, TAR, GZ, BZ2, TAR.GZ, TAR.BZ2
 * Tamaño máximo: 500MB (configurable via FILES_COMPRESSED_MAX_SIZE)
 * 
 * Uso:
 * ```php
 * class StoreActivityCompressedController {
 *     public function store(CompressedFileRequest $request) {
 *         $file = $request->getFile();
 *         // ...
 *     }
 * }
 * ```
 */
class CompressedFileRequest extends BaseArchiveRequest
{
    protected string $fileType = 'compressed';

    /**
     * Reglas adicionales específicas para archivos comprimidos.
     */
    protected function additionalRules(): array
    {
        return [
            'titulo' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'password_protected' => 'nullable|boolean',
        ];
    }

    /**
     * Mensajes personalizados para archivos comprimidos.
     */
    protected function customMessages(): array
    {
        return [
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 1000 caracteres.',
            'password_protected' => 'El estado de protección debe ser un valor booleano.',
        ];
    }
}
