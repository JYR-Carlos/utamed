# Handler Usage Guide - Archive Storage

## ¿Está prod ready?

✅ **SÍ**, si los tests pasan:

- Handler normaliza paths determinísticamente (`Str::slug()`)
- Service valida que las normalizaciones sean idempotentes
- Archivos se almacenan en estructura correcta
- Metadata se retorna completa
- BD constraints se respetan
- **Logging automático en el servicio** (no en controlador)
- **Almacenamiento atómico** (ver Atomicity Guarantee abajo)

## Atomicity Guarantee

**El almacenamiento es atómico:**

- ✅ Pre-almacenamiento (validación, scanning, compresión): Si falla → cleanup + log + exception
- ✅ Almacenamiento (Phase 4): Éxito → archivo en disco, logging best-effort (fallback a default si custom log falla)
- ✅ Post-almacenamiento: Si logging falla → fallback a `defaultLogOperation()` (final, confiable)
- ✅ Limpieza sincrónica: Intenta borrar temp + stored files en caso de error
- ✅ Orphaned files: Si sync delete falla → marca BD para async cleanup job

**Logging nunca se pierden** gracias a fallback chain: custom `logOperation()` → `defaultLogOperation()` (guaranteed).

## Flujo de Uso en Controlador

### Caso: Estudiante carga archivo de tarea grupal (video)

```php
// app/Http/Controllers/ActividadController.php

use App\Services\Archive\Handlers\AgendaArchiveHandler;
use App\Http\Requests\Archive\VideoRequest;  // Validador tipado
use App\Exceptions\Archive\{
    FileValidationException,
    VirusDetectedException,
    CompressionException,
    StorageException,
};

class ActividadController extends Controller
{
    public function submitGroupWork(VideoRequest $request, $grupoId)
    {
        // 1. VideoRequest valida AUTOMÁTICAMENTE:
        //    - Archivo presente
        //    - Tipo: mp4, webm, avi, mov, mkv, flv, wmv, m4v
        //    - Tamaño: máx 500MB (desde config/files.php)
        //    - MIME type: video/mp4, video/webm, etc
        //    (Ver config/filetypes.php para tipos)

        // 2. Obtener grupo y autorizar
        $grupo = ActividadAsignadaGrupo::findOrFail($grupoId);
        $this->authorize('submitWork', $grupo);

        // 3. Guardar archivo usando Handler
        try {
            $file = $request->file('file');  // Ya validado por VideoRequest

            $result = AgendaArchiveHandler::store(
                grupo: $grupo,
                file: $file,
                fecha: now(),
                fileName: null  // Opcional: custom name
            );

            // 4. Guardar metadata en BD
            $grupo->entrega()->create([
                'ruta_almacenamiento' => $result['path'],
                'nombre_archivo' => $result['file_name'],
                'tamaño_bytes' => $result['size_bytes'],
                'tipo_mime' => $result['mime_type'],
                'disco' => $result['disk'],
                'fecha_entrega' => now(),
                'archivo_original' => $result['original_name'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Video guardado correctamente',
                'file_name' => $result['file_name'],
            ]);

        // 5. MANEJO DE ERRORES TIPADOS (exceptions del sistema)
        // El servicio loguea automáticamente (ver logOperation en AbstractArchiveService)

        } catch (FileValidationException $e) {
            // Archivo falla validación: corrupto, wrong type, tamaño excedido, etc
            // HTTP 422 Unprocessable Entity
            // Logging: warning level (ya hecho por servicio)
            return response()->json([
                'success' => false,
                'error_type' => $e->errorType->value,  // 'SIZE_EXCEEDED', 'INVALID_MIMETYPE', etc
                'message' => 'Archivo no válido: ' . $e->getMessage(),
            ], 422);

        } catch (VirusDetectedException $e) {
            // Virus o malware detectado
            // HTTP 422 Unprocessable Entity
            // Logging: alert level (ya hecho por servicio)
            return response()->json([
                'success' => false,
                'error_type' => $e->errorType->value,  // 'VIRUS_FOUND', etc
                'message' => 'Archivo infectado detectado',
            ], 422);

        } catch (CompressionException $e) {
            // Falla en compresión/optimización
            // HTTP 422 Unprocessable Entity
            // Logging: error level (ya hecho por servicio)
            return response()->json([
                'success' => false,
                'error_type' => $e->errorType->value,
                'message' => 'Error al procesar el video',
            ], 422);

        } catch (StorageException $e) {
            // Falla en almacenamiento en disco
            // Causas: disco lleno, permisos, conectividad, etc
            // HTTP 500 Internal Server Error
            // Logging: critical level (ya hecho por servicio)
            return response()->json([
                'success' => false,
                'error_type' => $e->errorType->value,  // 'DISK_FULL', 'PERMISSION_DENIED', etc
                'message' => 'Error al guardar. Intenta más tarde.',
                'archive_id' => $e->archiveId,  // Para support/debugging
            ], 500);

        } catch (\Throwable $e) {
            // Cualquier otro error inesperado
            Log::error('Unexpected error in submitGroupWork', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error inesperado al guardar',
            ], 500);
        }
    }
}
```

## Validadores Disponibles (por tipo de archivo)

Todos usan `BaseArchiveRequest` que hereda de `FormRequest`:

| Validador                     | Tipos Permitidos                        | Max Size | Ubicación                             |
| ----------------------------- | --------------------------------------- | -------- | ------------------------------------- |
| `VideoRequest`                | mp4, webm, avi, mov, mkv, flv, wmv, m4v | 500MB    | `app/Http/Requests/Archive/Extended/` |
| `ImageRequest`                | jpg, jpeg, png, gif, webp, svg, ico     | 50MB     | Ídem                                  |
| `PdfRequest`                  | pdf                                     | 100MB    | Ídem                                  |
| `WordDocumentRequest`         | doc, docx, odt                          | 20MB     | Ídem                                  |
| `PresentationDocumentRequest` | ppt, pptx, odp                          | 100MB    | Ídem                                  |
| `SpreadsheetRequest`          | xls, xlsx, ods, csv                     | 50MB     | Ídem                                  |
| `MediaRequest`                | mp3, wav, flac, aac, ogg, m4a           | 100MB    | Ídem                                  |
| `CompressedFileRequest`       | zip, rar, 7z, tar, gz                   | 500MB    | Ídem                                  |
| `LinkRequest`                 | url text                                | N/A      | Ídem                                  |
| `RawArtRequest`               | psd, ai, sketch, fig                    | 500MB    | Ídem                                  |

**Cómo usar:**

```php
// Use el request específico en el controlador
public function storeVideo(VideoRequest $request) { }      // ✅
public function storeImage(ImageRequest $request) { }      // ✅
public function storePdf(PdfRequest $request) { }          // ✅

// NO uses FormRequest genérico
public function store(Request $request) { }                // ❌ No valida tipos
```

## Sistema de Excepciones

### Flujo de Excepciones

```
Handler::store()
    ↓
  performStorage() [Template Method]
    ├─ Phase 1: preValidate()
    │    └─ Throws: FileValidationException ❌
    │
    ├─ Phase 2: scanForViruses()
    │    └─ Throws: VirusDetectedException ❌
    │
    ├─ Phase 3: compressFile()
    │    └─ Throws: CompressionException ❌
    │
    └─ Phase 4: storeUploadedFile()
         └─ Throws: StorageException ❌
```

### Tipos de Excepciones

**1. FileValidationException** (HTTP 422)

```php
try { AgendaArchiveHandler::store(...) }
catch (FileValidationException $e) {
    // $e->errorType puede ser:
    // - CORRUPTED_FILE
    // - INVALID_MIMETYPE
    // - INVALID_EXTENSION
    // - SIZE_EXCEEDED
    // - INVALID_HEADER
    // - FILE_TYPE_MISMATCH
}
```

**2. VirusDetectedException** (HTTP 422)

```php
catch (VirusDetectedException $e) {
    // $e->errorType: VIRUS_FOUND, SCAN_FAILED, etc
}
```

**3. CompressionException** (HTTP 422)

```php
catch (CompressionException $e) {
    // Falla en optimización/compresión
}
```

**4. StorageException** (HTTP 500)

```php
catch (StorageException $e) {
    // $e->errorType puede ser:
    // - DISK_FULL
    // - PERMISSION_DENIED
    // - CONNECTIVITY_FAILED
    // - INVALID_PATH
    // - IO_ERROR
    // - QUOTA_EXCEEDED
}
```

## Logging Automático (Hecho por Servicio)

El `AbstractArchiveService::performStorage()` loguea automáticamente:

```php
// Log guardado en: config('files.logging.channel')
// Por defecto: 'stack' (archivo + console)

$operationLog = [
    'archive_id' => 'uuid-único',
    'timestamp_start' => now(),
    'file_original_name' => 'video.mp4',
    'file_original_size' => 52428800,  // bytes
    'status' => 'success|validation_failed|virus_detected|compression_failed|storage_failed',
    'disk' => 'local_archives',
    'path' => 'archivos/agenda/...',
    'file_optimized_size' => 48234496,
    'compression_ratio' => 0.92,
    'duration_ms' => 3521,
    'error' => '...',  // Solo si status != success
];

// Niveles de log automáticos:
// - Success: 'info'
// - Validation failure: 'warning'
// - Virus detected: 'alert'
// - Compression failure: 'error'
// - Storage failure: 'critical'
```

**⚠️ NO loguees en el controlador**, el servicio ya lo hace.

## Qué retorna AgendaArchiveHandler::store()

```php
$result = [
    'disk' => 'local_archives',              // Disco de almacenamiento
    'directory' => 'archivos/agenda/test-course/catedra/test-activity/2026-05-25',
    'path' => 'archivos/agenda/test-course/catedra/test-activity/2026-05-25/agenda_1716624000_a1b2c3d4.pdf',
    'file_name' => 'agenda_1716624000_a1b2c3d4.pdf',
    'size_bytes' => 262144,                   // En bytes (256KB)
    'mime_type' => 'application/pdf',
    'original_name' => 'submission.pdf',      // Nombre original del usuario
];
```

## Estructura de carpetas generada

```
storage/app/local_archives/
└── archivos/                          (config: files.storage.base_path)
    └── agenda/                        (Service rootSegment)
        └── test-course/               (Curso normalizado)
            └── catedra/               (TipoComponente normalizado)
                └── test-activity/     (Actividad normalizada)
                    └── 2026-05-25/    (Fecha: Y-m-d)
                        └── etest0-etest1/  (Integrantes normalizados)
                            └── agenda_1716624000_a1b2c3d4.pdf
```

## Normalizaciones aplicadas (en Handler)

| Original               | Normalizado           |
| ---------------------- | --------------------- |
| `Test Course`          | `test-course`         |
| `CÁTEDRA`              | `catedra`             |
| `Análisis de Datos #1` | `analisis-de-datos-1` |
| `ETest0-ETest1`        | `etest0-etest1`       |

## Configuración necesaria

**En `.env`:**

```env
FILES_STORAGE_DISK=local_archives
FILES_STORAGE_BASE_PATH=archivos

# Validación (opcional)
FILES_VALIDATION_VIRUS_SCAN_ENABLED=false  # Habilitar escaneo ClamAV si tienes daemon
FILES_VALIDATION_CONTENT_VALIDATION_ENABLED=true
```

**En `config/files.php`:**

```php
'storage' => [
    'disk' => env('FILES_STORAGE_DISK', 'local_archives'),
    'base_path' => env('FILES_STORAGE_BASE_PATH', 'archivos'),
],

'validation' => [
    'virus_scan_enabled' => env('FILES_VALIDATION_VIRUS_SCAN_ENABLED', false),
    'content_validation_enabled' => env('FILES_VALIDATION_CONTENT_VALIDATION_ENABLED', true),
],

'logging' => [
    'channel' => 'stack',  // Dónde guardar logs de operaciones
    'log_optimizations' => true,
],
```

## Recuperar archivo más tarde

```php
use Illuminate\Support\Facades\Storage;

// Obtener metadataña de BD
$entrega = $grupo->entrega;

// Descargar al cliente
return Storage::disk($entrega->disco)->download(
    $entrega->ruta_almacenamiento,
    $entrega->nombre_archivo
);

// O leer contenido (para procesar)
$content = Storage::disk($entrega->disco)->get(
    $entrega->ruta_almacenamiento
);

// O generar URL temporal (para compartir)
$url = Storage::disk($entrega->disco)->temporaryUrl(
    $entrega->ruta_almacenamiento,
    now()->addHours(1)
);
```

## Flujo Completo: Validación + Storage + Logging

```
1. VideoRequest valida automáticamente:
   ├─ File exists
   ├─ MIME type ∈ ['video/mp4', 'video/webm', ...]
   ├─ Extension ∈ ['mp4', 'webm', 'avi', ...]
   └─ Size ≤ 500MB

2. Handler::store() ejecuta:
   ├─ buildPath()      → normaliza rutas con Str::slug()
   ├─ formatPath()     → crea estructura determinística
   ├─ generateFileName() → crea agenda_TIMESTAMP_HASH.mp4
   └─ AgendaArchiveService.performStorage()
       ├─ preValidate()         [throws FileValidationException]
       ├─ scanForViruses()      [throws VirusDetectedException]
       ├─ compressFile()        [throws CompressionException]
       ├─ storeUploadedFile()   [throws StorageException]
       └─ logOperation()        ← Logging aquí (SERVICIO)

3. Servicio loguea automáticamente:
   ├─ Success    → 'info' level
   ├─ Validation → 'warning' level
   ├─ Virus      → 'alert' level
   ├─ Compression → 'error' level
   └─ Storage    → 'critical' level

4. Controlador captura typed exceptions:
   ├─ FileValidationException    → HTTP 422
   ├─ VirusDetectedException    → HTTP 422
   ├─ CompressionException       → HTTP 422
   ├─ StorageException           → HTTP 500
   └─ Responde al cliente
```

## Manejo de errores

Recuperar archivo más tarde

```php
use Illuminate\Support\Facades\Storage;

// Obtener metadataña de BD
$entrega = $grupo->entrega;

// Descargar al cliente
return Storage::disk($entrega->disco)->download(
    $entrega->ruta_almacenamiento,
    $entrega->nombre_archivo
);

// O leer contenido (para procesar)
$content = Storage::disk($entrega->disco)->get(
    $entrega->ruta_almacenamiento
);

// O generar URL temporal (para compartir)
$url = Storage::disk($entrega->disco)->temporaryUrl(
    $entrega->ruta_almacenamiento,
    now()->addHours(1)
);
```

## Flujo Arquitectónico Completo

```
Controlador
    ↓
    └─→ VideoRequest (validación tipada)
        ├─ ¿Archivo presente?
        ├─ ¿Tipo correcto? (video/mp4, video/webm, etc)
        ├─ ¿Extensión correcta? (.mp4, .webm, etc)
        └─ ¿Tamaño dentro del límite? (≤ 500MB)
    ↓
    └─→ AgendaArchiveHandler::store()
        ├─ buildPath()              ← Construye ruta desde grupo
        ├─ formatPath()             ← Normaliza con Str::slug()
        ├─ generateFileName()       ← Crea: agenda_TIMESTAMP_HASH.mp4
        └─ AgendaArchiveService.performStorage()
                ├─ Phase 1: preValidate()      [Validación adicional]
                ├─ Phase 2: scanForViruses()   [Si está enabled]
                ├─ Phase 3: compressFile()     [Si aplica]
                ├─ Phase 4: storeUploadedFile() [Guardar en disco]
                └─ logOperation()              ← 🔴 LOGGING AQUÍ (SERVICIO)
    ↓
    └─→ Controlador captura exceptions tipadas
        ├─ FileValidationException    → HTTP 422
        ├─ VirusDetectedException    → HTTP 422
        ├─ CompressionException       → HTTP 422
        ├─ StorageException           → HTTP 500
        └─ Responde JSON al cliente
    ↓
    └─→ BD: Guardar metadata (path, filename, size, mime_type)

**Responsabilidades:**
- **VideoRequest**: Validación básica (tipo, tamaño, MIME)
- **Handler**: Adapta dominio (Grupo) → Paths normalizados
- **Service**: Orquesta validación avanzada, compresión, almacenamiento, logging
- **Controlador**: Captura excepciones, responde al cliente
- **BD**: Persiste metadata para recuperación
```

## Checklist de Implementación

- [ ] Usar validador específico (`VideoRequest`, `ImageRequest`, etc) en lugar de `Request` genérico
- [ ] Capturar typed exceptions (`FileValidationException`, `StorageException`, etc)
- [ ] NO loguear en controlador → el servicio ya lo hace
- [ ] Guardar metadata retornada en BD (path, filename, size, mime_type)
- [ ] Responder con códigos HTTP correctos (422 para validación, 500 para storage)
- [ ] Incluir `archive_id` en respuesta de error (para debugging/support)
- [ ] Probar con archivos grandes (cercanos al límite de tamaño)
- [ ] Probar casos de error (disco lleno, permisos, extensión inválida)

## Casos de Uso Adicionales

### Subir documento PDF para tarea

```php
// app/Http/Controllers/PdfController.php
public function uploadAssignment(PdfRequest $request, $taskId) {
    // PdfRequest valida automáticamente:
    // - Tipo: application/pdf
    // - Extensión: .pdf
    // - Tamaño: máx 100MB

    try {
        $result = AgendaArchiveHandler::store(
            grupo: ActividadAsignadaGrupo::findOrFail($taskId),
            file: $request->file('pdf'),
            fecha: now()
        );
        // ... resto igual
    } catch (StorageException $e) { }
}
```

### Subir imagen de portada

```php
public function uploadCover(ImageRequest $request, $activityId) {
    // ImageRequest valida:
    // - Tipos: jpg, jpeg, png, gif, webp, svg, ico
    // - Tamaño: máx 50MB

    try {
        $result = AgendaArchiveHandler::store(
            grupo: ActividadAsignadaGrupo::findOrFail($activityId),
            file: $request->file('image'),
            fecha: now(),
            fileName: 'cover.jpg'  // Custom name
        );
    } catch (FileValidationException $e) {
        // Manejar específicamente
    }
}
```

## FAQ

**P: ¿Por qué los tests pasan pero no guardó el archivo en `storage/app/local_archives`?**
R: En tests se usa `Storage::fake('local_archives')`, que simula el disco sin escribir en disco real. Verifica en BD que se guardó la metadata.

**P: ¿Puedo usar nombres de archivo personalizados?**
R: Sí, pasa el parámetro `fileName` al `store()`. Pero es mejor generar automático (timestamp + hash) para evitar colisiones.

**P: ¿Qué pasa si el archivo ya existe?**
R: No hay conflicto porque cada archivo tiene nombre único (hash de timestamp). Diferentes grupos/usuarios = diferentes rutas.

**P: ¿Dónde veo los logs de operaciones?**
R: En el archivo configurado en `config('files.logging.channel')`, por defecto `storage/logs/laravel.log`.

**P: ¿Puedo cambiar la estructura de carpetas?**
R: No sin refactorizar Handler. La estructura es determinística para reproducibilidad:

```
archivos/         ← base_path
  agenda/         ← root_segment
    course/       ← normalizado
      catedra/    ← normalizado
        activity/ ← normalizado
          date/   ← Y-m-d
            students/
              file.ext
```

## Tests Relacionados

- [ArchiveServiceTest.php](../tests/Integration/Services/ArchiveServiceTest.php) - 49 tests validando todo
  - ✅ PHASE 2: Handler entry point (13 tests)
  - ✅ PHASE 2.5: Handler methods normalization (8 tests)
  - ✅ PHASE 3: Service idempotence (19 tests)
  - ✅ PHASE 4: Abstract methods (4 tests)
  - ✅ PHASE 5: Integration edge cases (5 tests)
