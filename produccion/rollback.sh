#!/usr/bin/env bash
set -euo pipefail

if [ -f ".env.prod" ]; then
    ENV_FILE=".env.prod"
else
    ENV_FILE=".env"
fi

REMOTE_REPO_DIR="/home/utamed/utamed"
REMOTE_TARGET_DIR="/var/www/prod_utamed"
SHARED_STORAGE_DIR="/var/www/shared_utamed/storage"
WEB_USER="www-data"
WEB_GROUP="www-data"

if [ ! -f "$ENV_FILE" ]; then
    echo "Error: no existe el archivo de entorno ($ENV_FILE)"
    exit 1
fi

set -a
source "$ENV_FILE"
set +a

if [ -z "${SERVER_IP:-}" ] || [ -z "${SERVER_USER:-}" ]; then
    echo "Error: SERVER_IP y SERVER_USER deben estar definidos en $ENV_FILE"
    exit 1
fi

SSH_KEY_AUTH=false
if ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=5 "$SERVER_USER@$SERVER_IP" true 2>/dev/null; then
    SSH_KEY_AUTH=true
fi

SERVER_PASSWORD=""
if [ "$SSH_KEY_AUTH" = "false" ]; then
    read -s -p "Contraseña SSH/SUDO para $SERVER_USER@$SERVER_IP: " SERVER_PASSWORD
    echo ""
fi

echo "=========================================="
echo "    PLAN DE CONTINGENCIA / ROLLBACK UTAMED"
echo "=========================================="
echo "1) Rollback completo (Código anterior + Último Snapshot DB)"
echo "2) Rollback solo Código (Revertir commit anterior, rebuild y sync)"
echo "3) Rollback solo Base de Datos (Restaurar último snapshot DB puerto 5432)"
echo "4) Purgar cachés y reiniciar permisos (Fix error 500 / blank screen)"
echo "5) Cancelar"
read -p "Selecciona una opción [1-5]: " OPTION

if [ "$OPTION" = "5" ]; then
    echo "Operación cancelada."
    exit 0
fi

if [ "$SSH_KEY_AUTH" = "true" ]; then
    SSH_CMD=(ssh -o StrictHostKeyChecking=accept-new "$SERVER_USER@$SERVER_IP")
else
    SSH_CMD=(sshpass -p "$SERVER_PASSWORD" ssh -o StrictHostKeyChecking=accept-new "$SERVER_USER@$SERVER_IP")
fi

"${SSH_CMD[@]}" bash -s <<EOF
set -euo pipefail

REMOTE_REPO_DIR="$REMOTE_REPO_DIR"
REMOTE_TARGET_DIR="$REMOTE_TARGET_DIR"
SHARED_STORAGE_DIR="$SHARED_STORAGE_DIR"
WEB_USER="$WEB_USER"
WEB_GROUP="$WEB_GROUP"
SERVER_PASSWORD="$SERVER_PASSWORD"
OPTION="$OPTION"

sudo_cmd() {
    sudo -n "\$@" 2>/dev/null || echo "\$SERVER_PASSWORD" | sudo -S "\$@"
}

rollback_db() {
    echo "--> Buscando último snapshot de base de datos..."
    LATEST_DUMP=\$(ls -t /home/utamed/backups/*.dump 2>/dev/null | head -n 1)
    if [ -z "\$LATEST_DUMP" ]; then
        echo "Error: no se encontraron snapshots en /home/utamed/backups"
        return 1
    fi
    echo "--> Restaurando \$LATEST_DUMP en PostgreSQL (puerto 5432)..."
    PGPASSWORD=utamed pg_restore -h 127.0.0.1 -p 5432 -U utamed -d utamed_1ra_fase --clean --if-exists "\$LATEST_DUMP" || true
    echo "--> Snapshot restaurado correctamente."
}

rollback_code() {
    echo "--> Revirtiendo código al commit anterior..."
    cd "\$REMOTE_REPO_DIR"
    git checkout HEAD~1
    git submodule update --init --recursive
    npm ci
    npm run build
    
    echo "--> Sincronizando archivos a producción..."
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
}

clear_cache() {
    echo "--> Reconfigurando permisos y cachés de Laravel..."
    cd "\$REMOTE_TARGET_DIR"
    sudo_cmd chown -R "\$WEB_USER:\$WEB_GROUP" "\$SHARED_STORAGE_DIR"
    sudo_cmd chmod -R ug+rwX "\$SHARED_STORAGE_DIR"
    sudo_cmd chown -R "\$WEB_USER:\$WEB_GROUP" "\$REMOTE_TARGET_DIR/bootstrap/cache"
    sudo_cmd chmod -R ug+rwX "\$REMOTE_TARGET_DIR/bootstrap/cache"
    
    sudo_cmd rm -f bootstrap/cache/*.php
    sudo_cmd -u "\$WEB_USER" php artisan optimize:clear
    sudo_cmd -u "\$WEB_USER" php artisan optimize
}

case "\$OPTION" in
    1)
        rollback_db
        rollback_code
        clear_cache
        ;;
    2)
        rollback_code
        clear_cache
        ;;
    3)
        rollback_db
        clear_cache
        ;;
    4)
        clear_cache
        ;;
esac

echo "--> Verificando estado:"
php artisan about
echo "Operación de contingencia completada."
EOF
