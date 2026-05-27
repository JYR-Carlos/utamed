<?php

/**
 * Archive Service Configuration
 *
 * Centralized configuration for the file service system:
 * - Image optimization settings
 * - Storage paths
 * - Cleanup policies
 *
 * @see Fase 2 documentation: docs/FASE_2_VALIDACION_ARCHIVOS.md
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Image Optimization Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the ImageOptimizer service which processes image uploads
    |
    */

    'image_optimizer' => [

        /*
        |----------------------------------------------------------------------
        | Maximum Image Width
        |----------------------------------------------------------------------
        |
        | Images wider than this pixel value will be resized proportionally.
        | Recommended: 2000-4000 for web, up to 5000 for high-quality storage
        |
        | Default: 2000px
        |
        */
        'max_width' => (int) env('ARCHIVE_IMAGE_MAX_WIDTH', 2000),

        /*
        |----------------------------------------------------------------------
        | WebP Compression Quality
        |----------------------------------------------------------------------
        |
        | Quality level for WebP conversion (0-100)
        | - 60-70: Lower quality, smaller files (web)
        | - 80-85: Balanced (recommended)
        | - 90-100: High quality, larger files (archival)
        |
        | Default: 80
        |
        */
        'webp_quality' => (int) env('ARCHIVE_IMAGE_WEBP_QUALITY', 80),

        /*
        |----------------------------------------------------------------------
        | Preserve EXIF Metadata
        |----------------------------------------------------------------------
        |
        | Whether to preserve EXIF data from camera/camera photos during
        | WebP conversion. Note: Reduces file size if disabled.
        |
        | Default: false
        |
        */
        'preserve_exif' => (bool) env('ARCHIVE_IMAGE_PRESERVE_EXIF', false),

        /*
        |----------------------------------------------------------------------
        | Vector Formats (Not Optimized)
        |----------------------------------------------------------------------
        |
        | File extensions that should NOT be optimized (stored as-is).
        | These are vector/non-raster formats that don't benefit from WebP.
        |
        | Default: svg, pdf, eps, ai, emf, wmf
        |
        */
        'vector_formats' => explode(
            ',',
            env('ARCHIVE_IMAGE_VECTOR_FORMATS', 'svg,pdf,eps,ai,emf,wmf')
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    |
    | Temporary and permanent storage paths for archive processing
    |
    */

    'storage' => [

        /*
        |----------------------------------------------------------------------
        | Temporary Directory
        |----------------------------------------------------------------------
        |
        | Relative path within storage_path() where temporary files are stored
        | during processing (e.g., optimized images, extracts before final move)
        |
        | Example: storage_path('app/temp/archive')
        |
        */
        'temp_directory' => env('ARCHIVE_TEMP_DIR', 'app/temp/archive'),

        /*
        |----------------------------------------------------------------------
        | Archive Disk
        |----------------------------------------------------------------------
        |
        | Laravel Storage disk for permanent archive storage.
        | Configure in config/filesystems.php
        |
        | Default: 'local_archives'
        |
        */
        'disk' => env('ARCHIVE_STORAGE_DISK', 'local_archives'),

        /*
        |----------------------------------------------------------------------
        | Base Archive Path
        |----------------------------------------------------------------------
        |
        | Base directory within storage disk where archives are organized.
        | Subdirectories are created per contexto/curso/activity
        |
        | Example: 'archivos/2026'
        |
        */
        'base_path' => env('ARCHIVE_BASE_PATH', 'archivos'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Policies
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic cleanup of orphaned/temporary files
    |
    */

    'cleanup' => [

        /*
        |----------------------------------------------------------------------
        | Enable Auto Cleanup
        |----------------------------------------------------------------------
        |
        | Whether to run cleanup of orphaned temporary files.
        | Can be disabled for debugging or in development.
        |
        | Default: true
        |
        */
        'enabled' => (bool) env('ARCHIVE_CLEANUP_ENABLED', true),

        /*
        |----------------------------------------------------------------------
        | Cleanup Age (minutes)
        |----------------------------------------------------------------------
        |
        | Delete temporary files older than this many minutes.
        | Temporary files from failed uploads should be cleaned up quickly.
        |
        | Default: 120 (2 hours)
        |
        */
        'temp_file_age_minutes' => (int) env('ARCHIVE_CLEANUP_TEMP_AGE_MINUTES', 120),

        /*
        |----------------------------------------------------------------------
        | Orphaned Record Retention (days)
        |----------------------------------------------------------------------
        |
        | Keep records of orphaned files for this many days before hard-delete
        | from database. Allows for auditing and recovery.
        |
        | Default: 30 days
        |
        */
        'orphaned_retention_days' => (int) env('ARCHIVE_CLEANUP_ORPHANED_RETENTION_DAYS', 30),

        /*
        |----------------------------------------------------------------------
        | Max Cleanup Runtime (seconds)
        |----------------------------------------------------------------------
        |
        | Maximum time the cleanup command can run before exiting.
        | Prevents long-running processes from blocking deployment.
        |
        | Default: 300 (5 minutes)
        |
        */
        'max_runtime_seconds' => (int) env('ARCHIVE_CLEANUP_MAX_RUNTIME_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    |
    | Additional validation settings beyond what's in config/files.php
    |
    */

    'validation' => [

        /*
        |----------------------------------------------------------------------
        | Enable Virus Scanning
        |----------------------------------------------------------------------
        |
        | Whether to scan uploaded files for viruses using ClamAV or similar.
        | Requires separate integration (not implemented in Phase 2).
        |
        | Default: false
        |
        */
        'virus_scan_enabled' => (bool) env('ARCHIVE_VIRUS_SCAN_ENABLED', false),

        /*
        |----------------------------------------------------------------------
        | Enable Content Detection
        |----------------------------------------------------------------------
        |
        | Verify actual file contents match extension/MIME type.
        | Detects files renamed with wrong extensions.
        |
        | Default: true
        |
        */
        'content_validation_enabled' => (bool) env('ARCHIVE_CONTENT_VALIDATION_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Archive service logging configuration
    |
    */

    'logging' => [

        /*
        |----------------------------------------------------------------------
        | Log Channel
        |----------------------------------------------------------------------
        |
        | Channel to use for archive service logs.
        | Configure in config/logging.php
        |
        | Default: 'single'
        |
        */
        'channel' => env('ARCHIVE_LOG_CHANNEL', 'single'),

        /*
        |----------------------------------------------------------------------
        | Log All Optimizations
        |----------------------------------------------------------------------
        |
        | Whether to log every image optimization (verbose logging).
        | Useful for debugging, disable in production for performance.
        |
        | Default: false
        |
        */
        'log_optimizations' => (bool) env('ARCHIVE_LOG_OPTIMIZATIONS', false),

        /*
        |----------------------------------------------------------------------
        | Log All Uploads
        |----------------------------------------------------------------------
        |
        | Whether to log every file upload (both success and failure).
        |
        | Default: true
        |
        */
        'log_uploads' => (bool) env('ARCHIVE_LOG_UPLOADS', true),
    ],
];
