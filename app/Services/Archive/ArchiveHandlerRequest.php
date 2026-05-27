<?php

namespace App\Services\Archive;

use Illuminate\Http\UploadedFile;

/**
 * Data Transfer Object for Archive Handler
 *
 * Encapsulates the generic parameters needed by AbstractArchiveService::handleStore()
 * allowing each specific service (AgendaArchiveService, DocumentArchiveService, etc)
 * to have their own public static handleStore() with domain-specific signatures.
 *
 * The flow:
 * 1. Specific service (e.g., AgendaArchiveService::handleStore($grupo, $file, $fecha))
 * 2. Adapts parameters to generic ArchiveHandlerRequest
 * 3. Calls protected AbstractArchiveService::handleStore($request)
 * 4. Abstract service executes common workflow with domain-specific hooks
 *
 * Example:
 * ```php
 * $request = new ArchiveHandlerRequest(
 *     file: $uploadedFile,
 *     relativeDirectory: 'agenda/course/activity/2024-05-18/group-1',
 *     fileName: 'agenda-12345678-1234-1234-1234-123456789012.mp4'
 * );
 *
 * $result = $service->handleStore($request);
 * ```
 */
class ArchiveHandlerRequest
{
  /**
   * The uploaded file to store
   */
  public readonly UploadedFile $file;

  /**
   * Target directory relative to disk root.
   * Will be normalized and combined with base_path and root_segment.
   *
   * Example: 'agenda/course-name/activity-name/2024-05-18/student-names'
   */
  public readonly string $relativeDirectory;

  /**
   * Optional explicit filename. If not provided, a deterministic UUID-based name is generated.
   *
   * If null, AbstractArchiveService will generate: '{prefix}-{uuid}.{ext}'
   * Example: 'agenda-12345678-1234-1234-1234-123456789012.mp4'
   */
  public readonly ?string $fileName;

  /**
   * Create a new archive handler request.
   *
   * @param UploadedFile $file The uploaded file to store
   * @param string $relativeDirectory Target directory relative to disk root
   * @param string|null $fileName Optional explicit filename
   */
  public function __construct(
    UploadedFile $file,
    string $relativeDirectory,
    ?string $fileName = null
  ) {
    $this->file = $file;
    $this->relativeDirectory = $relativeDirectory;
    $this->fileName = $fileName;
  }
}
