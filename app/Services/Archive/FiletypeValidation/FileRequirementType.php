<?php

namespace App\Services\Archive\FiletypeValidation;

enum FileRequirementType: string
{
  case VIDEO = 'video';
  case IMAGE = 'image';
  case COMPRESSED = 'compressed';
  case WORD_DOCUMENT = 'word_document';
  case PRESENTATION = 'presentation';
  case SPREADSHEET = 'spreadsheet';
  case PDF = 'pdf';
  case LINK = 'link';
  case MEDIA = 'media';
  case RAW_ART = 'raw_art';
  case DOCUMENT = 'document';

  /**
   * Devuelve el nombre legible para los mensajes de error.
   */
  public function label(): string
  {
    return match ($this) {
      self::VIDEO => 'videos',
      self::IMAGE => 'imágenes',
      self::COMPRESSED => 'archivos comprimidos',
      self::WORD_DOCUMENT => 'documentos de Word',
      self::PRESENTATION => 'presentaciones',
      self::SPREADSHEET => 'hojas de cálculo',
      self::PDF => 'PDFs',
      self::LINK => 'enlaces',
      self::MEDIA => 'archivos multimedia',
      self::RAW_ART => 'archivos de arte crudo (RAW/PSD/AI)',
      self::DOCUMENT => 'documentos genéricos',
    };
  }
}
