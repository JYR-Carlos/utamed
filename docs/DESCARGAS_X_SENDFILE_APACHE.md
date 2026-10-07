# Descargas con X-Sendfile en Apache y prueba de carga de entregas

> Contexto: observación 5 del módulo de actividades («ver si se cae con mucho flujo de subida y descarga»), rama `arreglos-actividades-06-10`. Producción usa **Apache**.

## Qué cambió en el código

- **Subida de entregas** (`Student\AgendaController::storeEntrega`): el archivo se valida, escanea, comprime y escribe **antes** de abrir la transacción. La transacción sólo inserta la fila de agenda (milisegundos) y vuelve a revisar el plazo y «ya evaluado» con el grupo bloqueado (`lockForUpdate`). Si se rechaza o falla, el archivo queda con `pendiente_de_borrado = true`. Cada subida deja en el log `ms_archivo` y `ms_total`.
- **Descargas** (enunciados y entregas, `response()->file()` / `response()->download()`): con `FILES_X_SENDFILE=true`, PHP sólo autoriza y responde la cabecera `X-Sendfile`; Apache envía el archivo y el worker de PHP-FPM queda libre.

## Activar X-Sendfile en Apache

1. Instalar y habilitar `mod_xsendfile` (en Debian/Ubuntu: `apt install libapache2-mod-xsendfile && a2enmod xsendfile`).
2. En el `VirtualHost` de la aplicación:

   ```apache
   XSendFile On
   # Sólo los directorios de archivos de la app (discos local y local_archives)
   XSendFilePath /ruta/a/utamed/storage/app/private
   XSendFilePath /ruta/a/utamed/storage/app/archives

   # La cabecera de la PETICIÓN que Symfony espera. «set» pisa cualquier
   # valor que mande el cliente.
   RequestHeader set X-Sendfile-Type X-Sendfile
   ```

3. En el `.env` de producción: `FILES_X_SENDFILE=true` y `php artisan config:cache`.
4. Comprobar: descargar un enunciado y ver en las cabeceras de respuesta del navegador que llega el archivo completo; en el log de acceso de Apache, el tamaño de la respuesta debe ser el del archivo.

Si `FILES_X_SENDFILE=true` pero Apache **no** está configurado, las descargas llegan vacías. Con `false` (valor por defecto) todo sigue como antes.

Tests: `tests/Feature/Docente/DescargaXSendfileTest.php` y `tests/Feature/Student/EntregaArchivoFueraDeTransaccionTest.php`.

## Prueba de carga (pendiente: falta una máquina parecida a producción)

El servidor de desarrollo (`php artisan serve`) atiende de a una petición y no sirve para medir. Para medir hace falta Apache + PHP-FPM + PostgreSQL con la misma configuración que producción.

**Escenario:** el cierre de un plazo. [N] estudiantes suben un PDF de [X] MB durante los últimos 15 minutos, mientras otros descargan el enunciado. Valores a confirmar con el equipo: N = matrícula del curso más grande y X = tamaño típico de una entrega.

**Herramienta sugerida:** k6 o JMeter. Cada usuario virtual inicia sesión con un RUT de prueba, toma el token CSRF y hace el `POST /estudiante/grupos-asignados/{grupo}/entregas` con el archivo.

**Qué mirar, antes y después de este cambio:**

| Dónde | Qué |
|---|---|
| PostgreSQL | `SELECT state, count(*) FROM pg_stat_activity GROUP BY state;`: las conexiones `idle in transaction` deberían bajar a casi cero. |
| PHP-FPM | `pm.status_path`: procesos activos frente a `pm.max_children`, y si aparece «max children reached» en el log. |
| Apache | errores 502/503/504 y tiempos de respuesta (percentil 95). |
| Laravel | `ms_archivo` y `ms_total` de `[AgendaController::storeEntrega] Entrega guardada`. |

**Límites a revisar junto con la prueba:**
- `upload_max_filesize` y `post_max_size` (PHP);
- `LimitRequestBody` (Apache);
- `max_connections` (PostgreSQL);
- `pm.max_children` (PHP-FPM).
