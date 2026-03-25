<?php

/**
 * Generador de SQL de Roles desde roles_config.php
 *
 * Lee la configuración de roles desde scripts/roles_config.php y genera:
 *
 *   11-roles-config.sql — INSERTs en usuario.rol y usuario.asignacion_rol_permiso
 *
 * Este módulo es llamado automáticamente por generate_permissions_sql.php
 * después de generar los permisos base.
 *
 * Uso (directo):     php scripts/generate_roles_sql.php
 * Uso (automático):  php scripts/generate_permissions_sql.php
 */

namespace GenerateRolesSql;

// Cargar autoloaders y bootstrap de Laravel (solo si no están cargados)
if (!function_exists('app')) {
  require __DIR__ . '/../vendor/autoload.php';
  require_once __DIR__ . '/../bootstrap/app.php';
}

// ============================================================
// Función Principal: Generar SQL de Roles
// ============================================================

function generateRolesSql(): void
{
  // Cargar la configuración de roles
  $rolesConfigPath = __DIR__ . '/roles_config.php';

  if (!file_exists($rolesConfigPath)) {
    echo "❌ Error: No se encontró roles_config.php en {$rolesConfigPath}\n";
    exit(1);
  }

  $rolesConfig = require $rolesConfigPath;

  if (!is_array($rolesConfig) || empty($rolesConfig)) {
    echo "❌ Error: roles_config.php debe retornar un array no vacío\n";
    exit(1);
  }

  echo "\n" . str_repeat("=", 80) . "\n";
  echo "🔄 GENERADOR DE ROLES SQL\n";
  echo str_repeat("=", 80) . "\n";

  // ============================================================
  // Procesar y validar roles
  // ============================================================
  $rolesData = [];
  $totalPermisos = 0;

  foreach ($rolesConfig as $roleName => $permissions) {
    if (!is_string($roleName)) {
      echo "❌ Error: Las claves de roles deben ser strings (nombre del rol)\n";
      exit(1);
    }

    if (!is_array($permissions)) {
      echo "❌ Error: Permisos del rol '{$roleName}' debe ser un array\n";
      exit(1);
    }

    // Validar que sean tuplas [Permission enum, boolean]
    $parsedPermissions = [];
    $permEnumsOnly = [];  // Para getCompatibleContexts() que espera solo enums

    foreach ($permissions as $idx => $tuple) {
      if (!is_array($tuple) || count($tuple) !== 2) {
        echo "❌ Error: Permiso inválido en rol '{$roleName}' en índice {$idx}.\n"
          . "   Esperado: [Permissions::PERMISO, boolean]\n"
          . "   Recibido: " . var_export($tuple, true) . "\n";
        exit(1);
      }

      $permission = $tuple[0];
      $puedeDelegrar = $tuple[1];

      if (!($permission instanceof \App\Support\Permissions)) {
        echo "❌ Error: Primer elemento de tupla debe ser Permissions enum\n"
          . "Rol '{$roleName}', índice {$idx}: " . var_export($permission, true) . "\n";
        exit(1);
      }

      if (!is_bool($puedeDelegrar)) {
        echo "❌ Error: Segundo elemento de tupla debe ser boolean (true/false)\n"
          . "Rol '{$roleName}', índice {$idx}: " . var_export($puedeDelegrar, true) . "\n";
        exit(1);
      }

      $parsedPermissions[] = $tuple;
      $permEnumsOnly[] = $permission;
    }

    $rolesData[$roleName] = [
      'nombre' => $roleName,
      'permisos' => $parsedPermissions,  // Array de tuplas [Permission enum, puede_delegar boolean]
      'permisos_enums_only' => $permEnumsOnly,  // Para funciones que solo necesitan enums
      'cantidad_permisos' => count($parsedPermissions),
    ];

    $totalPermisos += count($parsedPermissions);
  }

  echo "✅ Se extrajeron " . count($rolesData) . " roles\n";
  echo "📊 Total de asignaciones permiso-rol: {$totalPermisos}\n\n";

  // ============================================================
  // VALIDAR COMPATIBILIDAD DE CONTEXTOS
  // ============================================================
  echo "🔍 VALIDANDO COMPATIBILIDAD DE CONTEXTOS DE ROLES...\n\n";

  $validationErrors = [];
  $validationWarnings = [];

  foreach ($rolesData as $roleName => $data) {
    try {
      $compatibleContexts = \App\Services\Authorization\PermissionContextConstraints::getCompatibleContexts($data['permisos_enums_only']);

      if (empty($compatibleContexts)) {
        $validationErrors[] = "Role '{$roleName}': No tiene contextos compatibles. "
          . "Todos sus permisos son incompatibles entre sí.";
      } else {
        $contextNames = array_map(fn($c) => $c->value, $compatibleContexts);
        $contextsStr = implode(', ', $contextNames);
        printf("✓ %-25s → Asignable en: %s\n", $roleName, $contextsStr);
      }
    } catch (\InvalidArgumentException $e) {
      $validationErrors[] = "Role '{$roleName}': " . $e->getMessage();
    }
  }

  if (!empty($validationErrors)) {
    echo "\n❌ ERRORES DE VALIDACIÓN ENCONTRADOS:\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($validationErrors as $error) {
      echo "  ❌ {$error}\n";
    }
    echo str_repeat("-", 80) . "\n";
    echo "\n⚠️  No se puede generar SQL con roles incompatibles.\n";
    echo "    Revisar roles_config.php y asegurar que cada rol pueda asignarse\n";
    echo "    a al menos un contexto válido.\n\n";
    exit(1);
  }

  echo "\n✅ Todas las roles pasaron validación de contextos\n\n";

  // ============================================================
  // Mostrar tabla de roles y permisos
  // ============================================================
  echo "📋 ROLES CONFIGURADOS:\n";
  echo str_repeat("-", 80) . "\n";
  printf("| %-30s | %-20s | %-20s |\n", "Rol", "Permisos", "Primer Permiso");
  echo str_repeat("-", 80) . "\n";

  foreach ($rolesData as $data) {
    $firstPerm = isset($data['permisos'][0]) ? $data['permisos'][0][0]->value : '(ninguno)';
    printf(
      "| %-30s | %-20d | %-20s |\n",
      substr($data['nombre'], 0, 28),
      $data['cantidad_permisos'],
      substr($firstPerm, 0, 18)
    );
  }
  echo str_repeat("-", 80) . "\n\n";

  // ============================================================
  // GENERAR 11-roles-config.sql
  // ============================================================
  $sqlPath = __DIR__ . '/../database-model/init_scripts/03-inserts/11-roles-config.sql';
  $sqlDir = dirname($sqlPath);

  if (!is_dir($sqlDir)) {
    mkdir($sqlDir, 0755, true);
  }

  $sql = generateRolesSqlContent($rolesData);

  file_put_contents($sqlPath, $sql);

  echo "✅ Archivo SQL generado: {$sqlPath}\n";
  echo "📊 Roles: " . count($rolesData) . "\n";
  echo "📊 Total asignaciones: {$totalPermisos}\n\n";

  // ============================================================
  // Preview de permisos por rol
  // ============================================================
  echo "📋 PREVIEW DE PERMISOS ASIGNADOS:\n";
  echo str_repeat("-", 80) . "\n";

  foreach ($rolesData as $roleName => $data) {
    $permisos = $data['permisos'];
    $previewPerms = array_slice($permisos, 0, 3);
    $remaining = count($permisos) - 3;

    $permLabels = array_map(fn($p) => $p[0]->value, $previewPerms);
    $previewStr = implode(', ', $permLabels);
    if ($remaining > 0) {
      $previewStr .= ", ... +" . $remaining;
    }

    printf("%-30s: %s\n", substr($roleName, 0, 28), $previewStr);
  }

  echo str_repeat("-", 80) . "\n\n";
  echo "✅ Generación de roles completada exitosamente\n";
  echo "📌 Ejecutar: php scripts/generate_permissions_sql.php\n";
  echo "    (Los roles se aplican automáticamente después)\n\n";
}

// ============================================================
// Función Helper: Generar contenido del SQL
// ============================================================

function generateRolesSqlContent(array $rolesData): string
{
  $sql = "-- AUTOGENERADO desde scripts/roles_config.php — NO EDITAR MANUALMENTE.\n";
  $sql .= "-- Regenerar con: php scripts/generate_permissions_sql.php\n";
  $sql .= "-- (Este archivo se genera automáticamente como parte del proceso)\n\n";

  // ============================================================
  // Parte 1: Insertar roles (si no existen)
  // ============================================================
  $sql .= "-- ===============================================================================\n";
  $sql .= "-- PARTE 1: Crear roles del sistema (usuario.rol)\n";
  $sql .= "-- ===============================================================================\n\n";

  $sql .= "DO \$\$\n";
  $sql .= "DECLARE\n";
  $sql .= "    v_creado_por integer;\n";
  $sql .= "BEGIN\n";
  $sql .= "    -- Obtener el ID del usuario superadmin como creador de los roles\n";
  $sql .= "    SELECT id_usuario INTO v_creado_por\n";
  $sql .= "    FROM usuario.usuario\n";
  $sql .= "    WHERE username = 'superadmin'\n";
  $sql .= "    LIMIT 1;\n\n";
  $sql .= "    IF v_creado_por IS NULL THEN\n";
  $sql .= "        RAISE EXCEPTION '11-roles-config.sql: No se encontró el usuario superadmin. Ejecutar 05-usuarios-base.sql primero.';\n";
  $sql .= "    END IF;\n\n";
  $sql .= "    -- Insertar roles solo si no existen ya\n";
  $sql .= "    INSERT INTO usuario.rol (nombre, creado_por)\n";
  $sql .= "    SELECT nombre, v_creado_por\n";
  $sql .= "    FROM (VALUES\n";

  $roleNames = array_keys($rolesData);
  $roleInserts = array_map(fn($name) => "        ('" . str_replace("'", "''", $name) . "')", $roleNames);
  $sql .= implode(",\n", $roleInserts) . "\n";

  $sql .= "    ) AS roles(nombre)\n";
  $sql .= "    WHERE NOT EXISTS (\n";
  $sql .= "        SELECT 1 FROM usuario.rol r WHERE r.nombre = roles.nombre\n";
  $sql .= "    );\n\n";
  $sql .= "    RAISE NOTICE '✅ Roles del sistema creados correctamente.';\n";
  $sql .= "END;\n";
  $sql .= "\$\$;\n\n";

  // ============================================================
  // Parte 2: Asignar permisos a roles (una statement por rol)
  // ============================================================
  $sql .= "-- ===============================================================================\n";
  $sql .= "-- PARTE 2: Asignación de Permisos a Roles (usuario.asignacion_rol_permiso)\n";
  $sql .= "-- ===============================================================================\n\n";

  foreach ($rolesData as $roleName => $data) {
    $permisosConConfig = $data['permisos'];  // Array de tuplas [Permission enum, puede_delegar boolean]

    // Agrupar por puede_delegar para optimizar: un SELECT por (rol, puede_delegar)
    $agrupadoPorDelegacion = [];
    foreach ($permisosConConfig as $permConfig) {
      $permission = $permConfig[0];  // Enum
      $puedeDelegrar = $permConfig[1];  // Boolean

      $puedeDelegrarStr = $puedeDelegrar ? 'TRUE' : 'FALSE';
      if (!isset($agrupadoPorDelegacion[$puedeDelegrarStr])) {
        $agrupadoPorDelegacion[$puedeDelegrarStr] = [];
      }
      $agrupadoPorDelegacion[$puedeDelegrarStr][] = $permission;
    }

    // Generar un INSERT per rol con múltiples SELECT unidos por UNION ALL
    $selectClauses = [];
    foreach ($agrupadoPorDelegacion as $puedeDelegrar => $permisos) {
      $slugs = array_map(function ($perm) {
        $slug = $perm instanceof \App\Support\Permissions ? $perm->value : $perm;
        return "        '" . str_replace("'", "''", $slug) . "'";
      }, $permisos);

      $selectClauses[] = "SELECT r.id_rol, p.id_permiso, {$puedeDelegrar}\nFROM usuario.rol r\n    CROSS JOIN usuario.permiso p\nWHERE r.nombre = '" . str_replace("'", "''", $roleName) . "'\n    AND p.slug IN (\n" . implode(",\n", $slugs) . "\n    )\n    AND NOT EXISTS (\n        SELECT 1\n        FROM usuario.asignacion_rol_permiso x\n        WHERE x.id_rol = r.id_rol\n            AND x.id_permiso = p.id_permiso\n    )";
    }

    if (!empty($selectClauses)) {
      $sql .= "-- Rol: {$roleName} ({$data['cantidad_permisos']} permisos)\n";
      $sql .= "INSERT INTO usuario.asignacion_rol_permiso (\n";
      $sql .= "    id_rol,\n";
      $sql .= "    id_permiso,\n";
      $sql .= "    puede_delegar_permisos\n";
      $sql .= ")\n";
      $sql .= implode("\nUNION ALL\n", $selectClauses) . ";\n\n";
    }
  }

  return $sql;
}

// ============================================================
// Ejecutar si se llama directamente
// ============================================================

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
  generateRolesSql();
}

// ============================================================
// Ejecutar si se llama directamente
// ============================================================

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
  generateRolesSql();
}
