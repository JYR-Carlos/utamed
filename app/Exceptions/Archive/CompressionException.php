<?php

namespace App\Exceptions\Archive;

/**
 * Enumeration of compression error types.
 */
enum CompressionErrorType: string {
  case OPTIMIZATION_FAILED = 'OPTIMIZATION_FAILED';
  case CODEC_UNAVAILABLE = 'CODEC_UNAVAILABLE';
  case MEMORY_EXCEEDED = 'MEMORY_EXCEEDED';
  case TIMEOUT = 'TIMEOUT';
  case UNSUPPORTED_FORMAT = 'UNSUPPORTED_FORMAT';
  case UNSPECIFIED = 'UNSPECIFIED';
}

/**
 * Thrown when file compression/optimization fails.
 * 
 * Thrown by: compressFile()
 * HTTP Status: 500 Internal Server Error
 * Action: File not stored. Incident logged at ERROR level.
 */
class CompressionException extends ArchiveException
{
  public function __construct(
    public readonly CompressionErrorType $errorType = CompressionErrorType::OPTIMIZATION_FAILED,
    string $reason = '',
    ?string $archiveId = null,
    ?\Throwable $previous = null
  ) {
    $message = match ($this->errorType) {
      CompressionErrorType::OPTIMIZATION_FAILED => "File optimization failed: {$reason}",
      CompressionErrorType::CODEC_UNAVAILABLE => "Required codec is not available: {$reason}",
      CompressionErrorType::MEMORY_EXCEEDED => "Optimization exceeded memory limit: {$reason}",
      CompressionErrorType::TIMEOUT => "Optimization timed out: {$reason}",
      CompressionErrorType::UNSUPPORTED_FORMAT => "File format not supported for optimization: {$reason}",
      CompressionErrorType::UNSPECIFIED => "Compression failed: {$reason}",
    };

    parent::__construct($this->errorType->value, $message, 500, $previous, $archiveId);
  }
}
