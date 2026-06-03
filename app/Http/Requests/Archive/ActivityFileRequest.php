<?php

namespace App\Http\Requests\Archive;

use App\Rules\Builders\FileRequirementType;

/**
 * ActivityFileRequest
 * 
 * Validación para uploads de archivos auxiliares de actividades (imágenes y PDFs).
 * 
 * Tipos permitidos: 
 * - Imágenes: PNG, JPG, JPEG, WebP, GIF, BMP, SVG, TIFF
 * - PDFs
 * 
 * Tamaño máximo: 
 * - Imágenes: 50MB (configurable via FILES_IMAGE_MAX_SIZE)
 * - PDFs: 100MB (configurable via FILES_PDF_MAX_SIZE)
 * 
 * Las imágenes se optimizarán automáticamente a WebP durante el almacenamiento.
 * 
 * Uso:
 * ```php
 * class StoreActivityFileController {
 *     public function store(ActivityFileRequest $request) {
 *         $file = $request->getFile();
 *         // ...
 *     }
 * }
 * ```
 * 
 * Related:
 * - ActivityArchiveService: File storage and compression
 * - ActivityArchiveHandler: Path building and delegation
 */
class ActivityFileRequest extends BaseArchiveRequest
{
  /**
   * 
   */
  protected array $fileCategories = [
    FileRequirementType::IMAGE,
    FileRequirementType::PDF,
  ];

  /**
   * Campo que contiene el archivo (nombre del parámetro del form).
   */
  protected string $fileField = 'archivo';

  /**
   * Reglas adicionales combinadas de imagen + PDF.
   */
  protected function additionalRules(): array
  {
    return [
      // Metadatos comunes
      'titulo' => 'nullable|string|max:255',
      'descripcion' => 'nullable|string|max:1000',

      // Metadatos específicos de imagen
      'etiquetas' => 'nullable|array|max:10',
      'etiquetas.*' => 'string|max:50',

      // Metadatos específicos de PDF
      'autor' => 'nullable|string|max:255',
      'numero_paginas' => 'nullable|integer|min:1|max:5000',

      // Nombre personalizado
      'nombre_archivo' => [
        'nullable',
        'string',
        'max:255',
        'regex:/^[\pL\pN\s._\-]+$/u', // UTF-8
      ],
    ];
  }

  /**
   * Mensajes personalizados para validación.
   */
  public function customMessages(): array
  {
    return [
      // Metadatos comunes
      'titulo.max' => 'El título no puede exceder 255 caracteres.',
      'descripcion.max' => 'La descripción no puede exceder 1000 caracteres.',

      // Imagen
      'etiquetas.max' => 'No se pueden agregar más de 10 etiquetas.',
      'etiquetas.*.max' => 'Cada etiqueta no puede exceder 50 caracteres.',

      // PDF
      'autor.max' => 'El autor no puede exceder 255 caracteres.',
      'numero_paginas.min' => 'Debe tener al menos 1 página.',
      'numero_paginas.max' => 'No puede tener más de 5000 páginas.',

      // Nombre archivo
      'nombre_archivo.regex' => 'El nombre del archivo solo puede contener letras, números, guiones, puntos y guiones bajos.',
    ];
  }

  /**
   * Get the custom filename if provided.
   *
   * @return string|null
   */
  public function getFileName(): ?string
  {
    return $this->input('nombre_archivo');
  }

  /**
   * Get the file title if provided.
   *
   * @return string|null
   */
  public function getTitle(): ?string
  {
    return $this->input('titulo');
  }

  /**
   * Get the file description if provided.
   *
   * @return string|null
   */
  public function getDescription(): ?string
  {
    return $this->input('descripcion');
  }
}
