#!/usr/bin/env bash
set -euo pipefail

ENV_FILE=".env"

REMOTE_REPO_DIR="/home/utamed/utamed"
REMOTE_TARGET_DIR="/var/www/prod_utamed"
SHARED_STORAGE_DIR="/var/www/shared_utamed/storage"

BRANCH="produccion"

WEB_USER="www-data"
WEB_GROUP="www-data"

if [ ! -f "$ENV_FILE" ]; then
    echo "Error: no existe el archivo .env"
    exit 1
fi

set -a
source "$ENV_FILE"
set +a

if [ -z "${SERVER_IP:-}" ] || [ -z "${SERVER_USER:-}" ]; then
    echo "Error: SERVER_IP y SERVER_USER deben estar definidos en .env"
    exit 1
fi

read -s -p "Contraseña SSH/SUDO para $SERVER_USER@$SERVER_IP: " SERVER_PASSWORD
echo ""

if [ -z "$SERVER_PASSWORD" ]; then
    echo "Error: contraseña vacía"
    exit 1
fi

if ! command -v sshpass >/dev/null 2>&1; then
    echo "Error: necesitas instalar sshpass"
    echo "Debian/Ubuntu: sudo apt install sshpass"
    exit 1
fi

echo "[1/9] Conectando al servidor..."

sshpass -p "$SERVER_PASSWORD" ssh \
    -o StrictHostKeyChecking=accept-new \
    "$SERVER_USER@$SERVER_IP" bash -s <<EOF

set -euo pipefail

REMOTE_REPO_DIR="$REMOTE_REPO_DIR"
REMOTE_TARGET_DIR="$REMOTE_TARGET_DIR"
SHARED_STORAGE_DIR="$SHARED_STORAGE_DIR"
BRANCH="$BRANCH"
WEB_USER="$WEB_USER"
WEB_GROUP="$WEB_GROUP"
SERVER_PASSWORD="$SERVER_PASSWORD"

sudo_cmd() {
    echo "\$SERVER_PASSWORD" | sudo -S "\$@"
}

echo "[2/9] Entrando al repositorio fuente..."
cd "\$REMOTE_REPO_DIR"

if [ ! -d ".git" ]; then
    echo "Error: \$REMOTE_REPO_DIR no es un repositorio Git"
    exit 1
fi

echo "[3/9] Actualizando rama \$BRANCH..."
git checkout "\$BRANCH"
git pull origin "\$BRANCH"

git submodule sync --recursive
git submodule update --init --recursive

echo "[4/9] Instalando dependencias PHP..."

composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

echo "[5/9] Instalando dependencias Node..."
if [ -f "package-lock.json" ]; then
    npm ci
else
    npm install
fi

echo "[6/9] Compilando frontend..."
npm run build

echo "[7/9] Sincronizando archivos a producción..."
sudo_cmd mkdir -p "\$REMOTE_TARGET_DIR"

sudo_cmd rsync -a --delete \
    --exclude=".git" \
    --exclude=".github" \
    --exclude="node_modules" \
    --exclude="tests" \
    --exclude=".env" \
    --exclude=".env.example" \
    --exclude="storage" \
    ./ \
    "\$REMOTE_TARGET_DIR/"

echo "[8/9] Configurando storage compartido y permisos..."

sudo_cmd mkdir -p "\$SHARED_STORAGE_DIR"
sudo_cmd chown -R "\$WEB_USER:\$WEB_GROUP" "\$SHARED_STORAGE_DIR"
sudo_cmd chmod -R ug+rwX "\$SHARED_STORAGE_DIR"

if [ ! -L "\$REMOTE_TARGET_DIR/storage" ]; then
    sudo_cmd rm -rf "\$REMOTE_TARGET_DIR/storage"
    sudo_cmd ln -s "\$SHARED_STORAGE_DIR" "\$REMOTE_TARGET_DIR/storage"
fi

sudo_cmd chown -h "\$WEB_USER:\$WEB_GROUP" "\$REMOTE_TARGET_DIR/storage"

sudo_cmd chown -R "\$WEB_USER:\$WEB_GROUP" "\$REMOTE_TARGET_DIR/bootstrap/cache"
sudo_cmd chmod -R ug+rwX "\$REMOTE_TARGET_DIR/bootstrap/cache"

echo "[9/9] Ejecutando tareas Laravel en producción..."
cd "\$REMOTE_TARGET_DIR"

sudo_cmd rm -f bootstrap/cache/*.php

sudo_cmd -u "\$WEB_USER" php artisan optimize:clear

sudo_cmd mkdir -p "$SHARED_STORAGE_DIR/app/public"
sudo_cmd mkdir -p "$SHARED_STORAGE_DIR/framework/cache"
sudo_cmd mkdir -p "$SHARED_STORAGE_DIR/framework/sessions"
sudo_cmd mkdir -p "$SHARED_STORAGE_DIR/framework/views"
sudo_cmd mkdir -p "$SHARED_STORAGE_DIR/logs"

sudo_cmd chown -R "$WEB_USER:$WEB_GROUP" "$SHARED_STORAGE_DIR"
sudo_cmd chmod -R ug+rwX "$SHARED_STORAGE_DIR"

sudo_cmd rm -f public/storage
sudo_cmd ln -s ../storage/app/public public/storage
sudo_cmd chown -h "$WEB_USER:$WEB_GROUP" public/storage

sudo_cmd -u "\$WEB_USER" php artisan migrate --force
sudo_cmd -u "\$WEB_USER" php artisan optimize

echo "Verificando estado final:"
php artisan about
ls -ld "\$REMOTE_TARGET_DIR"
ls -ld "\$REMOTE_TARGET_DIR/storage"
ls -ld "\$SHARED_STORAGE_DIR"
ls -ld "\$REMOTE_TARGET_DIR/bootstrap/cache"

echo "Despliegue completado correctamente."

EOF
