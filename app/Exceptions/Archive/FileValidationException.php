<?php

namespace App\Exceptions\Archive;

/**
 * Enumeration of file validation error types.
 */
enum FileValidationErrorType: string {
  case CORRUPTED_FILE = 'CORRUPTED_FILE';
  case INVALID_MIMETYPE = 'INVALID_MIMETYPE';
  case INVALID_EXTENSION = 'INVALID_EXTENSION';
  case SIZE_EXCEEDED = 'SIZE_EXCEEDED';
  case INVALID_HEADER = 'INVALID_HEADER';
  case FILE_TYPE_MISMATCH = 'FILE_TYPE_MISMATCH';
  case UNSPECIFIED = 'UNSPECIFIED';
}

/**
 * Thrown when file validation fails (size, type, corruption, etc).
 * 
 * Thrown by: preValidate()
 * HTTP Status: 422 Unprocessable Entity
 */
class FileValidationException extends ArchiveException
{
  public function __construct(
    public readonly FileValidationErrorType $errorType = FileValidationErrorType::UNSPECIFIED,
    string $reason = '',
    ?string $archiveId = null,
    \Throwable $previous = null
  ) {
    $message = match ($this->errorType) {
      FileValidationErrorType::CORRUPTED_FILE => "File is corrupted or unreadable: {$reason}",
      FileValidationErrorType::INVALID_MIMETYPE => "File MIME type is invalid: {$reason}",
      FileValidationErrorType::INVALID_EXTENSION => "File extension is not allowed: {$reason}",
      FileValidationErrorType::SIZE_EXCEEDED => "File exceeds size limit: {$reason}",
      FileValidationErrorType::INVALID_HEADER => "File header validation failed: {$reason}",
      FileValidationErrorType::FILE_TYPE_MISMATCH => "File type mismatch: {$reason}",
      FileValidationErrorType::UNSPECIFIED => "File validation failed: {$reason}",
    };

    parent::__construct($this->errorType->value, $message, 422, $previous, $archiveId);
  }
}
