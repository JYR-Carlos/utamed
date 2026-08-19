<?php

namespace App\Exceptions\Archive;

/**
 * Enumeration of virus detection error types.
 */
enum VirusDetectionErrorType: string {
  case VIRUS_FOUND = 'VIRUS_FOUND';
  case SUSPICIOUS_CONTENT = 'SUSPICIOUS_CONTENT';
  case SCAN_TIMEOUT = 'SCAN_TIMEOUT';
  case SCANNER_ERROR = 'SCANNER_ERROR';
  case UNSPECIFIED = 'UNSPECIFIED';
}

/**
 * Thrown when virus scanner detects malicious content.
 * 
 * Thrown by: scanForViruses()
 * HTTP Status: 422 Unprocessable Entity
 * Action: File should NOT be stored. Incident logged at ALERT level.
 */
class VirusDetectedException extends ArchiveException
{
  /**
   * Details about the detection (virus name, signature, etc)
   */
  private string $detectionDetails;

  public function __construct(
    public readonly VirusDetectionErrorType $errorType = VirusDetectionErrorType::VIRUS_FOUND,
    string $details = '',
    ?string $archiveId = null,
    ?\Throwable $previous = null
  ) {
    $message = match ($this->errorType) {
      VirusDetectionErrorType::VIRUS_FOUND => "Virus detected: {$details}",
      VirusDetectionErrorType::SUSPICIOUS_CONTENT => "File contains suspicious content: {$details}",
      VirusDetectionErrorType::SCAN_TIMEOUT => "Virus scan timed out: {$details}",
      VirusDetectionErrorType::SCANNER_ERROR => "Virus scanner error: {$details}",
      VirusDetectionErrorType::UNSPECIFIED => "Virus detection issue: {$details}",
    };

    parent::__construct($this->errorType->value, $message, 422, $previous, $archiveId);
    $this->detectionDetails = $details;
  }

  public function getDetectionDetails(): string
  {
    return $this->detectionDetails;
  }
}
