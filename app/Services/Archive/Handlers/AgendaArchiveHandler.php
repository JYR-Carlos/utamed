<?php

namespace App\Services\Archive\Handlers;

use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Services\Archive\AgendaArchiveService;
use App\Services\Archive\ArchiveHandlerRequest;
use App\Services\Archive\ArchiveStorageResult;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Handler for Agenda archive operations.
 *
 * Adapts Agenda-specific domain models to the generic Archive workflow.
 * Builds Agenda-specific paths and delegates to AgendaArchiveService->performStorage().
 *
 * Usage:
 * ```php
 * $result = AgendaArchiveHandler::store($grupo, $file, $fecha, $fileName);
 * ```
 *
 * Builds paths in the shape: agenda/{curso}/{componente}/{actividad}/{fecha}/{grupo}/
 */
class AgendaArchiveHandler
{
  /**
   * Store activity submission file with full validation chain.
   *
   * - Adapts Agenda-specific parameters to generic ArchiveHandlerRequest
   * - Builds path from ActividadAsignadaGrupo relationship chain
   * - Delegates to AbstractArchiveService::performStorage() for:
   *   * File validation
   *   * Virus scanning
   *   * Compression/optimization
   *   * Atomic storage with rollback
   *   * Operation logging
   *
   * @param ActividadAsignadaGrupo $grupo The activity group assignment
   * @param UploadedFile $file File to store
   * @param DateTimeInterface|null $fecha Optional date (defaults to now)
   * @param string|null $fileName Optional explicit filename
   * @return array Storage result with disk, path, file_name, and metadata
   *
   * @throws \App\Exceptions\Archive\FileValidationException
   * @throws \App\Exceptions\Archive\VirusDetectedException
   * @throws \App\Exceptions\Archive\CompressionException
   * @throws \App\Exceptions\Archive\StorageException
   * @throws \App\Exceptions\Archive\ArchiveException
   */
  public static function store(
    ActividadAsignadaGrupo $grupo,
    UploadedFile $file,
    ?DateTimeInterface $fecha = null,
    ?string $fileName = null
  ): ArchiveStorageResult {
    $fecha = $fecha ?: now();
    $relativeDirectory = self::buildPath($grupo, $fecha);
    $finalFileName = $fileName ?: self::generateFileName($file);

    $request = new ArchiveHandlerRequest(
      file: $file,
      relativeDirectory: $relativeDirectory,
      fileName: $finalFileName
    );

    $archiveService = new AgendaArchiveService();
    return $archiveService->performStorage($request);
  }

  /**
   * Build agenda-specific directory path from grupo relationship chain.
   *
   * Validates that all required relationships exist before building path.
   *
   * @param ActividadAsignadaGrupo $grupo
   * @param DateTimeInterface $fecha
   * @return string Relative directory path
   *
   * @throws InvalidArgumentException If relationships are missing
   */
  private static function buildPath(
    ActividadAsignadaGrupo $grupo,
    DateTimeInterface $fecha
  ): string {
    $actividad = $grupo->actividad
      ?? throw new InvalidArgumentException(
        "ActividadAsignadaGrupo (id={$grupo->id_actividad_asignada_grupo}) missing Actividad"
      );

    $componente = $actividad->componente
      ?? throw new InvalidArgumentException(
        "Actividad (id={$actividad->id_actividad}) missing Componente"
      );

    $curso = $componente->curso
      ?? throw new InvalidArgumentException(
        "Componente (id={$componente->id_componente}) missing Curso"
      );

    return self::formatPath($curso, $componente, $actividad, $fecha, $grupo);
  }

  /**
   * Format path components into final directory path.
   *
   * Format: {curso}/{componente_type}/{actividad}/{fecha}/{student_names}/
   *
   * @param Curso $curso
   * @param Componente $componente
   * @param Actividad $actividad
   * @param DateTimeInterface $fecha
   * @param ActividadAsignadaGrupo $grupo
   * @return string
   */
  private static function formatPath(
    Curso $curso,
    Componente $componente,
    Actividad $actividad,
    DateTimeInterface $fecha,
    ActividadAsignadaGrupo $grupo
  ): string {
    $estudiantesNombres = $grupo->integranteGrupos
      ->map(fn($integrante) => $integrante->estudiante->nombreAbreviado())
      ->implode('-');

    // Normalize each path segment at Handler level for deterministic behavior
    return implode('/', [
      Str::slug($curso->nombre),
      Str::slug($componente->tipoComponente->tipo),
      Str::slug($actividad->nombre),
      $fecha->format('Y-m-d'),
      Str::slug($estudiantesNombres),
    ]);
  }

  /**
   * Generate deterministic filename for stored file.
   *
   * @param UploadedFile $file
   * @return string
   */
  private static function generateFileName(UploadedFile $file): string
  {
    $extension = $file->getClientOriginalExtension();
    $timestamp = now()->timestamp;
    $hash = \Illuminate\Support\Str::random(8);

    return "agenda_{$timestamp}_{$hash}.{$extension}";
  }
}
