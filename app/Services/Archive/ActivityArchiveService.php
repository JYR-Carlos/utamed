<?php

namespace App\Services\Archive;

use App\Exceptions\Archive\CompressionException;
use App\Exceptions\Archive\FileValidationErrorType;
use App\Exceptions\Archive\FileValidationException;
use App\Exceptions\Archive\ArchiveException;
use App\Exceptions\Archive\ArchiveErrorType;

use Illuminate\Http\UploadedFile;

/**
 * Activity-specific implementation of Archive storage template method.
 *
 * Extends AbstractArchiveService to specialize file validation and compression
 * for activity auxiliary files (images or PDFs).
 *
 * Allowed file types:
 * - Images: PNG, JPG, WebP (max 50MB each)
 * - PDFs: application/pdf (max 5MB each)
 *
 * Supports single active file per activity (new upload replaces previous).
 *
 * Usage:
 * ```php
 * $activityService = new ActivityArchiveService();
 * $result = $activityService->performStorage($request);
 * ```
 */
class ActivityArchiveService extends AbstractArchiveService
{
  public function __construct()
  {
    // Set rootSegment to 'actividad' so files are stored under {basePath}/actividad/
    parent::__construct(rootSegment: 'actividad');
  }

  /**
   * Get image configuration from config/filetypes.php
   * 
   * @throws ArchiveException If configuration is missing
   */
  private function getImageConfig(): array
  {
    $config = config('filetypes.image');

    if (!$config) {
      throw new ArchiveException(
        ArchiveErrorType::CONFIGURATION_ERROR,
        "Image configuration not found in config/filetypes.php"
      );
    }

    if (empty($config['extensions']) || empty($config['mimes'])) {
      throw new ArchiveException(
        ArchiveErrorType::CONFIGURATION_ERROR,
        "Image configuration incomplete: missing extensions or mimes"
      );
    }

    return [
      'extensions' => $config['extensions'],
      'mimes' => $config['mimes'],
      'max_size' => $config['max_size'] ?? config('filetypes.global.max_file_size'),
    ];
  }

  /**
   * Get PDF configuration from config/filetypes.php
   * 
   * @throws ArchiveException If configuration is missing
   */
  private function getPdfConfig(): array
  {
    $config = config('filetypes.pdf');

    if (!$config) {
      throw new ArchiveException(
        ArchiveErrorType::CONFIGURATION_ERROR,
        "PDF configuration not found in config/filetypes.php"
      );
    }

    if (empty($config['extensions']) || empty($config['mimes'])) {
      throw new ArchiveException(
        ArchiveErrorType::CONFIGURATION_ERROR,
        "PDF configuration incomplete: missing extensions or mimes"
      );
    }

    return [
      'extensions' => $config['extensions'],
      'mimes' => $config['mimes'],
      'max_size' => $config['max_size'] ?? config('filetypes.global.max_file_size'),
    ];
  }

  /**
   * Validate uploaded file for activity-specific constraints.
   *
   * Implements the validation hook required by AbstractArchiveService.
   * Checks:
   * - File is either IMAGE (PNG, JPG, WebP) or PDF
   * - File size is within limits (50MB for images, 5MB for PDFs)
   * - MIME type matches declared type
   * - Extension is whitelisted
   *
   * @param UploadedFile $file
   * @param string $archiveId Unique operation ID for tracing
   * @return void
   *
   * @throws FileValidationException
   */
  protected function preValidate(UploadedFile $file, string $archiveId): void
  {
    $extension = strtolower($file->getClientOriginalExtension());
    $mimeType = $file->getMimeType();
    $fileSize = $file->getSize();

    $imageConfig = $this->getImageConfig();
    $pdfConfig = $this->getPdfConfig();

    // Normalize config arrays
    $imageExtensions = array_map('strtolower', $imageConfig['extensions'] ?? []);
    $imageMimes = $imageConfig['mimes'] ?? [];
    $maxImageSize = $imageConfig['max_size'];

    $pdfExtensions = array_map('strtolower', $pdfConfig['extensions'] ?? []);
    $pdfMimes = $pdfConfig['mimes'] ?? [];
    $maxPdfSize = $pdfConfig['max_size'];

    // Determine file type (image or pdf)
    $isImage = \in_array($extension, $imageExtensions, true)
      && \in_array($mimeType, $imageMimes, true);
    $isPdf = \in_array($extension, $pdfExtensions, true)
      && \in_array($mimeType, $pdfMimes, true);

    if (!$isImage && !$isPdf) {
      throw new FileValidationException(
        FileValidationErrorType::INVALID_EXTENSION,
        "File type not allowed. Only images (" . implode(', ', $imageExtensions) . ") or PDFs are accepted. "
          . "Got: {$mimeType} (.{$extension})",
        $archiveId
      );
    }

    // Validate size based on type
    if ($isImage && $fileSize > $maxImageSize) {
      $maxImageMb = round($maxImageSize / 1024 / 1024, 2);
      throw new FileValidationException(
        FileValidationErrorType::SIZE_EXCEEDED,
        "Image file exceeds maximum size of {$maxImageMb}MB. File size: " . round($fileSize / 1024 / 1024, 2) . "MB",
        $archiveId
      );
    }

    if ($isPdf && $fileSize > $maxPdfSize) {
      $maxPdfMb = round($maxPdfSize / 1024 / 1024, 2);
      throw new FileValidationException(
        FileValidationErrorType::SIZE_EXCEEDED,
        "PDF file exceeds maximum size of {$maxPdfMb}MB. File size: " . round($fileSize / 1024 / 1024, 2) . "MB",
        $archiveId
      );
    }

    // Additional check: verify file is not empty
    if ($fileSize === 0) {
      throw new FileValidationException(
        FileValidationErrorType::CORRUPTED_FILE,
        "File is empty or corrupted",
        $archiveId
      );
    }
  }

  /**
   * Compress and optimize file for storage.
   *
   * Implements the compression hook required by AbstractArchiveService.
   * Currently returns file as-is without compression.
   *
   * Future optimizations can include:
   * - Image optimization (resize, convert to WebP)
   * - PDF compression (ghostscript)
   * - Quality/DPI reduction for images
   *
   * @param UploadedFile $file
   * @param string $archiveId Unique operation ID for tracing
   * @return UploadedFile
   *
   * @throws CompressionException
   */
  protected function compressFile(UploadedFile $file, string $archiveId): UploadedFile
  {
    // TODO: Implement activity-specific compression (optional)
    // Future: Optimize images, compress PDFs, etc.
    // For now, return file as-is (no compression)
    return $file;
  }
}
