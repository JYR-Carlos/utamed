<?php

namespace App\Services\Archive;

use App\Exceptions\Archive\ArchiveException;
use App\Exceptions\Archive\CompressionException;
use App\Exceptions\Archive\FileValidationException;
use App\Exceptions\Archive\StorageException;
use App\Exceptions\Archive\StorageErrorType;
use App\Exceptions\Archive\VirusDetectedException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Base service for archive storage.
 *
 * Centralizes disk resolution, directory composition and file persistence so
 * concrete services only need to describe their path structure.
 * 
 * Ruta final ejemplo:
 * {root}/{basePath}/archivo.ext
 */
abstract class AbstractArchiveService
{
  /**
   * Disco configurado en filesystem.php
   * 
   * @var string String con el nombre del disco a usar para almacenamiento
   */
  protected string $disk;

  /**
   * Ruta base dentro del disco para almacenar archivos.
   * 
   * Se configura desde config('files.storage.base_path')
   *
   * @var string Ruta relativa dentro del disco
   */
  protected string $basePath;

  /**
   * Directorio temporal para procesamiento de archivos antes de almacenamiento.
   *
   * @var string Ruta relativa dentro del disco, por ejemplo 'app/temp/archive'
   */
  protected string $tempDirectory;

  /**
   * Segmento raíz para prefijar rutas.
   *
   * @var string Segmento fijo que se antepone a todas las rutas, por ejemplo 'agenda'
   */
  protected string $rootSegment = '';

  /**
   * Ruta del último archivo almacenado (para cleanup en caso de error).
   *
   * @var ?string Ruta guardada durante Phase 4, null si no se llegó a almacenar
   */
  protected ?string $lastStoredPath = null;

  /**
   * Archive ID de la operación actual (para correlacionar logs).
   *
   * @var ?string UUID7 único de la operación en progreso
   */
  protected ?string $currentArchiveId = null;

  public function __construct(
    ?string $rootSegment = null,
    ?string $tempDirectory = null
  ) {
    // Si no se recibe por parámetro ni existe en el config throwea
    $this->disk =
      config('files.storage.disk')
      ?? config('filesystems.default')
      ?? throw new \RuntimeException("El disco de almacenamiento no está definido en las configuraciones.");

    $this->basePath =
      config('files.storage.base_path')
      ?? throw new \RuntimeException("La ruta base (base_path) no está definida en las configuraciones.");

    // Estos son opcionales, si no se configuran se asume que no se usan
    $this->rootSegment = $rootSegment ?: (config('files.storage.root_segment') ?? '');
    $this->tempDirectory = $tempDirectory ?: (config('files.storage.temp_directory') ?? "app/temp/{$this->basePath}");
  }

  /**
   * Robust file storage handler with full validation, optimization, and error recovery.
   *
   * This is the template method that defines the common workflow for ALL archive services.
   * Specific services (AgendaArchiveService, DocumentArchiveService, etc) have their own
   * public static handleStore() with domain-specific signatures, but all delegate to this
   * protected method after adapting their parameters to ArchiveHandlerRequest.
   *
   * ## Execution Flow & Return Types
   *
   * **SUCCESS PATH (Happy Path):**
   * - All phases complete without error
   * - Returns: `ArchiveStorageResult` containing:
   *   - `uuidArchivo`: UUID v7 of the stored file record in database
   *   - `disk`: Storage disk identifier (local, s3, azure, etc)
   *   - `directory`: Relative directory path where file is stored
   *   - `path`: Complete relative path from disk root to file
   *   - `fileName`: Filename as stored on disk (hashName or custom)
   *   - `sizeBytes`: File size in bytes after optimization
   *   - `mimeType`: MIME type detected during validation
   *   - `originalName`: Client-provided filename for auditing
   *
   * **ERROR PATHS (all throw exceptions, no partial state returned):**
   *
   * 1. **FileValidationException** - Pre-validation Phase (Phase 1)
   *    - Triggered when: File size exceeds limit, extension not allowed, content validation fails
   *    - Cleanup: Synchronous cleanup of temp files; logs recorded
   *    - Returns: never (exception thrown)
   *
   * 2. **VirusDetectedException** - Virus Scanning Phase (Phase 2)
   *    - Triggered when: File contains malicious content (if scanning enabled)
   *    - Cleanup: Synchronous cleanup of temp files; logs recorded
   *    - Returns: never (exception thrown)
   *
   * 3. **CompressionException** - Optimization Phase (Phase 3)
   *    - Triggered when: Image optimization, video transcoding, or format conversion fails
   *    - Cleanup: Synchronous cleanup of temp files and partially optimized data
   *    - Returns: never (exception thrown)
   *
   * 4. **StorageException** - Storage Phase (Phase 4)
   *    - Triggered when: Disk write fails, permissions denied, disk full, or DB insert fails
   *    - Cleanup: Attempts synchronous delete; marks for async cleanup if sync fails
   *    - Returns: never (exception thrown)
   *
   * 5. **StorageException** - Unexpected Exception (Generic catch)
   *    - Triggered when: Any other exception type propagates
   *    - Cleanup: If file was stored (lastStoredPath set), attempts cleanup
   *    - Returns: never (wraps original exception in StorageException)
   *
   * ## Phases (In Order)
   *
   * 1. **Pre-validation**: Verify file integrity and constraints (hook: preValidate)
   * 2. **Virus scanning**: Detect malicious content if configured (hook: scanForViruses)
   * 3. **Compression/Optimization**: Optimize file size/quality (hook: compressFile)
   * 4. **Storage**: Persist file to disk and create DB record (concrete: storeUploadedFile)
   * 5. **Logging**: Record operation result with audit trail (hook: logOperation)
   *
   * ## Cleanup Strategy
   *
   * - **All-or-Nothing**: If any phase fails, entire operation is rolled back
   * - **Cleanup is Best-Effort**: If cleanup itself fails, operation still throws original exception
   * - **Temp files**: Deleted synchronously on any error
   * - **Stored files**: Deleted synchronously; if sync delete fails, marked for async cleanup job
   * - **Logging**: Always attempted after cleanup; failures don't prevent exception propagation
   *
   * @param ArchiveHandlerRequest $request Generic request containing file, directory, filename
   * @return ArchiveStorageResult Typed result with disk, path, file_name, UUID, and metadata
   *
   * @throws FileValidationException File fails validation (size, extension, content check)
   * @throws VirusDetectedException Virus detected in file content
   * @throws CompressionException Compression or optimization failed
   * @throws StorageException Disk storage or DB insert failed, or unknown exception occurred
   * @throws ArchiveException Generic archive operation error (for subclass use)
   */
  public function performStorage(ArchiveHandlerRequest $request): ArchiveStorageResult
  {
    $archiveId = Str::uuid7()->toString();
    $this->currentArchiveId = $archiveId;
    $this->lastStoredPath = null;
    
    $operationLog = [
      'archive_id' => $archiveId,
      'timestamp_start' => now(),
      'file_original_name' => $request->file->getClientOriginalName(),
      'file_original_size' => $request->file->getSize(),
      'status' => 'pending',
    ];

    try {
      // Phase 1: Pre-validation
      $this->preValidate($request->file, $archiveId);

      // Phase 2: Virus scanning
      $this->scanForViruses($request->file, $archiveId);

      // Phase 3: Compression/Optimization
      $optimizedFile = $this->compressFile($request->file, $archiveId);
      $operationLog['file_optimized_size'] = $optimizedFile->getSize();
      $operationLog['compression_ratio'] = $this->calculateCompressionRatio(
        $request->file->getSize(),
        $optimizedFile->getSize()
      );

      // Phase 4: Storage
      $storedFile = $this->storeUploadedFile(
        $optimizedFile,
        $request->relativeDirectory,
        $request->fileName
      );
      
      // Guardar ruta para posible cleanup en caso de error post-almacenamiento
      $this->lastStoredPath = $storedFile->path;

      // Success - log as best-effort (no lanzar excepción si logging falla)
      $operationLog['status'] = 'success';
      $operationLog['disk'] = $storedFile->disk;
      $operationLog['path'] = $storedFile->path;
      $operationLog['timestamp_end'] = now();
      $operationLog['duration_ms'] = now()->diffInMilliseconds($operationLog['timestamp_start']);

      try {
        $this->logOperation($operationLog, 'info');
      } catch (\Exception $logError) {
        // Log failure is best-effort: archivo está en disco, es success de negocio
        // Fallback a implementación default si subclase logOperation() falla
        $this->defaultLogOperation($operationLog, 'info');
      }

      return $storedFile;
    } catch (FileValidationException $e) {
      $operationLog['status'] = 'validation_failed';
      $operationLog['error'] = $e->getMessage();
      $operationLog['timestamp_end'] = now();

      // Garantizar limpieza primero, sin depender de logOperation()
      $this->cleanupPartialResults();

      try {
        $this->logOperation($operationLog, 'warning');
      } catch (\Exception $logError) {
        // Fallback a implementación default si subclase logOperation() falla
        $this->defaultLogOperation($operationLog, 'warning');
      }

      throw $e;
    } catch (VirusDetectedException $e) {
      $operationLog['status'] = 'virus_detected';
      $operationLog['error'] = $e->getMessage();
      $operationLog['timestamp_end'] = now();

      // Garantizar limpieza primero
      $this->cleanupPartialResults();

      try {
        $this->logOperation($operationLog, 'alert');
      } catch (\Exception $logError) {
        // Fallback a implementación default si subclase logOperation() falla
        $this->defaultLogOperation($operationLog, 'alert');
      }

      throw $e;
    } catch (CompressionException $e) {
      $operationLog['status'] = 'compression_failed';
      $operationLog['error'] = $e->getMessage();
      $operationLog['timestamp_end'] = now();

      // Garantizar limpieza primero
      $this->cleanupPartialResults();

      try {
        $this->logOperation($operationLog, 'error');
      } catch (\Exception $logError) {
        // Fallback a implementación default si subclase logOperation() falla
        $this->defaultLogOperation($operationLog, 'error');
      }

      throw $e;
    } catch (StorageException $e) {
      $operationLog['status'] = 'storage_failed';
      $operationLog['error'] = $e->getMessage();
      $operationLog['timestamp_end'] = now();

      // Garantizar limpieza primero
      $this->cleanupPartialResults();

      try {
        $this->logOperation($operationLog, 'critical');
      } catch (\Exception $logError) {
        // Fallback a implementación default si subclase logOperation() falla
        $this->defaultLogOperation($operationLog, 'critical');
      }

      throw $e;
    } catch (\Exception $e) {
      $operationLog['status'] = 'unknown_error';
      $operationLog['error'] = $e->getMessage();
      $operationLog['exception_class'] = get_class($e);
      $operationLog['timestamp_end'] = now();

      // Si ya almacenamos (lastStoredPath seteado) y el error es desconocido,
      // intentamos limpiar primero antes de loguear
      if ($this->lastStoredPath) {
        $this->cleanupPartialResults();
      }

      // Loguear error con fallback default si logOperation() también falla
      try {
        $this->logOperation($operationLog, 'error');
      } catch (\Exception $logError) {
        // Fallback a implementación default si subclase logOperation() falla
        $this->defaultLogOperation($operationLog, 'error');
      }

      throw new StorageException(
        StorageErrorType::IO_ERROR,
        "Unexpected error during file storage: {$e->getMessage()}",
        $archiveId,
        null,
        $e
      );
    }
  }

  /**
   * Validate uploaded file for size, type, and integrity.
   *
   * HOOK: Subclasses MUST implement with their specific validation logic.
   *
   * @param UploadedFile $file
   * @param string $archiveId Unique operation ID for tracing
   * @return void
   *
   * @throws FileValidationException
   */
  abstract protected function preValidate(UploadedFile $file, string $archiveId): void;

  /**
   * Scan file for viruses using configured scanner.
   *
   * HOOK: Subclasses can override with their specific scan logic.
   * Default: Uses config('files.validation.virus_scan_enabled') to check if scanning enabled.
   * If enabled, connect to configured ClamAV daemon or VirusTotal API.
   * 
   * @param UploadedFile $file
   * @param string $archiveId Unique operation ID for tracing
   * @return void
   *
   * @throws VirusDetectedException
   */
  protected function scanForViruses(UploadedFile $file, string $archiveId): void
  {
    if (!config('files.validation.virus_scan_enabled')) {
      return;
    }

    // TODO: Default implementation - connect to ClamAV daemon or VirusTotal
    // Example:
    // - Connect to ClamAV daemon
    // - Send file for scanning
    // - Parse response
    //
    // $scanResult = app(VirusScanService::class)->scan($file);
    // if ($scanResult->isInfected()) {
    //   throw new VirusDetectedException(
    //     VirusDetectionErrorType::VIRUS_FOUND,
    //     $scanResult->getVirusName(),
    //     $archiveId
    //   );
    // }
  }

  /**
   * Compress and optimize file based on type.
   *
   * HOOK: Subclasses MUST implement with their specific optimization logic.
   * Must return the optimized file (may be same file if no optimization needed).
   *
   * @param UploadedFile $file
   * @param string $archiveId Unique operation ID for tracing
   * @return UploadedFile Optimized file
   *
   * @throws CompressionException
   */
  abstract protected function compressFile(UploadedFile $file, string $archiveId): UploadedFile;

  /**
   * Log archive operation with structured data for auditing and debugging.
   *
   * HOOK: Subclasses can override with their specific logging logic (DB, Sentry, etc).
   * Default: Delegates to defaultLogOperation() for standard Laravel channel logging.
   *
   * @param array $operationLog Structured log data with keys:
   *   - archive_id: Unique operation identifier
   *   - status: 'success', 'validation_failed', 'virus_detected', etc
   *   - timestamp_start, timestamp_end: Operation timing
   *   - file_original_name, file_original_size, file_optimized_size
   *   - error: Error message if status is failure
   *   - disk, path: Storage location if success
   * @param string $logLevel Monolog level (info, warning, error, alert, critical)
   * @return void
   */
  protected function logOperation(array $operationLog, string $logLevel = 'info'): void
  {
    // Comportamiento por defecto si la clase hija no lo sobrescribe
    $this->defaultLogOperation($operationLog, $logLevel);
  }

  /**
   * Default logging implementation using Laravel's Log facade.
   *
   * This is FINAL and cannot be overridden by subclasses.
   * Used as fallback when custom logOperation() implementations fail.
   *
   * @param array $operationLog
   * @param string $logLevel
   * @return void
   */
  final protected function defaultLogOperation(array $operationLog, string $logLevel = 'info'): void
  {
    $channel = config('files.logging.channel', 'stack');
    Log::channel($channel)->{$logLevel}('Archive operation', $operationLog);
  }

  /**
   * Clean up partial/temporary results if operation fails.
   *
   * HOOK: Subclasses can override with their specific cleanup strategy.
   * Default: Marks files as pending_de_borrado in BD for async cleanup job.
   *
   * Called automatically on any exception during storage workflow.
   * Ensures proper tracking of orphaned files through audit logs.
   *
   * ⚠️ TODO: Limpieza Asíncrona de Archivos Almacenados
   * 
   * Estrategia actual (PENDIENTE): 
   * 1. Marcar en BD tabla `operaciones.archivos` con:
   *    - pendiente_de_borrado = true
   *    - razón_pendiente = 'storage_error|logging_error|virus_detected'
   *    - fecha_marcado = now()
   * 2. Loguear cada intento de marcar/error
   * 3. Crear scheduled job `CleanupOrphanedArchivesJob` que:
   *    - Busque archivos marcados > X minutos de antigüedad
   *    - Intente borrar de disco
   *    - Loguee resultado (success|failure)
   *    - Actualice BD (deleted_at, o delete_error)
   * 4. El log NUNCA se borra, solo se marca el archivo
   * 
   * Así mantemos auditoría completa de qué pasó y cuándo.
   *
   * @return void
   */
  protected function cleanupPartialResults(): void
  {
    try {
      // Phase 1: Limpiar archivos temporales de esta operación (sincrónico)
      $tempFiles = Storage::disk($this->disk)
        ->files($this->tempDirectory);

      $tempFilesDeleted = 0;
      foreach ($tempFiles as $file) {
        // Solo delete files matching this archiveId pattern
        if (str_contains($file, $this->currentArchiveId)) {
          try {
            Storage::disk($this->disk)->delete($file);
            $tempFilesDeleted++;
          } catch (\Exception $e) {
            $this->logOperation([
              'status' => 'temp_file_delete_failed',
              'temp_file' => $file,
              'error' => $e->getMessage(),
            ], 'warning');
          }
        }
      }

      if ($tempFilesDeleted > 0) {
        $this->logOperation([
          'status' => 'temp_files_cleaned',
          'temp_files_deleted' => $tempFilesDeleted,
        ], 'info');
      }

      // Phase 2: Intentar borrar archivo almacenado (sincrónico, si existe)
      if ($this->lastStoredPath) {
        try {
          $this->logOperation([
            'status' => 'cleanup_stored_file_attempt',
            'path' => $this->lastStoredPath,
            'message' => 'Intentando borrar archivo almacenado sincrónico',
          ], 'info');

          Storage::disk($this->disk)->delete($this->lastStoredPath);

          $this->logOperation([
            'status' => 'cleanup_stored_file_success',
            'path' => $this->lastStoredPath,
            'message' => 'Archivo almacenado borrado exitosamente',
          ], 'info');

        } catch (\Exception $e) {
          // Borrado sincrónico falló → marcar para limpieza asíncrona
          $this->logOperation([
            'status' => 'cleanup_stored_file_failed',
            'path' => $this->lastStoredPath,
            'error' => $e->getMessage(),
            'message' => 'Borrado sincrónico falló. Archivo será marcado para limpieza asíncrona.',
          ], 'warning');

          // TODO: Implementar marcado en BD para cleanup asíncrono
          // DB::table('operaciones_archivos')->updateOrCreate(
          //   ['path' => $this->lastStoredPath, 'archive_id' => $this->currentArchiveId],
          //   [
          //     'pendiente_de_borrado' => true,
          //     'razón_pendiente' => 'sync_delete_failed',
          //     'fecha_marcado' => now(),
          //     'sync_error' => $e->getMessage(),
          //   ]
          // );
        }
      }

    } catch (\Exception $e) {
      // Log cleanup failure pero no lanzar - cleanup es best-effort
      // El log se mantiene intacto para auditoría
      $this->logOperation([
        'status' => 'cleanup_phase_failed',
        'error' => $e->getMessage(),
        'message' => 'Error durante fase de limpieza',
      ], 'error');
    }

    // Subclasses can override to also:
    // - Trigger CleanupOrphanedArchivesJob for scheduled cleanup
    // - Update database records with cleanup status
    // - Send notifications to admins if cleanup fails repeatedly
  }

  /**
   * Helper: Calculate compression ratio between original and optimized size.
   *
   * @param int $originalSize
   * @param int $optimizedSize
   * @return float Ratio (0-100), where 100 = no compression, 0 = 100% compressed
   */
  private function calculateCompressionRatio(int $originalSize, int $optimizedSize): float
  {
    if ($originalSize === 0) {
      return 0;
    }

    return round(($optimizedSize / $originalSize) * 100, 2);
  }


  /**
   * Store a file using a relative directory path and an optional explicit name.
   *
   * Creates both a disk storage entry and a database record (Archivo model) for tracking.
   * Assigns a UUID v7 automatically for referential integrity.
   *
   * @param UploadedFile $file The file to store (already validated and optimized)
   * @param string $relativeDirectory Relative path within the disk (e.g., 'programa-123/videos')
   * @param string|null $fileName Optional explicit filename; if null, uses hash name
   *
   * @return ArchiveStorageResult Typed result containing disk, path, UUID, and metadata
   *
   * @throws StorageException If disk write fails or DB insert fails
   */
  protected function storeUploadedFile(UploadedFile $file, string $relativeDirectory, ?string $fileName = null): ArchiveStorageResult
  {
    $normalizedDirectory = $this->normalizeRelativePath($relativeDirectory);

    $basePrefix = $this->basePath . '/';
    $rootPrefix = $this->rootSegment ? $this->rootSegment . '/' : '';

    $prefixedDirectory = "{$basePrefix}{$rootPrefix}{$normalizedDirectory}";

    $storageName = $fileName ?: $file->hashName();

    $storedPath = Storage::disk($this->disk)->putFileAs($prefixedDirectory, $file, $storageName);

    // Crear registro en BD con UUID v7 automático
    $archivoModel = app(\App\Models\Operaciones\Archivo::class);
    $archivo = $archivoModel->create([
      'ruta_fisica' => $storedPath,
      'nombre_original' => $file->getClientOriginalName(),
      'extension' => $file->extension(),
      'mime_type' => $file->getMimeType(),
      'peso_bytes' => $file->getSize(),
      'pendiente_de_borrado' => false,
      'desaparecio_archivo' => false,
    ]);

    // Retornar con tipo ArchiveStorageResult
    return new ArchiveStorageResult(
      uuidArchivo: $archivo->uuid_archivo,
      disk: $this->disk,
      directory: $prefixedDirectory,
      path: $storedPath,
      fileName: $storageName,
      sizeBytes: $file->getSize(),
      mimeType: $file->getMimeType(),
      originalName: $file->getClientOriginalName()
    );
  }

  /**
   * Build a relative path from normalized segments.
   */
  protected function buildRelativePath(string ...$segments): string
  {
    $cleanSegments = array_filter(array_map([$this, 'normalizePathSegment'], $segments), static fn($segment) => $segment !== '');

    return implode('/', $cleanSegments);
  }

  /**
   * Compose a root-based path and append any additional segments.
   */
  protected function buildRootedPath(string ...$segments): string
  {
    return $this->buildRelativePath($this->rootSegment, ...$segments);
  }

  /**
   * Normalize a directory or filename segment.
   */
  protected function normalizePathSegment(string $segment): string
  {
    $segment = trim($segment);

    if ($segment === '') {
      return '';
    }

    $segment = str_replace(['\\', '/'], '-', $segment);
    $segment = Str::slug($segment, '-');

    return trim($segment, '-');
  }

  /**
   * Normalize a relative path to prevent duplicate separators.
   */
  protected function normalizeRelativePath(string $path): string
  {
    $segments = array_values(array_filter(explode('/', str_replace('\\', '/', $path)), static fn($segment) => trim($segment) !== ''));

    return implode('/', array_map([$this, 'normalizePathSegment'], $segments));
  }

  /**
   * Build a deterministic file name using a prefix and extension.
   */
  protected function makeFileName(string $prefix, string $extension): string
  {
    $cleanPrefix = $this->normalizePathSegment($prefix);
    $cleanExtension = ltrim(strtolower($extension), '.');

    return $cleanPrefix . '-' . Str::uuid7()->toString() . '.' . $cleanExtension;
  }
}
