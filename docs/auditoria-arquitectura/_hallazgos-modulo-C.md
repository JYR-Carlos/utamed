# Módulo C — Estudiante / Agenda y subida de archivos

**Alcance auditado:** `Student\AgendaController`, `Student\ActivityController`, `Student\CourseController`,
cadena completa de subida (`AgendaFileRequest` → `BaseArchiveRequest` → `FileRequirementBuilder` →
`AgendaArchiveHandler` → `AbstractArchiveService`), `config/filetypes.php`, `config/files.php`,
frontend `pages/student/*` (21 páginas).

---

## 🔴 Crítico

### C-1 · Un GET inserta al estudiante en actividades de cursos ajenos (escalada de privilegios)
**Archivo:** `app/Http/Controllers/Student/ActivityController.php:31-64`

Verifica inscripción en `$curso` y `$actividad->visible`, pero **nunca verifica que `$actividad`
pertenezca a `$curso`** — mismo patrón que B-1, del lado estudiante. Y en `:62`:

```php
if (!$actividad->es_grupal) {
    (new GrupoIndividualService())->asegurarGrupo($actividad, $estudiante->id_estudiante);
}
```

`asegurarGrupo` **crea** un `ActividadAsignadaGrupo` + `IntegranteGrupo` si no existen.

**Cadena de explotación:**
1. Estudiante inscrito en curso A pide `GET /estudiante/cursos/{A}/actividad/{X}`, con X actividad
   individual del curso B.
2. El sistema lo da de alta como integrante de un grupo nuevo en la actividad X.
3. `AgendaController::store` y `storeEntrega` autorizan **por pertenencia a `IntegranteGrupo`**
   (`:57-59` y `:90-98`) — pertenencia que el paso 2 acaba de fabricar. Ya puede enviar mensajes y subir
   archivos en un curso donde no está inscrito.
4. El docente de B ve un estudiante fantasma en su pantalla de calificaciones.

Escritura no autorizada disparada por una petición de lectura, que además convierte la autorización de
los otros dos endpoints en un control auto-satisfacible.

### C-2 · El antivirus no existe: es un stub vacío
**Archivo:** `app/Services/Archive/AbstractArchiveService.php:339-359`

```php
protected function scanForViruses(UploadedFile $file, string $archiveId): void
{
    if (!config('files.validation.virus_scan_enabled')) return;
    // TODO: Default implementation - connect to ClamAV daemon or VirusTotal
    // …todo el cuerpo comentado…
}
```

Default `ARCHIVE_VIRUS_SCAN_ENABLED=false` (`config/files.php:222`).

- `VirusDetectedException` nunca se lanza → el `catch` de `AgendaController:148-154` es código muerto.
- El docblock de `AgendaController:31` afirma que el handler hace "validación, **antivirus**, compresión
  y almacenamiento".
- Peor que no tenerlo: activar el flag da **falsa sensación de protección**, porque el escáner
  "habilitado" aprueba todo en silencio. Debería lanzar `ArchiveException(CONFIGURATION_ERROR)` si se
  habilita sin implementación.

---

## 🟠 Alto

### C-3 · El nombre del archivo en disco lo controla el usuario
**Archivo:** `AbstractArchiveService.php:567`

```php
$storageName = $fileName ?: $file->hashName();
$storedPath = Storage::disk($this->disk)->putFileAs($prefixedDirectory, $file, $storageName);
```

`$fileName` viene de `AgendaFileRequest::getFileName()` → input `nombre_archivo`, validado solo con
`regex:/^[\pL\pN\s._\-]+$/u`. El regex bloquea `/` y `\` (no hay traversal) pero **permite cualquier
extensión**: `nombre_archivo=shell.php` guarda el fichero como `shell.php` aunque el contenido validado
sea un PDF. `generateFileName()` (que sí deriva la extensión real) queda anulado. El regex también
acepta `..` como nombre completo.

**Mitigante actual:** disco `local_archives` → `storage_path('app/archives')`
(`config/filesystems.php:50-54`), fuera del docroot. Si se sirve por web o se sincroniza a un bucket
público, es RCE.

**Fix:** derivar siempre la extensión del archivo validado, ignorando la del cliente.

### C-4 · El límite de tamaño por tipo se evade cruzando extensión y MIME
**Archivo:** `app/Services/Archive/FiletypeValidation/FileRequirementBuilder.php:83-107`

`extensions:` y `mimetypes:` se construyen como **uniones planas** de las 9 categorías habilitadas,
mientras que el closure de tamaño exige coincidencia **dentro de la misma categoría**:

```php
foreach ($this->typeConfigs as $config) {
    if (in_array($extension, $config['extensions']) && in_array($mimeType, $config['mimes'])) {
        if ($fileSize > $config['max_size']) { $fail(...); }
        return;
    }
}
// ← sin coincidencia: no falla y no valida el tamaño
```

Un archivo con extensión de una categoría y MIME de otra pasa ambas reglas planas, no casa con ningún
`typeConfig`, y sale del closure **sin control de tamaño** — solo lo acota `upload_max_filesize`. El
techo legítimo ya es alto: MEDIA y RAW_ART permiten 1 GB (`config/filetypes.php:207, 225`).

### C-5 · Ruta registrada contra un método inexistente
`routes/web.php:467-471` registra `AgendaController@storeFile`.
`grep -rn "function storeFile" app/Http/Controllers/` → **0 resultados**. Todo POST a
`estudiante/agendas/{registroAgenda}/archivos` responde 500.

### C-6 · El catch general deja la transacción abierta
**Archivo:** `AgendaController.php:189-198`

Los seis catches específicos hacen `DB::rollBack()`; el `catch (\Throwable $e)` **no**. Responde con la
transacción viva y el registro `Agenda` de `:116` ya insertado.

### C-7 · Guard con falsy que omite la fecha límite
**Archivo:** `AgendaController.php:101`

`if ($actividad) { …comprobar plazo… }`. Si `$actividadAsignadaGrupo->actividad` es null, la
comprobación de plazo se salta entera y la entrega se acepta. Tercera aparición del antipatrón (B-6, C-1).

---

## 🟡 Medio

| # | Hallazgo | Ubicación |
|---|---|---|
| C-8 | **El allowlist de subida se apaga con una variable de entorno**: `FILES_ENABLE_MIME_VALIDATION=false` / `FILES_ENABLE_EXTENSION_VALIDATION=false`. | `config/filetypes.php:50-51` |
| C-9 | **Categorías reales por encima de las documentadas.** El docblock dice "Word, PDF, imágenes"; `$fileCategories` habilita además SPREADSHEET, PRESENTATION, COMPRESSED, MEDIA, RAW_ART y DOCUMENT. Comprimidos sin inspección de contenido ni límite de ratio → zip bomb. | `AgendaFileRequest.php:41-51` |
| C-10 | Divergencia disco/BD: se registra `'extension' => $file->extension()` (deducida del MIME) mientras el fichero en disco lleva la extensión del cliente (C-3). | `AbstractArchiveService:585` |
| C-11 | Email de todos los compañeros de grupo en los props. | `ActivityController:85` |
| C-12 | N+1 en el listado de cursos: `Programa::where(...)->exists()` dentro de `formatCurso`, una vez por inscripción. | `CourseController:74-77` |
| C-13 | `asegurarGrupo` se ejecuta en cada carga de actividad individual — versión por-estudiante de B-7. | `ActivityController:62` |

---

## ✅ Verificado correcto

- **La autorización de `AgendaController` es correcta**: ambos endpoints de escritura verifican
  pertenencia real al grupo con `IntegranteGrupo::…->firstOrFail()` antes de tocar nada. El problema no
  es este controlador: C-1 le fabrica la pertenencia desde fuera.
- El plazo de entrega con holgura configurable (`nro_dias_adicionales_para_bloqueo`) se aplica en
  servidor, no solo en la UI.
- `performStorage` bien estructurado: fases explícitas, `cleanupPartialResults()` en cada rama de error,
  `archive_id` (UUID v7) para trazabilidad, logging best-effort que no tumba una operación exitosa.
- **Props mapeados campo a campo en los tres controladores** — ningún volcado de modelo completo, a
  diferencia del módulo docente.
- Frontend estudiante: 0 `$:` legacy y **0 `fetch`/`axios` crudos** en 21 páginas — todo pasa por el
  router de Inertia, con CSRF automático.
- Los segmentos de directorio se normalizan con `Str::slug()` antes de construir la ruta
  (`AgendaArchiveHandler:130-138`).

---

## 🔁 Patrón transversal detectado

El guard `if ($relacion) { comprobar }` en lugar de `if (!coincide) { abort }`. Cuando la relación es
nula, el control desaparece en silencio. Apariciones: **B-6, C-1, C-7**. Tratar como corrección
transversal en el plan de acción.
