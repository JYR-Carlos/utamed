<?php

namespace App\Rules\Builders;

use App\Exceptions\Archive\ArchiveException;
use App\Exceptions\Archive\ArchiveErrorType;

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

class FileRequirementBuilder
{
  protected array $extensions = [];
  protected array $mimes = [];
  protected array $typeConfigs = [];

  public static function make(): self
  {
    return new self();
  }

  public function addConfig(FileRequirementType $configType): self
  {
    try {
      $config = config("filetypes.{$configType->value}");

      $globalMaxFileSize = config('filetypes.global.max_file_size');

      if ($globalMaxFileSize === null) {
        throw new ArchiveException(
          ArchiveErrorType::CONFIGURATION_ERROR,
          "Global max file size configuration is missing."
        );
      }

      if (empty($config)) {
        throw new ArchiveException(
          ArchiveErrorType::CONFIGURATION_ERROR,
          "File type configuration not found for: {$configType->value}"
        );
      }

      if (
        empty($config['extensions'])
        || empty($config['mimes'])
      ) {
        throw new ArchiveException(
          ArchiveErrorType::CONFIGURATION_ERROR,
          "File type configuration incomplete for: {$configType->value}"
        );
      }

      $currentMaxSize = $config['max_size'] ?? $globalMaxFileSize;

      // Guardamos el perfil específico para la validación dinámica de tamaño
      $this->typeConfigs[$configType->value] = [
        'extensions' => array_map('strtolower', $config['extensions']),
        'mimes'      => $config['mimes'],
        'max_size'   => $config['max_size'] ?? $globalMaxFileSize,
        'label'      => $configType->label(),
      ];

      // Mantenemos los arrays planos para la validación nativa rápida de Laravel
      $this->extensions = [...$this->extensions, ...$config['extensions']];
      $this->mimes = [...$this->mimes, ...$config['mimes']];
    } catch (\Exception $e) {
      // Re-throw as ArchiveException if it's not already one
      if (!($e instanceof ArchiveException)) {
        throw new ArchiveException(
          ArchiveErrorType::CONFIGURATION_ERROR,
          "Error loading file type configuration for: {$configType->value} - " . $e->getMessage()
        );
      }
      throw $e;
    }

    return $this;
  }

  public function buildLaravelRules(): array
  {
    $rules = [
      'required',
      'file',
    ];

    if (config('filetypes.global.enable_extension_validation', true)) {
      $rules[] = 'extensions:' . implode(',', array_unique($this->extensions));
    }

    if (config('filetypes.global.enable_mime_validation', true)) {
      $rules[] = 'mimes:' . implode(',', array_unique($this->mimes));
    }

    $rules[] = function (string $attribute, mixed $value, \Closure $fail) {
      if (!$value instanceof \Illuminate\Http\UploadedFile) {
        return;
      }

      $extension = strtolower($value->extension());
      $mimeType = $value->getMimeType();
      $fileSize = $value->getSize();

      foreach ($this->typeConfigs as $config) {
        if (\in_array($extension, $config['extensions']) && \in_array($mimeType, $config['mimes'])) {
          if ($fileSize > $config['max_size']) {
            $maxMb = round($config['max_size'] / 1024 / 1024, 2);
            $fail("Los archivos tipo {$config['label']} no pueden superar los {$maxMb} MB.");
          }
          return;
        }
      }
    };

    return $rules;
  }

  /**
   * Genera mensajes dinámicos de error basados en las configuraciones cargadas.
   * Compatible con la estructura de FormRequest de Laravel.
   */
  public function buildLaravelMessages(string $fileField): array
  {
    $messages = [];

    // Si no hay configuraciones, devolvemos un array vacío
    if (empty($this->typeConfigs)) {
      return $messages;
    }

    $labels = [];
    $extensions = [];

    foreach ($this->typeConfigs as $config) {
      $labels[] = $config['label'];
      $extensions = [...$extensions, ...$config['extensions']];
    }

    $nombresTipos = implode(' o ', array_unique($labels)); // Ej: "imágen o PDF o ..."

    $extensionValidationEnabled = config('filetypes.global.enable_extension_validation', true);
    $mimeValidationEnabled = config('filetypes.global.enable_mime_validation', true);

    if ($extensionValidationEnabled && !empty($extensions)) {
      $extString = strtoupper(implode(', ', array_unique($extensions)));
      $messages["{$fileField}.extensions"] = "El archivo debe tener una de estas extensiones: {$extString}.";
    }

    if ($mimeValidationEnabled && !empty($this->mimes)) {
      $messages["{$fileField}.mimes"] = "El archivo debe ser de tipo válido para: {$nombresTipos}.";
    }

    return $messages;
  }
}
