<?php

namespace App\Exceptions\Archive;

use App\Exceptions\Archive\ArchiveErrorType;

/**
 * Enumeration of storage error types.
 */
enum StorageErrorType: string {
  case DISK_FULL = 'DISK_FULL';
  case PERMISSION_DENIED = 'PERMISSION_DENIED';
  case CONNECTIVITY_FAILED = 'CONNECTIVITY_FAILED';
  case INVALID_PATH = 'INVALID_PATH';
  case IO_ERROR = 'IO_ERROR';
  case QUOTA_EXCEEDED = 'QUOTA_EXCEEDED';
  case UNSPECIFIED = 'UNSPECIFIED';
}

/**
 * Thrown when file storage operation fails (permission, disk full, connectivity, etc).
 * 
 * Thrown by: store()
 * HTTP Status: 500 Internal Server Error
 * Action: Cleanup partial results from disk. Incident logged at CRITICAL level.
 */
class StorageException extends ArchiveException
{
  /**
   * Path where storage was attempted (for cleanup)
   */
  private ?string $storagePath;

  public function __construct(
    public readonly StorageErrorType $errorType = StorageErrorType::UNSPECIFIED,
    string $reason = '',
    ?string $archiveId = null,
    ?string $storagePath = null,
    ?\Throwable $previous = null
  ) {
    $message = match ($this->errorType) {
      StorageErrorType::DISK_FULL => "Disk is full: {$reason}",
      StorageErrorType::PERMISSION_DENIED => "Permission denied: {$reason}",
      StorageErrorType::CONNECTIVITY_FAILED => "Storage connectivity failed: {$reason}",
      StorageErrorType::INVALID_PATH => "Invalid storage path: {$reason}",
      StorageErrorType::IO_ERROR => "Disk I/O error: {$reason}",
      StorageErrorType::QUOTA_EXCEEDED => "Storage quota exceeded: {$reason}",
      StorageErrorType::UNSPECIFIED => "Storage operation failed: {$reason}",
    };

    parent::__construct(ArchiveErrorType::OPERATION_FAILED, $message, 500, $previous, $archiveId);
    $this->storagePath = $storagePath;
  }

  public function getStoragePath(): ?string
  {
    return $this->storagePath;
  }
}
