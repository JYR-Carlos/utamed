#!/usr/bin/env bash
# Funciones compartidas por los scripts de base de datos en Linux/macOS.
#
# Se incluye con:  source "$(dirname "${BASH_SOURCE[0]}")/lib/comun.sh"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
GRAY='\033[0;90m'
NC='\033[0m'

get_env_val() {
    local file="$1"
    local key="$2"
    if [ ! -f "$file" ]; then
        return 1
    fi
    local line
    line=$(grep -E "^[[:space:]]*${key}[[:space:]]*=" "$file" | head -n 1)
    if [ -z "$line" ]; then
        return 1
    fi
    local val
    val=$(echo "$line" | sed -E "s/^[[:space:]]*${key}[[:space:]]*=[[:space:]]*//" | sed -e 's/[[:space:]]*$//')
    if [[ "$val" =~ ^\"([^\"]*)\" ]] || [[ "$val" =~ ^\'([^\']*)\' ]]; then
        echo "${BASH_REMATCH[1]}"
    else
        echo "$val" | sed -E 's/[[:space:]]+#.*$//'
    fi
    return 0
}

get_php_con_pgsql() {
    local preferred="$1"
    local candidates=()
    [ -n "$preferred" ] && candidates+=("$preferred")
    [ -n "$PHP_BINARIO" ] && candidates+=("$PHP_BINARIO")
    candidates+=("php")

    for c in "${candidates[@]}"; do
        if command -v "$c" >/dev/null 2>&1; then
            local out
            out=$("$c" -r 'echo extension_loaded("pdo_pgsql") ? "SI" : "NO";' 2>/dev/null)
            if [ "$out" = "SI" ]; then
                echo "$c"
                return 0
            fi
        fi
    done
    return 1
}

assert_php_con_pgsql() {
    local preferred="$1"
    local php_bin
    php_bin=$(get_php_con_pgsql "$preferred")
    if [ -n "$php_bin" ]; then
        echo "$php_bin"
        return 0
    fi

    echo -e "\n${RED}ERROR: no se encontró un binario de PHP con pdo_pgsql.${NC}" >&2
    echo -e "${YELLOW}El php del PATH debe tener habilitada la extensión pdo_pgsql.${NC}" >&2
    echo -e "${YELLOW}Opciones:${NC}" >&2
    echo -e "${YELLOW}  - definir PHP_BINARIO con la ruta al PHP correcto, o${NC}" >&2
    echo -e "${YELLOW}  - pasar --php <ruta> al script.${NC}\n" >&2
    return 1
}

get_config_testing() {
    local root_dir="$1"
    local xml_file="$root_dir/phpunit.xml"
    if [ ! -f "$xml_file" ]; then
        return 1
    fi
    php -r '
    $xml = @simplexml_load_file($argv[1]);
    if (!$xml) exit(1);
    foreach ($xml->xpath("//php/env") as $env) {
        $name = (string)$env["name"];
        $val = (string)$env["value"];
        if (str_starts_with($name, "DB_") || $name === "APP_ENV") {
            echo "export {$name}=\x27" . addcslashes($val, "\x27\\") . "\x27\n";
        }
    }
    ' "$xml_file"
}
