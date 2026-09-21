# Resumen de Cambios en Infraestructura y Despliegue (UTAMED)

Este documento registra los ajustes realizados en el flujo de despliegue, acceso al servidor de producción y base de datos:

## 1. Acceso Remoto y Permisos
- **Autenticación SSH por Llave**:
  - Se vinculó la llave pública `dyri0n` (`ssh-ed25519`) en `~/.ssh/authorized_keys` del usuario `utamed` en el servidor `146.83.111.155`.
  - Se configuró el alias `utamed-prod` en `~/.ssh/config` tanto para Windows OpenSSH como para WSL (`ssh utamed-prod`).
- **Sudo Desatendido (Sin Contraseña)**:
  - Se agregó la regla `utamed ALL=(ALL) NOPASSWD:ALL` en `/etc/sudoers.d/utamed`.
  - Resuelve el problema donde `sudo` interactivo en sesiones no interactivas abortaba con `sudo: no password was provided` o consumía el input en el paso `[7/9]`.

## 2. Entornos y Configuración (.env)
- **Separación estricta de entornos**:
  - `.env` (Desarrollo local): Mantiene `APP_ENV=local`, `APP_DEBUG=true` y `DB_PORT=15432` (para contenedores/túneles locales).
  - `.env.prod` (Producción): Réplica exacta del servidor con `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=http://146.83.111.155`, PostgreSQL nativo en `DB_PORT=5432` y credenciales `SERVER_IP` / `SERVER_USER`.

## 3. Mejoras en Script de Despliegue (`produccion/despliegue.sh`)
- **Detección Automática de Entorno**: Prioriza `.env.prod` si existe; si no, recurre a `.env`.
- **Conexión SSH Inteligente**: Detecta automáticamente si la autenticación por llave SSH está disponible (`SSH_KEY_AUTH`), evitando solicitar contraseñas interactivas ni requerir `sshpass`.
- **Sudo Seguro**: Utiliza `sudo -n` para todas las tareas de sincronización `rsync`, permisos de `www-data` y symlinks.
- **Snapshot Preventivo de Base de Datos**:
  - Antes de ejecutar `php artisan migrate --force` en el paso `[9/9]`, ejecuta automáticamente `pg_dump` al puerto `5432` hacia `/home/utamed/backups/pre_deploy_TIMESTAMP.dump`.
  - Rota y retiene automáticamente los últimos 10 respaldos en disco para evitar saturación.

## 4. Plan de Contingencia (`produccion/rollback.sh`)
- **Script interactivo de recuperación rápida**:
  - Opción 1: Rollback completo (revierte commit anterior con Git + restaura último snapshot de BD + limpia caché).
  - Opción 2: Rollback solo código (sin tocar la BD).
  - Opción 3: Rollback solo base de datos (restaura el `.dump` más reciente con `pg_restore`).
  - Opción 4: Purgar caché y reparar permisos de `www-data` y `storage` (soluciona pantallas en blanco o errores 500).

## 5. Base de Datos (PostgreSQL 17)
- **Servidor nativo**: Corre en el puerto `5432` con usuario `utamed` y base de datos `utamed_1ra_fase`.
- **Reglas de acceso (`pg_hba.conf`)**:
  - Se habilitó la subred `192.168.0.0/16` además de `146.83.0.0/16`.
  - Debido al firewall perimetral institucional de la universidad (puerto 5432 bloqueado desde el exterior de la red del campus), las conexiones remotas desde clientes como pgModeler o DBeaver deben realizarse a través de un **túnel SSH** hacia el puerto 22.
