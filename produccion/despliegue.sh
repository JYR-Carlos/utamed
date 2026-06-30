#!/usr/bin/env bash
set -euo pipefail

# ==========================
# Configuración
# ==========================

ENV_FILE=".env"

REMOTE_REPO_DIR="~/utamed"
REMOTE_TARGET_DIR="/var/www/prod_utamed"

BRANCH="produccion"

WEB_USER="www-data"
WEB_GROUP="www-data"

# ==========================
# Cargar .env local
# ==========================

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

# ==========================
# Solicitar contraseña
# ==========================

read -s -p "Contraseña SSH/SUDO para $SERVER_USER@$SERVER_IP: " SERVER_PASSWORD
echo ""

if [ -z "$SERVER_PASSWORD" ]; then
    echo "Error: contraseña vacía"
    exit 1
fi

# ==========================
# Verificar sshpass
# ==========================

if ! command -v sshpass >/dev/null 2>&1; then
    echo "Error: necesitas instalar sshpass"
    echo "Debian/Ubuntu: sudo apt install sshpass"
    echo "Fedora: sudo dnf install sshpass"
    exit 1
fi

# ==========================
# Ejecutar despliegue remoto
# ==========================

echo "[1/8] Conectando al servidor..."

sshpass -p "$SERVER_PASSWORD" ssh \
    -o StrictHostKeyChecking=accept-new \
    "$SERVER_USER@$SERVER_IP" bash -s <<EOF

set -euo pipefail

REMOTE_REPO_DIR="$REMOTE_REPO_DIR"
REMOTE_TARGET_DIR="$REMOTE_TARGET_DIR"
BRANCH="$BRANCH"
WEB_USER="$WEB_USER"
WEB_GROUP="$WEB_GROUP"
SERVER_PASSWORD="$SERVER_PASSWORD"

echo "[2/8] Entrando al repositorio..."
cd "\$REMOTE_REPO_DIR"

if [ ! -d ".git" ]; then
    echo "Error: \$REMOTE_REPO_DIR no es un repositorio Git"
    exit 1
fi

echo "[3/8] Actualizando rama \$BRANCH..."
git checkout "\$BRANCH"
git pull origin "\$BRANCH"

echo "[4/8] Instalando dependencias PHP..."
composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

echo "[5/8] Ejecutando tareas Laravel..."
php artisan migrate --force
php artisan optimize:clear
php artisan optimize

if [ ! -L "public/storage" ]; then
    php artisan storage:link || true
fi

echo "[6/8] Instalando dependencias Node..."
if [ -f "package-lock.json" ]; then
    npm ci
else
    npm install
fi

echo "[7/8] Compilando frontend..."
npm run build

echo "[8/8] Sincronizando archivos a producción..."

echo "\$SERVER_PASSWORD" | sudo -S mkdir -p "\$REMOTE_TARGET_DIR"

echo "\$SERVER_PASSWORD" | sudo -S rsync -a --delete \
    --exclude=".git" \
    --exclude=".github" \
    --exclude="node_modules" \
    --exclude="tests" \
    --exclude=".env.example" \
    ./ \
    "\$REMOTE_TARGET_DIR/"

echo "\$SERVER_PASSWORD" | sudo -S chown -R "\$WEB_USER:\$WEB_GROUP" "\$REMOTE_TARGET_DIR"

echo "Verificando propietario final:"
ls -ld "\$REMOTE_TARGET_DIR"

echo "Despliegue completado correctamente."

EOF
