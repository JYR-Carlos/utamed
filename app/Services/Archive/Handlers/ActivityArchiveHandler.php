<?php

namespace App\Services\Archive\Handlers;

use App\Models\Agenda\Actividad;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;
use App\Services\Archive\ActivityArchiveService;
use App\Services\Archive\ArchiveHandlerRequest;
use App\Services\Archive\ArchiveStorageResult;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Handler for Activity archive operations.
 *
 * Adapts Activity-specific domain models to the generic Archive workflow.
 * Builds Activity-specific paths and delegates to ActivityArchiveService->performStorage().
 *
 * Supports both hierarchies:
 * - Curso → Componente → Actividad (via id_componente)
 * - Curso → Unidad → Actividad (via id_unidad)
 *
 * Usage:
 * ```php
 * // Using direct Actividad model
 * $result = ActivityArchiveHandler::store($actividad, $file, $fileName);
 *
 * // With explicit date (defaults to now)
 * $result = ActivityArchiveHandler::store($actividad, $file, fecha: now()->subDay());
 * ```
 *
 * Builds paths in the shape:
 * - actividad/{curso}/{componente}/{unidad}/{actividad}/
 *
 * Note: Path always includes both componente and unidad segments (even if redundant).
 * Single active file: New uploads replace previous file (if any).
 */
class ActivityArchiveHandler
{
  /**
   * Store activity auxiliary file with full validation chain.
   *
   * - Adapts Activity-specific parameters to generic ArchiveHandlerRequest
   * - Builds path from Actividad relationship chain (Curso → Componente|Unidad → Actividad)
   * - Delegates to AbstractArchiveService::performStorage() for:
   *   * File validation (image or PDF only)
   *   * Virus scanning
   *   * Compression/optimization
   *   * Atomic storage with rollback
   *   * Operation logging
   *
   * Single file per activity: Previous file will be orphaned (cleanup handled async).
   *
   * @param Actividad $actividad The activity to store file for
   * @param UploadedFile $file File to store (must be image or PDF)
   * @param DateTimeInterface|null $fecha Optional date (defaults to now) - currently unused but kept for consistency
   * @param string|null $fileName Optional explicit filename (defaults to generated deterministic name)
   * @return ArchiveStorageResult Storage result with disk, path, file_name, and metadata
   *
   * @throws \App\Exceptions\Archive\FileValidationException
   * @throws \App\Exceptions\Archive\VirusDetectedException
   * @throws \App\Exceptions\Archive\CompressionException
   * @throws \App\Exceptions\Archive\StorageException
   * @throws \App\Exceptions\Archive\ArchiveException
   * @throws InvalidArgumentException If relationships are missing
   */
  public static function store(
    Actividad $actividad,
    UploadedFile $file,
    ?DateTimeInterface $fecha = null, // TODO: agregar a la clase para modificar
    // todo: ver como implementar atomicidad pasando el cliente de db a esta funcion
    ?string $fileName = null
  ): ArchiveStorageResult {
    $relativeDirectory = self::buildPath($actividad);
    $finalFileName = $fileName ?: self::generateFileName($file);

    $request = new ArchiveHandlerRequest(
      file: $file,
      relativeDirectory: $relativeDirectory,
      fileName: $finalFileName
    );

    $archiveService = new ActivityArchiveService();
    return $archiveService->performStorage($request);
  }

  /**
   * Build activity-specific directory path from actividad relationship chain.
   *
   * Always includes both Componente and Unidad in path (even if redundant).
   * Validates that all required relationships exist before building path.
   *
   * Path format: actividad/{curso}/{componente}/{unidad}/{actividad}/
   *
   * @param Actividad $actividad
   * @return string Relative directory path
   *
   * @throws InvalidArgumentException If relationships are missing
   */
  private static function buildPath(Actividad $actividad): string
  {
    // Validate all required relationships exist
    $curso = $actividad->componente?->curso
      ?? $actividad->unidad?->curso
      ?? throw new InvalidArgumentException(
        "Actividad (id={$actividad->id_actividad}) missing Componente and Unidad relationships"
      );

    $componente = $actividad->componente
      ?? throw new InvalidArgumentException(
        "Actividad (id={$actividad->id_actividad}) missing Componente relationship"
      );

    $unidad = $actividad->unidad
      ?? throw new InvalidArgumentException(
        "Actividad (id={$actividad->id_actividad}) missing Unidad relationship"
      );

    return self::formatPath(
      $curso,
      $componente,
      $unidad,
      $actividad
    );
  }

  /**
   * Format path components into final directory path.
   *
   * Format:
   * - actividad/{curso}/{componente}/{unidad}/{actividad}/
   *
   * @param Curso $curso
   * @param Componente $componente
   * @param Unidad $unidad
   * @param Actividad $actividad
   * @return string
   */
  private static function formatPath(
    Curso $curso,
    Componente $componente,
    Unidad $unidad,
    Actividad $actividad
  ): string {
    return implode('/', [
      'actividad',
      Str::slug($curso->nombre),
      Str::slug($componente->tipoComponente->tipo),
      Str::slug($unidad->nombre),
      Str::slug($actividad->nombre),
    ]);
  }

  /**
   * Generate deterministic filename for stored file.
   *
   * Format: activity_{timestamp}_{random}.{extension}
   *
   * @param UploadedFile $file
   * @return string
   */
  private static function generateFileName(UploadedFile $file): string
  {
    $extension = $file->getClientOriginalExtension();
    $timestamp = now()->timestamp;
    $hash = Str::random(8);

    return "activity_{$timestamp}_{$hash}.{$extension}";
  }
}
