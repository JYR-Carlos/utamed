<?php

namespace App\Services\Archive\FiletypeValidation;

use App\Exceptions\Archive\ArchiveException;
use App\Exceptions\Archive\ArchiveErrorType;

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
      $rules[] = 'mimetypes:' . implode(',', array_unique($this->mimes));
    }

    $rules[] = function (string $attribute, mixed $value, \Closure $fail) {
      if (!$value instanceof \Illuminate\Http\UploadedFile) {
        return;
      }

      // `extensions:` y `mimetypes:` son uniones planas de todas las categorías
      // habilitadas, así que un archivo con extensión de una categoría y MIME de
      // otra pasa ambas reglas. Antes ese cruce no casaba con ningún typeConfig y
      // el closure salía sin comprobar el tamaño: bastaba renombrar un archivo
      // para saltarse el límite de su categoría y quedar sólo bajo
      // `upload_max_filesize`.
      $match = $this->resolveSizeLimit($value);

      if ($match === null) {
        $fail('El archivo no corresponde a ninguno de los tipos permitidos: su extensión y su contenido no coinciden.');
        return;
      }

      if ($value->getSize() > $match['max_size']) {
        $maxMb = round($match['max_size'] / 1024 / 1024, 2);
        $fail("Los archivos tipo {$match['label']} no pueden superar los {$maxMb} MB.");
      }
    };

    return $rules;
  }

  /**
   * Resuelve el límite de tamaño aplicable a un archivo concreto.
   *
   * Estrategia, de más a menos específica:
   * 1. **Coincidencia completa** (extensión y MIME de la misma categoría): manda
   *    el límite de esa categoría.
   * 2. **Coincidencia parcial** (sólo extensión o sólo MIME): se aplica el límite
   *    **más restrictivo** de las categorías candidatas. Así, cruzar la extensión
   *    de una categoría con el MIME de otra no concede el techo de la más
   *    permisiva (MEDIA y RAW_ART llegan a 1 GB).
   * 3. **Sin coincidencia**: null, y quien llama rechaza el archivo.
   *
   * Se comparan tanto la extensión declarada por el cliente como la deducida del
   * contenido: basta que una de las dos pertenezca a la categoría.
   *
   * @return array{max_size:int, label:string}|null
   */
  protected function resolveSizeLimit(\Illuminate\Http\UploadedFile $file): ?array
  {
    $clientExtension  = strtolower($file->getClientOriginalExtension());
    $guessedExtension = strtolower((string) $file->extension());
    $mimeType         = $file->getMimeType();

    $partial = null;

    foreach ($this->typeConfigs as $config) {
      $extensionMatches = \in_array($clientExtension, $config['extensions'], true)
        || \in_array($guessedExtension, $config['extensions'], true);
      $mimeMatches = \in_array($mimeType, $config['mimes'], true);

      if ($extensionMatches && $mimeMatches) {
        return ['max_size' => (int) $config['max_size'], 'label' => $config['label']];
      }

      if ($extensionMatches || $mimeMatches) {
        if ($partial === null || $config['max_size'] < $partial['max_size']) {
          $partial = ['max_size' => (int) $config['max_size'], 'label' => $config['label']];
        }
      }
    }

    return $partial;
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
      $messages["{$fileField}.mimetypes"] = "El archivo debe ser de tipo válido para: {$nombresTipos}.";
    }

    return $messages;
  }
}
