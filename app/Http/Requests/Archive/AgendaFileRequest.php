<?php

namespace App\Http\Requests\Archive;

use App\Rules\Builders\FileRequirementType;

/**
 * AgendaFileRequest
 * 
 * Validación para uploads de archivos de entregas/agenda de estudiantes.
 * 
 * Tipos permitidos (configurables por contexto):
 * - Documentos de Word: DOC, DOCX, DOCM
 * - PDFs
 * - Imágenes: PNG, JPG, JPEG, WebP, GIF, BMP, SVG, TIFF
 * 
 * Tamaño máximo:
 * - Documentos: 100MB (configurable via FILES_WORD_DOCUMENT_MAX_SIZE)
 * - PDFs: 100MB (configurable via FILES_PDF_MAX_SIZE)
 * - Imágenes: 50MB (configurable via FILES_IMAGE_MAX_SIZE)
 * 
 * Los archivos se almacenarán en el contexto de agenda del estudiante.
 * 
 * Uso:
 * ```php
 * class StoreAgendaFileController {
 *     public function store(AgendaFileRequest $request) {
 *         $file = $request->getFile();
 *         $agenda = $request->getAgendaId();
 *         // ...
 *     }
 * }
 * ```
 * 
 * Related:
 * - Agenda Model
 * - AgendaArchiveService (si existe)
 * - AgendaArchiveHandler (si existe)
 */
class AgendaFileRequest extends BaseArchiveRequest
{
  /**
   * Tipos de archivo permitidos para entregas de agenda.
   * 
   * Sugerencia: Si necesitas agregar más tipos, considera:
   * - FileRequirementType::SPREADSHEET: Hojas de cálculo (XLS, XLSX)
   * - FileRequirementType::PRESENTATION: Presentaciones (PPT, PPTX)
   * - FileRequirementType::COMPRESSED: Archivos ZIP, RAR (para múltiples archivos)
   * - FileRequirementType::MEDIA: Audio/Video (MP3, MP4)
   */
  protected array $fileCategories = [
    FileRequirementType::WORD_DOCUMENT,
    FileRequirementType::PDF,
    FileRequirementType::IMAGE,
    FileRequirementType::SPREADSHEET,
    FileRequirementType::PRESENTATION,
    FileRequirementType::COMPRESSED,
    FileRequirementType::MEDIA,
    FileRequirementType::RAW_ART,
    FileRequirementType::DOCUMENT,
  ];

  /**
   * Campo que contiene el archivo (nombre del parámetro del form).
   */
  protected string $fileField = 'archivo';

  /**
   * Reglas adicionales para archivos de agenda.
   */
  protected function additionalRules(): array
  {
    return [
      // Metadatos comunes
      'titulo' => 'nullable|string|max:255',
      'descripcion' => 'nullable|string|max:1000',

      // Nombre personalizado del archivo
      'nombre_archivo' => [
        'nullable',
        'string',
        'max:255',
        'regex:/^[\pL\pN\s._\-]+$/u', // UTF-8 compatible
      ],

      // Fecha de entrega (si aplica)
      'fecha_entrega' => 'nullable|date',

      // Categoría o etiqueta de la entrega
      'categoria' => 'nullable|string|max:100',
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

      // Nombre archivo
      'nombre_archivo.regex' => 'El nombre del archivo solo puede contener letras, números, guiones, puntos y guiones bajos.',
      'nombre_archivo.max' => 'El nombre del archivo no puede exceder 255 caracteres.',

      // Fecha
      'fecha_entrega.date' => 'La fecha de entrega no es válida.',

      // Categoría
      'categoria.max' => 'La categoría no puede exceder 100 caracteres.',
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

  /**
   * Get the delivery date if provided.
   *
   * @return string|null
   */
  public function getDeliveryDate(): ?string
  {
    return $this->input('fecha_entrega');
  }

  /**
   * Get the category if provided.
   *
   * @return string|null
   */
  public function getCategory(): ?string
  {
    return $this->input('categoria');
  }
}
