 Propuesta: Exception Types para Archive Service

## Estructura

Cada excepción hereda de `ArchiveException` (abstract) y define sus propios tipos como constantes.

```php
abstract class ArchiveException extends Exception {
    public readonly string $type;  // Discriminador
    public readonly ?string $archiveId;  // Para tracing
}
```

---

## 1. FileValidationException

**HTTP**: 422 | **Log Level**: WARNING

| Tipo | Significado | Cuándo Ocurre |
|------|-------------|---------------|
| `CORRUPTED_FILE` | Archivo corrupto o ilegible | Header no coincide, archivo truncado |
| `INVALID_MIMETYPE` | MIME type no coincide | extension vs contenido real |
| `INVALID_EXTENSION` | Extensión no permitida | `.exe` en upload de imágenes |
| `SIZE_EXCEEDED` | Archivo muy grande | > max_size configurado |
| `INVALID_HEADER` | Magic bytes inválidos | Primeros bytes no coinciden con tipo |
| `FILE_TYPE_MISMATCH` | Tipo de archivo engañoso | Dice ser PDF pero es ZIP |
| `UNSPECIFIED` | Error genérico | No clasificable |

**Ejemplo Uso**:
```php
throw new FileValidationException(
    type: FileValidationException::TYPE_CORRUPTED_FILE,
    reason: "File header 'PK' expected but got 'XX'",
    archiveId: $archiveId
);
```

---

## 2. VirusDetectedException

**HTTP**: 422 | **Log Level**: ALERT (security incident!)

| Tipo | Significado | Cuándo Ocurre |
|------|-------------|---------------|
| `VIRUS_FOUND` | Virus detectado | ClamAV encontró firma |
| `SUSPICIOUS_CONTENT` | Contenido sospechoso | Heurísticas activas pero no definitivo |
| `SCAN_TIMEOUT` | Timeout del scanner | Tardó > 60s |
| `SCANNER_ERROR` | Error del servicio | ClamAV down, no response |
| `UNSPECIFIED` | Error genérico | |

**Ejemplo Uso**:
```php
throw new VirusDetectedException(
    type: VirusDetectedException::TYPE_VIRUS_FOUND,
    details: "Trojan.Generic.5f8c9a2d",
    archiveId: $archiveId
);
```

---

## 3. CompressionException

**HTTP**: 500 | **Log Level**: ERROR

| Tipo | Significado | Cuándo Ocurre |
|------|-------------|---------------|
| `OPTIMIZATION_FAILED` | Fallo en optimización | ImageOptimizer error |
| `CODEC_UNAVAILABLE` | Codec no disponible | FFmpeg no instalado |
| `MEMORY_EXCEEDED` | Memoria insuficiente | > memory_limit |
| `TIMEOUT` | Timeout de optimización | > 30s |
| `UNSUPPORTED_FORMAT` | Formato no soportado | TIFF cuando esperamos JPG |
| `UNSPECIFIED` | Error genérico | |

**Ejemplo Uso**:
```php
throw new CompressionException(
    type: CompressionException::TYPE_CODEC_UNAVAILABLE,
    reason: "FFmpeg not found in PATH",
    archiveId: $archiveId
);
```

---

## 4. StorageException

**HTTP**: 500 | **Log Level**: CRITICAL (página oncall!)

| Tipo | Significado | Cuándo Ocurre |
|------|-------------|---------------|
| `DISK_FULL` | Disco lleno | Storage::put() falla por espacio |
| `PERMISSION_DENIED` | Permiso denegado | No write permission en directorio |
| `CONNECTIVITY_FAILED` | Conexión remota | S3 timeout, no response |
| `INVALID_PATH` | Ruta inválida | Directorio no existe, caracteres inválidos |
| `IO_ERROR` | Error I/O del disco | Bad sector, disco dañado |
| `QUOTA_EXCEEDED` | Cuota excedida | Límite de usuario/tenant |
| `UNSPECIFIED` | Error genérico | |

**Ejemplo Uso**:
```php
throw new StorageException(
    type: StorageException::TYPE_DISK_FULL,
    reason: "Only 512MB available, need 1GB",
    archiveId: $archiveId,
    storagePath: $path  // Para cleanup
);
```

---

## 5. ArchiveException (Generic)

**Uso**: Cuando no encaja en las categorías anteriores

| Tipo | Significado |
|------|-------------|
| `UNSPECIFIED` | Error no clasificable |

```php
throw new ArchiveException(
    type: ArchiveException::TYPE_UNSPECIFIED,
    message: "Unexpected error...",
    code: 500,
    archiveId: $archiveId
);
```

---

## Ventajas de Esta Estructura

✅ **Type-safe**: Constantes en lugar de strings magic  
✅ **Descriptive**: Cada tipo explica exactamente qué pasó  
✅ **Traceable**: archiveId permite logging estructurado  
✅ **Loggable**: Fácil filtrar por tipo en observabilidad  
✅ **Extensible**: Agregar nuevos tipos es trivial  

---

## En el Controller

```php
try {
    $result = AgendaArchiveService::handleStore($grupo, $file, now());
} catch (FileValidationException $e) {
    match ($e->type) {
        FileValidationException::TYPE_CORRUPTED_FILE => Log::warning("Corrupted file", ['id' => $e->getArchiveId()]),
        FileValidationException::TYPE_SIZE_EXCEEDED => response()->json(['error' => 'File too large'], 422),
        default => response()->json(['error' => $e->getMessage()], 422),
    };
} catch (StorageException $e) {
    match ($e->type) {
        StorageException::TYPE_DISK_FULL => /* PAGE ONCALL */,
        StorageException::TYPE_PERMISSION_DENIED => Log::critical("Permission issue"),
        default => /* handle */,
    };
}
```

---

## Propuestas Abiertas

¿Falta algún tipo? ¿Sobra alguno? Edita las listas arriba y confirma.

Ejemplos potenciales a agregar:
- **FileValidationException**: `TYPE_EMPTY_FILE` (archivo 0 bytes)?
- **CompressionException**: `TYPE_RESOURCE_LIMIT` (CPU/RAM capped)?
- **StorageException**: `TYPE_NETWORK_TIMEOUT` (diferenciado de CONNECTIVITY)?
