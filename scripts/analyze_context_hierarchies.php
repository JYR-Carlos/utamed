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
    'schema_prefix' => 'administrativo,agenda,curso,usuario,operaciones,auditoria',
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
        "sessions",
        // Auditoria
        "programa_historial",
    ],
    'global_context_tables' => [ // Tablas sin contexto pero modificables (contexto global)
        "asignatura",
        "rol",
        "usuario",
        "estudiante",
        "docente",
        "archivo",
    ],
    'max_depth' => 5,

    // Relaciones de contexto padre cuyo camino es indirecto (sin FK directa entre tablas directas).
    // Clave: schema.tabla hijo (tabla directa). Valor: array con el camino completo hasta el padre.
    // El último elemento del array es la tabla directa padre final.
    // Se fusionan con las FK autodetectadas en PASO 1.5; las auto-detectadas sobreescriben en conflicto.
    'manual_parent_context' => [
        'curso.curso' => [
            'administrativo.asignacion_plan',
            'administrativo.plan',
            'administrativo.carrera',       // contexto padre final
        ],
        'agenda.actividad' => [
            'curso.seccion',
            'curso.curso',                  // contexto padre final
        ],
    ],
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
// PASO 1.5: DETECTAR FKs ENTRE TABLAS DIRECTAS (JERARQUÍA AUTODETECTADA)
// ==================================================================================

step("Detectando relaciones entre tablas directas...");

// Auto-detectado: FK directa entre dos tablas directas
// Mapa: childFullName => parentFullName (string)
$autoDetectedParentContextEntries = [];
foreach ($tablesWithContext as $childFull => $_) {
    foreach ($dataStructure[$childFull]['fks'] ?? [] as $col => $targetFull) {
        if (isset($tablesWithContext[$targetFull]) && $targetFull !== $childFull) {
            $childLabel = $dataStructure[$childFull]['table'];
            $parentLabel = $dataStructure[$targetFull]['table'];
            $autoDetectedParentContextEntries[$childFull] = $targetFull;
        }
    }
}

// Construir mapa combinado: childFullName => string|array (path completo hasta padre final)
// Las entradas auto sobreescriben las manuales si coinciden en la misma tabla hijo.
$parentContextMap = array_merge($config['manual_parent_context'], $autoDetectedParentContextEntries);

$autoCount = count($autoDetectedParentContextEntries);
$manualCount = count($config['manual_parent_context']);
step("parent_context_map: " . count($parentContextMap) . " entradas ({$autoCount} auto-detectadas, {$manualCount} manuales)");

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
            $queue[] = [
                'current' => $target,
                'path' => [$target], // nombre completo schema.tabla
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

                $queue[] = [
                    'current' => $nextTarget,
                    'path' => array_merge($node['path'], [$nextTarget]), // nombre completo
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

$output = outputConfig($results, $contextFilterColumn, $filteredTables, $parentContextMap, $autoDetectedParentContextEntries);

echo $output;

// Respaldar archivo anterior si existe, luego generar el nuevo
$filePath = __DIR__ . '/generated_context_hierarchies.php';
$backup = backupIfExists($filePath);

if ($backup) {
    echo "\n✅ Respaldo anterior guardado en: $backup";
}

file_put_contents($filePath, $output);
echo "\n✅ Nuevo archivo generado en: $filePath\n";

// ==================================================================================
// FUNCIONES
// ==================================================================================

/**
 * Respalda el archivo si existe, agregando sufijo -OLD-<timestamp>
 * @return string|null Ruta del backup creado, o null si el archivo no existe
 */
function backupIfExists(string $filePath): ?string
{
    if (!file_exists($filePath)) {
        return null;
    }

    // Extraer extensión y nombre base
    $pathInfo = pathinfo($filePath);
    $dir = $pathInfo['dirname'];
    $filename = $pathInfo['filename'];
    $extension = $pathInfo['extension'] ?? '';

    // Generar timestamp con zona UTC
    $timestamp = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d_H-i-s');

    // Construir ruta del respaldo
    $backupPath = $dir . '/' . $filename . '-OLD-' . $timestamp . ($extension ? '.' . $extension : '');

    // Renombrar archivo original
    rename($filePath, $backupPath);

    return $backupPath;
}

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

function outputConfig($results, $contextColumnName, $filteredTables = [], $parentContextMap = [], $autoDetectedMap = [])
{
    /**
     * Genera configuración PHP usando heredoc (formato limpio y legible)
     *
     * $parentContextMap: [schema.tabla => null|string|array]
     *   null   → raíz (no tiene padre)
     *   string → FK directa al padre (schema.tabla del padre)
     *   array  → ruta indirecta [intermedio1, intermedio2, ..., padreDirecto]
     * $autoDetectedMap: [schema.tabla => schema.tabla] — sólo FK directas auto-detectadas
     */

    $tab = '    '; // 4 espacios para indentación

    // ── Sección 'direct' (parent: null | 'schema.tabla' | ['s.t',...] ) ───────
    $directLines = [];
    foreach ($results['direct'] as $table => $cfg) {
        $typeName = $cfg['name'];
        if (!array_key_exists($table, $parentContextMap)) {
            // Raíz (no hay entrada en el mapa → sin padre)
            $directLines[] = "{$tab}{$tab}'$table' => [";
            $directLines[] = "{$tab}{$tab}{$tab}'contextTypeName' => '$typeName',";
            $directLines[] = "{$tab}{$tab}{$tab}'parent' => null, // raíz";
            $directLines[] = "{$tab}{$tab}],";
        } elseif (is_array($parentContextMap[$table])) {
            // Ruta indirecta manual
            $pathItems = "'" . implode("', '", $parentContextMap[$table]) . "'";
            $directLines[] = "{$tab}{$tab}'$table' => [";
            $directLines[] = "{$tab}{$tab}{$tab}'contextTypeName' => '$typeName',";
            $directLines[] = "{$tab}{$tab}{$tab}'parent' => [{$pathItems}], // manual (ruta indirecta)";
            $directLines[] = "{$tab}{$tab}],";
        } else {
            // FK directa auto-detectada
            $parentFull = $parentContextMap[$table];
            $directLines[] = "{$tab}{$tab}'$table' => [";
            $directLines[] = "{$tab}{$tab}{$tab}'contextTypeName' => '$typeName',";
            $directLines[] = "{$tab}{$tab}{$tab}'parent' => '$parentFull', // auto (FK directa)";
            $directLines[] = "{$tab}{$tab}],";
        }
    }
    $directBlock = implode("\n", $directLines);

    // ── Sección 'hierarchical' ────────────────────────────────────────────────
    $hierarchicalLines = [];
    foreach ($results['hierarchical'] as $table => $config) {
        $paths = $config['paths'];
        $hierarchicalLines[] = "{$tab}{$tab}'$table' => [";
        foreach ($paths as $i => $path) {
            $pathStr = "['" . implode("', '", $path) . "']";
            $comma = ($i < count($paths) - 1) ? ',' : '';
            $hierarchicalLines[] = "{$tab}{$tab}{$tab}" . $pathStr . $comma;
        }
        $hierarchicalLines[] = "{$tab}{$tab}],";
    }
    $hierarchicalBlock = implode("\n", $hierarchicalLines);

    // Generar sección global (lista indexada de schema.tabla)
    $globalLines = [];
    foreach ($results['global'] as $table => $config) {
        $globalLines[] = "{$tab}{$tab}'$table',";
    }
    $globalBlock = implode("\n", $globalLines);

    // Generar sección complex
    $complexLines = [];
    foreach ($results['complex'] as $table => $_) {
        $complexLines[] = "{$tab}{$tab}// '$table', // TODO: revisar manualmente";
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
// Para regenerar: php scripts/analyze_context_hierarchies.php

return [
{$tab}'context_column' => '$contextColumnName',

{$tab}// Tablas que poseen id_contexto directamente.
{$tab}// 'parent': null (raíz), string (FK directa, schema.tabla), o array (ruta indirecta hasta padre).
{$tab}// 'contextTypeName': nombre corto del tipo de contexto (usarse en lugar de extraer de schema.tabla).
{$tab}'direct' => [
$directBlock
{$tab}],

{$tab}// Tablas sin id_contexto propias que aplican a nivel global.
{$tab}// Lista de nombres completos schema.tabla.
{$tab}'global' => [
$globalBlock
{$tab}],

{$tab}// Tablas sin id_contexto propio que llegan a un contexto vía FK chain.
{$tab}// Los pasos de cada camino usan nombres completos schema.tabla.
{$tab}'hierarchical' => [
$hierarchicalBlock
{$tab}],

{$tab}'complex' => [
$complexBlock
{$tab}],
];
$filteredBlock
PHP;

    return $php;
}
