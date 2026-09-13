#!/usr/bin/env bash
# ==============================================================================
# Wrapper para database-model/scripts/soft_reset.sh con guardas de seguridad
# ==============================================================================

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$ROOT_DIR/scripts/lib/comun.sh"

ENTORNOS_PERMITIDOS=("local" "development" "dev" "testing" "test")

APP_ENV_VAL="$APP_ENV"
if [ -z "$APP_ENV_VAL" ]; then
    APP_ENV_VAL=$(get_env_val "$ROOT_DIR/.env" "APP_ENV")
fi
APP_ENV_VAL=$(echo "$APP_ENV_VAL" | tr -d '[:space:]' | tr '[:upper:]' '[:lower:]')

FORZAR=false
for arg in "$@"; do
    if [ "$arg" == "--force" ] || [ "$arg" == "-Force" ]; then
        FORZAR=true
        break
    fi
done

if [ -z "$APP_ENV_VAL" ]; then
    echo "" >&2
    echo -e "${RED}ABORTADO: no se pudo determinar APP_ENV.${NC}" >&2
    echo -e "${RED}Este script hace DROP DATABASE; sin saber el entorno no se ejecuta.${NC}" >&2
    echo -e "${YELLOW}Define APP_ENV en .env o en el entorno, o pasa --force si sabes lo que haces.${NC}" >&2
    echo "" >&2
    if [ "$FORZAR" = false ]; then
        exit 1
    fi
    echo -e "${YELLOW}--force activo: se continúa sin conocer el entorno.${NC}"
else
    PERMITIDO=false
    for env_permitido in "${ENTORNOS_PERMITIDOS[@]}"; do
        if [ "$APP_ENV_VAL" == "$env_permitido" ]; then
            PERMITIDO=true
            break
        fi
    done

    if [ "$PERMITIDO" = false ]; then
        echo "" >&2
        echo -e "${RED}ABORTADO: APP_ENV=$APP_ENV_VAL no está en la lista de entornos desechables.${NC}" >&2
        echo -e "${RED}Permitidos: ${ENTORNOS_PERMITIDOS[*]}${NC}" >&2
        echo -e "${RED}Este script hace DROP DATABASE. Los datos no se recuperan.${NC}" >&2
        echo -e "${YELLOW}Para aplicar un cambio de esquema sin borrar: php artisan migrate${NC}" >&2
        echo -e "${YELLOW}Ver docs/FLUJO_MIGRACIONES_ESQUEMA.md${NC}" >&2
        echo "" >&2
        exit 1
    fi
fi

DB_HOST_VAL=$(get_env_val "$ROOT_DIR/.env" "DB_HOST")
DB_NAME_VAL=$(get_env_val "$ROOT_DIR/.env" "DB_DATABASE")

if [ -n "$DB_HOST_VAL" ] && [[ ! "$DB_HOST_VAL" =~ ^(127\.0\.0\.1|localhost|::1)$ ]]; then
    echo "" >&2
    echo -e "${RED}ABORTADO: DB_HOST=$DB_HOST_VAL no es local.${NC}" >&2
    echo -e "${RED}Este script sólo está pensado para la base de desarrollo en Docker.${NC}" >&2
    echo "" >&2
    exit 1
fi

echo -e "${GRAY}Destino del reset: $DB_HOST_VAL/$DB_NAME_VAL (APP_ENV=$APP_ENV_VAL)${NC}"

SCRIPT_PATH="$ROOT_DIR/database-model/scripts/soft_reset.sh"
if [ ! -f "$SCRIPT_PATH" ]; then
    echo -e "${RED}Error: Script no encontrado: $SCRIPT_PATH${NC}" >&2
    exit 1
fi

bash "$SCRIPT_PATH" "$@"
