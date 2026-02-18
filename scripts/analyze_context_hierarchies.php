<?php

/**
 * ANALIZADOR AUTOMÁTICO DE JERARQUÍAS DE CONTEXTO
 * 
 * Detecta relaciones BD que llevan a tablas con id_contexto
 * 
 * USO:
 * php scripts/analyze_context_hierarchies.php
 */

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/utils.printing.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// ==================================================================================
// CONFIGURACIÓN INICIAL
// ==================================================================================

$config = [
    'database' => 'utamed_1ra_fase',
    'schema_prefix' => 'administrativo,agenda,curso,usuario',
    'context_columns' => ['id_contexto'],
    'filter_prefix' => [  // Excluir vistas y tablas de enumerables
        'vw_',
        'estado_',
        'tipo_'
    ],
    'filter_tables' => [ // Vacío = analizar todos.
        // Tablas sin contexto ni relación a contexto
        "permiso",
        // Tablas con relación a contexto pero que en realidad no tienen contexto
        "contexto",
        "tipo_contexto",
        "usuario_permiso_especial",
        "usuario_rol_asignacion",
        "asignacion_rol_permiso",
        // Tablas de Laravel
        "cache",
        "cache_locks",
        "failed_jobs",
        "job_batches",
        "jobs",
        "migrations",
        "password_reset_tokens",
        "sessions"
    ],
    'global_context_tables' => [ // Tablas sin contexto pero modificables (contexto global)
        "asignatura",
        "rol",
        "usuario",
        "estudiante",
        "docente"
    ],
    'max_depth' => 5,
];

section("⚙️  CONFIGURACIÓN", [
    "Base de datos" => $config['database'],
    "Esquemas" => $config['schema_prefix'],
    "Columnas contexto" => implode(", ", $config['context_columns']),
]);

// ==================================================================================
// PASO 1: CARGAR ESTRUCTURA DE DATOS DE BD
// ==================================================================================

section("📊 CARGANDO ESTRUCTURA DE DATOS");

// Cargar todas las tablas
$schemas = explode(',', $config['schema_prefix']);
$placeholders = implode(',', array_fill(0, count($schemas), '?'));
$tables = DB::select("
    SELECT table_schema, table_name
    FROM information_schema.tables
    WHERE table_catalog = ? AND table_schema IN ($placeholders)
    ORDER BY table_schema, table_name
", array_merge([$config['database']], $schemas));

step(count($tables) . " tablas encontradas");

// Cargar columnas e identificar contextos
$dataStructure = [];
$tablesWithContext = [];
$filteredTables = []; // Rastrear tablas filtradas

foreach ($tables as $t) {
    // Filtrar por prefijo (vistas, etc) o por nombre específico
    [$shouldFilter, $reason] = shouldFilterTable(
        $t->table_name,
        $config['filter_prefix'],
        $config['filter_tables']
    );

    if (
        $shouldFilter === true
    ) {
        $filteredTables[] = "{$t->table_schema}.{$t->table_name}: {$reason}";
        continue;
    }

    $fullName = "{$t->table_schema}.{$t->table_name}";

    // Obtener columnas de esta tabla
    $columns = DB::select("
        SELECT column_name, data_type
        FROM information_schema.columns
        WHERE table_schema = ? AND table_name = ?
    ", [$t->table_schema, $t->table_name]);

    $columnMap = array_column((array) $columns, 'data_type', 'column_name');
    $contextCols = array_intersect_key($columnMap, array_flip($config['context_columns']));

    $dataStructure[$fullName] = [
        'schema' => $t->table_schema,
        'table' => $t->table_name,
        'columns' => $columnMap,
        'context_columns' => array_keys($contextCols),
        'fks' => [], // Se llena después
    ];

    if (!empty($contextCols)) {
        $tablesWithContext[$fullName] = array_keys($contextCols)[0]; // Primera columna contexto
    }
}

step("Tablas con contexto: " . count($tablesWithContext));

// Cargar Foreign Keys
step("Cargando relaciones...");

foreach ($dataStructure as $fullName => &$tableData) {
    $fks = DB::select("
        SELECT 
            string_agg(att.attname, ',' ORDER BY u.ordinality) AS columns,
            fn.nspname AS target_schema,
            fc.relname AS target_table
        FROM pg_constraint con
        JOIN pg_class c ON con.conrelid = c.oid
        JOIN pg_namespace n ON c.relnamespace = n.oid
        JOIN LATERAL unnest(con.conkey) WITH ORDINALITY AS u(attnum, ordinality) ON true
        JOIN pg_attribute att ON att.attrelid = c.oid AND att.attnum = u.attnum
        JOIN pg_class fc ON con.confrelid = fc.oid
        JOIN pg_namespace fn ON fc.relnamespace = fn.oid
        WHERE con.contype = 'f' AND c.relname = ? AND n.nspname = ?
        GROUP BY con.oid, fn.nspname, fc.relname
    ", [$tableData['table'], $tableData['schema']]);

    foreach ($fks as $fk) {
        $targetName = "{$fk->target_schema}.{$fk->target_table}";
        $tableData['fks'][$fk->columns] = $targetName;
    }
}
unset($tableData, $fk); // Romper referencias

step("Foreign Keys cargadas: " . array_sum(array_map('count', array_column($dataStructure, 'fks'))));

// ==================================================================================
// PASO 2: ALGORITMO DE DETECCIÓN
// ==================================================================================

section("🔍 DETECTANDO JERARQUÍAS");

function detectContextPath($table, $dataStructure, $tablesWithContext, $maxDepth)
{
    /**
     * Busca cadena de FKs que lleva de $table a una tabla con contexto
     * Retorna: ['table1', 'table2', ...] o null si no encuentra
     */

    $queue = [];
    $visited = [$table];

    // Inicializar con FKs directos
    if (!empty($dataStructure[$table]['fks'])) {
        foreach ($dataStructure[$table]['fks'] as $target) {
            $parts = explode('.', $target);
            $targetTableName = end($parts); // Extraer nombre de tabla
            $queue[] = [
                'current' => $target,
                'path' => [$targetTableName],
                'visited' => [$table, $target],
            ];
        }
    }

    $foundPaths = [];

    while (!empty($queue)) {
        $node = array_shift($queue);

        // Encontró tabla con contexto
        if (isset($tablesWithContext[$node['current']])) {
            $foundPaths[] = $node['path'];
            continue;
        }

        // No explorar más allá del máximo
        if (count($node['path']) >= $maxDepth) {
            continue;
        }

        // Si no existe en estructura (tabla ajena), ignorar
        if (!isset($dataStructure[$node['current']])) {
            continue;
        }

        // Explorar siguiente nivel
        foreach ($dataStructure[$node['current']]['fks'] as $nextTarget) {
            if (!in_array($nextTarget, $node['visited'])) {
                $newVisited = $node['visited'];
                $newVisited[] = $nextTarget;

                $nextParts = explode('.', $nextTarget);
                $nextTableName = end($nextParts); // Extraer nombre de tabla

                $queue[] = [
                    'current' => $nextTarget,
                    'path' => array_merge($node['path'], [$nextTableName]),
                    'visited' => $newVisited,
                ];
            }
        }
    }

    if (empty($foundPaths)) {
        return null;
    }

    // Retornar TODOS los caminos encontrados (ordenados por longitud)
    usort($foundPaths, fn($a, $b) => count($a) <=> count($b));
    return $foundPaths;
}

// Ejecutar detección
$results = [
    'direct' => [],
    'hierarchical' => [],
    'global' => [],
    'complex' => [],
];

foreach ($dataStructure as $fullName => $tableData) {
    $tableName = $tableData['table'];

    // Verificar si está en global_context_tables
    $isGlobal = false;
    foreach ($config['global_context_tables'] as $globalTable) {
        if (strtolower($tableName) === strtolower($globalTable)) {
            $isGlobal = true;
            break;
        }
    }

    if ($isGlobal) {
        // Contexto global (sin jerarquía)
        $results['global'][$fullName] = [
            'name' => strtolower($tableName),
        ];
    } elseif (isset($tablesWithContext[$fullName])) {
        // Tabla con contexto directo
        $contextCol = $tablesWithContext[$fullName];
        $results['direct'][$fullName] = [
            'context_column' => $contextCol,
            'name' => strtolower($tableData['table']),
        ];
    } else {
        // Buscar jerarquía
        $paths = detectContextPath(
            $fullName,
            $dataStructure,
            $tablesWithContext,
            $config['max_depth']
        );

        if ($paths) {
            $results['hierarchical'][$fullName] = [
                'paths' => $paths,  // Array de todos los caminos posibles
                'name' => strtolower($tableData['table']),
            ];
        } else {
            $results['complex'][$fullName] = [
                '// TODO: revisar manualmente' => null,
            ];
        }
    }
}

step("Directas: " . count($results['direct']));
step("Jerárquicas: " . count($results['hierarchical']));
step("Complejas: " . count($results['complex']));

// ==================================================================================
// PASO 3: GENERAR OUTPUT
// ==================================================================================

section("📝 CONFIGURACIÓN GENERADA");

// Obtener primera columna de contexto (asumiendo solo una)
$contextFilterColumn = current($config['context_columns']);

$output = outputConfig($results, $contextFilterColumn, $filteredTables);

echo $output;

// Procesar argumentos de línea de comandos
$outputFile = null;
foreach ($argv as $i => $arg) {
    if (($arg === '--output-file' || $arg === '-o') && isset($argv[$i + 1])) {
        $outputFile = $argv[$i + 1];
        break;
    }
}

// Guardar a archivo si se especificó
if ($outputFile) {
    file_put_contents($outputFile, $output);
    echo "\n✅ Guardado en: $outputFile\n";
}

// ==================================================================================
// FUNCIONES
// ==================================================================================


/**
 * Filtra tablas según prefijos o nombres específicos
 * @return array [bool $shouldFilter, string $reason]
 */
function shouldFilterTable($tableName, $filterPrefixes = [], $filterTables = []): array
{
    /**
     * Retorna true si la tabla debe ser excluida del análisis
     * 
     * Chequea:
     * 1. Por prefijo (ej: vw_* para vistas)
     * 2. Por nombre exacto (ej: Contexto, Usuario_Rol_Asignación)
     */

    $FILTERED_BY_PREFIX = "Prefijo filtrado";
    $FILTERED_BY_NAME = "Tabla filtrada";

    // Chequear por prefijo
    foreach ($filterPrefixes as $prefix) {
        if (str_starts_with(strtolower($tableName), strtolower($prefix))) {
            return [true, $FILTERED_BY_PREFIX];
        }
    }

    // Chequear por nombre exacto (case-insensitive)
    foreach ($filterTables as $filterName) {
        if (strtolower($tableName) === strtolower($filterName)) {
            return [true, $FILTERED_BY_NAME];
        }
    }

    return [false, null];
}

function outputConfig($results, $contextColumnName, $filteredTables = [])
{
    /**
     * Genera configuración PHP usando heredoc (formato limpio y legible)
     */

    // Generar sección direct
    $directLines = [];
    foreach ($results['direct'] as $table => $config) {
        $directLines[] = "        '$table' => '{$config['name']}',";
    }
    $directBlock = implode("\n", $directLines);

    // Generar sección hierarchical
    $hierarchicalLines = [];
    foreach ($results['hierarchical'] as $table => $config) {
        // SIEMPRE usar doble array para consistencia
        $paths = $config['paths'];
        $pathStrings = [];
        foreach ($paths as $path) {
            $pathStr = "['" . implode("', '", $path) . "']";
            $pathStrings[] = $pathStr;
        }
        $hierarchicalLines[] = "        '$table' => [";
        foreach ($pathStrings as $i => $pathStr) {
            $comma = ($i < count($pathStrings) - 1) ? ',' : '';
            $hierarchicalLines[] = "            " . $pathStr . $comma;
        }
        $hierarchicalLines[] = "        ],";
    }
    $hierarchicalBlock = implode("\n", $hierarchicalLines);

    // Generar sección global
    $globalLines = [];
    foreach ($results['global'] as $table => $config) {
        $globalLines[] = "        '$table' => '{$config['name']}',";
    }
    $globalBlock = implode("\n", $globalLines);

    // Generar sección complex
    $complexLines = [];
    foreach ($results['complex'] as $table => $_) {
        $complexLines[] = "        // '$table', // TODO: revisar manualmente";
    }
    $complexBlock = implode("\n", $complexLines);

    // Generar sección filtered (tablas excluidas)
    $filteredBlock = '';
    if (!empty($filteredTables)) {
        $filteredComment = "// TABLAS FILTRADAS (Excluidas por prefijo o configuración):\n";
        foreach ($filteredTables as $table) {
            $filteredComment .= "// - $table\n";
        }
        $filteredBlock = "\n/*\n" . $filteredComment . "*/\n";
    }

    // Usar heredoc para generar el código PHP limpio
    $php = <<<PHP
<?php

// GENERADO AUTOMÁTICAMENTE - REVISAR Y AJUSTAR SI ES NECESARIO

\$contextHierarchies = [
    'context_column' => '$contextColumnName',
    'direct' => [
$directBlock
    ],
    'hierarchical' => [
$hierarchicalBlock
    ],
    'global' => [
$globalBlock
    ],
    'complex' => [
$complexBlock
    ],
];
$filteredBlock
PHP;

    return $php;
}
