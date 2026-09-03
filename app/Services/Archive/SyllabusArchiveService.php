<?php

namespace App\Services\Archive;

use App\Exceptions\Archive\ArchiveErrorType;
use App\Exceptions\Archive\ArchiveException;
use App\Exceptions\Archive\CompressionException;
use App\Exceptions\Archive\FileValidationErrorType;
use App\Exceptions\Archive\FileValidationException;
use Illuminate\Http\UploadedFile;

/**
 * Syllabus-specific implementation of Archive storage template method.
 *
 * Extends AbstractArchiveService to specialize file validation and storage
 * for syllabus programs and academic bibliographies.
 *
 * Allowed file types:
 * - PDFs: application/pdf (max 100MB)
 * - Word documents: doc, docx, odt, rtf, txt (max 50MB)
 * - Presentations: ppt, pptx, odp (max 100MB)
 * - Generic documents: epub, etc. (max 100MB)
 *
 * Usage:
 * ```php
 * $syllabusService = new SyllabusArchiveService();
 * $result = $syllabusService->performStorage($request);
 * ```
 */
class SyllabusArchiveService extends AbstractArchiveService
{
    public function __construct()
    {
        // Root segment sets files under {basePath}/syllabus/
        parent::__construct(rootSegment: 'syllabus');
    }

    /**
     * Obtener configuración combinada de tipos permitidos desde config/filetypes.php
     *
     * @return array{extensions: string[], mimes: string[], max_size: int}
     * @throws ArchiveException
     */
    private function    getAllowedTypesConfig(): array
    {
        $categories = ['pdf', 'word_document', 'presentation', 'document'];
        $extensions = [];
        $mimes = [];
        $maxSize = 0;

        foreach ($categories as $cat) {
            $cfg = config("filetypes.{$cat}");
            if (!$cfg) {
                continue;
            }

            if (!empty($cfg['extensions'])) {
                $extensions = [...$extensions, ...$cfg['extensions']];
            }
            if (!empty($cfg['mimes'])) {
                $mimes = [...$mimes, ...$cfg['mimes']];
            }
            if (!empty($cfg['max_size']) && $cfg['max_size'] > $maxSize) {
                $maxSize = (int) $cfg['max_size'];
            }
        }

        // Agregar extensión epub y su mime en caso de no estar en el config
        if (!\in_array('epub', $extensions, true)) {
            $extensions[] = 'epub';
        }
        if (!\in_array('application/epub+zip', $mimes, true)) {
            $mimes[] = 'application/epub+zip';
        }

        if (empty($extensions) || empty($mimes)) {
            throw new ArchiveException(
                ArchiveErrorType::CONFIGURATION_ERROR,
                "Syllabus file configuration missing or incomplete in config/filetypes.php"
            );
        }

        return [
            'extensions' => array_unique(array_map('strtolower', $extensions)),
            'mimes' => array_unique($mimes),
            'max_size' => $maxSize ?: (int) config('filetypes.global.max_file_size', 52428800),
        ];
    }

    /**
     * Validate uploaded file for syllabus/bibliography constraints.
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

        $config = $this->getAllowedTypesConfig();

        $isAllowed = \in_array($extension, $config['extensions'], true)
            && \in_array($mimeType, $config['mimes'], true);

        if (!$isAllowed) {
            throw new FileValidationException(
                FileValidationErrorType::INVALID_EXTENSION,
                "Tipo de archivo no permitido. Solo se aceptan documentos (PDF, Word, PPT, EPUB, etc.). Recibido: {$mimeType} (.{$extension})",
                $archiveId
            );
        }

        if ($fileSize > $config['max_size']) {
            $maxMb = round($config['max_size'] / 1024 / 1024, 2);
            $sizeMb = round($fileSize / 1024 / 1024, 2);
            throw new FileValidationException(
                FileValidationErrorType::SIZE_EXCEEDED,
                "El archivo excede el tamaño máximo permitido de {$maxMb}MB. Tamaño: {$sizeMb}MB",
                $archiveId
            );
        }

        if ($fileSize === 0) {
            throw new FileValidationException(
                FileValidationErrorType::CORRUPTED_FILE,
                "El archivo está vacío o dañado",
                $archiveId
            );
        }
    }

    /**
     * Compress and optimize file for storage.
     *
     * @param UploadedFile $file
     * @param string $archiveId
     * @return UploadedFile
     *
     * @throws CompressionException
     */
    protected function compressFile(UploadedFile $file, string $archiveId): UploadedFile
    {
        // Por ahora se guarda tal cual sin compresión destructiva
        return $file;
    }
}
