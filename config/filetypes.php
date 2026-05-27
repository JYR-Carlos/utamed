<?php

/**
 * ============================================================================
 * CONFIGURACIÓN DE TIPOS DE ARCHIVO Y VALIDACIONES
 * ============================================================================
 * 
 * Define los tipos de archivo permitidos en el sistema, sus extensiones,
 * MIME types y límites de tamaño. Esta configuración es utilizada por
 * los FormRequests especializados para validación.
 * 
 * Estructura:
 * 'tipo' => [
 *     'extensions' => ['ext1', 'ext2'],
 *     'mimes' => ['mime/type1', 'mime/type2'],
 *     'max_size' => bytes,
 *     'description' => 'Descripción del tipo',
 * ]
 */

return [

    /*
    |--------------------------------------------------------------------------
    | ARCHIVO DE CONFIGURACIÓN POR DEFECTO
    |--------------------------------------------------------------------------
    */
    'default' => env('FILES_DEFAULT_TYPE', 'document'),

    /*
    |--------------------------------------------------------------------------
    | LÍMITES GLOBALES
    |--------------------------------------------------------------------------
    */
    'global' => [
        'max_file_size' => (int) env('FILES_MAX_FILE_SIZE', 52428800), // 50MB
        'enable_mime_validation' => env('FILES_ENABLE_MIME_VALIDATION', true),
        'enable_extension_validation' => env('FILES_ENABLE_EXTENSION_VALIDATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE ARCHIVO: VIDEOS
    |--------------------------------------------------------------------------
    */
    'video' => [
        'extensions' => explode(',', env(
            'FILES_VIDEO_EXTENSIONS',
            'mp4,webm,avi,mov,mkv,flv,wmv,m4v'
        )),
        'mimes' => explode(',', env(
            'FILES_VIDEO_MIMES',
            'video/mp4,video/webm,video/x-msvideo,video/quicktime,video/x-matroska,video/x-flv,video/x-ms-wmv,video/x-m4v'
        )),
        'max_size' => (int) env('FILES_VIDEO_MAX_SIZE', 524288000), // 500MB
        'description' => 'Archivos de video',
    ],

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE ARCHIVO: IMÁGENES (incluyendo vectores)
    |--------------------------------------------------------------------------
    */
    'image' => [
        'extensions' => explode(',', env(
            'FILES_IMAGE_EXTENSIONS',
            'png,jpg,jpeg,webp,gif,bmp,svg,tiff'
        )),
        'mimes' => explode(',', env(
            'FILES_IMAGE_MIMES',
            'image/png,image/jpeg,image/webp,image/gif,image/bmp,image/svg+xml,image/tiff'
        )),
        'max_size' => (int) env('FILES_IMAGE_MAX_SIZE', 52428800), // 50MB
        'description' => 'Archivos de imagen (raster y vectoriales)',
    ],

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE ARCHIVO: ARCHIVOS COMPRIMIDOS
    |--------------------------------------------------------------------------
    */
    'compressed' => [
        'extensions' => explode(',', env(
            'FILES_COMPRESSED_EXTENSIONS',
            'zip,rar,7z,tar,gz,bz2,tar.gz,tar.bz2'
        )),
        'mimes' => explode(',', env(
            'FILES_COMPRESSED_MIMES',
            'application/zip,application/x-rar-compressed,application/x-7z-compressed,application/x-tar,application/gzip,application/x-bzip2'
        )),
        'max_size' => (int) env('FILES_COMPRESSED_MAX_SIZE', 524288000), // 500MB
        'description' => 'Archivos comprimidos',
    ],

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE ARCHIVO: DOCUMENTOS DE TEXTO (Word, RTF)
    |--------------------------------------------------------------------------
    */
    'word_document' => [
        'extensions' => explode(',', env(
            'FILES_WORD_DOCUMENT_EXTENSIONS',
            'doc,docx,docm,dot,dotx,dotm,rtf,odt,txt'
        )),
        'mimes' => explode(',', env(
            'FILES_WORD_DOCUMENT_MIMES',
            'application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-word.document.macroenabled.12,application/vnd.openxmlformats-officedocument.wordprocessingml.template,application/rtf,application/vnd.oasis.opendocument.text,text/plain'
        )),
        'max_size' => (int) env('FILES_WORD_DOCUMENT_MAX_SIZE', 52428800), // 50MB
        'description' => 'Documentos de texto (Word, RTF, ODT)',
    ],

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE ARCHIVO: PRESENTACIONES (PowerPoint, Keynote, Impress)
    |--------------------------------------------------------------------------
    */
    'presentation' => [
        'extensions' => explode(',', env(
            'FILES_PRESENTATION_EXTENSIONS',
            'ppt,pptx,pptm,pot,potx,potm,odp,key'
        )),
        'mimes' => explode(',', env(
            'FILES_PRESENTATION_MIMES',
            'application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.ms-powerpoint.presentation.macroenabled.12,application/vnd.openxmlformats-officedocument.presentationml.slidemaster,application/vnd.oasis.opendocument.presentation,application/vnd.apple.keynote'
        )),
        'max_size' => (int) env('FILES_PRESENTATION_MAX_SIZE', 104857600), // 100MB
        'description' => 'Presentaciones (PowerPoint, Keynote, Impress)',
    ],

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE ARCHIVO: HOJAS DE CÁLCULO (Excel, Calc, Numbers)
    |--------------------------------------------------------------------------
    */
    'spreadsheet' => [
        'extensions' => explode(',', env(
            'FILES_SPREADSHEET_EXTENSIONS',
            'xls,xlsx,xlsm,xlt,xltx,xltm,ods,numbers,csv,tsv'
        )),
        'mimes' => explode(',', env(
            'FILES_SPREADSHEET_MIMES',
            'application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel.sheet.macroenabled.12,application/vnd.openxmlformats-officedocument.spreadsheetml.template,application/vnd.oasis.opendocument.spreadsheet,application/vnd.apple.numbers,text/csv,text/tab-separated-values'
        )),
        'max_size' => (int) env('FILES_SPREADSHEET_MAX_SIZE', 52428800), // 50MB
        'description' => 'Hojas de cálculo (Excel, Calc, Numbers)',
    ],

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE ARCHIVO: DOCUMENTOS PORTÁTILES (PDF)
    |--------------------------------------------------------------------------
    */
    'pdf' => [
        'extensions' => explode(',', env(
            'FILES_PDF_EXTENSIONS',
            'pdf'
        )),
        'mimes' => explode(',', env(
            'FILES_PDF_MIMES',
            'application/pdf'
        )),
        'max_size' => (int) env('FILES_PDF_MAX_SIZE', 104857600), // 100MB
        'description' => 'Documentos PDF',
    ],

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE ARCHIVO: ENLACES (URLs a páginas web)
    |--------------------------------------------------------------------------
    */
    'link' => [
        'extensions' => ['url', 'link', 'txt'], // No se valida extensión para URLs
        'mimes' => ['text/plain', 'text/uri-list'],
        'max_size' => 10240, // 10KB (solo metadatos)
        'description' => 'Enlaces a recursos web',
        'allow_no_file' => true, // Permite que no haya archivo físico
    ],

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE ARCHIVO: MEDIA (Superset: videos, imágenes, audio)
    |--------------------------------------------------------------------------
    */
    'media' => [
        'extensions' => explode(',', env(
            'FILES_MEDIA_EXTENSIONS',
            'mp4,webm,avi,mov,mkv,flv,wmv,m4v,mp3,wav,flac,aac,ogg,wma,png,jpg,jpeg,webp,gif,bmp,svg,tiff,psd,ai,raw,cr2,nef,arw'
        )),
        'mimes' => explode(',', env(
            'FILES_MEDIA_MIMES',
            'video/mp4,video/webm,video/x-msvideo,video/quicktime,video/x-matroska,video/x-flv,video/x-ms-wmv,video/x-m4v,audio/mpeg,audio/wav,audio/flac,audio/aac,audio/ogg,audio/x-ms-wma,image/png,image/jpeg,image/webp,image/gif,image/bmp,image/svg+xml,image/tiff,image/vnd.adobe.photoshop,application/postscript,image/x-raw,image/x-canon-crw,image/x-canon-cr2,image/x-nikon-nef,image/x-sony-arw'
        )),
        'max_size' => (int) env('FILES_MEDIA_MAX_SIZE', 1048576000), // 1GB
        'description' => 'Archivos multimedia (videos, imágenes, audio)',
    ],

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE ARCHIVO: DATOS DE ARTE RAW (Raw, PSD, AI, XCF)
    |--------------------------------------------------------------------------
    */
    'raw_art' => [
        'extensions' => explode(',', env(
            'FILES_RAW_ART_EXTENSIONS',
            'psd,ai,xcf,raw,cr2,nef,arw,dng,eps,pdf'
        )),
        'mimes' => explode(',', env(
            'FILES_RAW_ART_MIMES',
            'image/vnd.adobe.photoshop,application/postscript,image/x-xcf,image/x-raw,image/x-canon-crw,image/x-canon-cr2,image/x-nikon-nef,image/x-sony-arw,image/x-canon-dng,application/pdf'
        )),
        'max_size' => (int) env('FILES_RAW_ART_MAX_SIZE', 1048576000), // 1GB
        'description' => 'Archivos de arte raw (PSD, AI, archivos raw de cámara)',
    ],

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE ARCHIVO: DOCUMENTOS GENÉRICOS
    |--------------------------------------------------------------------------
    | 
    | Usado como fallback para documentos que no encajan en otras categorías
    */
    'document' => [
        'extensions' => explode(',', env(
            'FILES_DOCUMENT_EXTENSIONS',
            'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,odt,ods,odp'
        )),
        'mimes' => explode(',', env(
            'FILES_DOCUMENT_MIMES',
            'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,application/rtf,application/vnd.oasis.opendocument.text,application/vnd.oasis.opendocument.spreadsheet,application/vnd.oasis.opendocument.presentation'
        )),
        'max_size' => (int) env('FILES_DOCUMENT_MAX_SIZE', 104857600), // 100MB
        'description' => 'Documentos genéricos',
    ],

    /*
    |--------------------------------------------------------------------------
    | MAPEO DE TIPOS: Aliases para acceso simplificado
    |--------------------------------------------------------------------------
    */
    'aliases' => [
        'mov' => 'video',
        'avi' => 'video',
        'jpg' => 'image',
        'png' => 'image',
        'doc' => 'word_document',
        'docx' => 'word_document',
        'ppt' => 'presentation',
        'pptx' => 'presentation',
        'xls' => 'spreadsheet',
        'xlsx' => 'spreadsheet',
        'zip' => 'compressed',
    ],

];
