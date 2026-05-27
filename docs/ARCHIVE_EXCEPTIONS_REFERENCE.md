# Archive Exceptions Reference

Sistema completo de excepciones tipadas para manejo de errores en almacenamiento de archivos.

## Jerarquía de Excepciones

```
ArchiveException (base)
├── FileValidationException
├── VirusDetectedException
├── CompressionException
└── StorageException
```

## 1. FileValidationException (HTTP 422)

Lanzada por `preValidate()` cuando el archivo falla validación.

**Tipos de Error:**

```php
enum FileValidationErrorType: string {
  case CORRUPTED_FILE = 'CORRUPTED_FILE';         // Archivo corrupto o ilegible
  case INVALID_MIMETYPE = 'INVALID_MIMETYPE';     // MIME type no permitido
  case INVALID_EXTENSION = 'INVALID_EXTENSION';   // Extensión no permitida
  case SIZE_EXCEEDED = 'SIZE_EXCEEDED';           // Excede tamaño máximo
  case INVALID_HEADER = 'INVALID_HEADER';         // Cabecera/firma inválida
  case FILE_TYPE_MISMATCH = 'FILE_TYPE_MISMATCH'; // Extensión ≠ contenido
  case UNSPECIFIED = 'UNSPECIFIED';               // Error genérico
}
```

**Captura y Manejo:**

```php
try {
    $result = AgendaArchiveHandler::store($grupo, $file, now());
} catch (FileValidationException $e) {
    match($e->errorType) {
        FileValidationErrorType::SIZE_EXCEEDED => response()->json([
            'error' => 'El archivo es muy grande',
            'max_size_mb' => 500,
        ], 422),

        FileValidationErrorType::INVALID_EXTENSION => response()->json([
            'error' => 'Extensión no permitida',
            'allowed' => ['mp4', 'webm', 'avi'],
        ], 422),

        FileValidationErrorType::INVALID_MIMETYPE => response()->json([
            'error' => 'Tipo de archivo no permitido',
            'your_type' => $e->getMessage(),
        ], 422),

        FileValidationErrorType::CORRUPTED_FILE => response()->json([
            'error' => 'El archivo está corrupto o no es legible',
        ], 422),

        FileValidationErrorType::FILE_TYPE_MISMATCH => response()->json([
            'error' => 'El contenido del archivo no coincide con su tipo',
        ], 422),

        default => response()->json([
            'error' => 'Error de validación del archivo',
        ], 422),
    };
}
```

**Logging:** `warning` level (automático en servicio)

---

## 2. VirusDetectedException (HTTP 422)

Lanzada por `scanForViruses()` cuando se detecta malware.

**Tipos de Error:**

```php
enum VirusDetectionErrorType: string {
  case VIRUS_FOUND = 'VIRUS_FOUND';               // Virus/malware detectado
  case SCAN_FAILED = 'SCAN_FAILED';               // Falló el escaneo
  case ENGINE_UNAVAILABLE = 'ENGINE_UNAVAILABLE'; // ClamAV/motor no disponible
  case UNSPECIFIED = 'UNSPECIFIED';               // Error genérico
}
```

**Captura y Manejo:**

```php
try {
    $result = AgendaArchiveHandler::store($grupo, $file, now());
} catch (VirusDetectedException $e) {
    match($e->errorType) {
        VirusDetectionErrorType::VIRUS_FOUND => response()->json([
            'error' => 'Archivo infectado detectado. Rechazado.',
            'virus_name' => $e->getMessage(),
        ], 422),

        VirusDetectionErrorType::ENGINE_UNAVAILABLE => response()->json([
            'error' => 'Servicio de escaneo no disponible',
            'archive_id' => $e->archiveId,
        ], 422),

        VirusDetectionErrorType::SCAN_FAILED => response()->json([
            'error' => 'Error al escanear el archivo',
        ], 422),

        default => response()->json([
            'error' => 'Error en validación de seguridad',
        ], 422),
    };
}
```

**Logging:** `alert` level (automático en servicio)

---

## 3. CompressionException (HTTP 422)

Lanzada por `compressFile()` cuando falla la compresión/optimización.

**Tipos de Error:**

```php
enum CompressionErrorType: string {
  case INVALID_FORMAT = 'INVALID_FORMAT';         // Formato no soportado
  case COMPRESSION_FAILED = 'COMPRESSION_FAILED'; // Error al comprimir
  case OUTPUT_EXCEEDED = 'OUTPUT_EXCEEDED';       // Output > límite
  case TIMEOUT = 'TIMEOUT';                       // Timeout en compresión
  case OUT_OF_MEMORY = 'OUT_OF_MEMORY';           // Memoria insuficiente
  case UNSPECIFIED = 'UNSPECIFIED';               // Error genérico
}
```

**Captura y Manejo:**

```php
try {
    $result = AgendaArchiveHandler::store($grupo, $file, now());
} catch (CompressionException $e) {
    match($e->errorType) {
        CompressionErrorType::OUT_OF_MEMORY => response()->json([
            'error' => 'Servidor sin memoria. Intenta con archivo más pequeño.',
            'archive_id' => $e->archiveId,
        ], 422),

        CompressionErrorType::TIMEOUT => response()->json([
            'error' => 'Procesamiento tardó demasiado',
            'archive_id' => $e->archiveId,
        ], 422),

        CompressionErrorType::COMPRESSION_FAILED => response()->json([
            'error' => 'Error al procesar el archivo',
        ], 422),

        default => response()->json([
            'error' => 'Error en optimización de archivo',
        ], 422),
    };
}
```

**Logging:** `error` level (automático en servicio)

---

## 4. StorageException (HTTP 500)

Lanzada por `storeUploadedFile()` cuando falla el almacenamiento.

**Tipos de Error:**

```php
enum StorageErrorType: string {
  case DISK_FULL = 'DISK_FULL';                   // Disco lleno
  case PERMISSION_DENIED = 'PERMISSION_DENIED';   // Permisos insuficientes
  case CONNECTIVITY_FAILED = 'CONNECTIVITY_FAILED'; // Falló conexión a almacenamiento
  case INVALID_PATH = 'INVALID_PATH';             // Ruta inválida/peligrosa
  case IO_ERROR = 'IO_ERROR';                     // Error I/O general
  case QUOTA_EXCEEDED = 'QUOTA_EXCEEDED';         // Cuota del usuario excedida
  case UNSPECIFIED = 'UNSPECIFIED';               // Error genérico
}
```

**Captura y Manejo:**

```php
try {
    $result = AgendaArchiveHandler::store($grupo, $file, now());
} catch (StorageException $e) {
    match($e->errorType) {
        StorageErrorType::DISK_FULL => response()->json([
            'error' => 'Almacenamiento lleno. Contacta a administrador.',
            'archive_id' => $e->archiveId,
        ], 500),

        StorageErrorType::PERMISSION_DENIED => response()->json([
            'error' => 'Permisos insuficientes en servidor',
            'archive_id' => $e->archiveId,
        ], 500),

        StorageErrorType::CONNECTIVITY_FAILED => response()->json([
            'error' => 'No hay conexión al almacenamiento',
            'archive_id' => $e->archiveId,
        ], 500),

        StorageErrorType::QUOTA_EXCEEDED => response()->json([
            'error' => 'Has excedido tu cuota de almacenamiento',
        ], 422),  // 422 porque es del usuario, no del servidor

        StorageErrorType::IO_ERROR => response()->json([
            'error' => 'Error de entrada/salida. Intenta más tarde.',
            'archive_id' => $e->archiveId,
        ], 500),

        default => response()->json([
            'error' => 'Error al guardar el archivo. Intenta más tarde.',
            'archive_id' => $e->archiveId,
        ], 500),
    };
}
```

**Logging:** `critical` level (automático en servicio)

---

## Manejo Global (Trait o Middleware)

```php
// app/Http/Middleware/HandleArchiveExceptions.php

namespace App\Http\Middleware;

use App\Exceptions\Archive\{
    FileValidationException,
    VirusDetectedException,
    CompressionException,
    StorageException,
};

class HandleArchiveExceptions
{
    public function handle($request, Closure $next)
    {
        try {
            return $next($request);
        } catch (FileValidationException $e) {
            return response()->json([
                'error' => 'Validación fallida',
                'type' => $e->errorType->value,
                'message' => $e->getMessage(),
            ], 422);
        } catch (VirusDetectedException $e) {
            return response()->json([
                'error' => 'Archivo infectado',
                'type' => $e->errorType->value,
            ], 422);
        } catch (CompressionException $e) {
            return response()->json([
                'error' => 'Error al procesar',
                'type' => $e->errorType->value,
                'archive_id' => $e->archiveId,
            ], 422);
        } catch (StorageException $e) {
            return response()->json([
                'error' => 'Error de almacenamiento',
                'type' => $e->errorType->value,
                'archive_id' => $e->archiveId,
            ], 500);
        }
    }
}
```

Luego registra en `app/Http/Kernel.php`:

```php
protected $middleware = [
    // ...
    \App\Http\Middleware\HandleArchiveExceptions::class,
];
```

---

## Propiedades Comunes

Todas las excepciones heredan de `ArchiveException`:

```php
$e->errorType        // enum: VIRUS_FOUND, DISK_FULL, etc
$e->getMessage()     // string: descripción detallada
$e->getCode()        // int: código HTTP (422, 500)
$e->archiveId        // string: UUID único para debugging/logging
$e->storagePath      // ?string: ruta donde se intentó guardar
```

---

## Logging Automático (Servicio)

El `AbstractArchiveService::performStorage()` loguea automáticamente:

```
Success:
  Level: info
  Log: {archive_id, timestamp, file_size, duration_ms, path}

Validation failure:
  Level: warning
  Log: {archive_id, file_name, error_type, error_message}

Virus detected:
  Level: alert
  Log: {archive_id, virus_name, error_type}

Compression failure:
  Level: error
  Log: {archive_id, error_type, error_message}

Storage failure:
  Level: critical
  Log: {archive_id, error_type, error_message, storage_path}
```

**Ubicación:** `config('files.logging.channel')` (default: `storage/logs/laravel.log`)

---

## Cleanup Automático

En caso de excepción, el servicio llama automáticamente a `cleanupPartialResults()`:

- Borra archivos temporales relacionados con `$archiveId`
- Si hay fallo de storage: intenta limpiar archivos parcialmente guardados
- Loguea cualquier error de cleanup pero no lo rethrowea

No necesitas manejar cleanup en el controlador.

---

## Ejemplo Completo

```php
class SubmissionController extends Controller
{
    public function submitVideo(VideoRequest $request, $grupoId)
    {
        $grupo = ActividadAsignadaGrupo::findOrFail($grupoId);
        $this->authorize('submit', $grupo);

        try {
            $result = AgendaArchiveHandler::store(
                grupo: $grupo,
                file: $request->file('video'),
                fecha: now()
            );

            // Guardar en BD
            $grupo->entrega()->create([
                'ruta_almacenamiento' => $result['path'],
                'nombre_archivo' => $result['file_name'],
                'tamaño_bytes' => $result['size_bytes'],
                'tipo_mime' => $result['mime_type'],
                'disco' => $result['disk'],
            ]);

            return response()->json([
                'success' => true,
                'file_name' => $result['file_name'],
            ]);

        } catch (FileValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => match($e->errorType) {
                    FileValidationErrorType::SIZE_EXCEEDED => 'Video muy grande (max 500MB)',
                    FileValidationErrorType::INVALID_EXTENSION => 'Formato no permitido',
                    default => 'Archivo inválido',
                },
            ], 422);

        } catch (VirusDetectedException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Archivo detectado como infectado',
            ], 422);

        } catch (CompressionException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al procesar video. Intenta más tarde.',
                'archive_id' => $e->archiveId,
            ], 422);

        } catch (StorageException $e) {
            return response()->json([
                'success' => false,
                'error' => match($e->errorType) {
                    StorageErrorType::DISK_FULL => 'Almacenamiento lleno',
                    StorageErrorType::PERMISSION_DENIED => 'Error de permisos',
                    StorageErrorType::QUOTA_EXCEEDED => 'Excediste cuota',
                    default => 'Error al guardar archivo',
                },
                'archive_id' => $e->archiveId,
            ], 500);
        }
    }
}
```

---

## Notas Importantes

1. **Logging**: El servicio ya loguea todo. NO loguees de nuevo en controlador.
2. **Cleanup**: Automático. No necesitas manejar archivos temporales.
3. **archiveId**: Úsalo en respuesta de error para que usuario pueda reportar al support.
4. **HTTP Codes**: 422 para errores del usuario, 500 para errores del servidor.
5. **Typed Exceptions**: Siempre captura específicamente, no uses `\Exception` genérico.
