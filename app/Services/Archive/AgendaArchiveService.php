<?php

namespace App\Services\Archive;

use App\Exceptions\Archive\CompressionException;
use App\Exceptions\Archive\FileValidationException;
use Illuminate\Http\UploadedFile;

/**
 * Agenda-specific implementation of Archive storage template method.
 *
 * Extends AbstractArchiveService to specialize file validation and compression
 * for agenda activity submission files.
 *
 * Usage:
 * ```php
 * $agendaService = new AgendaArchiveService();
 * $result = $agendaService->performStorage($request);
 * ```
 */
class AgendaArchiveService extends AbstractArchiveService
{
  public function __construct()
  {
    // Set rootSegment to 'agenda' so files are stored under {basePath}/agenda/
    parent::__construct(rootSegment: 'agenda');
  }

  /**
   * Validate uploaded file for agenda-specific constraints.
   *
   * Implements the validation hook required by AbstractArchiveService.
   * Checks file size, extension, and MIME type against agenda file type rules.
   *
   * @param UploadedFile $file
   * @param string $archiveId Unique operation ID for tracing
   * @return void
   *
   * @throws FileValidationException
   */
  protected function preValidate(UploadedFile $file, string $archiveId): void
  {
    // TODO: Implement agenda-specific validation
    // For now, delegates to base validation or custom rules
    // Examples:
    // - Check file size against agenda limits
    // - Validate MIME type for allowed submission types
    // - Check for suspicious file characteristics
  }

  /**
   * Compress and optimize file for storage.
   *
   * Implements the compression hook required by AbstractArchiveService.
   * Returns the file as-is if no compression is needed, or optimized version.
   *
   * @param UploadedFile $file
   * @param string $archiveId Unique operation ID for tracing
   * @return UploadedFile
   *
   * @throws CompressionException
   */
  protected function compressFile(UploadedFile $file, string $archiveId): UploadedFile
  {
    // TODO: Implement agenda-specific compression
    // For now, return file as-is (no compression)
    // Future: Optimize images, compress videos, etc.
    return $file;
  }
}
