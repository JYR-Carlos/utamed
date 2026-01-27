#!/usr/bin/env php
<?php

echo "📋 RESUMEN DE CONFIGURACIÓN - GENERADOR DE MODELOS\n";
echo "==================================================\n\n";

// Leer configuración del generador
$generatorContent = file_get_contents(__DIR__ . '/generate_models.php');

// Extraer configuración manual de pivots
preg_match('/\$manualPivotTables = \[(.*?)\];/s', $generatorContent, $matches);

echo "🔧 TABLAS PIVOT MANUALES CONFIGURADAS:\n";
echo "--------------------------------------\n";

if (isset($matches[1])) {
  $config = $matches[1];

  // Buscar configuraciones simples (true)
  preg_match_all("/'([^']+)'\s*=>\s*true/", $config, $simpleMatches);

  // Buscar configuraciones avanzadas (arrays)
  preg_match_all("/'([^']+)'\s*=>\s*\[/", $config, $advancedMatches);

  $totalCount = 0;

  if (!empty($simpleMatches[1])) {
    echo "\n  ✅ CONFIGURACIÓN SIMPLE (auto-detecta todas las FKs):\n";
    foreach ($simpleMatches[1] as $pivot) {
      // Filtrar solo claves que empiezan con 'utamed'
      if (strpos($pivot, 'utamed.') === 0) {
        $parts = explode('.', $pivot);
        $schema = str_replace('utamed.', '', $parts[0] . '.' . $parts[1]);
        $table = $parts[2] ?? '';
        echo "    ✓ {$schema}\\{$table}\n";
        $totalCount++;
      }
    }
  }

  if (!empty($advancedMatches[1])) {
    echo "\n  ⚙️  CONFIGURACIÓN AVANZADA (con filtros específicos):\n";
    foreach ($advancedMatches[1] as $pivot) {
      // Filtrar solo claves que empiezan con 'utamed'
      if (strpos($pivot, 'utamed.') === 0) {
        $parts = explode('.', $pivot);
        $schema = str_replace('utamed.', '', $parts[0] . '.' . $parts[1]);
        $table = $parts[2] ?? '';
        echo "    ✓ {$schema}\\{$table} (con filtros)\n";
        $totalCount++;
      }
    }
  }

  if ($totalCount > 0) {
    echo "\n📊 Total: {$totalCount} tabla(s) pivot configurada(s)\n";
  } else {
    echo "  (ninguna configurada)\n";
  }
} else {
  echo "  Error: No se pudo leer la configuración\n";
}

// Mostrar configuración de relaciones
echo "\n\n🔄 RENOMBRADO DE RELACIONES (belongsTo + hasMany/hasOne):\n";
echo "-----------------------------------------------------------\n";

preg_match('/\$relationNames = \[(.*?)\];/s', $generatorContent, $relationMatches);
if (isset($relationMatches[1])) {
  $relationConfig = $relationMatches[1];
  preg_match_all("/'utamed\.([^']+)'/", $relationConfig, $relationTableMatches);

  if (!empty($relationTableMatches[1])) {
    foreach (array_unique($relationTableMatches[1]) as $table) {
      echo "  ✓ " . str_replace('.', '\\', $table) . "\n";
    }
    echo "\n📊 Total: " . count(array_unique($relationTableMatches[1])) . " tabla(s) con renombrado\n";
    echo "   ℹ️  Usa '_self' para belongsTo, nombre de tabla para hasMany/hasOne\n";
  } else {
    echo "  (ninguna configurada)\n";
  }
} else {
  echo "  (ninguna configurada)\n";
}

echo "✅ Configuración unificada en \$relationNames\n";
echo "✅ Nombres de método pluralizados en español\n";
echo "✅ Evita duplicados garantizado\n\n";
echo "   Configuración:\n";
echo "     'utamed.Usuario.Usuario_Rol_Contexto' => [\n";
echo "       'tables' => ['Usuario', 'Rol'],\n";
echo "     ]\n";
echo "   Genera:\n";
echo "     - Usuario->roles()\n";
echo "     - Rol->usuarios()\n";
echo "     - (ignora Contexto)\n\n";

echo "📚 DOCUMENTACIÓN ADICIONAL:\n";
echo "---------------------------\n";
echo "  📄 GUIA_RELACIONES_INVERSAS.md - Guía paso a paso de hasMany/hasOne\n";
echo "  📄 GUIA_PIVOTS.md - Guía completa de belongsToMany\n";
echo "  📄 database/docs/CHANGELOG_PIVOTS.md - Historial de cambios\n";
echo "\n";
echo "💡 TIP: ¿Confundido con relaciones inversas?\n";
echo "  1. Abre GUIA_RELACIONES_INVERSAS.md\n";
echo "  2. Sigue el ejemplo didáctico paso a paso\n";
echo "  3. Busca 'Estudiante tiene Ramos' para ver un caso simple\n";
