#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 DETECCIÓN DE TABLAS PIVOT (belongsToMany)\n";
echo "=============================================\n\n";

$tables = DB::select("
    SELECT table_schema, table_name
    FROM information_schema.tables
    WHERE table_catalog = 'utamed_1ra_fase'
    AND table_schema LIKE 'utamed.%'
    AND table_type = 'BASE TABLE'
    ORDER BY table_schema, table_name
");

echo "📋 Analizando " . count($tables) . " tablas...\n\n";

$pivotCandidates = [];

foreach ($tables as $t) {
  // Obtener FKs de la tabla
  $fks = DB::select("
        SELECT
            con.conname,
            string_agg(att.attname, ',' ORDER BY ordinality) AS column_names,
            fn.nspname AS foreign_table_schema,
            fc.relname AS foreign_table_name,
            string_agg(fatt.attname, ',' ORDER BY ordinality) AS foreign_column_names
        FROM pg_constraint con
        JOIN pg_class c ON con.conrelid = c.oid
        JOIN pg_namespace n ON c.relnamespace = n.oid
        JOIN LATERAL unnest(con.conkey) WITH ORDINALITY AS u(attnum, ordinality) ON true
        JOIN pg_attribute att ON att.attrelid = c.oid AND att.attnum = u.attnum
        JOIN pg_class fc ON con.confrelid = fc.oid
        JOIN pg_namespace fn ON fc.relnamespace = fn.oid
        JOIN LATERAL unnest(con.confkey) WITH ORDINALITY AS uf(fattnum, ordinality2) ON uf.ordinality2 = u.ordinality
        JOIN pg_attribute fatt ON fatt.attrelid = fc.oid AND fatt.attnum = uf.fattnum
        WHERE con.contype = 'f'
            AND c.relname = ?
            AND n.nspname = ?
        GROUP BY con.oid, con.conname, fn.nspname, fc.relname
    ", [$t->table_name, $t->table_schema]);

  // Criterio pivot mejorado
  if (count($fks) >= 2) {
    // Obtener PK
    $pk = DB::select("
            SELECT array_to_string(array_agg(a.attname ORDER BY array_position(i.indkey, a.attnum)), ',') as pk_columns
            FROM pg_index i
            JOIN pg_class c ON i.indrelid = c.oid
            JOIN pg_namespace n ON c.relnamespace = n.oid
            JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = ANY(i.indkey)
            WHERE n.nspname = ?
                AND c.relname = ?
                AND i.indisprimary = true
            GROUP BY i.indexrelid
        ", [$t->table_schema, $t->table_name]);

    $pkColumns = $pk[0]->pk_columns ?? '';

    // Recopilar columnas FK
    $allFkColumns = [];
    foreach ($fks as $fk) {
      $allFkColumns = array_merge($allFkColumns, explode(',', $fk->column_names));
    }
    $allFkColumns = array_unique($allFkColumns);
    sort($allFkColumns);
    $fkColumnsStr = implode(',', $allFkColumns);

    // Verificar criterios
    $pkIsFks = ($pkColumns === $fkColumnsStr);

    $hasUnique = DB::select("
            SELECT COUNT(*) as count
            FROM pg_index i
            JOIN pg_class c ON i.indrelid = c.oid
            JOIN pg_namespace n ON c.relnamespace = n.oid
            JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS u(attnum, ordinality) ON true
            JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = u.attnum
            WHERE n.nspname = ?
                AND c.relname = ?
                AND i.indisunique = true
            GROUP BY i.indexrelid
            HAVING array_to_string(array_agg(a.attname ORDER BY u.ordinality), ',') = ?
        ", [$t->table_schema, $t->table_name, $fkColumnsStr]);

    $hasUniqueOnFks = count($hasUnique) > 0;

    if ($pkIsFks || $hasUniqueOnFks) {
      // Obtener columnas de la tabla
      $columns = DB::select("
                SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = ?
                    AND table_name = ?
                ORDER BY ordinal_position
            ", [$t->table_schema, $t->table_name]);

      $columnNames = array_map(fn($c) => $c->column_name, $columns);

      $fkCols = $allFkColumns;
      $nonFkColumns = array_diff($columnNames, $fkCols);
      $nonFkColumns = array_values(array_filter($nonFkColumns, fn($c) => !in_array($c, ['created_at', 'updated_at', 'fecha_creacion', 'fecha_actualizacion', 'esta_activo'])));

      $pivotCandidates[] = [
        'schema' => $t->table_schema,
        'table' => $t->table_name,
        'num_fks' => count($fks),
        'pk_columns' => $pkColumns,
        'fk_columns' => $fkColumnsStr,
        'pk_is_fks' => $pkIsFks,
        'has_unique_on_fks' => $hasUniqueOnFks,
        'fks' => array_map(fn($fk) => [
          'schema' => $fk->foreign_table_schema,
          'table' => $fk->foreign_table_name,
          'columns' => $fk->column_names,
        ], $fks),
        'additional_columns' => $nonFkColumns,
        'is_pure_pivot' => count($nonFkColumns) == 0,
      ];
    }
  }
}

if (empty($pivotCandidates)) {
  echo "❌ No se encontraron tablas pivot\n";
} else {
  echo "✅ Se encontraron " . count($pivotCandidates) . " tablas pivot:\n\n";

  foreach ($pivotCandidates as $pivot) {
    $schema = str_replace('utamed.', '', $pivot['schema']);
    $pivotTable = $pivot['table'];
    $numFks = $pivot['num_fks'];

    echo "📌 {$schema}\\{$pivotTable}\n";
    echo "   " . ($pivot['is_pure_pivot'] ? '🔗 Pivot puro' : '📦 Pivot con datos') . "\n";
    echo "   FKs: {$numFks} " . ($numFks == 2 ? '(simple)' : '(múltiple)') . "\n";
    echo "   PK: [{$pivot['pk_columns']}]\n";
    echo "   FK cols: [{$pivot['fk_columns']}]\n";
    echo "   " . ($pivot['pk_is_fks'] ? '✅ PK = FKs' : '❌ PK ≠ FKs') . "\n";
    echo "   " . ($pivot['has_unique_on_fks'] ? '✅ UNIQUE en FKs' : '❌ No UNIQUE en FKs') . "\n";

    echo "   Conecta:\n";
    foreach ($pivot['fks'] as $idx => $fk) {
      $num = $idx + 1;
      echo "     FK{$num}: {$fk['table']} [{$fk['columns']}]\n";
    }

    if (!$pivot['is_pure_pivot']) {
      echo "   Columnas adicionales: " . implode(', ', $pivot['additional_columns']) . "\n";
    }

    // Mostrar configuración sugerida
    $fullTableName = $pivot['schema'] . '.' . $pivotTable;
    echo "   \n";
    echo "   💡 Para usar en generate_models.php:\n";
    echo "      '{$fullTableName}' => true,\n";

    echo "\n";
  }

  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
  echo "📝 RESUMEN POR TIPO:\n";

  $simple = array_filter($pivotCandidates, fn($p) => $p['num_fks'] == 2);
  $multiple = array_filter($pivotCandidates, fn($p) => $p['num_fks'] > 2);
  $pure = array_filter($pivotCandidates, fn($p) => $p['is_pure_pivot']);
  $withData = array_filter($pivotCandidates, fn($p) => !$p['is_pure_pivot']);

  echo "   🔗 Pivots simples (2 FKs): " . count($simple) . "\n";
  echo "   🔀 Pivots múltiples (3+ FKs): " . count($multiple) . "\n";
  echo "   ⚪ Pivots puros: " . count($pure) . "\n";
  echo "   📦 Pivots con datos: " . count($withData) . "\n";
}

echo "\n💡 CRITERIOS DE DETECCIÓN:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "• Tiene 2+ foreign keys\n";
echo "• Y cumple al menos uno:\n";
echo "  - PK compuesta por todas las FKs (pivot puro)\n";
echo "  - UNIQUE compuesto en todas las FKs (evita duplicados)\n\n";

echo "🎯 SOPORTE DE RELACIONES:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "• Pivots con 2 FKs → 2 relaciones belongsToMany (ida y vuelta)\n";
echo "• Pivots con 3 FKs → 6 relaciones belongsToMany (todas las combinaciones)\n";
echo "• Pivots con 4 FKs → 12 relaciones belongsToMany\n";
echo "• Configuración avanzada permite filtrar qué relaciones generar\n";
