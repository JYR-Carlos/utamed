<?php

/**
 * Generador de SQL de Permisos desde permissions_config.php
 * 
 * Lee la configuración de permisos desde scripts/permissions_config.php
 * y genera el archivo database-model/init_scripts/03-inserts/10-permisos.sql
 * Solo genera la parte de INSERT de permisos.
 * Las asignaciones de roles se mantienen manualmente.
 * 
 * Uso: php scripts/generate_permissions_sql.php
 */

// Cargar la configuración
$configPath = __DIR__ . '/permissions_config.php';
$config = require $configPath;

if (!$config) {
    echo "❌ Error: No se pudo cargar permissions_config.php\n";
    exit(1);
}

/**
 * Extraer recursivamente todos los permisos de la config
 * Retorna array de permisos con slug y nombre
 */
function extractPermissions($config, $prefix = '') {
    $permisos = [];
    
    foreach ($config as $key => $value) {
        if (!is_array($value)) {
            continue;
        }
        
        // Construir el path completo
        $path = $prefix ? "{$prefix}/{$key}" : $key;
        
        // Si tiene _actions, agregar los permisos de las acciones
        if (isset($value['_actions']) && is_array($value['_actions'])) {
            // Agregar el permiso de la categoría vacía (ej: 'usuarios')
            $permisos[$path] = [
                'slug' => $path,
                'nombre' => $path
            ];
            
            // Agregar permisos de las acciones
            foreach ($value['_actions'] as $action) {
                $slug = "{$path}:{$action}";
                $permisos[$slug] = [
                    'slug' => $slug,
                    'nombre' => $slug
                ];
            }
            
            // Agregar wildcard si hay acciones
            $wildcard = "{$path}:*";
            $permisos[$wildcard] = [
                'slug' => $wildcard,
                'nombre' => $wildcard
            ];
        }
        
        // Recursivamente procesar sub-elementos
        $subElements = array_filter($value, fn($k) => $k !== '_actions', ARRAY_FILTER_USE_KEY);
        if (!empty($subElements)) {
            $subPermisos = extractPermissions($subElements, $path);
            $permisos = array_merge($permisos, $subPermisos);
        }
    }
    
    return $permisos;
}

// Extraer todos los permisos
$permisos = extractPermissions($config);

// Agregar permiso wildcard principal
$permisos = array_merge([
    '*' => ['slug' => '*', 'nombre' => '*']
], $permisos);

// Ordenar por slug
uksort($permisos, 'strcmp');

echo "✅ Se extrajeron " . count($permisos) . " permisos\n";

// Generar SQL para INSERT
$sqlLines = ["INSERT INTO usuario.permiso (slug, nombre)", "VALUES"];
$values = [];

foreach ($permisos as $perm) {
    $slug = $perm['slug'];
    $nombre = $perm['nombre'];
    $values[] = "\t('{$slug}', '{$nombre}')";
}

$sql = implode("\n", $sqlLines) . "\n" . implode(",\n", $values) . ";";

// Escribir archivo SQL (solo la parte de permisos)
$outputPath = __DIR__ . '/../database-model/init_scripts/03-inserts/10-permisos.sql';
$outputDir = dirname($outputPath);

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

file_put_contents($outputPath, $sql . "\n");

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

echo "\n✅ Generación completada exitosamente\n";
echo "📌 NOTA: Las asignaciones de permisos a roles se mantienen en la sección WITH de la migración\n";
