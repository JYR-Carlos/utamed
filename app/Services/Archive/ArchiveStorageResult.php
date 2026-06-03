<?php

namespace App\Services\Archive;

/**
 * Typed return value for archive storage operations.
 *
 * Encapsulates the complete result of a successful file storage operation,
 * providing strict typing and IDE autocomplete for all storage metadata.
 *
 * @immutable
 */
final class ArchiveStorageResult
{
  /**
   * Unique UUID v7 identifier for the stored file record in database.
   */
  public readonly string $uuidArchivo;

  /**
   * Storage disk identifier (configured in filesystems.php).
   * Example: 'local', 's3', 'azure', etc.
   */
  public readonly string $disk;

  /**
   * Full relative directory path within the disk where file is stored.
   * Example: 'agenda/stored/2024/programa-123'
   */
  public readonly string $directory;

  /**
   * Complete relative path from disk root to the stored file.
   * Example: 'agenda/stored/2024/programa-123/file-uuid.mp4'
   */
  public readonly string $path;

  /**
   * Filename as stored on disk (may differ from original).
   * Example: 'a1b2c3d4-e5f6-...-file.mp4'
   */
  public readonly string $fileName;

  /**
   * File size in bytes after optimization/compression.
   */
  public readonly int $sizeBytes;

  /**
   * MIME type detected during storage.
   * Example: 'video/mp4', 'image/jpeg', 'application/pdf'
   */
  public readonly string $mimeType;

  /**
   * Original filename as provided by the client.
   * Kept for auditing and user reference.
   */
  public readonly string $originalName;

  public function __construct(
    string $uuidArchivo,
    string $disk,
    string $directory,
    string $path,
    string $fileName,
    int $sizeBytes,
    string $mimeType,
    string $originalName
  ) {
    $this->uuidArchivo = $uuidArchivo;
    $this->disk = $disk;
    $this->directory = $directory;
    $this->path = $path;
    $this->fileName = $fileName;
    $this->sizeBytes = $sizeBytes;
    $this->mimeType = $mimeType;
    $this->originalName = $originalName;
  }

  /**
   * Construct from the legacy array format (for backward compatibility).
   *
   * @param array $data Legacy array with keys:
   *   - uuid_archivo: string
   *   - disk: string
   *   - directory: string
   *   - path: string
   *   - file_name: string
   *   - size_bytes: int
   *   - mime_type: string
   *   - original_name: string
   *
   * @return self
   */
  public static function fromArray(array $data): self
  {
    return new self(
      uuidArchivo: $data['uuid_archivo'],
      disk: $data['disk'],
      directory: $data['directory'],
      path: $data['path'],
      fileName: $data['file_name'],
      sizeBytes: $data['size_bytes'],
      mimeType: $data['mime_type'],
      originalName: $data['original_name']
    );
  }

  /**
   * Convert back to legacy array format for backward compatibility.
   *
   * @return array Legacy array structure
   */
  public function toArray(): array
  {
    return [
      'uuid_archivo' => $this->uuidArchivo,
      'disk' => $this->disk,
      'directory' => $this->directory,
      'path' => $this->path,
      'file_name' => $this->fileName,
      'size_bytes' => $this->sizeBytes,
      'mime_type' => $this->mimeType,
      'original_name' => $this->originalName,
    ];
  }

  /**
   * Check if file still exists on disk.
   *
   * @return bool
   */
  public function existsOnDisk(): bool
  {
    return \Illuminate\Support\Facades\Storage::disk($this->disk)->exists($this->path);
  }

  /**
   * Get human-readable file size (e.g., "2.5 MB").
   *
   * @return string
   */
  public function getReadableSize(): string
  {
    $bytes = $this->sizeBytes;
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
  }
}
