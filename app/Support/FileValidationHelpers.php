<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Number;

/**
 * FileValidationHelpers
 * 
 * Helpers para validación y manipulación de archivos.
 * 
 * ⚠️  IMPORTANTE: Usar SOLO cuando FormRequests no sean viables
 * 
 * En controladores, SIEMPRE preferir inyectar un FormRequest:
 * ```php
 * class StoreVideoController {
 *     public function store(VideoRequest $request) {
 *         // Validación automática + segura
 *         $file = $request->getFile();
 *     }
 * }
 * ```
 * 
 * Los helpers son SOLO para:
 * - Servicios (validación programática)
 * - Comandos Artisan (batch processing)
 * - Jobs/Queues
 * - Lógica no-HTTP (cuando no hay FormRequest disponible)
 * 
 * Ejemplo correcto en Servicio:
 * ```php
 * class VideoProcessingService {
 *     public function process(UploadedFile $file) {
 *         $result = FileValidationHelpers::validateFile($file, 'video');
 *         if (!$result['valid']) {
 *             throw new ValidationException(implode(', ', $result['errors']));
 *         }
 *     }
 * }
 * ```
 */
class FileValidationHelpers
{
    /**
     * Obtener configuración de un tipo de archivo.
     * 
     * @param string $fileType Clave del tipo de archivo en config('filetypes')
     * @return array|null Configuración del tipo o null si no existe
     */
    public static function getFileTypeConfig(string $fileType): ?array
    {
        return config("filetypes.{$fileType}");
    }

    /**
     * Verificar si un archivo está permitido según su tipo.
     * 
     * @param \Illuminate\Http\UploadedFile $file Archivo a verificar
     * @param string $fileType Tipo de archivo esperado
     * @return bool True si el archivo es válido para el tipo
     */
    public static function isFileTypeValid(UploadedFile $file, string $fileType): bool
    {
        $fileConfig = config("filetypes.{$fileType}");
        
        if (!$fileConfig) {
            return false;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        $extensions = array_map('strtolower', $fileConfig['extensions'] ?? []);
        $mimes = $fileConfig['mimes'] ?? [];

        return \in_array($extension, $extensions) && \in_array($mimeType, $mimes);
    }

    /**
     * Obtener extensiones permitidas para un tipo de archivo.
     * 
     * @param string $fileType Tipo de archivo
     * @return array Array de extensiones permitidas
     */
    public static function getAllowedExtensions(string $fileType): array
    {
        $config = self::getFileTypeConfig($fileType);
        return $config['extensions'] ?? [];
    }

    /**
     * Obtener MIME types permitidos para un tipo de archivo.
     * 
     * @param string $fileType Tipo de archivo
     * @return array Array de MIME types permitidos
     */
    public static function getAllowedMimes(string $fileType): array
    {
        $config = self::getFileTypeConfig($fileType);
        return $config['mimes'] ?? [];
    }

    /**
     * Obtener tamaño máximo permitido para un tipo de archivo.
     * 
     * @param string $fileType Tipo de archivo
     * @return int Tamaño máximo en bytes
     */
    public static function getMaxFileSize(string $fileType): int
    {
        $config = self::getFileTypeConfig($fileType);
        return $config['max_size'] ?? config('filetypes.global.max_file_size', 52428800);
    }

    /**
     * Obtener descripción de un tipo de archivo.
     * 
     * @param string $fileType Tipo de archivo
     * @return string Descripción del tipo
     */
    public static function getFileTypeDescription(string $fileType): string
    {
        $config = self::getFileTypeConfig($fileType);
        return $config['description'] ?? $fileType;
    }

    /**
     * Detectar el tipo de archivo basándose en su extensión.
     * 
     * Usa los aliases definidos en config('filetypes.aliases').
     * 
     * @param string $extension Extensión del archivo (sin punto)
     * @return string|null Tipo de archivo o null si no es reconocido
     */
    public static function detectFileTypeByExtension(string $extension): ?string
    {
        $extension = strtolower($extension);
        $aliases = config('filetypes.aliases', []);

        // Buscar en aliases primero
        if (isset($aliases[$extension])) {
            return $aliases[$extension];
        }

        // Si no encuentra en aliases, buscar en los tipos
        foreach ((array) config('filetypes') as $typeKey => $typeConfig) {
            if (\is_array($typeConfig) && isset($typeConfig['extensions'])) {
                if (\in_array($extension, array_map('strtolower', $typeConfig['extensions']))) {
                    return $typeKey;
                }
            }
        }

        return null;
    }

    /**
     * Obtener el FormRequest recomendado para un tipo de archivo.
     * 
     * ⚠️  Este método es útil solo en contextos no-HTTP donde necesitas
     * seleccionar dinámicamente qué FormRequest usar, pero en controladores
     * siempre inyecta el FormRequest directamente.
     * 
     * @param string $fileType Tipo de archivo
     * @return string Clase del FormRequest
     * @throws \InvalidArgumentException Si no hay FormRequest para el tipo
     */
    public static function getFormRequestClass(string $fileType): string
    {
        $mapping = [
            'video'         => \App\Http\Requests\Archive\Extended\VideoRequest::class,
            'image'         => \App\Http\Requests\Archive\Extended\ImageRequest::class,
            'compressed'    => \App\Http\Requests\Archive\Extended\CompressedFileRequest::class,
            'word_document' => \App\Http\Requests\Archive\Extended\WordDocumentRequest::class,
            'presentation'  => \App\Http\Requests\Archive\Extended\PresentationDocumentRequest::class,
            'spreadsheet'   => \App\Http\Requests\Archive\Extended\SpreadsheetRequest::class,
            'pdf'           => \App\Http\Requests\Archive\Extended\PdfRequest::class,
            'link'          => \App\Http\Requests\Archive\Extended\LinkRequest::class,
            'media'         => \App\Http\Requests\Archive\Extended\MediaRequest::class,
            'raw_art'       => \App\Http\Requests\Archive\Extended\RawArtRequest::class,
            'document'      => \App\Http\Requests\Archive\BaseArchiveRequest::class,
        ];

        if (!isset($mapping[$fileType])) {
            throw new \InvalidArgumentException(
                "No FormRequest encontrado para tipo de archivo: {$fileType}"
            );
        }

        return $mapping[$fileType];
    }

    /**
     * Validar que un archivo cumple con los requisitos de un tipo.
     * 
     * Realiza validaciones completas: extensión, MIME type, tamaño.
     * 
     * ⚠️  NO USAR EN CONTROLADORES. Si es posible, inyecta un FormRequest:
     * 
     * ❌ Incorrecto (en controlador):
     * ```php
     * $result = FileValidationHelpers::validateFile($request->file('video'), 'video');
     * ```
     * 
     * ✅ Correcto (en controlador):
     * ```php
     * public function store(VideoRequest $request) { // Validación automática
     *     $file = $request->getFile();
     * }
     * ```
     * 
     * ✅ Correcto (en Servicio/Job donde no hay FormRequest):
     * ```php
     * $result = FileValidationHelpers::validateFile($file, 'video');
     * if (!$result['valid']) throw new Exception(...);
     * ```
     * 
     * @param \Illuminate\Http\UploadedFile $file Archivo a validar
     * @param string $fileType Tipo de archivo esperado
     * @return array Array con 'valid' (bool) y 'errors' (array de mensajes)
     */
    public static function validateFile(UploadedFile $file, string $fileType): array
    {
        $errors = [];
        $config = self::getFileTypeConfig($fileType);

        if (!$config) {
            return [
                'valid' => false,
                'errors' => ["Tipo de archivo no válido: {$fileType}"],
            ];
        }

        // Verificar tamaño
        if ($file->getSize() > $config['max_size']) {
            $errors[] = sprintf(
                'El archivo excede el tamaño máximo de %s',
                Number::fileSize($config['max_size'])
            );
        }

        // Verificar extensión
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = array_map('strtolower', $config['extensions']);
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = sprintf(
                'Extensión no permitida. Extensiones válidas: %s',
                implode(', ', $allowedExtensions)
            );
        }

        // Verificar MIME type
        $mimeType = $file->getMimeType();
        $allowedMimes = $config['mimes'];
        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = sprintf(
                'Tipo MIME no permitido. Tipos válidos: %s',
                implode(', ', $allowedMimes)
            );
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
