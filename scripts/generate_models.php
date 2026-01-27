<?php

/**
 * ==================================================================================
 * GENERADOR DE MODELOS ELOQUENT PARA POSTGRESQL CON ESQUEMAS ANIDADOS
 * ==================================================================================
 * 
 * FUNCIONALIDADES:
 * 
 * 1. DETECCIÓN AUTOMÁTICA DE TABLAS
 *    - Escanea múltiples esquemas PostgreSQL (utamed.Administrativo, utamed.Usuario, etc.)
 *    - Soporta esquemas con notación de puntos en sus nombres
 *    - Filtra por catálogo específico (utamed_1ra_fase)
 * 
 * 2. PATRÓN BASE MODELS
 *    - Implementa patrón Base Model / Extended Model 
 *    - Genera clases Base abstractas que se regeneran sin perder personalizaciones
 *    - Genera clases extendidas que preservan código personalizado
 *    - Separación clara entre código generado y código manual
 * 
 * 3. DETECCIÓN DE COLUMNAS Y TIPOS
 *    - Extrae todas las columnas con sus tipos de datos
 *    - Genera automáticamente $fillable excluyendo PK y timestamps (configurable)
 *    - Detecta y configura campos booleanos (esta_activo)
 * 
 * 4. DETECCIÓN DE CLAVES PRIMARIAS
 *    - Usa catálogo pg_index de PostgreSQL para detectar PKs
 *    - Soporta esquemas con puntos mediante casting a regclass
 *    - Configura automáticamente $primaryKey en modelos
 * 
 * 5. DETECCIÓN DE FOREIGN KEYS (belongsTo)
 *    - Usa catálogo pg_constraint para detectar FKs con precisión
 *    - Soporta múltiples FKs a la misma tabla (genera métodos únicos)
 *    - Genera relaciones belongsTo automáticamente
 * 
 * 6. DETECCIÓN DE RELACIONES INVERSAS (hasMany/hasOne)
 *    - Pre-analiza todas las FKs del sistema completo
 *    - Detecta qué tablas apuntan a cada tabla
 *    - Genera automáticamente relaciones hasMany inversas
 *    - Evita duplicados en nombres de métodos
 * 
 * 7. CONFIGURACIÓN DE TIMESTAMPS
 *    - Detecta si existe campo fecha_creacion
 *    - Configura CREATED_AT personalizado
 *    - Desactiva UPDATED_AT (no existe en el esquema)
 * 
 * 8. MANEJO DE SOFT DELETES
 *    - Permite la configuración automática de soft deletes
 *    - Actualmente NO usa SoftDeletes trait (atributo esta_activo es boolean)
 *    - Genera scope active() para filtrar registros activos
 *    - Trata esta_activo como flag booleano
 * 
 * 9. ORGANIZACIÓN DE ARCHIVOS
 *    - Base Models en: app/Models/Base/{Schema}/Base{ClassName}.php
 *    - Extended Models en: app/Models/{Schema}/{ClassName}.php
 *    - Creación automática de directorios si no existen
 * 
 * 10. PRESERVACIÓN DE PERSONALIZACIONES
 *     - Los modelos extendidos solo se crean si NO existen
 *     - Permite agregar métodos, scopes, accessors sin perder cambios
 *     - Advertencias claras en comentarios sobre qué NO editar
 * 
 * ==================================================================================
 * EJECUCIÓN: php generate_models.php
 * ==================================================================================
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ==================================================================================
// FUNCIÓN: pluralizeSpanish - Pluralización en español
// ==================================================================================
// Convierte palabras singulares al plural en español
// Reglas aplicadas:
// - Terminación en vocal (a, e, i, o, u): agregar -s
// - Terminación con tilde en ó/í: normalizar y aplicar regla
// - Terminación en -ción: cambiar a -ciones
// - Terminación en -sión: cambiar a -siones
// - Terminación en consonante: agregar -es
function pluralizeSpanish($word)
{
  // Normalizar: remover acentos para la lógica
  $normalized = str_replace(
    ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'],
    ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'],
    $word
  );

  $normalized = strtolower($normalized);
  $wordLower = strtolower($word);

  $lastChar = substr($normalized, -1);
  $last2Chars = substr($normalized, -2);
  $last3Chars = substr($normalized, -3);
  $last4Chars = substr($normalized, -4);

  // Terminación en -ción → -ciones (con o sin tilde)
  if ($last4Chars === 'cion' || substr($wordLower, -4) === 'ción') {
    return substr($word, 0, -4) . 'ciones';
  }

  // Terminación en -sión → -siones
  if ($last4Chars === 'sion' || substr($wordLower, -4) === 'sión') {
    return substr($word, 0, -4) . 'siones';
  }

  // Terminación en -z → -ces
  if ($lastChar === 'z') {
    return substr($word, 0, -1) . 'ces';
  }

  // Vocales (a, e, i, o, u) y vocales con tilde → agregar -s
  $lastCharOriginal = substr($wordLower, -1);
  if (
    in_array($lastChar, ['a', 'e', 'i', 'o', 'u']) ||
    in_array($lastCharOriginal, ['á', 'é', 'í', 'ó', 'ú'])
  ) {
    return $word . 's';
  }

  // Consonantes → agregar -es
  return $word . 'es';
}

// ==================================================================================
// CONFIGURACIÓN MANUAL DE TABLAS PIVOT
// ==================================================================================
//
// Define aquí las tablas que deben tratarse como pivots para relaciones belongsToMany
// El generador detectará automáticamente TODAS las FKs de cada tabla pivot especificada
//
// FORMATO SIMPLE (auto-detecta todas las FKs con nombres automáticos):
// 'utamed.Schema.NombreTabla' => true
//
// FORMATO AVANZADO (control fino sobre relaciones):
// 'utamed.Schema.NombreTabla' => [
//   'tables' => ['tabla1', 'tabla2'],           // Solo genera relaciones con estas tablas
//   'exclude_tables' => ['tabla3'],             // Excluye estas tablas de las relaciones
//   'auto_suffix' => true,                      // Agrega sufijo del pivot automáticamente (default: false)
//   'relation_names' => [                       // Renombrado personalizado de relaciones
//     'TablaOrigen' => [
//       'TablaDestino' => 'nombre_metodo',
//     ],
//   ],
// ]
//
// CASOS DE USO:
// - Pivots con 2 FKs (muchos-a-muchos simple)
//   Ejemplo: Curso ↔ Estudiante
//   Genera: Curso->estudiantes(), Estudiante->cursos()
//
// - Pivots con 3+ FKs (muchos-a-muchos múltiple)
//   Ejemplo: Usuario ↔ Rol ↔ Contexto
//   Genera: Usuario->roles(), Usuario->contextos(), Rol->usuarios(), etc.
//
// - Pivots con nombres conflictivos (evita duplicados con hasMany/hasOne)
//   Ejemplo: Usuario tiene hasMany(Rol) Y belongsToMany(Rol)
//   Usa 'auto_suffix' o 'relation_names' para diferenciar
//
// EJEMPLOS:
//
// // 1. Pivot simple (auto-detecta todas las FKs):
// 'utamed.Curso.Inscripcion_Curso' => true,
//
// // 2. Pivot con sufijo automático (evita conflictos):
// 'utamed.Usuario.Usuario_Rol_Asignación' => [
//   'auto_suffix' => true,  // Genera: roles_ura(), contextos_ura()
// ],
//
// // 3. Pivot con renombrado personalizado:
// 'utamed.Usuario.Usuario_Permiso_Especial' => [
//   'relation_names' => [
//     'Usuario' => [
//       'Contexto' => 'contextos_upe',
//       'Permiso' => 'permisos_especiales',
//     ],
//   ],
// ],
//
// // 4. Pivot triple solo algunas relaciones:
// 'utamed.Usuario.Usuario_Rol_Contexto' => [
//   'tables' => ['Usuario', 'Rol'],  // Ignora Contexto
//   'auto_suffix' => true,
// ],
//
$manualPivotTables = [
  // Ejemplos con configuración mejorada:
  'utamed.Administrativo.Asignacion_Plan' => true,
  'utamed.Curso.Inscripcion_Curso' => true,
  'utamed.Curso.Inscripcion_Seccion' => true,
  'utamed.Agenda.Actividad_Asignada' => true,
  'utamed.Agenda.Asignado_Actividad' => true,

  // Usuario con renombrado para evitar conflictos
  'utamed.Usuario.Usuario_Rol_Asignación' => [
    'auto_suffix' => true,  // Genera: rolesUra(), contextosUra(), usuariosUra()
  ],

  'utamed.Usuario.Usuario_Permiso_Especial' => [
    'auto_suffix' => true,  // Genera: permisosUpe(), contextosUpe()
  ],

  'utamed.Usuario.Asignación_Rol_Permiso' => true,
];

// ==================================================================================
// CONFIGURACIÓN DE RENOMBRADO DE RELACIONES (belongsTo + hasMany/hasOne)
// ==================================================================================
//
// UNIFICA EL RENOMBRADO DE TODOS LOS TIPOS DE RELACIONES EN UN SOLO LUGAR
//
// FORMATO:
// 'utamed.{Schema}.{Tabla}' => [
//   '_self' => [                           ← belongsTo: Relaciones EN esta tabla
//     '{columna_fk}' => '{nuevo_nombre}',
//   ],
//   '{OtraTabla}' => [                     ← hasMany/hasOne: Relaciones DESDE otra tabla
//     '{columna_fk}' => '{nuevo_nombre}',
//   ],
// ]
//
// CLAVE ESPECIAL '_self':
// - Renombra métodos belongsTo en la MISMA tabla que tiene las FKs
// - Útil cuando tienes múltiples FKs a la misma tabla (usuario(), usuario1(), etc.)
//
// EJEMPLO COMPLETO - Usuario_Rol_Asignación:
//
// Problema:
//   BaseUsuarioRolAsignación.php (belongsTo):
//     - usuario()   (FK: id_usuario)              ← OK
//     - usuario1()  (FK: asignado_por)            ← ❌ Confuso
//
//   BaseUsuario.php (hasMany):
//     - usuarioRolAsignaciónes()  (FK: id_usuario)      ← ❌ Tilde mal procesada
//     - usuarioRolAsignaciónes1() (FK: asignado_por)    ← ❌ Duplicado
//
// Solución:
//   'utamed.Usuario.Usuario_Rol_Asignación' => [
//     '_self' => [
//       'asignado_por' => 'asignadoPor',  // belongsTo: usuario1() → asignadoPor()
//     ],
//     'Usuario' => [
//       'id_usuario' => 'asignacionesRolRecibidas',      // hasMany en Usuario
//       'asignado_por' => 'asignacionesRolRealizadas',   // hasMany en Usuario
//     ],
//   ],
//
// Resultado:
//   BaseUsuarioRolAsignación.php:
//     - usuario()      (FK: id_usuario)     ← Sin cambios
//     - asignadoPor()  (FK: asignado_por)   ← ✅ belongsTo renombrado
//
//   BaseUsuario.php:
//     - asignacionesRolRecibidas()    ← ✅ hasMany renombrado
//     - asignacionesRolRealizadas()   ← ✅ hasMany renombrado
//
// MÁS EJEMPLOS:
//
// // Rol creado por Usuario
// 'utamed.Usuario.Rol' => [
//   'Usuario' => [
//     'id_usuario_autor' => 'rolesCreados',  // hasMany: evita conflicto con belongsToMany
//   ],
// ],
//
// // Mensaje con remitente y destinatario
// 'utamed.Sistema.Mensaje' => [
//   '_self' => [
//     'id_usuario_remitente' => 'remitente',      // belongsTo
//     'id_usuario_destinatario' => 'destinatario', // belongsTo
//   ],
//   'Usuario' => [
//     'id_usuario_remitente' => 'mensajesEnviados',    // hasMany
//     'id_usuario_destinatario' => 'mensajesRecibidos', // hasMany
//   ],
// ],
//
// 📖 Ver guía completa: GUIA_RELACIONES_INVERSAS.md
// 🔍 Ver configuración actual: php show_pivot_config.php
//
$relationNames = [
  // Usuario crea Roles (evitar conflicto con belongsToMany)
  'utamed.Usuario.Rol' => [
    'Usuario' => [
      'id_usuario_autor' => 'rolesCreados',  // hasMany en Usuario
    ],
  ],

  // Usuario_Rol_Asignación: belongsTo + hasMany combinados
  'utamed.Usuario.Usuario_Rol_Asignación' => [
    '_self' => [
      'asignado_por' => 'asignadoPor',  // belongsTo: usuario1() → asignadoPor()
    ],
    'Usuario' => [
      'id_usuario' => 'asignacionesRolRecibidas',      // hasMany en Usuario
      'asignado_por' => 'asignacionesRolRealizadas',   // hasMany en Usuario
    ],
  ],

  // Usuario_Permiso_Especial
  'utamed.Usuario.Usuario_Permiso_Especial' => [
    'Usuario' => [
      'id_usuario' => 'permisosEspecialesAsignados',
    ],
  ],
];

echo "🚀 Generando modelos desde PostgreSQL...\n\n";

// ==================================================================================
// PASO 1: DETECCIÓN DE TABLAS EN ESQUEMAS POSTGRESQL
// ==================================================================================
// 
// OBJETIVO: Obtener todas las tablas de los esquemas utamed.* del catálogo especificado
// 
// FUNCIONAMIENTO:
// - Consulta information_schema.tables del catálogo 'utamed_1ra_fase'
// - Filtra solo esquemas que empiezan con 'utamed.' (esquemas anidados con puntos)
// - Ordena por schema y nombre de tabla para procesamiento consistente
// - Retorna: table_schema (ej: 'utamed.Administrativo'), table_name (ej: 'facultad')
//
// NOTA: Esquemas con puntos en PostgreSQL requieren comillas dobles al referenciarlos
// ==================================================================================

// Obtener tablas
$tables = DB::select("
    SELECT table_schema, table_name
    FROM information_schema.tables
    WHERE table_catalog = 'utamed_1ra_fase'
    AND table_schema LIKE 'utamed.%'
    ORDER BY table_schema, table_name
");

echo "Encontradas " . count($tables) . " tablas\n\n";

// ==================================================================================
// PASO 2: PRE-ANÁLISIS DE RELACIONES INVERSAS (hasMany/hasOne)
// ==================================================================================
//
// OBJETIVO: Construir un índice global de todas las Foreign Keys para detectar
//           relaciones inversas antes de generar cada modelo
//
// FUNCIONAMIENTO:
// 1. Recorre TODAS las tablas del sistema
// 2. Para cada tabla, consulta sus Foreign Keys usando pg_constraint (catálogo PostgreSQL)
// 3. Construye un array indexado por tabla destino: $allForeignKeys['schema.tabla'] = [...]
// 4. Cada entrada contiene: tabla origen, columna origen, columna destino
//
// CONSULTA pg_constraint:
// - pg_constraint: Catálogo de restricciones (FKs, PKs, checks, etc.)
// - pg_class: Catálogo de tablas/índices
// - pg_namespace: Catálogo de esquemas
// - pg_attribute: Catálogo de columnas
// - contype = 'f': Solo Foreign Keys
// - conkey/confkey: Arrays de columnas locales/remotas
//
// RESULTADO: $allForeignKeys['utamed.Administrativo.facultad'] = [
//   ['source_table' => 'departamento', 'source_column' => 'id_facultad', ...]
// ]
//
// USO POSTERIOR: Al generar facultad.php, se detecta que departamento apunta a ella
//                y se genera automáticamente: public function departamentos() { hasMany(...) }
// ==================================================================================

// Pre-cargar todas las FK para detectar relaciones inversas
echo "📊 Analizando relaciones inversas...\n";
$allForeignKeys = [];
foreach ($tables as $t) {
  $fks = DB::select("
        SELECT
            string_agg(att.attname, ',' ORDER BY ordinality) AS column_names,
            min(fn.nspname) AS foreign_table_schema,
            min(fc.relname) AS foreign_table_name,
            string_agg(fatt.attname, ',' ORDER BY ordinality) AS foreign_column_names,
            min(c.relname) as source_table,
            con.oid as constraint_oid
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
        GROUP BY con.oid
    ", [$t->table_name, $t->table_schema]);

  foreach ($fks as $fk) {
    // Detectar si las columnas FK tienen índice UNIQUE (indica relación 1:1)
    // Usa unnest() para comparar exactamente las columnas del índice con las de la FK
    $hasUniqueIndex = DB::select("
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
    ", [$t->table_schema, $t->table_name, $fk->column_names]);

    $isUnique = count($hasUniqueIndex) > 0;

    $foreignKey = $fk->foreign_table_schema . '.' . $fk->foreign_table_name;
    if (!isset($allForeignKeys[$foreignKey])) {
      $allForeignKeys[$foreignKey] = [];
    }
    $allForeignKeys[$foreignKey][] = [
      'source_schema' => $t->table_schema,
      'source_table' => $t->table_name,
      'source_columns' => $fk->column_names,
      'target_columns' => $fk->foreign_column_names,
      'is_unique' => $isUnique, // Indica si es relación 1:1
    ];
  }
}
echo "✓ Relaciones inversas analizadas\n\n";

// ==================================================================================
// PASO 2.5: CARGAR TABLAS PIVOT (belongsToMany)
// ==================================================================================
//
// OBJETIVO: Procesar tablas pivot definidas en $manualPivotTables
//
// CONFIGURACIÓN:
// - Define tablas pivot en $manualPivotTables al inicio del script
// - Solo pivots con 2 FKs (muchos-a-muchos simple)
// - Formato: 'utamed.Schema.Tabla' => true
//
// RESULTADO: $pivotTables['schema.pivot_table'] = [
//   'fk1' => ['schema' => ..., 'table' => ..., 'columns' => ...],
//   'fk2' => ['schema' => ..., 'table' => ..., 'columns' => ...]
// ]
//
// NOTA: Pivots triples (3+ FKs) requieren lógica personalizada manual
// ==================================================================================

echo "🔗 Cargando tablas pivot...\n";
$pivotTables = [];

foreach ($tables as $t) {
  $pivotKey = $t->table_schema . '.' . $t->table_name;

  // Solo procesar si está en la configuración manual
  if (isset($manualPivotTables[$pivotKey])) {
    $pivotConfig = $manualPivotTables[$pivotKey];

    // Obtener TODAS las FKs de la tabla
    $fks = DB::select("
        SELECT
            con.oid,
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
        GROUP BY con.oid, fn.nspname, fc.relname
    ", [$t->table_name, $t->table_schema]);

    if (count($fks) >= 2) {
      // Convertir FKs a formato estructurado
      $fkList = [];
      foreach ($fks as $idx => $fk) {
        $fkList[] = [
          'index' => $idx,
          'schema' => $fk->foreign_table_schema,
          'table' => $fk->foreign_table_name,
          'local_columns' => $fk->column_names,
          'foreign_columns' => $fk->foreign_column_names,
        ];
      }

      // Aplicar filtros de configuración si existen
      if (is_array($pivotConfig)) {
        // Filtrar por tablas específicas si están definidas
        if (isset($pivotConfig['tables']) && is_array($pivotConfig['tables'])) {
          $allowedTables = array_map('strtolower', $pivotConfig['tables']);
          $fkList = array_filter($fkList, function ($fk) use ($allowedTables) {
            return in_array(strtolower($fk['table']), $allowedTables);
          });
        }

        // Excluir tablas específicas si están definidas
        if (isset($pivotConfig['exclude_tables']) && is_array($pivotConfig['exclude_tables'])) {
          $excludedTables = array_map('strtolower', $pivotConfig['exclude_tables']);
          $fkList = array_filter($fkList, function ($fk) use ($excludedTables) {
            return !in_array(strtolower($fk['table']), $excludedTables);
          });
        }
      }

      // Reindexar array después de filtrar
      $fkList = array_values($fkList);

      // Guardar información de pivot con todas sus FKs
      if (count($fkList) >= 2) {
        $pivotTables[$pivotKey] = [
          'pivot_schema' => $t->table_schema,
          'pivot_table' => $t->table_name,
          'config' => $pivotConfig,  // Guardar configuración para usar después
          'fks' => $fkList,
          'num_fks' => count($fkList),
        ];
        echo "  ✓ {$pivotKey}: {$fkList[0]['table']}";
        for ($i = 1; $i < count($fkList); $i++) {
          echo " ↔ {$fkList[$i]['table']}";
        }
        echo " (" . count($fkList) . " FKs)";

        // Mostrar configuración especial si existe
        if (is_array($pivotConfig)) {
          if (isset($pivotConfig['auto_suffix']) && $pivotConfig['auto_suffix']) {
            echo " [auto_suffix]";
          }
          if (isset($pivotConfig['relation_names'])) {
            echo " [custom_names]";
          }
        }
        echo "\n";
      } else {
        echo "  ⚠ {$pivotKey}: Menos de 2 FKs válidas después de filtrar\n";
      }
    } else {
      echo "  ⚠ {$pivotKey}: Solo tiene " . count($fks) . " FK(s)\n";
    }
  }
}

echo "\n✓ Tablas pivot cargadas: " . count($pivotTables) . "\n\n";

// ==================================================================================
// PASO 3: BUCLE PRINCIPAL - GENERAR MODELOS PARA CADA TABLA
// ==================================================================================
//
// OBJETIVO: Procesar cada tabla y generar su par de modelos (Base + Extendido)
//
// PROCESAMIENTO POR TABLA:
// 1. Extrae schema y nombre de tabla
// 2. Convierte nombres a convenciones de Laravel:
//    - Schema: utamed.Administrativo → Administrativo
//    - Tabla: facultad → Facultad (StudlyCase)
// 3. Crea directorios necesarios
// 4. Obtiene metadata (columnas, PK, FKs)
// 5. Genera Base Model (sobrescribible)
// 6. Genera Extended Model (solo si no existe)
// ==================================================================================

foreach ($tables as $tableInfo) {
  $schema = $tableInfo->table_schema;
  $tableName = $tableInfo->table_name;
  $schemaName = str_replace('utamed.', '', $schema);
  $className = Str::studly($tableName);

  echo "Generando: {$schemaName}\\{$className} <- {$schema}.{$tableName}\n";

  // ==================================================================================
  // PASO 3.1: CREAR ESTRUCTURA DE DIRECTORIOS
  // ==================================================================================
  //
  // ESTRUCTURA GENERADA:
  // app/Models/
  //   ├── Base/
  //   │   ├── Administrativo/
  //   │   │   ├── BaseFacultad.php      ← Generado automáticamente (sobrescribible)
  //   │   │   └── BaseDepartamento.php
  //   │   └── Usuario/
  //   │       └── BaseUsuario.php
  //   ├── Administrativo/
  //   │   ├── Facultad.php               ← Extendido (preserva personalizaciones)
  //   │   └── Departamento.php
  //   └── Usuario/
  //       └── Usuario.php
  // ==================================================================================

  // Crear directorios
  $baseModelDir = __DIR__ . "/app/Models/Base/{$schemaName}";
  $modelDir = __DIR__ . "/app/Models/{$schemaName}";

  if (!is_dir($baseModelDir)) {
    mkdir($baseModelDir, 0755, true);
  }
  if (!is_dir($modelDir)) {
    mkdir($modelDir, 0755, true);
  }

  $baseModelPath = "{$baseModelDir}/Base{$className}.php";
  $modelPath = "{$modelDir}/{$className}.php";

  // ==================================================================================
  // PASO 3.2: OBTENER COLUMNAS DE LA TABLA
  // ==================================================================================
  //
  // OBJETIVO: Extraer todas las columnas con sus metadatos
  //
  // INFORMACIÓN OBTENIDA:
  // - column_name: Nombre de la columna
  // - data_type: Tipo PostgreSQL (integer, varchar, boolean, timestamp, etc.)
  // - is_nullable: YES/NO
  // - column_default: Valor por defecto (ej: nextval, CURRENT_TIMESTAMP, etc.)
  //
  // USO:
  // - Generar array $fillable
  // - Detectar tipos para $casts
  // - Detectar timestamps (fecha_creacion)
  // ==================================================================================

  // Obtener columnas
  $columns = DB::select("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_schema = ? AND table_name = ?
        ORDER BY ordinal_position
    ", [$schema, $tableName]);

  // ==================================================================================
  // PASO 3.3: DETECTAR CLAVE PRIMARIA
  // ==================================================================================
  //
  // OBJETIVO: Identificar la columna que es Primary Key
  //
  // FUNCIONAMIENTO:
  // - Usa pg_index: Catálogo de índices de PostgreSQL
  // - indisprimary: Flag que indica si es índice de PK
  // - pg_attribute: Catálogo de columnas
  // - indrelid/attrelid: OID de la tabla
  // - indkey/attnum: Número de columna en el índice
  //
  // CASTING A regclass:
  // - PostgreSQL necesita que esquemas con puntos se entrecomillen
  // - Formato: '"utamed.Administrativo"."facultad"'::regclass
  // - regclass convierte nombre de tabla a OID interno
  //
  // RESULTADO: Nombre de la columna PK (ej: 'id_facultad')
  // FALLBACK: Si no se encuentra, usa 'id' por defecto
  // ==================================================================================

  // Obtener PK
  $pkResult = DB::select("
        SELECT a.attname as column_name
        FROM pg_index i
        JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
        WHERE i.indrelid = ('\"' || ? || '\".\"' || ? || '\"')::regclass AND i.indisprimary
        LIMIT 1
    ", [$schema, $tableName]);

  $primaryKey = !empty($pkResult) ? $pkResult[0]->column_name : 'id';

  // ==================================================================================
  // PASO 3.4: OBTENER FOREIGN KEYS (Para relaciones belongsTo)
  // ==================================================================================
  //
  // OBJETIVO: Detectar todas las FKs de la tabla actual para generar belongsTo()
  //
  // CONSULTA pg_constraint DETALLADA:
  // - con.contype = 'f': Solo restricciones de tipo Foreign Key
  // - conrelid: OID de la tabla que contiene la FK (tabla actual)
  // - confrelid: OID de la tabla referenciada (tabla destino)
  // - conkey: Array de números de columnas locales (puede ser múltiple en FKs compuestas)
  // - confkey: Array de números de columnas remotas
  //
  // JOINS REALIZADOS:
  // 1. pg_class c: Tabla local (donde está la FK)
  // 2. pg_namespace n: Schema de la tabla local
  // 3. pg_attribute att: Columna local de la FK
  // 4. pg_class fc: Tabla referenciada (destino de la FK)
  // 5. pg_namespace fn: Schema de la tabla referenciada
  // 6. pg_attribute fatt: Columna referenciada
  //
  // RESULTADO: Lista de FKs con:
  // - column_name: Columna local (ej: 'id_facultad')
  // - foreign_table_schema: Schema destino (ej: 'utamed.Administrativo')
  // - foreign_table_name: Tabla destino (ej: 'facultad')
  // - foreign_column_name: Columna destino (ej: 'id_facultad')
  //
  // EJEMPLO FK COMPUESTA:
  // Si tabla tiene FK (id_depto, id_facultad) → (departamento.id_depto, departamento.id_facultad)
  // La consulta retorna DOS filas (una por cada columna)
  // ==================================================================================

  // Obtener FK usando catálogo de PostgreSQL
  $foreignKeys = DB::select("
        SELECT
            string_agg(att.attname, ',' ORDER BY ordinality) AS column_names,
            min(fn.nspname) AS foreign_table_schema,
            min(fc.relname) AS foreign_table_name,
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
        GROUP BY con.oid
    ", [$tableName, $schema]);

  // ==================================================================================
  // PASO 3.5: GENERAR ARRAY $fillable
  // ==================================================================================
  //
  // OBJETIVO: Crear lista de columnas asignables masivamente
  //
  // FUNCIONAMIENTO:
  // 1. Toma todas las columnas de la tabla
  // 2. EXCLUYE:
  //    - Clave primaria (no debe ser asignable)
  //    - fecha_creacion (timestamp, manejado por Eloquent)
  //    - esta_activo (flag de estado, no asignable directamente)
  // 3. Formatea cada columna con indentación
  //
  // EJEMPLO RESULTADO:
  // protected $fillable = [
  //     'nombre',
  //     'descripcion',
  //     'id_facultad',
  // ];
  //
  // USO: Permite hacer Modelo::create(['nombre' => 'Test']) sin mass assignment exception
  // ==================================================================================

  // Generar fillable
  $fillable = collect($columns)
    ->pluck('column_name')
    ->reject(fn($col) => in_array($col, [$primaryKey, 'fecha_creacion', 'esta_activo']))
    ->map(fn($col) => "        '{$col}'")
    ->implode(",\n");

  // ==================================================================================
  // PASO 3.6: CONFIGURAR TIMESTAMPS
  // ==================================================================================
  //
  // OBJETIVO: Configurar manejo de timestamps según esquema de BD
  //
  // ESQUEMA DE BD:
  // - Tiene: fecha_creacion (timestamp de creación)
  // - NO tiene: fecha_actualizacion/updated_at
  //
  // CONFIGURACIÓN ELOQUENT:
  // Si existe fecha_creacion:
  //   const CREATED_AT = 'fecha_creacion';  ← Mapea created_at a esta columna
  //   const UPDATED_AT = null;              ← Desactiva updated_at
  //
  // Si NO existe:
  //   public $timestamps = false;           ← Desactiva completamente timestamps
  //
  // EFECTO:
  // - Model::create() automáticamente setea fecha_creacion
  // - No intenta actualizar updated_at (evita errores de columna no existente)
  // ==================================================================================

  // Detectar timestamps
  $hasTimestamps = collect($columns)->contains('column_name', 'fecha_creacion');
  $timestampsConfig = $hasTimestamps ? "
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;" : "
    public \$timestamps = false;";

  // ==================================================================================
  // PASO 3.7: CONFIGURAR SOFT DELETES (NO USADO EN ESTE ESQUEMA)
  // ==================================================================================
  //
  // DECISIÓN: NO usar trait SoftDeletes de Laravel
  //
  // RAZÓN:
  // - SoftDeletes espera una columna TIMESTAMP (deleted_at)
  // - Esta BD usa 'esta_activo' BOOLEAN (1 = activo, 0 = inactivo)
  // - No es un soft delete, es un flag de estado
  //
  // SOLUCIÓN IMPLEMENTADA:
  // - Tratar esta_activo como boolean cast
  // - Crear scope active() para filtrar: whereRaw('esta_activo IS NOT NULL')
  // - NO agregar SoftDeletes trait
  //
  // USO:
  // - Modelo::active()->get()           ← Solo activos
  // - Modelo::where('esta_activo', 1)   ← Forma alternativa
  //
  // NOTA: Si en el futuro se necesita soft delete real, agregar columna deleted_at
  //       tipo timestamp y descomentar líneas siguientes
  // ==================================================================================

  // NO usar soft deletes - esta_activo es un flag booleano, no un timestamp
  $softDeleteImport = "";
  $softDeleteTrait = "";
  $softDeleteConst = "";

  // ==================================================================================
  // PASO 3.8: GENERAR RELACIONES belongsTo (FK → Modelo padre)
  // ==================================================================================
  //
  // OBJETIVO: Crear métodos belongsTo para cada Foreign Key detectada
  //
  // FUNCIONAMIENTO:
  // 1. Itera sobre cada FK encontrada en PASO 3.4
  // 2. Extrae schema y modelo destino
  // 3. Genera nombre de método basado en la columna local
  //
  // GENERACIÓN DE NOMBRE DE MÉTODO:
  // - Columna FK: 'id_facultad' → Método: 'facultad()'
  // - Proceso: Quita prefijo 'id_', convierte a camelCase
  //
  // MANEJO DE DUPLICADOS:
  // - Si una tabla tiene múltiples FKs a la misma tabla (FK compuesta)
  // - Genera: facultad(), facultad1(), facultad2()
  // - Evita conflictos de nombres de métodos
  //
  // CÓDIGO GENERADO:
  // public function facultad()
  // {
  //     return $this->belongsTo(\App\Models\Administrativo\Facultad::class, 'id_facultad', 'id_facultad');
  // }
  //
  // PARÁMETROS belongsTo:
  // 1. Clase del modelo relacionado
  // 2. Foreign key local (columna en esta tabla)
  // 3. Primary key remota (columna en tabla destino)
  //
  // USO: $departamento->facultad  ← Retorna modelo Facultad relacionado
  // ==================================================================================

  // Generar relaciones
  $relations = '';
  if (!empty($foreignKeys)) {
    $relations = "\n    // Relaciones\n";
    $usedMethods = [];

    foreach ($foreignKeys as $fk) {
      $relatedSchema = str_replace('utamed.', '', $fk->foreign_table_schema);
      $relatedModel = Str::studly($fk->foreign_table_name);

      // Detectar si es FK simple o compuesta
      $localCols = explode(',', $fk->column_names);
      $isComposite = count($localCols) > 1;

      // Clave para configuración de renombrado
      $sourceTableKey = $schema . '.' . $tableName;

      // Verificar si hay renombrado personalizado para esta FK (usando _self)
      $customName = null;
      if (isset($relationNames[$sourceTableKey]['_self'][$fk->column_names])) {
        $customName = $relationNames[$sourceTableKey]['_self'][$fk->column_names];
      }

      // Crear nombre de método basado en configuración o tabla destino
      if ($customName) {
        $methodName = $customName;
      } else {
        $methodName = Str::camel($fk->foreign_table_name);

        // Si ya existe, agregar sufijo numérico
        $originalMethod = $methodName;
        $counter = 1;
        while (in_array($methodName, $usedMethods)) {
          $methodName = $originalMethod . $counter;
          $counter++;
        }
      }
      $usedMethods[] = $methodName;

      $relations .= "\n    public function {$methodName}()\n";
      $relations .= "    {\n";

      if ($isComposite) {
        // FK compuesta: pasar arrays
        $localArray = "['" . implode("', '", $localCols) . "']";
        $foreignArray = "['" . implode("', '", explode(',', $fk->foreign_column_names)) . "']";
        $relations .= "        return \$this->belongsTo(\\App\\Models\\{$relatedSchema}\\{$relatedModel}::class, {$localArray}, {$foreignArray});\n";
      } else {
        // FK simple: strings
        $relations .= "        return \$this->belongsTo(\\App\\Models\\{$relatedSchema}\\{$relatedModel}::class, '{$fk->column_names}', '{$fk->foreign_column_names}');\n";
      }

      $relations .= "    }\n";
    }
  }

  // ==================================================================================
  // PASO 3.9: GENERAR RELACIONES INVERSAS hasMany/hasOne (Modelo hijo → Esta tabla)
  // ==================================================================================
  //
  // OBJETIVO: Crear métodos hasMany o hasOne para tablas que tienen FK hacia esta tabla
  //
  // FUNCIONAMIENTO:
  // 1. Busca en $allForeignKeys (generado en PASO 2) si otras tablas apuntan a esta
  // 2. Clave de búsqueda: $schema.table_name (ej: 'utamed.Administrativo.facultad')
  // 3. Por cada tabla que apunta, genera un método hasMany o hasOne
  // 4. DETECCIÓN 1:1: Si la FK tiene índice UNIQUE → hasOne (relación uno-a-uno)
  //
  // EJEMPLO hasMany:
  // Tabla actual: facultad
  // Búsqueda: $allForeignKeys['utamed.Administrativo.facultad']
  // Resultado: [
  //   ['source_table' => 'departamento', 'source_column' => 'id_facultad', 'is_unique' => false]
  // ]
  //
  // EJEMPLO hasOne:
  // Tabla actual: curso
  // Búsqueda: $allForeignKeys['utamed.Administrativo.curso']
  // Resultado: [
  //   ['source_table' => 'programa', 'source_column' => 'id_curso', 'is_unique' => true]
  // ]
  //
  // GENERACIÓN DE NOMBRE DE MÉTODO:
  // - hasMany: 'departamento' → 'departamentos()' (plural)
  // - hasOne: 'programa' → 'programa()' (singular)
  // - Proceso: Pluraliza/singulariza según tipo, convierte a camelCase
  //
  // CÓDIGO GENERADO hasMany:
  // public function departamentos()
  // {
  //     return $this->hasMany(\App\Models\Administrativo\Departamento::class, 'id_facultad', 'id_facultad');
  // }
  //
  // CÓDIGO GENERADO hasOne:
  // public function programa()
  // {
  //     return $this->hasOne(\App\Models\Administrativo\Programa::class, 'id_curso', 'id_curso');
  // }
  //
  // EJEMPLOS DE RELACIONES 1:1 DETECTADAS:
  // - curso ↔ programa (programa tiene UNIQUE(id_curso))
  // - facultad ↔ contexto (contexto tiene UNIQUE(id_facultad))
  // - usuario ↔ estudiante (estudiante tiene UNIQUE(id_usuario))
  // - usuario ↔ docente (docente tiene UNIQUE(id_usuario))
  //
  // USO:
  // - $facultad->departamentos  ← Collection (1:N)
  // - $curso->programa          ← Model o null (1:1)
  // ==================================================================================

  // Generar relaciones inversas (hasMany/hasOne)
  $currentTableKey = $schema . '.' . $tableName;
  if (isset($allForeignKeys[$currentTableKey])) {
    if (empty($relations)) {
      $relations = "\n    // Relaciones\n";
    }

    $relations .= "\n    // Relaciones inversas\n";
    $inverseUsedMethods = [];

    foreach ($allForeignKeys[$currentTableKey] as $inverseFk) {
      $sourceSchema = str_replace('utamed.', '', $inverseFk['source_schema']);
      $sourceModel = Str::studly($inverseFk['source_table']);

      // Detectar si es FK compuesta
      $sourceCols = explode(',', $inverseFk['source_columns']);
      $targetCols = explode(',', $inverseFk['target_columns']);
      $isComposite = count($sourceCols) > 1;

      // Detectar si es relación 1:1 (tiene índice UNIQUE)
      $isOneToOne = $inverseFk['is_unique'] ?? false;
      $relationType = $isOneToOne ? 'hasOne' : 'hasMany';

      // Intentar obtener nombre personalizado de la configuración
      $customName = null;
      $sourceTableKey = $inverseFk['source_schema'] . '.' . $inverseFk['source_table'];
      if (isset($relationNames[$sourceTableKey][$className][$inverseFk['source_columns']])) {
        $customName = $relationNames[$sourceTableKey][$className][$inverseFk['source_columns']];
      }

      // Nombre del método
      if ($customName) {
        // Usar nombre personalizado
        $methodName = $customName;
      } else {
        // Nombre automático
        if ($isOneToOne) {
          // hasOne: nombre en singular (sin pluralizar)
          $methodName = Str::camel($inverseFk['source_table']);
        } else {
          // hasMany: nombre en plural
          $methodName = Str::camel(pluralizeSpanish($inverseFk['source_table']));
        }
      }

      // Evitar duplicados
      $originalMethod = $methodName;
      $counter = 1;
      while (in_array($methodName, $inverseUsedMethods)) {
        if ($customName) {
          // Si es nombre personalizado, agregar número
          $methodName = $customName . $counter;
        } else {
          $methodName = $originalMethod . $counter;
        }
        $counter++;
      }
      $inverseUsedMethods[] = $methodName;

      $relations .= "\n    public function {$methodName}()\n";
      $relations .= "    {\n";

      if ($isComposite) {
        // FK compuesta: pasar arrays
        $sourceArray = "['" . implode("', '", $sourceCols) . "']";
        $targetArray = "['" . implode("', '", $targetCols) . "']";
        $relations .= "        return \$this->{$relationType}(\\App\\Models\\{$sourceSchema}\\{$sourceModel}::class, {$sourceArray}, {$targetArray});\n";
      } else {
        // FK simple: strings
        $relations .= "        return \$this->{$relationType}(\\App\\Models\\{$sourceSchema}\\{$sourceModel}::class, '{$inverseFk['source_columns']}', '{$inverseFk['target_columns']}');\n";
      }

      $relations .= "    }\n";
    }
  }

  // ==================================================================================
  // PASO 3.10: GENERAR RELACIONES belongsToMany (Muchos-a-muchos con pivot)
  // ==================================================================================
  //
  // OBJETIVO: Crear métodos belongsToMany cuando esta tabla está relacionada
  //           con otras a través de tablas pivot
  //
  // FUNCIONAMIENTO:
  // 1. Busca en $pivotTables si hay tablas pivot que conecten esta tabla con otras
  // 2. Soporta pivots con 2, 3, 4+ FKs
  // 3. Genera una relación belongsToMany por cada OTRA tabla relacionada en el pivot
  //
  // EJEMPLO PIVOT CON 2 FKs:
  // Tabla actual: Curso
  // Pivot: Inscripcion_Curso (id_curso, id_estudiante)
  // Genera: Curso->estudiantes() (Curso ↔ Estudiante)
  //
  // EJEMPLO PIVOT CON 3 FKs:
  // Tabla actual: Usuario
  // Pivot: Usuario_Rol_Contexto (id_usuario, id_rol, id_contexto)
  // Genera: Usuario->roles(), Usuario->contextos()
  //
  // CÓDIGO GENERADO:
  // public function estudiantes()
  // {
  //     return $this->belongsToMany(
  //         \App\Models\Usuario\Estudiante::class,
  //         'Inscripcion_Curso',          // Tabla pivot
  //         'id_curso',                    // FK en pivot hacia esta tabla
  //         'id_estudiante'                // FK en pivot hacia tabla relacionada
  //     )->withPivot('cod_inscripcion_uta', 'fecha_inscripcion', ...);
  // }
  //
  // PARÁMETROS belongsToMany:
  // 1. Clase del modelo relacionado
  // 2. Nombre de tabla pivot
  // 3. FK en pivot que apunta a esta tabla (foreignPivotKey)
  // 4. FK en pivot que apunta a tabla relacionada (relatedPivotKey)
  //
  // withPivot(): Agrega columnas adicionales del pivot accesibles vía $model->pivot->columna
  //
  // USO: $curso->estudiantes  ← Collection con datos pivot
  //      $estudiante->pivot->fecha_inscripcion
  // ==================================================================================

  // Generar relaciones belongsToMany
  $belongsToManyUsedMethods = [];

  // Combinar con métodos ya usados de hasMany/hasOne/belongsTo para detectar conflictos
  $allUsedMethods = array_merge($inverseUsedMethods, $usedMethods);

  foreach ($pivotTables as $pivotKey => $pivotInfo) {
    $pivotSchema = $pivotInfo['pivot_schema'];
    $pivotTable = $pivotInfo['pivot_table'];
    $pivotConfig = $pivotInfo['config'];
    $allFks = $pivotInfo['fks'];

    // Buscar si alguna FK del pivot apunta a esta tabla
    $thisFkIndex = null;
    foreach ($allFks as $idx => $fk) {
      if ($fk['schema'] === $schema && $fk['table'] === $tableName) {
        $thisFkIndex = $idx;
        break;
      }
    }

    // Si esta tabla participa en el pivot
    if ($thisFkIndex !== null) {
      $thisFk = $allFks[$thisFkIndex];

      // Obtener columnas adicionales del pivot una sola vez
      $pivotColumns = DB::select("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = ?
          AND table_name = ?
        ORDER BY ordinal_position
      ", [$pivotSchema, $pivotTable]);

      $allPivotCols = array_map(fn($c) => $c->column_name, $pivotColumns);

      // Recolectar todas las columnas FK para excluirlas de withPivot
      $allFkCols = [];
      foreach ($allFks as $fk) {
        $allFkCols = array_merge($allFkCols, explode(',', $fk['local_columns']));
      }
      $allFkCols = array_unique($allFkCols);

      $excludeCols = array_merge($allFkCols, ['created_at', 'updated_at', 'fecha_creacion', 'fecha_actualizacion', 'esta_activo']);
      $additionalCols = array_diff($allPivotCols, $excludeCols);

      // Obtener sufijo automático si está configurado
      $autoSuffix = '';
      if (is_array($pivotConfig) && isset($pivotConfig['auto_suffix']) && $pivotConfig['auto_suffix']) {
        // Crear sufijo a partir del nombre de la tabla pivot
        // Ejemplo: Usuario_Rol_Asignación -> Ura
        $pivotWords = preg_split('/[_\s]+/', $pivotTable);
        $autoSuffix = strtoupper(implode('', array_map(fn($w) => substr($w, 0, 1), $pivotWords)));
      }

      // Generar una relación belongsToMany con cada OTRA tabla del pivot
      foreach ($allFks as $otherIdx => $otherFk) {
        if ($otherIdx === $thisFkIndex) {
          continue; // Saltar la FK que apunta a esta misma tabla
        }

        $relatedSchema = str_replace('utamed.', '', $otherFk['schema']);
        $relatedTable = $otherFk['table'];
        $relatedModel = Str::studly($relatedTable);

        // Intentar obtener nombre personalizado de la configuración
        $customName = null;
        if (is_array($pivotConfig) && isset($pivotConfig['relation_names'][$className][$relatedModel])) {
          $customName = $pivotConfig['relation_names'][$className][$relatedModel];
        }

        // Nombre del método
        if ($customName) {
          // Usar nombre personalizado
          $methodName = $customName;
        } else {
          // Nombre automático - plural de la tabla relacionada
          $methodName = Str::camel(pluralizeSpanish($relatedTable));

          // Aplicar sufijo automático si está configurado
          if ($autoSuffix) {
            $methodName .= $autoSuffix;
          }
        }

        // Verificar conflictos con hasMany/hasOne/belongsTo
        $originalMethod = $methodName;

        // Si ya existe en hasMany/hasOne/belongsTo, agregar sufijo
        if (in_array($methodName, $allUsedMethods) && !$autoSuffix && !$customName) {
          // Solo agregar sufijo si no tiene configuración personalizada
          $pivotWords = preg_split('/[_\s]+/', $pivotTable);
          $suffix = strtoupper(implode('', array_map(fn($w) => substr($w, 0, 1), $pivotWords)));
          $methodName .= $suffix;
        }

        // Evitar duplicados con otros belongsToMany
        $counter = 1;
        while (in_array($methodName, $belongsToManyUsedMethods) || in_array($methodName, $allUsedMethods)) {
          if ($customName) {
            // Si es nombre personalizado, agregar número
            $methodName = $customName . $counter;
          } else {
            $methodName = $originalMethod . $counter;
          }
          $counter++;
        }

        $belongsToManyUsedMethods[] = $methodName;
        $allUsedMethods[] = $methodName;

        if (empty($relations)) {
          $relations = "\n    // Relaciones\n";
        }

        // Agregar sección si es la primera belongsToMany
        if (count($belongsToManyUsedMethods) == 1) {
          $relations .= "\n    // Relaciones muchos-a-muchos\n";
        }

        $relations .= "\n    public function {$methodName}()\n";
        $relations .= "    {\n";
        $relations .= "        return \$this->belongsToMany(\n";
        $relations .= "            \\App\\Models\\{$relatedSchema}\\{$relatedModel}::class,\n";

        // Nombre completo de tabla pivot con comillas: "utamed.Schema"."Tabla"
        $quotedPivotTable = '\"' . $pivotSchema . '\".\"' . $pivotTable . '\"';
        $relations .= "            '{$quotedPivotTable}',\n";

        // FKs
        $relations .= "            '{$thisFk['local_columns']}',\n";
        $relations .= "            '{$otherFk['local_columns']}'\n";
        $relations .= "        )";

        // Agregar withPivot si hay columnas adicionales
        if (!empty($additionalCols)) {
          $pivotColsStr = "'" . implode("', '", $additionalCols) . "'";
          $relations .= "\n        ->withPivot({$pivotColsStr})";
        }

        $relations .= ";\n";
        $relations .= "    }\n";
      }
    }
  }

  // ==================================================================================
  // PASO 3.11: GENERAR ARCHIVO BASE MODEL (Sobrescribible)
  // ==================================================================================
  //
  // OBJETIVO: Crear clase abstracta Base con toda la configuración auto-generada
  //
  // ESTRUCTURA:
  // - Namespace: App\Models\Base\{Schema}
  // - Nombre: Base{ClassName} (ej: BaseFacultad)
  // - Tipo: abstract class (no instanciable directamente)
  // - Extiende: Illuminate\Database\Eloquent\Model
  //
  // CONTENIDO:
  // 1. Configuración de conexión y tabla
  // 2. Clave primaria y auto-incremento
  // 3. Configuración de timestamps
  // 4. Array $fillable
  // 5. Array $casts (esta_activo → boolean)
  // 6. Métodos de relaciones (belongsTo, hasMany)
  // 7. Scope active() para filtrar activos
  //
  // REGENERACIÓN:
  // - Este archivo SE SOBRESCRIBE en cada ejecución
  // - NO agregar código personalizado aquí
  // - Advertencia clara en PHPDoc
  //
  // RAZÓN DE SER ABSTRACTA:
  // - Fuerza a usar la clase extendida (Facultad.php)
  // - Evita instanciación accidental de la clase base
  // - Patron de diseño Template Method
  //
  // SCOPE active():
  // - Filtra registros donde esta_activo IS NOT NULL
  // - Uso: Facultad::active()->get()
  // - Necesario porque esta_activo es boolean (no soft delete)
  // ==================================================================================

  // Generar Base Model
  $baseContent = <<<PHP
<?php

namespace App\\Models\\Base\\{$schemaName};

use Illuminate\Database\Eloquent\Model;

/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class Base{$className} extends Model
{
    protected \$connection = 'pgsql';
    protected \$table = '{$tableName}';
    protected \$primaryKey = '{$primaryKey}';
    public \$incrementing = true;
{$timestampsConfig}

    protected \$fillable = [
{$fillable}
    ];

    protected \$casts = [
        'esta_activo' => 'boolean',
    ];
{$relations}
    // Scope para filtrar solo registros activos
    public function scopeActive(\$query)
    {
        return \$query->whereRaw('esta_activo IS NOT NULL');
    }
}
PHP;

  file_put_contents($baseModelPath, $baseContent);

  // ==================================================================================
  // PASO 3.12: GENERAR MODELO EXTENDIDO (Solo si no existe)
  // ==================================================================================
  //
  // OBJETIVO: Crear clase extendida para personalizaciones del usuario
  //
  // ESTRUCTURA:
  // - Namespace: App\Models\{Schema}
  // - Nombre: {ClassName} (ej: Facultad)
  // - Tipo: class (instanciable)
  // - Extiende: App\Models\Base\{Schema}\Base{ClassName}
  //
  // CONTENIDO INICIAL:
  // - Clase vacía con comentarios de guía
  // - Sugerencias de qué agregar (métodos, scopes, accessors, etc.)
  //
  // PRESERVACIÓN:
  // - Solo se crea si NO EXISTE el archivo
  // - Si existe, NO se modifica (preserva personalizaciones)
  // - Permite regenerar Base Models sin perder código custom
  //
  // CASOS DE USO:
  // 1. Agregar relaciones belongsToMany (muchos-a-muchos)
  // 2. Crear scopes personalizados (scopePorFacultad, scopeActivos, etc.)
  // 3. Agregar accessors/mutators (getFullNameAttribute, setPasswordAttribute)
  // 4. Implementar métodos de negocio (calcularPromedio, activar, etc.)
  // 5. Sobrescribir métodos del modelo base si necesario
  // 6. Agregar observers/events
  //
  // VENTAJA DEL PATRÓN:
  // - Separación total entre generado y manual
  // - Regeneración segura de Base Models
  // - Herencia limpia de configuración
  //
  // EJEMPLO DE USO POSTERIOR:
  // class Facultad extends BaseFacultad
  // {
  //     public function scopePorRegion($query, $region) {
  //         return $query->where('region', $region);
  //     }
  //
  //     public function carreras() {
  //         return $this->hasManyThrough(Carrera::class, Departamento::class);
  //     }
  // }
  // ==================================================================================

  // Generar o preservar Model extendido
  if (!file_exists($modelPath)) {
    $extendedContent = <<<PHP
<?php

namespace App\\Models\\{$schemaName};

use App\\Models\\Base\\{$schemaName}\\Base{$className};

/**
 * Modelo {$className}
 * 
 * Extiende de Base{$className} (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class {$className} extends Base{$className}
{
    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}
PHP;

    file_put_contents($modelPath, $extendedContent);
  }
}

// ==================================================================================
// PASO 4: RESUMEN FINAL
// ==================================================================================
//
// OBJETIVO: Informar al usuario sobre los archivos generados y próximos pasos
//
// INFORMACIÓN MOSTRADA:
// 1. Ubicación de Base Models (sobrescribibles)
// 2. Ubicación de Extended Models (preservados)
// 3. Recordatorio sobre preservación de personalizaciones
//
// PRÓXIMOS PASOS SUGERIDOS:
// 3. Agregar relaciones belongsToMany para tablas pivote
// 4. Agregar scopes personalizados en modelos extendidos
// 5. Implementar accessors/mutators según necesidad
// 6. Crear Form Requests para validación
// 7. Probar relaciones con Tinker: php artisan tinker
//
// COMANDOS ÚTILES:
// - php generate_models.php           ← Regenerar todos los modelos
// - php artisan tinker                ← Probar modelos interactivamente
// - php artisan migrate               ← Ejecutar migraciones (si aplica)
// - php artisan db:seed               ← Poblar BD con datos de prueba
// ==================================================================================

echo "\n✅ Base Models generados en app/Models/Base/\n";
echo "✅ Modelos extendidos en app/Models/\n";
echo "\n📝 Nota: Los modelos en app/Models/ solo se crean si no existen.\n";
echo "   Puedes personalizarlos sin que se sobrescriban al regenerar.\n";
