#!/usr/bin/env bash
# ==============================================================================
# Reconstruye la base de desarrollo o la de tests en Linux: reset + migrate + seed
# ==============================================================================
set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "$ROOT_DIR/scripts/lib/comun.sh"

IS_TESTING=false
SIN_SEED=false
PREF_PHP=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --testing|-Testing|-t)
            IS_TESTING=true
            shift
            ;;
        --sin-seed|--no-seed|-SinSeed)
            SIN_SEED=true
            shift
            ;;
        --php|-Php)
            PREF_PHP="$2"
            shift 2
            ;;
        *)
            shift
            ;;
    esac
done

PHP_BIN=$(assert_php_con_pgsql "$PREF_PHP")

if [ "$IS_TESTING" = true ]; then
    TEST_CONFIG=$(get_config_testing "$ROOT_DIR")
    if [ -z "$TEST_CONFIG" ]; then
        echo -e "${RED}ERROR: no se pudo leer la configuración DB_* de phpunit.xml.${NC}" >&2
        exit 1
    fi
    eval "$TEST_CONFIG"
    export APP_ENV='testing'
    ETIQUETA="testing ($DB_HOST:$DB_PORT/$DB_DATABASE)"
else
    ENV_APP=$(get_env_val "$ROOT_DIR/.env" "APP_ENV")
    HOST_VAL=$(get_env_val "$ROOT_DIR/.env" "DB_HOST")
    DB_VAL=$(get_env_val "$ROOT_DIR/.env" "DB_DATABASE")
    ETIQUETA="desarrollo ($HOST_VAL/$DB_VAL)"
    if [ -n "$ENV_APP" ]; then
        export APP_ENV="$ENV_APP"
    fi
fi

echo ""
echo -e "${CYAN}Reconstruyendo la base de $ETIQUETA${NC}"
echo -e "${YELLOW}Esto hace DROP DATABASE: se pierden todos los datos.${NC}"
echo ""

# 1. Reset (usa soft_reset.sh con guardas de entorno)
WRAPPER="$ROOT_DIR/soft_reset.sh"
if [ "$IS_TESTING" = true ]; then
    bash "$WRAPPER" --testing
else
    bash "$WRAPPER"
fi

# 2. Migraciones
echo ""
echo -e "${CYAN}Aplicando migraciones...${NC}"
"$PHP_BIN" "$ROOT_DIR/artisan" migrate --force

# 3. Semillas
if [ "$SIN_SEED" = false ]; then
    echo ""
    echo -e "${CYAN}Sembrando...${NC}"
    "$PHP_BIN" "$ROOT_DIR/artisan" db:seed --force
fi

echo ""
echo -e "${GREEN}Base de $ETIQUETA reconstruida exitosamente.${NC}"
echo ""
