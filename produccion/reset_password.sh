#!/usr/bin/env bash
# ==============================================================================
# UTAMED - Lanzador SSH para Reseteo Forzado de Contraseña en Producción (Linux/macOS)
# ==============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

ENV_FILE="$REPO_DIR/.env"
REMOTE_TARGET_DIR="/var/www/prod_utamed"
PORT="22"
IDENTITY_FILE=""
SERVER_IP=""
SERVER_USER=""

# Función de ayuda
show_help() {
    cat << EOF
Uso: $0 [OPCIONES]

Conecta por SSH al servidor de producción de UTAMED y ejecuta el asistente
interactivo de reseteo forzado de contraseña de usuarios.

Opciones:
  -s, --server-ip IP       IP o hostname del servidor de producción
  -u, --server-user USER   Usuario SSH para conectarse
  -d, --remote-dir DIR     Directorio raíz de Laravel en producción (por defecto: /var/www/prod_utamed)
  -p, --port PUERTO        Puerto SSH (por defecto: 22)
  -i, --identity RUTA      Ruta al archivo de clave privada SSH (opcional)
  -h, --help               Muestra esta ayuda y termina
EOF
}

# Parsear argumentos
while [[ $# -gt 0 ]]; do
    case "$1" in
        -s|--server-ip)
            SERVER_IP="$2"
            shift 2
            ;;
        -u|--server-user)
            SERVER_USER="$2"
            shift 2
            ;;
        -d|--remote-dir)
            REMOTE_TARGET_DIR="$2"
            shift 2
            ;;
        -p|--port)
            PORT="$2"
            shift 2
            ;;
        -i|--identity)
            IDENTITY_FILE="$2"
            shift 2
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            echo "Opción desconocida: $1" >&2
            show_help
            exit 1
            ;;
    esac
done

echo ""
echo -e "\033[1;36m======================================================================\033[0m"
echo -e "\033[1;37m  UTAMED - Reseteo Forzado de Contraseña en Producción (SSH)\033[0m \033[0;90m[Linux/macOS]\033[0m"
echo -e "\033[1;36m======================================================================\033[0m"

# Cargar variables desde .env.prod o .env si existe
ENV_FILE=""
if [[ -f "$REPO_DIR/.env.prod" ]]; then
    ENV_FILE="$REPO_DIR/.env.prod"
elif [[ -f "$REPO_DIR/.env" ]]; then
    ENV_FILE="$REPO_DIR/.env"
fi

if [[ -n "$ENV_FILE" && -f "$ENV_FILE" ]]; then
    if [[ -z "$SERVER_IP" ]]; then
        SERVER_IP="$(grep -E '^SERVER_IP=' "$ENV_FILE" | head -n1 | cut -d '=' -f2- | tr -d '"'\'' ' || true)"
    fi
    if [[ -z "$SERVER_USER" ]]; then
        SERVER_USER="$(grep -E '^SERVER_USER=' "$ENV_FILE" | head -n1 | cut -d '=' -f2- | tr -d '"'\'' ' || true)"
    fi
fi

# Pedir interactivamente si no están definidas
if [[ -z "$SERVER_IP" ]]; then
    read -r -p "Ingrese la IP del servidor de producción (SERVER_IP): " SERVER_IP
fi

if [[ -z "$SERVER_USER" ]]; then
    read -r -p "Ingrese el usuario SSH del servidor (SERVER_USER): " SERVER_USER
fi

if [[ -z "$SERVER_IP" || -z "$SERVER_USER" ]]; then
    echo -e "\033[1;31mError: Se requiere SERVER_IP y SERVER_USER para continuar.\033[0m" >&2
    exit 1
fi

# Verificar cliente SSH
if ! command -v ssh >/dev/null 2>&1; then
    echo -e "\033[1;31mError: El comando 'ssh' no está instalado o no se encuentra en el PATH.\033[0m" >&2
    exit 1
fi

# Verificar script worker
WORKER_FILE="$SCRIPT_DIR/reset_password_worker.php"
if [[ ! -f "$WORKER_FILE" ]]; then
    echo -e "\033[1;31mError: No se encontró el worker en: $WORKER_FILE\033[0m" >&2
    exit 1
fi

echo -e "• Servidor destino : \033[1;33m$SERVER_USER@$SERVER_IP\033[0m (Puerto: $PORT)"
echo -e "• Directorio Laravel: \033[1;33m$REMOTE_TARGET_DIR\033[0m"
echo -e "• Preparando sesión interactiva SSH..."
echo ""

# Codificación base64 portable (funciona en Linux GNU y macOS BSD)
WORKER_B64="$(base64 < "$WORKER_FILE" | tr -d '\r\n')"

# Opciones SSH
SSH_OPTS=(-t -p "$PORT" -o StrictHostKeyChecking=accept-new)
if [[ -n "$IDENTITY_FILE" ]]; then
    SSH_OPTS+=(-i "$IDENTITY_FILE")
fi

REMOTE_CMD="echo '$WORKER_B64' | base64 -d > /tmp/utamed_reset_pwd_worker.php && chmod 644 /tmp/utamed_reset_pwd_worker.php && sudo php /tmp/utamed_reset_pwd_worker.php --app-dir='$REMOTE_TARGET_DIR'; rm -f /tmp/utamed_reset_pwd_worker.php"

# Ejecutar sesión interactiva con TTY
ssh "${SSH_OPTS[@]}" "$SERVER_USER@$SERVER_IP" "$REMOTE_CMD"
