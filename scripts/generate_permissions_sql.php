<?php

/**
 * Generador de SQL de Permisos y Contextos desde permissions_config.php
 * 
 * Lee la configuración de permisos desde scripts/permissions_config.php y genera:
 *
 *   01-crear-contextos-permisos.sql  — INSERTs en usuario.tipo_contexto y usuario.contexto
 *   10-permisos.sql                  — INSERTs en usuario.permiso
 *
 * Las asignaciones de roles se mantienen manualmente.
 *
 * Uso: php scripts/generate_permissions_sql.php
 */

// Cargar autoloaders y bootstrap de Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Cargar la configuración
$configPath = __DIR__ . '/permissions_config.php';
$config = require $configPath;

if (!$config) {
    echo "❌ Error: No se pudo cargar permissions_config.php\n";
    exit(1);
}

// ============================================================
// Leer y validar _valid_context_types
// ============================================================
if (!isset($config['_valid_context_types'])) {
    echo "❌ Error: permissions_config.php no tiene '_valid_context_types'.\n";
    echo "   Agrégalo como primera clave del array antes de ejecutar este script.\n";
    exit(1);
}

$validContextTypes = $config['_valid_context_types']; // ['tipo' => 'tabla_referenciada']

// Validar que todos los permisos usan tipos configurados
$contextTypeValidator = function (array $node, string $path) use (&$contextTypeValidator, $validContextTypes): void {
    foreach (['_valid_context', '_valid_parent_context'] as $key) {
        if (isset($node[$key])) {
            $val = $node[$key];
            if (!isset($validContextTypes[$val])) {
                $valid = implode(', ', array_keys($validContextTypes));
                echo "❌ Tipo de contexto inválido en '{$path}.{$key}': '{$val}'.\n";
                echo "   Tipos configurados en '_valid_context_types': {$valid}\n";
                exit(1);
            }
        }
    }
    foreach ($node as $subKey => $subValue) {
        if ($subKey[0] === '_' || !is_array($subValue))
            continue;
        $contextTypeValidator($subValue, "{$path}.{$subKey}");
    }
};

foreach ($config as $rootKey => $rootValue) {
    if ($rootKey[0] === '_' || !is_array($rootValue))
        continue;
    $contextTypeValidator($rootValue, $rootKey);
}

echo "✅ Tipos de contexto validados\n";

// ============================================================
// GENERAR 01-crear-contextos-permisos.sql
// ============================================================
$contextSqlPath = __DIR__ . '/../database-model/init_scripts/03-inserts/01-crear-contextos-permisos.sql';

$contextInsertValues = [];
foreach ($validContextTypes as $tipo => $tablaRef) {
    $contextInsertValues[] = "\t('{$tipo}', '{$tablaRef}')";
}

$contextSql = "-- AUTOGENERADO desde scripts/permissions_config.php — NO EDITAR MANUALMENTE.\n";
$contextSql .= "-- Regenerar con: php scripts/generate_permissions_sql.php\n\n";
$contextSql .= "INSERT INTO usuario.tipo_contexto (categoria, tabla_referenciada)\n";
$contextSql .= "VALUES\n";
$contextSql .= implode(",\n", $contextInsertValues) . ";\n";
$contextSql .= "\n";
$contextSql .= "-- -------------------------------------------------------------------------------\n";
$contextSql .= "-- Insert: Crear contexto global\n";
$contextSql .= "-- Tabla: Contexto\n";
$contextSql .= "-- -------------------------------------------------------------------------------\n";
$contextSql .= "\n";
$contextSql .= "INSERT INTO usuario.contexto (\n";
$contextSql .= "    contexto_display,\n";
$contextSql .= "    id_tipo_contexto\n";
$contextSql .= ") VALUES (\n";
$contextSql .= "    'Contexto Global | Solo Permisos Administrativos',\n";
$contextSql .= "    (\n";
$contextSql .= "        SELECT id_tipo_contexto\n";
$contextSql .= "        FROM usuario.tipo_contexto\n";
$contextSql .= "        WHERE categoria = 'global'\n";
$contextSql .= "    )\n";
$contextSql .= ");\n";

$contextSqlDir = dirname($contextSqlPath);
if (!is_dir($contextSqlDir)) {
    mkdir($contextSqlDir, 0755, true);
}
file_put_contents($contextSqlPath, $contextSql);
echo "✅ Archivo SQL generado: {$contextSqlPath}\n";
echo "📊 Tipos de contexto: " . count($validContextTypes) . " (" . implode(', ', array_keys($validContextTypes)) . ")\n";

// ============================================================
// Extraer permisos del config
// ============================================================

/**
 * Extraer recursivamente todos los permisos de la config.
 * Retorna array de permisos con slug y nombre.
 * Salta TODAS las claves que empiezan con '_'.
 */
function extractPermissions($config, $prefix = '')
{
    $permisos = [];

    foreach ($config as $key => $value) {
        // Saltar todas las claves de metadatos (empiezan con '_')
        if ($key[0] === '_') {
            continue;
        }

        if (!is_array($value)) {
            continue;
        }

        // Construir el path completo
        $path = $prefix ? "{$prefix}/{$key}" : $key;

        // Si tiene _actions, agregar los permisos de las acciones
        if (isset($value['_actions']) && is_array($value['_actions'])) {
            foreach ($value['_actions'] as $action) {
                $slug = "{$path}:{$action}";
                $permisos[$slug] = [
                    'slug' => $slug,
                    'nombre' => $slug,
                ];
            }

            // Agregar wildcard si hay acciones
            $wildcard = "{$path}:*";
            $permisos[$wildcard] = [
                'slug' => $wildcard,
                'nombre' => $wildcard,
            ];
        }

        // Recursivamente procesar sub-elementos (sólo claves sin '_')
        $subPermisos = extractPermissions($value, $path);
        $permisos = array_merge($permisos, $subPermisos);
    }

    return $permisos;
}

// ============================================================
// GENERAR 10-permisos.sql
// ============================================================
$permisos = extractPermissions($config);

// Agregar permiso wildcard principal
$permisos = array_merge([
    '*' => ['slug' => '*', 'nombre' => '*'],
], $permisos);

// Ordenar por slug
uksort($permisos, 'strcmp');

echo "✅ Se extrajeron " . count($permisos) . " permisos\n";

// Generar SQL para INSERT
$sql = "-- AUTOGENERADO desde scripts/permissions_config.php — NO EDITAR MANUALMENTE.\n";
$sql .= "-- Regenerar con: php scripts/generate_permissions_sql.php\n\n";
$sql .= "INSERT INTO usuario.permiso (slug, nombre)\nVALUES\n";

$values = [];
foreach ($permisos as $perm) {
    $slug = $perm['slug'];
    $nombre = $perm['nombre'];
    $values[] = "\t('{$slug}', '{$nombre}')";
}

$sql .= implode(",\n", $values) . ";\n";

$outputPath = __DIR__ . '/../database-model/init_scripts/03-inserts/10-permisos.sql';
$outputDir = dirname($outputPath);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}
file_put_contents($outputPath, $sql);

echo "✅ Archivo SQL generado: {$outputPath}\n";
echo "📊 Total de permisos: " . count($permisos) . "\n";

// Mostrar un preview
echo "\n📋 Preview de permisos generados:\n";
$count = 0;
foreach ($permisos as $perm) {
    if ($count >= 10) {
        echo "\t... y " . (count($permisos) - 10) . " más\n";
        break;
    }
    echo "\t- {$perm['slug']}\n";
    $count++;
}

echo "\n✅ Generación de PERMISOS completada exitosamente\n";

// ============================================================
// VALIDAR TUPLAS EN ROLES_CONFIG
// ============================================================

echo "\n🔍 VALIDANDO ESTRUCTURA DE TUPLAS EN ROLES_CONFIG...\n\n";

$rolesConfigPath = __DIR__ . '/roles_config.php';
$rolesConfig = require $rolesConfigPath;

if (!is_array($rolesConfig) || empty($rolesConfig)) {
    echo "❌ Error: roles_config.php debe retornar un array no vacío\n";
    exit(1);
}

$tuplesValidationErrors = [];

foreach ($rolesConfig as $roleName => $permissions) {
    if (!is_array($permissions)) {
        $tuplesValidationErrors[] = "Rol '{$roleName}': Permisos debe ser un array";
        continue;
    }

    foreach ($permissions as $idx => $tuple) {
        if (!is_array($tuple) || count($tuple) !== 2) {
            $tuplesValidationErrors[] = "Rol '{$roleName}', índice {$idx}: "
                . "Permiso debe ser tupla [Permission enum, boolean]";
            continue;
        }

        $permission = $tuple[0];
        $puedeDelegrar = $tuple[1];

        if (!($permission instanceof \App\Support\Permissions)) {
            $tuplesValidationErrors[] = "Rol '{$roleName}', índice {$idx}: "
                . "Primer elemento debe ser Permissions enum, recibido: " . gettype($permission);
        }

        if (!is_bool($puedeDelegrar)) {
            $tuplesValidationErrors[] = "Rol '{$roleName}', índice {$idx}: "
                . "Segundo elemento debe ser boolean, recibido: " . gettype($puedeDelegrar);
        }
    }
}

if (!empty($tuplesValidationErrors)) {
    echo "❌ ERRORES EN ESTRUCTURA DE TUPLAS:\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($tuplesValidationErrors as $error) {
        echo "  ❌ {$error}\n";
    }
    echo str_repeat("-", 80) . "\n";
    echo "\n⚠️  Revisar roles_config.php y asegurar que cada permiso sea:\n";
    echo "    [Permissions::PERMISO, true/false]  # true = puede delegarse, false = no\n\n";
    exit(1);
}

echo "✅ Todas las tuplas en roles_config.php son válidas\n\n";

// ============================================================
// GENERAR ROLES AUTOMÁTICAMENTE
// ============================================================

require_once __DIR__ . '/generate_roles_sql.php';

\GenerateRolesSql\generateRolesSql();
