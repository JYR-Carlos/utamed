<?php

namespace App\Exceptions\Archive;

use Exception;

/**
 * Enumeration of generic archive error types.
 * Used for common, cross-cutting errors not specific to validation or compression.
 */
enum ArchiveErrorType: string
{
  case EMPTY_FILE = 'EMPTY_FILE';
  case OPERATION_FAILED = 'OPERATION_FAILED';
  case INVALID_ARCHIVE_ID = 'INVALID_ARCHIVE_ID';
  case UNSPECIFIED = 'UNSPECIFIED';
  case CONFIGURATION_ERROR = 'CONFIGURATION_ERROR';
}

/**
 * Base exception for all Archive service operations.
 * 
 * All specific archive exceptions inherit from this for unified error handling.
 * Each subclass defines its own type constants for distinguishing error scenarios.
 */
class ArchiveException extends Exception
{
  /**
   * Discriminator type for this exception family.
   * Each subclass defines constants like TYPE_VARIANT_A, TYPE_VARIANT_B
   * 
   * @var string
   */
  public readonly string $type;

  /**
   * Unique ID for this operation, used for tracing and debugging
   * 
   * @var string|null
   */
  protected ?string $archiveId;

  public function __construct(
    ArchiveErrorType $type,
    string $message = '',
    int $code = 0,
    ?\Throwable $previous = null,
    ?string $archiveId = null
  ) {
    parent::__construct($message, $code, $previous);
    $this->type = $type->value;
    $this->archiveId = $archiveId;
  }

  public function getArchiveId(): ?string
  {
    return $this->archiveId;
  }

  public function getType(): string
  {
    return $this->type;
  }
}
