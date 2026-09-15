# Guía Técnica del Pipeline de Despliegue a Producción

Este documento detalla la arquitectura, etapas y mecanismos de seguridad del pipeline de despliegue automatizado de UTAMED.

---

## 🏗️ Topología del Entorno de Producción

| Parámetro | Valor Estándar | Propósito |
| :--- | :--- | :--- |
| **Servidor Remoto** | `146.83.111.155` (`SERVER_IP`) | Host de producción |
| **Usuario SSH** | `utamed` (`SERVER_USER`) | Usuario administrativo con acceso a sudo |
| **Usuario Web** | `www-data` (`WEB_USER`) | Propietario de la ejecución de PHP y Nginx/Apache |
| **Repositorio Fuente** | `/home/utamed/utamed` | Directorio Git remoto donde se actualiza la rama `produccion` |
| **Directorio de Producción** | `/var/www/prod_utamed` | Directorio en vivo servido por el servidor web |
| **Storage Compartido** | `/var/www/shared_utamed/storage` | Directorio persistente de subidas, logs, sesiones y cachés |
| **Base de Datos** | PostgreSQL `127.0.0.1:5432` | Base de datos relacional de producción (`utamed_1ra_fase`) |

---

## ⚙️ Fases del Pipeline (`produccion/despliegue.sh`)

1. **Autenticación e Interfaz SSH**:
   - Conexión vía `sshpass` y `ssh` con `-o StrictHostKeyChecking=accept-new`.
   - Se solicita la contraseña SSH/SUDO de forma oculta en la terminal del operador.

2. **Sincronización del Repositorio Fuente**:
   - `git checkout produccion && git pull origin produccion`.
   - `git submodule sync --recursive && git submodule update --init --recursive`.

3. **Compilación de Artefactos**:
   - PHP: `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`.
   - Node: `npm ci` (o `npm install`) seguido de `npm run build` para compilar Svelte e Inertia.

4. **Sincronización Segura (`rsync`)**:
   - Copia espejo a `/var/www/prod_utamed` excluyendo `.git`, `.env`, `node_modules`, `tests` y `storage`.

5. **Storage Compartido y Enlaces Simbólicos**:
   - Enlace simbólico `/var/www/prod_utamed/storage -> /var/www/shared_utamed/storage`.
   - Enlace simbólico `/var/www/prod_utamed/public/storage -> storage/app/public`.
   - Configuración de permisos `ug+rwX` para `www-data:www-data`.

6. **Snapshot Preventivo de Base de Datos**:
   - Previo a ejecutar migraciones, se genera un dump binario comprimido con `pg_dump -Fc`:
     `/home/utamed/backups/pre_deploy_<YYYYMMDD_HHMMSS>.dump`
   - Se mantiene una política de rotación automática conservando los 10 volcados más recientes.

7. **Migraciones y Cachés Laravel**:
   - `php artisan migrate --force`: Aplica migraciones pendientes de esquema en la base de datos de producción.
   - `php artisan optimize`: Genera la caché optimizada de rutas, configuración y eventos.
   - `php artisan about`: Diagnóstico de salud final del runtime.

---

## 🆘 Protocolo de Contingencia (`produccion/rollback.sh`)

En caso de cualquier anomalía tras el despliegue:
1. Ejecutar `bash produccion/rollback.sh`.
2. Seleccionar la modalidad correspondiente:
   - **Opción 1 (Rollback Completo)**: Revierte el commit anterior en Git y restaura el dump de PostgreSQL de inmediato.
   - **Opción 2 (Rollback Código)**: Para errores en frontend o rutas donde la base de datos no fue afectada.
   - **Opción 3 (Rollback Base de Datos)**: Para migraciones fallidas o datos corruptos.
   - **Opción 4 (Purgar Cachés)**: Para errores de permisos o pantalla en blanco (`500`).
