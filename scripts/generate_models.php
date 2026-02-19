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
 *    - Detecta y configura soft deletes (fecha_eliminacion)
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
 *    - Usa SoftDeletes trait de Laravel para marcar registros como eliminados
 *    - Columna fecha_eliminacion (timestamp nullable) detectada automáticamente
 *    - Filtra automáticamente registros eliminados en queries
 *    - Soporta restore() para recuperar registros eliminados
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
 * EJECUCIÓN: php scripts/generate_models.php
 * ==================================================================================
 */


// Cargar autoload y bootstrap de Laravel
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/utils.printing.php';
$tab = str_repeat(' ', 4);
require_once __DIR__ . '/utils.pluralize.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Parsear argumentos CLI
$dryRun = in_array('--dry-run', $argv);
$verbose = in_array('--verbose', $argv);

// Usar clases de Laravel para hacerlo idiomático
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ==================================================================================
// CONFIGURACIÓN GENERAL
// ==================================================================================

$catalogName = 'utamed_1ra_fase';
$schemaPrefix = 'administrativo,agenda,curso,usuario';

// Configurar columnas de auditoria
$createdAtColumn = 'fecha_creacion';
$updatedAtColumn = 'fecha_modificacion';
$softDeleteColumnName = 'fecha_eliminacion'; // Nombre de columna para soft deletes
$contextColumn = 'id_contexto'; // Columna de contexto

// Dos formas de configurar columnas NO fillable:
// 1. Globales para todas las tablas (nombres comunes)
// 2. Específicas por tabla (array con 'table' y 'colname')
// 
// NOTA: $contextColumn (id_contexto) se excluye DINÁMICAMENTE en PASO 4.5
// Solo para tablas con contexto 'direct' (se asigna automáticamente)
// Para otros casos, id_contexto puede ser parte de la PK y debe ser fillable
$notFillableColumns = [
  // Globales (todas las tablas)
  $createdAtColumn,
  $updatedAtColumn,
  $softDeleteColumnName,
  // $contextColumn se maneja dinámicamente en PASO 4.5 según tipo de contexto
  // 'esta_activo' de roles y permisos
  ['table' => 'Usuario_Rol_Asignación', 'colname' => 'esta_activo'],
  ['table' => 'Usuario_Permiso_Especial', 'colname' => 'esta_activo'],
  // Curso
  ['table' => 'Curso', 'colname' => 'indice_grupo'],   // Generada por trigger (MAX query)
  ['table' => 'Curso', 'colname' => 'letra_grupo'],    // Generada de grupo_indice (STORED)
];

// Directorio para Models
$modelDir = app_path(path: 'Models');
// Derivados del base de Models
$baseModelDir = $modelDir . '/Base';

// Namespace base para plantilla de Models
$extendedModelNamespace = 'App\\Models';
$baseModelNamespace = $extendedModelNamespace . '\\Base';


// ==================================================================================
// CONFIGURACIÓN DE RENOMBRADO DE RELACIONES (belongsTo + hasMany/hasOne)
// ==================================================================================
//
// UNIFICA EL RENOMBRADO DE TODOS LOS TIPOS DE RELACIONES EN UN SOLO LUGAR
//
// FORMATO:
// '{Schema}.{Tabla}' => [
//   '_self' => [                           ← belongsTo: Relaciones EN esta tabla
//     '{local_key}' => '{nombre_metodo_belongs}',
//   ],
//   '{OtraTabla}' => [                     ← hasMany/hasOne: Relaciones DESDE otra tabla
//     '{foreign_key}' => '{nombre_metodo_has}',
//   ],
// ]
//
// Corolario:
//   -- SE LEE DE ADENTRO HACIA AFUERA: --
//   "{OtraTabla} puede invocar {nombre_metodo_has} 
//   para acceder a instancias de {Tabla} a través de la columna {foreign_key}."
//   "También, {Tabla} puede invocar {nombre_metodo_belongs}
//   para acceder a la instancia de {OtraTabla} relacionada a través de {local_key}."
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
//     - usuarioRolAsignaciones()  (FK: id_usuario)      ← ❌ No descriptivo
//     - usuarioRolAsignaciones1() (FK: asignado_por)    ← ❌ Duplicado y con otro significado
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

$relationNames = [
  // ===========================================================================================
  // CONFIGURACIÓN CORREGIDA BASADA EN ANÁLISIS DEL DICCIONARIO DE DATOS
  // ===========================================================================================

  // Usuario crea Roles (evitar conflicto con belongsToMany)
  'usuario.rol' => [
    'usuario' => [
      'creado_por' => 'rolesCreados',
    ],
  ],

  // usuario_rol_asignacion
  'usuario.usuario_rol_asignacion' => [
    '_self' => [
      'creado_por' => 'asignador',  // belongsTo: usuario que asigna el permiso
      'id_usuario' => 'receptor',   // belongsTo: usuario que recibe el permiso
      'eliminado_por' => 'borrador', // belongsTo: usuario que borra el permiso
    ],
    'usuario' => [
      'id_usuario' => 'asignacionesRolRecibidas',  // Usuario recibe roles
      'creado_por' => 'asignacionesRolRealizadas',  // Usuario asigna roles
      'eliminado_por' => 'asignacionesRolEliminadas' // Usuario que borra roles
    ],
  ],

  // usuario_permiso_especial: múltiples FKs hacia Usuario
  'usuario.usuario_permiso_especial' => [
    '_self' => [
      'creado_por' => 'asignador',  // belongsTo: usuario que asigna el permiso
      'id_usuario' => 'receptor',   // belongsTo: usuario que recibe el permiso
      'eliminado_por' => 'borrador', // belongsTo: usuario que borra el permiso
    ],
    'usuario' => [
      'id_usuario' => 'permisosEspecialesRecibidos', // Usuario recibe permisos
      'creado_por' => 'permisosEspecialesAsignados', // Usuario asigna permisos
      'eliminado_por' => 'permisosEspecialesEliminados' // Usuario que borra permisos
    ],
  ],

  // programa: Usuario crea programas
  'administrativo.programa' => [
    '_self' => [
      'creado_por' => 'autor',
    ],
    'usuario' => [
      'creado_por' => 'programasCreados',
    ],
  ],

  // seccion: Docente enseña secciones
  'curso.seccion' => [
    'docente' => [
      'id_docente' => 'seccionesQueDicta',
    ],
  ],

];

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
// 'Esquema.Tabla_Pivot' => [
//   'auto_suffix' => true,                      // Agrega sufijo del pivot automáticamente (default: false)
//   'relation_names' => [                       // Relaciones a generar (actúa como whitelist)
//     'Tabla_Origen' => [
//       'Tabla_Destino' => 'nombre_metodo',      // Nombre del método (belongsTo o hasMany)
//       
//       // o especificando más
//       'Tabla_Destino' => [
//         'method_name' => 'nombre_metodo',     // Nombre del método
//         'local_key' => 'columna_local',       // FK específica para esta relación
//         'foreign_key' => 'columna_remota',    // FK específica hacia tabla destino
//       ],
//     ],
//   ],
// ]
//
// Corolario:
//  "A través de {Esquema.Tabla_Pivot}, 
//  {Tabla_Origen} puede invocar {nombre_metodo} 
//  para acceder a todas las instancias de {Tabla_Destino} relacionadas a la tabla,
//  usando {columna_local} en referencia a {columna_remota}."
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
// - Pivots con múltiples FKs a la misma tabla (diferentes roles)
//   Ejemplo: Usuario_Permiso_Especial con id_usuario_recipiente e id_usuario_asignador
//   Permite especificar qué FK usar para cada relación semánticamente correcta
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
// // 3. Pivot con renombrado personalizado simple:
// 'utamed.Usuario.Usuario_Permiso_Especial' => [
//   'relation_names' => [
//     'Usuario' => [
//       'Contexto' => 'contextos_upe',
//       'Permiso' => 'permisos_especiales',
//     ],
//   ],
// ],
//
// // 4. Pivot con múltiples FKs hacia misma tabla (caso complejo):
// 'utamed.Usuario.Usuario_Permiso_Especial' => [
//   'relation_names' => [
//     'Usuario' => [
//       'Usuario' => [
//         ['method_name' => 'usuariosQueRecibenMisPermisos', 'local_key' => 'id_usuario_asignador', 'foreign_key' => 'id_usuario_recipiente'],
//         ['method_name' => 'usuariosQueAsignanMisPermisos', 'local_key' => 'id_usuario_recipiente', 'foreign_key' => 'id_usuario_asignador'],
//       ],
//       'Permiso' => 'permisosEspeciales',
//       'Contexto' => 'contextosPermisos',
//     ],
//   ],
// ],
//

$manualPivotTables = [
  // ===========================================================================================
  // PIVOTS SIMPLES (2 FKs)
  // ===========================================================================================

  // asignacion_plan: Asignatura ↔ Plan
  'administrativo.asignacion_plan' => [
    'relation_names' => [
      'asignatura' => [
        'plan' => 'planes', // asignatura->planes()
      ],
      'plan' => [
        'asignatura' => 'asignaturas', // plan->asignaturas()
      ],
    ],
  ],

  // inscripcion_curso: Curso ↔ Estudiante
  'curso.inscripcion_curso' => [
    'relation_names' => [
      'curso' => [
        'estudiante' => 'estudiantesInscritos', // curso->estudiantesInscritos()
      ],
      'estudiante' => [
        'curso' => 'cursosInscritos', // estudiante->cursosInscritos()
      ],
    ],
  ],

  // actividad_asignada: Actividad ↔ estado_actividad (solo genera relación desde estado_actividad)
  'agenda.actividad_asignada' => [
    'relation_names' => [
      // Solo especificamos estado_actividad -> actividad
      // Omitimos actividad para no generar relaciones inversas desde actividad
      'estado_actividad' => [
        'actividad' => 'actividadesConEstado', // estadoActividad->actividadesConEstado()
      ],
    ],
  ],

  // asignado_actividad: actividad_asignada ↔ Estudiante
  'agenda.asignado_actividad' => [
    'relation_names' => [
      'actividad_asignada' => [
        'estudiante' => 'estudiantesAsignados', // actividadAsignada->estudiantesAsignados()
      ],
      'estudiante' => [
        'actividad_asignada' => 'actividadesAsignadas', // estudiante->actividadesAsignadas()
      ],
    ],
  ],

  // asignacion_rol_permiso: Rol ↔ Permiso
  'usuario.asignacion_rol_permiso' => [
    'relation_names' => [
      'rol' => [
        'permiso' => 'permisos', // rol->permisos()
      ],
      'permiso' => [
        'rol' => 'roles', // permiso->roles()
      ],
    ],
  ],

  // ===========================================================================================
  // PIVOTS COMPLEJOS (3+ FKs o múltiples FKs a misma tabla)
  // ===========================================================================================

  // inscripcion_seccion: Estudiante ↔ Seccion (también involucra Curso)
  'curso.inscripcion_seccion' => [
    'relation_names' => [
      'estudiante' => [
        'seccion' => 'seccionesInscritas', // estudiante->seccionesInscritas()
        // No genera relación con Curso porque ya existe en inscripcion_curso
      ],
      'seccion' => [
        'estudiante' => 'estudiantesInscritos', // seccion->estudiantesInscritos()
      ],
    ],
  ],

  // usuario_permiso_especial: Usuario ↔ Usuario ↔ Permiso ↔ Contexto (múltiples FKs a Usuario)
  'usuario.usuario_permiso_especial' => [
    'relation_names' => [
      'usuario' => [
        'usuario' => [
          // los pivots desde usuario que recibe
          [
            'method_name' => 'usuariosQueAsignanMisPermisos',
            'local_key' => 'id_usuario',
            'foreign_key' => 'creado_por'
          ],
          [
            'method_name' => 'usuariosQueBorranMisPermisos',
            'local_key' => 'id_usuario',
            'foreign_key' => 'eliminado_por'
          ],
          // los pivots desde usuario que asigna
          [
            'method_name' => 'usuariosQueRecibenMisPermisos',
            'local_key' => 'creado_por',
            'foreign_key' => 'id_usuario'
          ],
          [
            'method_name' => 'usuariosQueBorranMisPermisosAsignados',
            'local_key' => 'creado_por',
            'foreign_key' => 'eliminado_por'
          ],
          // los pivots desde usuario que borra
          [
            'method_name' => 'usuariosQueBorranMisPermisosRecibidos',
            'local_key' => 'eliminado_por',
            'foreign_key' => 'id_usuario'
          ],
          [
            'method_name' => 'usuariosALosQueBorroSusPermisos',
            'local_key' => 'eliminado_por',
            'foreign_key' => 'creado_por'
          ],
        ],
        'permiso' => 'permisosEspeciales',
        'contexto' => 'contextosConPermisoEspecial',
      ],
      'permiso' => [
        'usuario' => 'usuariosConPermisoEspecial', // permiso->usuariosConPermisoEspecial()
        'contexto' => 'contextosConEstePermiso',   // permiso->contextosConEstePermiso()
      ],
      'contexto' => [
        'usuario' => 'usuariosConPermisoEspecialEnContexto', // contexto->usuariosConPermisoEspecialEnContexto()
        'permiso' => 'permisosEspecialesEnContexto',         // contexto->permisosEspecialesEnContexto()
      ],
    ],
  ],

  // usuario_rol_asignacion: Usuario ↔ Rol ↔ Contexto (múltiples FKs a Usuario)
  'usuario.usuario_rol_asignacion' => [
    'relation_names' => [
      'usuario' => [
        'usuario' => [
          // de usuario que recibe el rol
          [
            'method_name' => 'usuariosQueAsignanMisRoles',
            'local_key' => 'id_usuario',
            'foreign_key' => 'creado_por'
          ],
          [
            'method_name' => 'usuariosQueBorranMisRoles',
            'local_key' => 'id_usuario',
            'foreign_key' => 'eliminado_por'
          ],
          // de usuario que asigna el rol 
          [
            'method_name' => 'usuariosQueRecibenMisRoles',
            'local_key' => 'creado_por',
            'foreign_key' => 'id_usuario'
          ],
          [
            'method_name' => 'usuariosQueBorranMisRolesAsignados',
            'local_key' => 'creado_por',
            'foreign_key' => 'eliminado_por'
          ],
          // de usuario que elimina el rol
          [
            'method_name' => 'usuariosQueBorranMisRolesRecibidos',
            'local_key' => 'eliminado_por',
            'foreign_key' => 'id_usuario'
          ],
          [
            'method_name' => 'usuariosALosQueBorroSusRoles',
            'local_key' => 'eliminado_por',
            'foreign_key' => 'creado_por'
          ],
        ],
        'rol' => 'rolesAsignados',
        'contexto' => 'contextosConRolAsignado',
      ],
      'rol' => [
        'usuario' => 'usuariosConRolAsignado', // rol->usuariosConRolAsignado()
        'contexto' => 'contextosConEsteRol',   // rol->contextosConEsteRol()
      ],
      'contexto' => [
        'usuario' => 'usuariosConRolEnContexto', // contexto->usuariosConRolEnContexto()
        'rol' => 'rolesEnContexto',              // contexto->rolesEnContexto()
      ],
    ],
  ],
];



echo "🚀 Generando modelos desde PostgreSQL...\n";
if ($dryRun) {
  echo "MODO DRY-RUN (sin crear/modificar archivos)\n";
}
echo "\n";

// ==================================================================================
// CARGAR CONFIGURACIÓN DE CONTEXTOS
// ==================================================================================
// Leer del archivo autogenerado por analyze_context_hierarchies.php
// Este archivo usa nombres de tablas reales de BD (con underscores)
$contextConfig = [];
$contextConfigPath = __DIR__ . '/generated_context_hierarchies.php';

if (file_exists($contextConfigPath)) {
  // Cargar y extraer $contextHierarchies
  $contextHierarchies = [];
  include $contextConfigPath; // importar el archivo que define $contextHierarchies
  $contextConfig = $contextHierarchies ?? [];
  echo color("✓ Configuración de contextos cargada desde scripts/generated_context_hierarchies.php\n", 'green');
} else {
  echo color("⚠ Configuración de contextos no encontrada en scripts/generated_context_hierarchies.php\n", 'yellow');
  echo color("⚠ Asegúrate de ejecutar analyze_context_hierarchies.php -o generated_context_hierarchies.php para generar esta configuración\n", 'yellow');
  throw new Exception('Error: Falta archivo de configuración');
}

// Crear un set de modelos que tienen contexto para búsqueda rápida
// El archivo generado usa nombres de tabla reales (Asignacion_Plan), 
// usamos esos valores directamente sin conversión
$modelsWithContext = [];
foreach (['direct', 'hierarchical', 'global'] as $contextType) {
  foreach ($contextConfig[$contextType] ?? [] as $tableKey => $value) {
    $modelsWithContext[$tableKey] = $contextType;
  }
}

// Cargar mappings generados con rutas y métodos (para scopes jerárquicos)
$contextMappings = [];
$contextMappingsPath = config_path('generated-context-mappings.php');
if (file_exists($contextMappingsPath)) {
  $contextMappings = include $contextMappingsPath;
}

/**
 * Construir el método scopeWhereContextHierarchy basado en paths de relaciones
 * 
 * Genera un scope legible que usa whereHas en los paths del modelo.
 */
function buildContextHierarchyScopeMethod(array $paths, string $contextColumn): string
{
  if (empty($paths)) {
    return '';
  }

  // Construir código para cada path
  $pathConditions = [];
  foreach ($paths as $pathIndex => $pathMethods) {
    if (empty($pathMethods)) {
      continue;
    }

    // Construir la cadena de whereHas anidados
    $whereHasChain = buildWhereHasChain($pathMethods, $contextColumn);
    $pathConditions[] = $whereHasChain;
  }

  if (empty($pathConditions)) {
    return '';
  }

  // Si hay un solo path, genera código simple
  if (count($pathConditions) === 1) {
    $condition = $pathConditions[0];
    return <<<PHP

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Path: {$paths[0][0]}
     */
    public function scopeWhereContextHierarchy(\$query, array \$contextIds)
    {
        if (empty(\$contextIds)) {
            return \$query->whereRaw('1 = 0');
        }

        return \$query->{$condition};
    }
PHP;
  }

  // Múltiples paths: construir cada orWhereHas completo
  $orConditions = [];
  foreach ($pathConditions as $idx => $condition) {
    if ($idx === 0) {
      $orConditions[] = $condition;
    } else {
      $orConditions[] = "or" . ucfirst($condition);
    }
  }

  $chainedConditions = implode("\n            ->", $orConditions);

  return <<<PHP

    /**
     * Scope para filtrar por contexto jerárquico.
     * 
     * Paths múltiples detectados.
     */
    public function scopeWhereContextHierarchy(\$query, array \$contextIds)
    {
        if (empty(\$contextIds)) {
            return \$query->whereRaw('1 = 0');
        }

        return \$query->{$chainedConditions};
    }
PHP;
}

/**
 * Construir cadena de whereHas anidados para un path
 */
function buildWhereHasChain(array $pathMethods, string $contextColumn): string
{
  if (empty($pathMethods)) {
    return '';
  }

  // Tomar el primer método
  $firstMethod = array_shift($pathMethods);

  // Si no hay más métodos, este es el último (tiene el contexto)
  if (empty($pathMethods)) {
    return "whereHas('{$firstMethod}', function (\$q) use (\$contextIds) {
                \$q->whereIn('{$contextColumn}', \$contextIds);
            })";
  }

  // Hay más métodos: anidar recursivamente
  $nestedChain = buildWhereHasChain($pathMethods, $contextColumn);

  return "whereHas('{$firstMethod}', function (\$q) use (\$contextIds) {
                \$q->{$nestedChain};
            })";
}

// ==================================================================================
// PASO 1: DETECCIÓN DE TABLAS EN ESQUEMAS POSTGRESQL
// ==================================================================================
// 
// OBJETIVO: Obtener todas las tablas de los esquemas especificados del catálogo
// 
// FUNCIONAMIENTO:
// - Consulta information_schema.tables del catálogo 'utamed_1ra_fase'
// - Filtra solo los esquemas configurados (administrativo, agenda, curso, usuario)
// - Ordena por schema y nombre de tabla
// - Retorna: 
//    - table_schema (ej: 'administrativo'),
//    - table_name (ej: 'facultad')
//
// NOTA: PostgreSQL es case sensitive y requiere comillas dobles al referenciar
// tablas y esquemas.
// ==================================================================================

// Obtener tablas de la bd utamed_1ra_fase 
// pertenecientes a los esquemas configurados
$schemas = explode(',', $schemaPrefix);
$placeholders = implode(',', array_fill(0, count($schemas), '?'));
$tables = DB::select("
    SELECT 
      table_schema, 
      table_name
    FROM information_schema.tables
    WHERE table_catalog = ?
    AND table_schema IN ($placeholders)
    ORDER BY table_schema, table_name
", array_merge([$catalogName], $schemas));

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
// https://www.postgresql.org/docs/current/catalog-pg-constraint.html
//
// RESULTADO: $allForeignKeys['utamed.Administrativo.facultad'] = [
//   ['source_table' => 'departamento', 'source_column' => 'id_facultad', ...]
// ]
//
// USO POSTERIOR: Al generar facultad.php, se detecta que departamento apunta a ella
//                y se genera automáticamente: public function departamentos() { hasMany(...) }
// ==================================================================================

// Pre-cargar todas las FK para detectar relaciones inversas
echo "Analizando relaciones inversas...\n";
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
    // IMPORTANTE: Excluir:
    //   1. PK (indices primarios no son considerados para detectar 1:1)
    //   2. Índices UNIQUE Parciales con y sin expresiones (indexprs, indpred) 
    //      porque no garantizan unicidad absoluta
    // Solo UNIQUE constraints sin expresiones indican relación 1:1 verdadera
    $hasUniqueIndex = DB::select("
      SELECT COUNT(*) as count
      FROM pg_index i
      JOIN pg_class c ON i.indrelid = c.oid
      JOIN pg_namespace n ON c.relnamespace = n.oid
      JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS u(attnum, ordinality) ON true
      JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = u.attnum
      WHERE n.nspname = ?
        -- Si son indices unique que incluyen EXACTAMENTE las columnas de la FK
        AND i.indisunique = true
        -- En donde la tabla del índice coincide con la tabla que tiene la FK
        AND c.relname = ?
        -- Si no son índices primarios (PK)
        AND i.indisprimary = false
        -- Si no son índices con expresiones (ej: índices parciales o con condiciones)
        AND i.indexprs IS NULL
        -- Si no son índices con predicados (ej: índices parciales con WHERE)
        AND i.indpred IS NULL
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
echo "Relaciones inversas analizadas\n\n";

// ==================================================================================
// PASO 3: CARGAR TABLAS PIVOT (belongsToMany)
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
// NOTA: Pivots con más de tres relaciones
// requieren lógica personalizada manual
// ==================================================================================

echo "Cargando tablas pivot...\n";
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

      // Configuración se aplicará posteriormente mediante relation_names que actúa como whitelist

      // Normalizar índices del array de FKs
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
      } else {
        echo "  " . color("⚠ {$pivotKey}: Menos de 2 FKs detectadas", 'yellow') . "\n";
      }
    } else {
      echo "  " . color("⚠ {$pivotKey}: Solo tiene " . count($fks) . " FK(s)", 'yellow') . "\n";
    }
  }
}

// ==================================================================================
// IMPRIMIR ÁRBOL DE TABLAS PIVOT CONFIGURADAS
// ==================================================================================
//
// FORMATO DE SALIDA (modo verbose):
//
// Tablas pivot configuradas: 8
// Asignacion_Plan
// ├── Asignatura ↔ Plan (2 FKs)
// ├── Asignatura > Plan
// │   └── planes() (id_asignatura > id_plan)              [verde = custom]
// └── Plan > Asignatura
//     └── asignaturas() (id_plan > id_asignatura)         [verde = custom]
// Inscripcion_Curso
//     ├── Curso ↔ Estudiante (2 FKs)
//     └── ...
//
// En modo no-verbose, solo muestra los nombres con el resumen de FKs.
//
// COLORES:
// - Verde: Método con nombre custom (definido en relation_names)
// - Azul:  Método con auto_suffix
// - Rojo:  Método autogenerado sin configuración
// ==================================================================================

echo "\n" . color("Tablas pivot configuradas: " . count($pivotTables), 'bold') . "\n";

$pivotKeys = array_keys($pivotTables);
$pivotTotal = count($pivotKeys);

foreach ($pivotKeys as $pIdx => $pivotKey) {
  $pivotInfo = $pivotTables[$pivotKey];
  $fkList = $pivotInfo['fks'];
  $pivotConfig = $pivotInfo['config'];
  $isLastPivot = ($pIdx === $pivotTotal - 1);

  // Línea 1: Nombre del pivot
  $fkNames = implode(' ↔ ', array_map(fn($fk) => $fk['table'], $fkList));
  $fkCountStr = color("(" . count($fkList) . " FKs)", 'gray');

  // Etiqueta de configuración
  $configTag = '';
  if (is_array($pivotConfig)) {
    if (isset($pivotConfig['auto_suffix']) && $pivotConfig['auto_suffix']) {
      $configTag .= ' ' . color('[auto_suffix]', 'blue');
    }
    if (isset($pivotConfig['relation_names'])) {
      $configTag .= ' ' . color('[custom_names]', 'green');
    }
  }

  // Imprimir nombre del pivot sin símbolos de árbol (primer nivel)
  echo color($pivotInfo['pivot_table'], 'bold') . " {$fkCountStr}{$configTag}\n";

  if ($verbose) {
    $pivotChildPrefix = '';

    // Mostrar relaciones: una rama por cada par Origen > Destino definido en config
    $relationEntries = [];

    if (is_array($pivotConfig) && isset($pivotConfig['relation_names'])) {
      foreach ($pivotConfig['relation_names'] as $originModel => $targets) {
        foreach ($targets as $targetModel => $methodConfig) {
          if (is_array($methodConfig) && isset($methodConfig[0]) && is_array($methodConfig[0])) {
            // Múltiples relaciones avanzadas hacia la misma tabla
            foreach ($methodConfig as $rc) {
              $relationEntries[] = [
                'origin' => $originModel,
                'target' => $targetModel,
                'method' => $rc['method_name'],
                'local_key' => $rc['local_key'],
                'foreign_key' => $rc['foreign_key'],
                'origin_type' => 'custom',
              ];
            }
          } elseif (is_string($methodConfig)) {
            // Nombre simple personalizado
            $relationEntries[] = [
              'origin' => $originModel,
              'target' => $targetModel,
              'method' => $methodConfig,
              'local_key' => null,
              'foreign_key' => null,
              'origin_type' => 'custom',
            ];
          }
        }
      }
    }

    // Imprimir sub-árbol de relaciones
    $relTotal = count($relationEntries);
    foreach ($relationEntries as $rIdx => $rel) {
      $isLastRel = ($rIdx === $relTotal - 1);
      $methodColored = color($rel['method'] . '()', method_color($rel['origin_type']));
      $keyInfo = '';
      if ($rel['local_key'] && $rel['foreign_key']) {
        $keyInfo = ' ' . color("({$rel['local_key']} > {$rel['foreign_key']})", 'gray');
      }
      $direction = color($rel['origin'], 'bold') . ' > ' . $rel['target'];
      tree_print($pivotChildPrefix, $isLastRel, "{$direction}: {$methodColored}{$keyInfo}");
    }
  }
}

echo "\n";

// ==================================================================================
// PASO 4: BUCLE PRINCIPAL - GENERAR MODELOS PARA CADA TABLA
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

// Acumula info de cada modelo para el resumen final (verbose)
$modelSummary = [];

// ==================================================================================
// VARIABLE PARA RASTREAR RELACIONES
// ==================================================================================
// Estructura: $tableRelationMappings[$tableKey] = ['methods' => ['methodName' => 'TargetClass']]
// NOTA: $tableKey usa StudlyCase para consistencia con context config
// Ejemplo: 'utamed.Curso.Seccion' (no 'utamed.Curso.seccion')
// Se popula mientras se generan las relaciones
$tableRelationMappings = [];

foreach ($tables as $tableInfo) {
  $schema = $tableInfo->table_schema;
  $tableName = $tableInfo->table_name;
  $schemaName = Str::studly($schema); // Convertir schema a StudlyCase para namespace PSR-4
  $className = Str::studly($tableName);

  echo "Generando: {$schemaName}\\{$className}\n";

  // Determinar si este modelo tiene contexto
  // Usar nombre de tabla real (no modelo) para buscar en modelsWithContext
  $tableFullKey = "{$schema}.{$tableName}";
  $modelContextType = $modelsWithContext[$tableFullKey] ?? null;
  $implementsContext = !is_null($modelContextType);

  // Inicializar registro de métodos para este modelo (para resumen verbose)
  $modelMethods = [];

  // Inicializar arrays de métodos usados para detectar conflictos
  // (se poblan en los pasos 4.8, 4.9 dentro de sus condicionales)
  $usedMethods = [];
  $inverseUsedMethods = [];

  // Instanciar $notFillableColumns para cada tabla
  // Comienza con valores globales y se agrega exclusiones específicas de la tabla
  $tableNotFillableColumns = [
    $createdAtColumn,
    $updatedAtColumn,
    $softDeleteColumnName,
  ];
  // Agregar exclusiones específicas de tabla si existen
  foreach ($notFillableColumns as $exclude) {
    if (is_array($exclude) && isset($exclude['table']) && $exclude['table'] === $tableName) {
      $tableNotFillableColumns[] = $exclude;
    }
  }

  // ==================================================================================
  // PASO 4.1: CREAR ESTRUCTURA DE DIRECTORIOS
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

  // Crear directorios usando configuración global
  $baseModelSchemaDir = $baseModelDir . "/{$schemaName}";
  $modelSchemaDir = $modelDir . "/{$schemaName}";

  if (!is_dir($baseModelSchemaDir)) {
    mkdir($baseModelSchemaDir, 0755, true);
  }
  if (!is_dir($modelSchemaDir)) {
    mkdir($modelSchemaDir, 0755, true);
  }

  $baseModelPath = "{$baseModelSchemaDir}/Base{$className}.php";
  $modelPath = "{$modelSchemaDir}/{$className}.php";

  // ==================================================================================
  // PASO 4.2: OBTENER COLUMNAS DE LA TABLA
  // ==================================================================================
  //
  // OBJETIVO: Extraer todas las columnas con sus metadatos
  //
  // INFORMACIÓN OBTENIDA:
  // - column_name: Nombre de la columna
  // - data_type: Tipo PostgreSQL (integer, varchar, boolean, timestamp, etc.)
  // - is_nullable: YES/NO
  // - column_default: Valor por defecto (ej: nextval, CURRENT_TIMESTAMP, etc.)
  // - is_generated: NEVER/ALWAYS/BY DEFAULT (para detectar columnas autogeneradas)
  //
  // USO:
  // - Generar array $fillable
  // - Detectar tipos para $casts
  // - Detectar timestamps (fecha_creacion)
  // - Excluir columnas ALWAYS GENERATED
  // ==================================================================================

  // Obtener columnas con información sobre si son generadas
  // Usa pg_attribute.attidentity en lugar de information_schema.is_generated
  // attidentity: 'a' = ALWAYS, 'd' = BY DEFAULT, '' = not generated
  $columns = DB::select("
        SELECT 
          a.attname AS column_name,
          t.typname::text AS data_type,
          (NOT a.attnotnull)::text AS is_nullable,
          pg_get_expr(d.adbin, d.adrelid) AS column_default,
          CASE 
            WHEN a.attidentity = 'a' THEN 'ALWAYS'
            WHEN a.attidentity = 'd' THEN 'BY DEFAULT'
            ELSE 'NEVER'
          END AS is_generated
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        JOIN pg_attribute a ON a.attrelid = c.oid
        LEFT JOIN pg_type t ON t.oid = a.atttypid
        LEFT JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
        WHERE n.nspname = ? 
          AND c.relname = ?
          AND a.attnum > 0
          AND NOT a.attisdropped
        ORDER BY a.attnum
    ", [$schema, $tableName]);

  // ==================================================================================
  // PASO 4.3: DETECTAR CLAVE PRIMARIA
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

  // Obtener PK (soporta PK compuesta)
  $pkResult = DB::select("
        SELECT
            string_agg(a.attname, ',' ORDER BY a.attnum) AS column_names,
            count(*) as column_count
        FROM pg_index i
        JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
        WHERE i.indrelid = ('\"' || ? || '\".\"' || ? || '\"')::regclass AND i.indisprimary
        GROUP BY i.indexrelid
    ", [$schema, $tableName]);

  if (!empty($pkResult)) {
    $pkColumns = explode(',', $pkResult[0]->column_names);
    $primaryKey = (count($pkColumns) > 1) ? $pkColumns : $pkColumns[0];
  } else {
    $primaryKey = 'id';
  }

  $isCompositePK = is_array($primaryKey);
  $primaryKeyDefinition = $isCompositePK
    ? "['" . implode("', '", $primaryKey) . "']"
    : "'{$primaryKey}'";
  $incrementingValue = $isCompositePK ? 'false' : 'true';

  // ==================================================================================
  // PASO 4.3.5: DETECTAR Y EXCLUIR COLUMNAS ALWAYS GENERATED
  // ==================================================================================
  //
  // OBJETIVO: Identificar columnas que son ALWAYS GENERATED (autocalculadas)
  //
  // TIPOS DE COLUMNAS GENERADAS EN PostgreSQL:
  // - ALWAYS GENERATED AS (expression): Columna computada (ej: computed indexes)
  // - BY DEFAULT: Generada solo al insertar, puede ser sobrescrita
  //
  // EXCLUSION INTELIGENTE:
  // - Solo excluye ALWAYS GENERATED (is_generated = 'ALWAYS')
  // - No duplica exclusiones ya existentes en $notFillableColumns
  // - Verifica tanto columnas globales como especificas por tabla
  //
  // USO:
  // - Impide mass assignment de columnas autocalculadas
  // - Evita errores al intentar asignar una columna que BD calcula automaticamente
  //
  // EJEMPLO:
  // Si tabla tiene columna "full_name" GENERATED AS (first_name || ' ' || last_name)
  // -> Sera excluida automaticamente de $fillable
  // -> Previene: Model::create(['full_name' => 'Invalid'])
  // ==================================================================================

  // Detectar columnas ALWAYS GENERATED y agregarlas a exclusion
  foreach ($columns as $col) {
    if ($col->is_generated === 'ALWAYS') {
      $colName = $col->column_name;

      // Verificar si ya esta excluida (string)
      $alreadyExcluded = false;
      foreach ($tableNotFillableColumns as $exclude) {
        if (is_string($exclude) && $exclude === $colName) {
          $alreadyExcluded = true;
          break;
        }
      }

      // Verificar si ya esta excluida (array)
      if (!$alreadyExcluded) {
        foreach ($tableNotFillableColumns as $exclude) {
          if (is_array($exclude) && isset($exclude['table'], $exclude['colname'])) {
            if ($exclude['table'] === $tableName && $exclude['colname'] === $colName) {
              $alreadyExcluded = true;
              break;
            }
          }
        }
      }

      // Agregar a exclusion si no estaba ya
      if (!$alreadyExcluded) {
        $tableNotFillableColumns[] = [
          'table' => $tableName,
          'colname' => $colName
        ];
      }
    }
  }

  // ==================================================================================
  // PASO 4.4: OBTENER FOREIGN KEYS (Para relaciones belongsTo)
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
  // PASO 4.5: GENERAR ARRAY $fillable
  // ==================================================================================
  //
  // OBJETIVO: Crear lista de columnas asignables masivamente (mass assignment)
  //
  // ESTRATEGIA DE EXCLUSIÓN:
  // Solo se excluyen columnas que son GENERATED ALWAYS (autocalculadas por la BD)
  // Las claves primarias SÍ se incluyen en $fillable si no son auto-generadas
  //
  // REGLAS:
  // 1. EXCLUIR si: is_generated = 'ALWAYS' (BD la calcula automáticamente)
  // 2. INCLUIR si: Es PK pero NO es GENERATED ALWAYS (usuario debe poder asignarla)
  // 3. INCLUIR si: Es PK GENERATED ALWAYS pero de tipo compuesta (algunas partes pueden ser asignables)
  //
  // JUSTIFICACIÓN:
  // - Algunos modelos requieren asignar la PK (ej: Facultad recibe id_facultad)
  // - Otros tienen PK autogenerada (ej: Departamento.id_departamento GENERATED ALWAYS)
  // - Solo excluir lo que la BD genera automáticamente, no asumir reglas globales
  //
  // EJEMPLO - Facultad (PK simple, NO generada automáticamente):
  // CREATE TABLE Facultad (
  //     id_facultad smallint NOT NULL,  ← NO GENERATED, debe estar en $fillable
  //     nombre text
  // );
  // Resultado: $fillable = ['id_facultad', 'nombre']
  //
  // EJEMPLO - Departamento (PK simple, GENERADA automáticamente):
  // CREATE TABLE Departamento (
  //     id_departamento smallint NOT NULL GENERATED ALWAYS AS IDENTITY,  ← SÍ GENERATED, excluir
  //     id_facultad smallint NOT NULL,  ← NO GENERATED, incluir
  //     nombre text
  // );
  // Resultado: $fillable = ['id_facultad', 'nombre']
  //
  // USO: Permite hacer Modelo::create(['nombre' => 'Test']) sin mass assignment exception
  // ==================================================================================

  // Crear función helper para verificar si columna debe ser excluida
  $shouldExcludeColumn = function ($colName, $currentTable) use ($tableNotFillableColumns, $contextColumn, $schema, $modelsWithContext) {
    foreach ($tableNotFillableColumns as $exclude) {
      if (is_string($exclude)) {
        // Excluye globalmente si es string
        if ($colName === $exclude) {
          return true;
        }
      } elseif (is_array($exclude) && isset($exclude['table'], $exclude['colname'])) {
        // Excluye solo si tabla y columna coinciden
        if ($exclude['table'] === $currentTable && $exclude['colname'] === $colName) {
          return true;
        }
      }
    }

    // EXCLUSIÓN DINÁMICA: Excluir id_contexto solo si tabla es contexto 'direct'
    // Tablas con contexto 'direct' NO pueden cambiar id_contexto (se asigna automáticamente)
    // Otras tablas SÍ pueden tener id_contexto en fillable (ej: si es parte de PK compuesta)
    if ($colName === $contextColumn) {
      $tableKey = "{$schema}.{$currentTable}";
      $contextType = $modelsWithContext[$tableKey] ?? null;

      // Solo excluir si es contexto 'direct'
      if ($contextType === 'direct') {
        return true;
      }
    }

    return false;
  };

  // Generar array $fillable
  $fillable = collect($columns)
    ->pluck('column_name')
    ->reject(fn($col) => $shouldExcludeColumn($col, $tableName))
    ->map(fn($col) => "{$tab}{$tab}'{$col}'")
    ->implode(",\n");

  // ==================================================================================
  // PASO 4.6: CONFIGURAR TIMESTAMPS
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
  //   const UPDATED_AT = 'fecha_modificacion';
  //
  // Si NO existe:
  //   public $timestamps = false;           ← Desactiva completamente timestamps
  //
  // EFECTO:
  // - Model::create() automáticamente setea fecha_creacion
  // - Al actualizar el registrio modifica 'updated_at' (fecha_modificacion) 
  // ==================================================================================

  // Detectar timestamps
  $hasCreatedAt = collect($columns)->contains('column_name', $createdAtColumn);
  $hasUpdatedAt = collect($columns)->contains('column_name', $updatedAtColumn);

  // Solo activa si AMBAS existen
  if ($hasCreatedAt && $hasUpdatedAt) {
    $timestampsConfig = "
    const CREATED_AT = '$createdAtColumn';
    const UPDATED_AT = '$updatedAtColumn';";
  } else {
    // Si falta cualquiera, desactiva completamente
    $timestampsConfig = "
    public \$timestamps = false;";
  }

  // ==================================================================================
  // PASO 4.7: CONFIGURAR IMPORTS Y TRAITS DE LOS MODELOS
  // ==================================================================================

  // ==================================================================================
  // PASO 4.7.a: CONFIGURAR SOFT DELETES
  //
  // SoftDeletes en Laravel:
  // - SoftDeletes espera una columna TIMESTAMP (deleted_at o similar)
  // - Por defecto usa 'fecha_eliminacion'
  //
  // FUNCIONAMIENTO:
  // - Agrega el trait SoftDeletes al modelo
  // - Configurar const DELETED_AT = 'fecha_eliminacion'
  // - Laravel filtra automáticamente registros con fecha_eliminacion no nula
  //
  // USO:
  // - Modelo::all()                     ← Solo activos (excluye eliminados)
  // - Modelo::withTrashed()->get()      ← Incluye eliminados
  // - Modelo::onlyTrashed()->get()      ← Solo eliminados
  // - $modelo->delete()                 ← Soft delete (setea fecha_eliminacion)
  // - $modelo->restore()                ← Recupera (limpia fecha_eliminacion)
  // - $modelo->forceDelete()            ← Elimina permanentemente
  // ==================================================================================


  // Detectar si la tabla tiene fecha_eliminacion
  $hasSoftDeletes =
    collect($columns)
      ->contains('column_name', $softDeleteColumnName);

  $softDeleteImport = $hasSoftDeletes
    ? "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n"
    : "";

  $softDeleteTrait = $hasSoftDeletes
    ? "{$tab}use SoftDeletes;\n"
    : "";

  $softDeleteConst = $hasSoftDeletes
    ? "{$tab}const DELETED_AT = '$softDeleteColumnName';\n"
    : "";

  // ==================================================================================
  // PASO 4.7.b: CONFIGURAR RESOLUCIÓN DE CONTEXTO (SI APLICA AL MODELO)
  //
  // Contexto en modelos:
  // - Permite recuperar los contextos
  //   y filtrar automáticamente por contexto 
  //   (los contextos sobre los que el usuario tenga acceso)
  //
  // FUNCIONAMIENTO:
  // - Agrega imports necesarios para contexto
  // - Agrega traits ContextAware y FiltersContextScope
  // ==================================================================================

  // Agregar imports y traits para contexto si aplica
  $contextImport = $implementsContext
    ? "use App\\Contracts\\HasContext;\nuse App\\Traits\\ContextAware;\nuse App\\Traits\\QueryScopes\\FiltersContextScope;\n"
    : "";

  $contextTrait = $implementsContext
    ? "{$tab}use ContextAware;\n{$tab}use FiltersContextScope;\n"
    : "";

  $implementsClause = $implementsContext ? " implements HasContext" : "";

  // ==================================================================================
  // PASO 4.7.c: CONFIGURAR CLAVES COMPUESTAS CON awobaz/compoships
  // ==================================================================================

  $composhipsImport = "use Awobaz\\Compoships\\Compoships;\nuse App\\Extensions\\Compoships\\BelongsTo;\n";

  $composhipsTrait = "{$tab}use Compoships;\n";

  // ==================================================================================
  // PASO 4.7.d: COMPILAR TIMESTAMPS CON INDENTACIÓN CORRECTA
  // ==================================================================================

  $timestampsStorage = "";
  if ($hasCreatedAt && $hasUpdatedAt) {
    $timestampsStorage = "{$tab}const CREATED_AT = '$createdAtColumn';\n{$tab}const UPDATED_AT = '$updatedAtColumn';\n";
  } else {
    $timestampsStorage = "{$tab}public \$timestamps = false;\n";
  }

  // ==================================================================================
  // COMPILAR IMPORTS, TRAITS Y CONFIGURACIONES CON HEREDOC
  // ==================================================================================

  // Compilar todo dinámicamente sin dejar espacios en blanco
  $allImports = $composhipsImport . $softDeleteImport . $contextImport;

  // Compilar traits y constantes en un bloque único
  $allTraitsAndConsts = <<<EOL
{$softDeleteTrait}{$composhipsTrait}{$contextTrait}{$softDeleteConst}{$timestampsStorage}
EOL;

  // ==================================================================================
  // PASO 4.8: GENERAR RELACIONES belongsTo (FK → Modelo padre)
  // ==================================================================================
  //
  // OBJETIVO: Crear métodos belongsTo para cada Foreign Key detectada
  //
  // FUNCIONAMIENTO:
  // 1. Itera sobre cada FK encontrada en PASO 4.4
  // 2. Extrae schema y modelo destino de la FK
  // 3. Genera nombre de método según configuración o automáticamente
  //
  // CONFIGURACIÓN PERSONALIZADA (usando $relationNames):
  // - Clave especial: '_self' para relaciones belongsTo
  // - Formato:
  // $relationNames['schema.tabla']['_self'] = [
  //     'columna_fk' => 'nombreMetodo',
  //     ...
  // ];
  //
  // - Ejemplo real:
  // $relationNames['utamed.Base.departamento']['_self'] = [
  //     'id_facultad' => 'facultadPrincipal',
  // ];
  //
  // GENERACIÓN AUTOMÁTICA DE NOMBRE:
  // - Tabla destino: 'facultad' → Método: 'facultad()'
  // - Proceso: Usa nombre de tabla destino, convierte a camelCase
  // - NO usa el nombre de la columna FK
  //
  // MANEJO DE DUPLICADOS:
  // - Si una tabla tiene múltiples FKs a la misma tabla:
  //   - Genera: facultad(), facultad1(), facultad2()
  //   - Agrega sufijo numérico para evitar conflictos
  // - Ejemplo real: Usuario_Permiso_Especial tiene id_usuario_recipiente + id_usuario_asignador
  //   - Sin configuración: usuario(), usuario1()
  //   - Con configuración: usuarioRecipiente(), usuarioAsignador()
  //
  // SOPORTE FK COMPUESTA:
  // - Detecta si la FK tiene múltiples columnas
  // - Usa arrays en vez de strings para foreign key y primary key
  // - Ejemplo: ['col1', 'col2'] en vez de 'col1'
  //
  // CÓDIGO GENERADO (FK SIMPLE):
  // public function facultad()
  // {
  //     return $this->belongsTo(\App\Models\Administrativo\Facultad::class, 'id_facultad', 'id_facultad');
  // }
  //
  // CÓDIGO GENERADO (FK COMPUESTA):
  // public function detalleCurso()
  // {
  //     return $this->belongsTo(\App\Models\Curso\DetalleCurso::class, ['id_curso', 'id_periodo'], ['id_curso', 'id_periodo']);
  // }
  //
  // PARÁMETROS belongsTo:
  // 1. Clase del modelo relacionado (FQCN)
  // 2. Foreign key local (columna(s) en esta tabla)
  // 3. Primary key remota (columna(s) en tabla destino)
  //
  // USO: 
  // - $departamento->facultad  ← Retorna modelo Facultad relacionado (o null)
  // - $permiso->usuarioRecipiente  ← Con nombre personalizado
  // ==================================================================================

  // Generar relaciones
  $relations = '';
  if (!empty($foreignKeys)) {
    $relations = "\n    // Relaciones\n";
    $usedMethods = [];

    foreach ($foreignKeys as $fk) {
      $relatedSchema = Str::studly($fk->foreign_table_schema); // Convertir schema a StudlyCase para PSR-4
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

      // Registrar método para resumen verbose
      $modelMethods[] = [
        'method' => $methodName,
        'type' => 'belongsTo',
        'related' => $relatedModel,
        'keys' => $fk->column_names . ($fk->column_names !== $fk->foreign_column_names ? ' > ' . $fk->foreign_column_names : ''),
        'origin' => $customName ? 'custom' : 'auto',
      ];

      // Rastrear relación para contextos: Tabla actual -> Tabla destino
      // Usar formato schema.tabla en minúsculas para consistencia con context config
      $tableKey = "{$schema}.{$tableName}";
      if (!isset($tableRelationMappings[$tableKey])) {
        $tableRelationMappings[$tableKey] = ['methods' => []];
      }
      $tableRelationMappings[$tableKey]['methods'][$methodName] = $relatedModel;

      $relations .= "\n    public function {$methodName}()\n";
      $relations .= "    {\n";

      if ($isComposite) {
        // FK compuesta: pasar arrays
        $localArray = "['" . implode("', '", $localCols) . "']";
        $foreignArray = "['" . implode("', '", explode(',', $fk->foreign_column_names)) . "']";
        $relations .= "{$tab}{$tab}\$instance = new \\App\\Models\\{$relatedSchema}\\{$relatedModel}();\n";
        $relations .= "{$tab}{$tab}return new BelongsTo(\$instance->newQuery(), \$this, {$localArray}, {$foreignArray}, '{$methodName}');\n";
      } else {
        // FK simple: strings
        $relations .= "{$tab}{$tab}\$instance = new \\App\\Models\\{$relatedSchema}\\{$relatedModel}();\n";
        $relations .= "{$tab}{$tab}return new BelongsTo(\$instance->newQuery(), \$this, '{$fk->column_names}', '{$fk->foreign_column_names}', '{$methodName}');\n";
      }

      $relations .= "    }\n";
    }
  }

  // ==================================================================================
  // PASO 4.9: GENERAR RELACIONES INVERSAS hasMany/hasOne (Modelo hijo → Esta tabla)
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
  // CONFIGURACIÓN PERSONALIZADA (usando $relationNames):
  // - Formato: Para tabla que TIENE la FK (tabla origen), usando tabla destino como clave
  // - Estructura:
  // $relationNames['schema.tabla_origen'][ClassName_destino] = [
  //     'columna_fk' => 'nombreMetodo',
  //     ...
  // ];
  //
  // - Ejemplo real (Departamento tiene FK a Facultad):
  // $relationNames['utamed.Base.departamento']['Facultad'] = [
  //     'id_facultad' => 'misDepartamentos',  // Desde Facultad hacia Departamento
  // ];
  //
  // GENERACIÓN AUTOMÁTICA DE NOMBRE:
  // - hasMany (1:N): 'departamento' → 'departamentos()' (plural)
  // - hasOne (1:1): 'programa' → 'programa()' (singular)
  // - Detección 1:1: Si la FK tiene índice UNIQUE → usa hasOne
  //
  // MANEJO DE DUPLICADOS:
  // - Si múltiples tablas apuntan a esta con FKs diferentes, genera sufijos
  // - Ejemplo: departamentos(), departamentos1()
  //
  // CÓDIGO GENERADO (hasMany - 1:N):
  // public function departamentos()
  // {
  //     return $this->hasMany(\App\Models\Administrativo\Departamento::class, 'id_facultad', 'id_facultad');
  // }
  //
  // CÓDIGO GENERADO (hasOne - 1:1):
  // public function programa()
  // {
  //     return $this->hasOne(\App\Models\Administrativo\Programa::class, 'id_curso', 'id_curso');
  // }
  //
  // PARÁMETROS:
  // - hasMany: Retorna Collection (0...N registros)
  // - hasOne: Retorna Model|null (0 o 1 registro)
  //
  // EJEMPLOS DE RELACIONES 1:1 DETECTADAS:
  // - curso ↔ programa (programa tiene UNIQUE(id_curso))
  // - facultad ↔ contexto (contexto tiene UNIQUE(id_facultad))
  // - usuario ↔ estudiante (estudiante tiene UNIQUE(id_usuario))
  // - usuario ↔ docente (docente tiene UNIQUE(id_usuario))
  //
  // USO:
  // - $facultad->departamentos  ← Collection (1:N)
  // - $facultad->misDepartamentos  ← Con nombre personalizado
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
      $sourceSchema = Str::studly($inverseFk['source_schema']); // Convertir schema a StudlyCase para PSR-4
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

      // Registrar método para resumen verbose
      $modelMethods[] = [
        'method' => $methodName,
        'type' => $relationType,
        'related' => $sourceModel,
        'keys' => $inverseFk['source_columns'] . ($inverseFk['source_columns'] !== $inverseFk['target_columns'] ? ' > ' . $inverseFk['target_columns'] : ''),
        'origin' => $customName ? 'custom' : 'auto',
      ];

      // Rastrear relación para contextos: Tabla actual -> Tabla origen (hasMany/hasOne apuntan hacia la tabla origen)
      // Usar formato schema.tabla en minúsculas para consistencia con context config
      $tableKey = "{$schema}.{$tableName}";
      if (!isset($tableRelationMappings[$tableKey])) {
        $tableRelationMappings[$tableKey] = ['methods' => []];
      }
      $tableRelationMappings[$tableKey]['methods'][$methodName] = $sourceModel;

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
  // PASO 4.10: GENERAR RELACIONES belongsToMany (Muchos-a-muchos con pivot)
  // ==================================================================================
  //
  // OBJETIVO: Crear métodos belongsToMany cuando esta tabla está relacionada
  //           con otras a través de tablas pivot
  //
  // FUNCIONAMIENTO:
  // 1. Busca en $pivotTables si hay tablas pivot que conecten esta tabla con otras
  // 2. Itera sobre cada FK del pivot (soporta 2, 3, 4+ FKs)
  // 3. Por cada tabla relacionada (que NO sea esta tabla), genera una relación
  // 4. Aplica configuración: nombres personalizados, sufijos, exclusiones, local_key/foreign_key
  //
  // PROCESO DE GENERACIÓN (con configuración aplicada):
  //
  // 1. LECTURA DE CONFIGURACIÓN:
  //    - Lee $pivotConfig[$pivotTable] con opciones: auto_suffix, relation_names
  //    - relation_names actúa como WHITELIST: solo genera si está listada
  //
  // 2. DETERMINACIÓN DE NOMBRE DE MÉTODO:
  //    a) SI hay relation_names personalizado (string):
  //       → Usa el nombre personalizado directamente
  //       → Ejemplo: 'Usuario' => ['Rol' => 'miRoles'] → $usuario->miRoles()
  //
  //    b) SI hay relation_names personalizado (array avanzado):
  //       → Genera múltiples relaciones con diferentes nombres
  //       → Usa local_key y foreign_key especificados
  //       → Ejemplo: [['name' => 'rolesActivos', 'local_key' => ...], ...]
  //
  //    c) SI auto_suffix está activado:
  //       → Agrega sufijo del pivot al nombre automático
  //       → Ejemplo: Usuario_Rol_Asignación → Ura → roles() → rolesUra()
  //
  //    d) SI no hay configuración personalizada:
  //       → Pluraliza nombre de tabla relacionada
  //       → Ejemplo: Inscripcion_Curso + estudiante → estudiantes()
  //
  // 3. INCLUSIÓN DE COLUMNAS ADICIONALES (withPivot):
  //    - Recolecta todas las columnas del pivot
  //    - Excluye automáticamente: FK columns, 'created_at', 'updated_at', 'fecha_*'
  //    - Incluye: Solo columnas de datos del negocio
  //    - Accesibles vía: $modelo->pivot->columna
  //
  // EJEMPLOS DE RELACIONES GENERADAS:
  //
  // Ejemplo 1 - Configuración simple (sin personalización):
  // Pivot: Inscripcion_Curso (id_curso, id_estudiante)
  // Generado en Curso:
  //   public function estudiantes()
  //   {
  //       return $this->belongsToMany(\App\Models\Usuario\Estudiante::class, 'Inscripcion_Curso', 'id_curso', 'id_estudiante')
  //           ->withPivot('cod_inscripcion_uta', 'fecha_inscripcion', 'estado');
  //   }
  //
  // Ejemplo 2 - Con nombre personalizado (relation_names string):
  // Config: 'Curso' => ['Estudiante' => 'inscritos']
  // Generado en Curso:
  //   public function inscritos()
  //   {
  //       return $this->belongsToMany(\App\Models\Usuario\Estudiante::class, 'Inscripcion_Curso', 'id_curso', 'id_estudiante')
  //           ->withPivot('cod_inscripcion_uta', 'fecha_inscripcion', 'estado');
  //   }
  //
  // Ejemplo 3 - Con auto_suffix:
  // Config: auto_suffix => true
  // Pivot: Usuario_Rol_Asignación → Ura
  // Generado en Usuario:
  //   public function rolesUra()
  //   {
  //       return $this->belongsToMany(\App\Models\Seguridad\Rol::class, 'Usuario_Rol_Asignación', 'id_usuario', 'id_rol')
  //           ->withPivot('fecha_asignacion', 'vigente');
  //   }
  //
  // Ejemplo 4 - Pivots ternarios (3+ FKs):
  // Pivot: Usuario_Rol_Contexto (id_usuario, id_rol, id_contexto)
  // Generado en Usuario:
  //   public function roles()                    ← Relación a Rol
  //   public function contextos()                ← Relación a Contexto
  //
  // Generado en Rol:
  //   public function usuarios()                 ← Relación a Usuario
  //   public function contextos()                ← Relación a Contexto
  //
  // PARÁMETROS belongsToMany (en orden de generación):
  // 1. Clase del modelo relacionado (FQCN)
  // 2. Nombre de tabla pivot
  // 3. Foreign key en pivot hacia ESTA tabla (foreignPivotKey)
  // 4. Foreign key en pivot hacia tabla RELACIONADA (relatedPivotKey)
  //
  // CONFLICTOS AUTOMÁTICOS:
  // - Si el nombre generado ya existe (en belongsTo, hasMany, etc.):
  //   → Agrega sufijo numérico: roles(), roles1(), roles2()
  // - Detecta duplicados incluso en relaciones personalizadas
  //
  // USO DE LAS RELACIONES GENERADAS:
  //
  // Obtener todos los registros relacionados:
  //   $curso->estudiantes  ← Collection de Estudiante
  //   $usuario->rolesUra   ← Collection de Rol (con suffix)
  //
  // Acceder a datos del pivot:
  //   $curso->estudiantes()->first()->pivot->cod_inscripcion_uta
  //   $curso->estudiantes()->first()->pivot->fecha_inscripcion
  //
  // Con filtros:
  //   $curso->estudiantes()->wherePivot('estado', 'activo')->get()
  //   $usuario->rolesUra()->wherePivot('vigente', true)->get()
  //
  // Agregar nuevas relaciones:
  //   $curso->estudiantes()->attach($estudiante->id, ['fecha_inscripcion' => now()])
  //
  // Actualizar datos del pivot:
  //   $curso->estudiantes()->updateExistingPivot($estudiante->id, ['estado' => 'inactivo'])
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

      $excludeCols = array_merge($allFkCols, ['created_at', 'updated_at', 'fecha_creacion', 'fecha_modificacion', 'fecha_eliminacion']);
      $additionalCols = array_diff($allPivotCols, $excludeCols);

      // Obtener sufijo automático si está configurado
      $autoSuffix = '';
      if (is_array($pivotConfig) && isset($pivotConfig['auto_suffix']) && $pivotConfig['auto_suffix']) {
        // Crear sufijo a partir del nombre de la tabla pivot
        // Ejemplo: Usuario_Rol_Asignación -> Ura
        $pivotWords = preg_split('/[_\s]+/', $pivotTable);
        $autoSuffix = strtoupper(implode('', array_map(fn($w) => substr($w, 0, 1), $pivotWords)));
      }

      // ==================================================================================
      // AGRUPAR FKs POR TABLA DE DESTINO para evitar duplicados
      // ==================================================================================
      // Cuando hay múltiples FKs a la misma tabla (ej: id_usuario_recipiente, id_usuario_asignador),
      // solo debemos generar relaciones una vez por tabla única.
      // Agrupamos por schema.table y usamos la primera FK encontrada como default.

      $fksByTable = [];
      foreach ($allFks as $otherIdx => $otherFk) {
        if ($otherIdx === $thisFkIndex) {
          continue; // Saltar la FK que apunta a esta misma tabla
        }

        $tableKey = $otherFk['schema'] . '.' . $otherFk['table'];

        // Solo guardar la primera FK para cada tabla de destino
        if (!isset($fksByTable[$tableKey])) {
          $fksByTable[$tableKey] = [
            'fk' => $otherFk,
            'index' => $otherIdx,
          ];
        }
      }

      // Generar relaciones belongsToMany según configuración (una por tabla única)
      foreach ($fksByTable as $tableKey => $fkData) {
        $otherFk = $fkData['fk'];
        $otherIdx = $fkData['index'];

        $relatedSchema = Str::studly($otherFk['schema']); // Convertir schema a StudlyCase para PSR-4
        $relatedTable = $otherFk['table'];
        $relatedModel = Str::studly($relatedTable);

        // Si hay relation_names especificado, solo generar si está ahí listado explícitamente (actúa como whitelist)
        if (is_array($pivotConfig) && isset($pivotConfig['relation_names'])) {
          $hasExplicitConfig = false;
          // Buscar usando nombres de tabla en minúsculas (como están en la config)
          $currentTableLower = strtolower($tableName);
          $relatedTableLower = strtolower($relatedTable);
          if (isset($pivotConfig['relation_names'][$currentTableLower][$relatedTableLower])) {
            $hasExplicitConfig = true;
          }
          // Si tiene relation_names pero esta tabla NO está en la config, saltar SOLO si no hay auto_suffix
          if (!$hasExplicitConfig && !($pivotConfig['auto_suffix'] ?? false)) {
            continue;  // ← Solo salta si NO está en relation_names Y NO hay auto_suffix
          }
        }

        // Verificar si hay configuración personalizada para esta relación
        $customConfig = null;
        // Buscar usando nombres de tabla en minúsculas (como están en la config)
        $currentTableLower = strtolower($tableName);
        $relatedTableLower = strtolower($relatedTable);
        if (is_array($pivotConfig) && isset($pivotConfig['relation_names'][$currentTableLower][$relatedTableLower])) {
          $customConfig = $pivotConfig['relation_names'][$currentTableLower][$relatedTableLower];
        }

        // Determinar qué relaciones generar
        $relationsToGenerate = [];

        if (is_array($customConfig)) {
          // Configuración avanzada: múltiples relaciones hacia la misma tabla
          if (isset($customConfig[0]) && is_array($customConfig[0])) {
            // Array de configuraciones para múltiples relaciones hacia la misma tabla
            foreach ($customConfig as $relationConfig) {
              if (isset($relationConfig['method_name'], $relationConfig['local_key'], $relationConfig['foreign_key'])) {
                $relationsToGenerate[] = [
                  'method_name' => $relationConfig['method_name'],
                  'local_key' => $relationConfig['local_key'],
                  'foreign_key' => $relationConfig['foreign_key'],
                  'is_custom' => true,
                ];
              }
            }
          } else {
            // Configuración simple con método personalizado
            $relationsToGenerate[] = [
              'method_name' => $customConfig,
              'local_key' => $thisFk['local_columns'],
              'foreign_key' => $otherFk['local_columns'],
              'is_custom' => true,
            ];
          }
        } elseif (is_string($customConfig)) {
          // String simple - nombre personalizado
          $relationsToGenerate[] = [
            'method_name' => $customConfig,
            'local_key' => $thisFk['local_columns'],
            'foreign_key' => $otherFk['local_columns'],
            'is_custom' => true,
          ];
        } else {
          // Sin configuración personalizada - usar automático
          $methodName = Str::camel(pluralizeSpanish($relatedTable));
          if ($autoSuffix) {
            $methodName .= $autoSuffix;
          }

          $relationsToGenerate[] = [
            'method_name' => $methodName,
            'local_key' => $thisFk['local_columns'],
            'foreign_key' => $otherFk['local_columns'],
            'is_custom' => false,
          ];
        }

        // Generar cada relación
        foreach ($relationsToGenerate as $relationConfig) {
          $methodName = $relationConfig['method_name'];
          $localKey = $relationConfig['local_key'];
          $foreignKey = $relationConfig['foreign_key'];
          $isCustom = $relationConfig['is_custom'];

          // Verificar conflictos solo si no es personalizado
          if (!$isCustom) {
            $originalMethod = $methodName;

            // Si ya existe en hasMany/hasOne/belongsTo, agregar sufijo
            if (in_array($methodName, $allUsedMethods) && !$autoSuffix) {
              $pivotWords = preg_split('/[_\s]+/', $pivotTable);
              $suffix = strtoupper(implode('', array_map(fn($w) => substr($w, 0, 1), $pivotWords)));
              $methodName .= $suffix;
            }

            // Evitar duplicados con otros belongsToMany
            $counter = 1;
            while (in_array($methodName, $belongsToManyUsedMethods) || in_array($methodName, $allUsedMethods)) {
              $methodName = $originalMethod . $counter;
              $counter++;
            }
          }

          // Validar que el método no esté duplicado (incluso para personalizados)
          if (in_array($methodName, $belongsToManyUsedMethods)) {
            echo "  " . color("⚠ Método duplicado detectado: {$methodName}, saltando...", 'yellow') . "\n";
            continue;
          }

          $belongsToManyUsedMethods[] = $methodName;
          $allUsedMethods[] = $methodName;

          // Determinar origen para el color del resumen
          $methodOrigin = 'auto';
          if ($isCustom) {
            $methodOrigin = 'custom';
          } elseif ($autoSuffix) {
            $methodOrigin = 'auto_suffix';
          }

          // Registrar método para resumen verbose
          $modelMethods[] = [
            'method' => $methodName,
            'type' => 'belongsToMany',
            'related' => $relatedModel,
            'keys' => $localKey . ' > ' . $foreignKey,
            'pivot' => $pivotTable,
            'origin' => $methodOrigin,
          ];

          // Rastrear relación para contextos: Tabla actual -> Tabla relacionada
          // Usar formato schema.tabla en minúsculas para consistencia con context config
          $tableKey = "{$schema}.{$tableName}";
          if (!isset($tableRelationMappings[$tableKey])) {
            $tableRelationMappings[$tableKey] = ['methods' => []];
          }
          $tableRelationMappings[$tableKey]['methods'][$methodName] = $relatedModel;

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
          $quotedPivotTable = $pivotTable;
          $relations .= "            '{$quotedPivotTable}',\n";

          // FKs - usar las específicas de la configuración
          $relations .= "            '{$localKey}',\n";
          $relations .= "            '{$foreignKey}'\n";
          $relations .= "        )";

          // Agregar withPivot si hay columnas adicionales
          if (!empty($additionalCols)) {
            $pivotColsStr = "'" . implode("', '", $additionalCols) . "'";
            $relations .= "\n            ->withPivot({$pivotColsStr})";
          }

          $relations .= ";\n";
          $relations .= "    }\n";
        }
      }
    }
  }

  // ==================================================================================
  // PASO 4.10.5: GENERAR SCOPE JERARQUICO (SOLO SI APLICA)
  // ==================================================================================
  $contextScopeMethods = '';
  if ($implementsContext) {
    // Construir clave para buscar en contextMappings: Schema\ModelName
    $modelKey = "{$schemaName}\\{$className}";
    $mapping = $contextMappings[$modelKey] ?? null;

    if ($verbose) {
      if ($mapping) {
        echo "  DEBUG: Encontrado mapping para {$modelKey}, tipo: " . ($mapping['type'] ?? 'unknown') . "\n";
      } else {
        echo "  DEBUG: No encontrado mapping para {$modelKey}\n";
      }
    }

    if ($mapping && ($mapping['type'] ?? null) === 'hierarchical') {
      $paths = [];
      foreach ($mapping['paths'] as $path) {
        $methods = [];
        foreach ($path as $step) {
          if (!empty($step['method'])) {
            $methods[] = $step['method'];
          }
        }
        if (!empty($methods)) {
          $paths[] = $methods;
        }
      }

      $contextColumn = $contextConfig['context_column'] ?? $contextColumn;
      $contextScopeMethods = buildContextHierarchyScopeMethod($paths, $contextColumn);
    }
  }

  // ==================================================================================
  // PASO 4.11: GENERAR ARCHIVO BASE MODEL (Sobrescribible)
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
  // 3. Trait SoftDeletes (si tiene fecha_eliminacion)
  // 4. Configuración de timestamps
  // 5. Array $fillable
  // 6. Métodos de relaciones (belongsTo, hasMany)
  // 7. Const DELETED_AT (si tiene soft deletes)
  //
  // REGENERACIÓN:
  // - Este archivo SE SOBRESCRIBE en cada ejecución
  // - NO agregar código personalizado aquí
  // - Advertencia clara en PHPDoc
  //
  // RAZÓN DE SER ABSTRACTA:
  // - Fuerza a usar la clase extendida (Facultad.php)
  // - Evita instanciación accidental de la clase base
  // - Patrón de diseño Template Method
  //
  // SOFT DELETES (si la tabla tiene fecha_eliminacion):
  // - Usa trait SoftDeletes de Laravel
  // - Mapea DELETED_AT a fecha_eliminacion
  // - Filtrado automático de registros eliminados en queries
  // ==================================================================================

  // Generar Base Model
  $baseContent = <<<PHP
<?php

namespace {$baseModelNamespace}\\{$schemaName};

use App\\Models\\BaseModel as CustomBaseModel;
{$allImports}
/**
 * Clase Base generada automáticamente
 * NO EDITAR - Se sobrescribe al regenerar
 */
abstract class Base{$className} extends CustomBaseModel{$implementsClause}
{
{$allTraitsAndConsts}    protected \$connection = 'pgsql';
    protected \$table = '{$tableName}';
    protected \$primaryKey = {$primaryKeyDefinition};
    public \$incrementing = {$incrementingValue};

    protected \$fillable = [
{$fillable}
    ];

{$relations}{$contextScopeMethods}
}

PHP;

  if (!$dryRun) {
    file_put_contents($baseModelPath, $baseContent);
  } else {
    echo "    [DRY-RUN] Base model: $baseModelPath\n";
  }

  // ==================================================================================
  // PASO 4.12: GENERAR MODELO EXTENDIDO (Solo si no existe)
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

namespace {$extendedModelNamespace}\\{$schemaName};

use {$baseModelNamespace}\\{$schemaName}\\Base{$className};

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

    if (!$dryRun) {
      file_put_contents($modelPath, $extendedContent);
    } else {
      echo "    [DRY-RUN] Extended model: $modelPath\n";
    }
  }

  // Guardar métodos del modelo para el resumen final
  $modelSummary[] = [
    'schema' => $schemaName,
    'class' => $className,
    'methods' => $modelMethods,
  ];
}

// ==================================================================================
// PASO 5: RESUMEN FINAL
// ==================================================================================
//
// OBJETIVO: Informar al usuario sobre los archivos generados y próximos pasos
//
// INFORMACIÓN MOSTRADA:
// 1. Ubicación de Base Models (sobrescribibles)
// 2. Ubicación de Extended Models (preservados)
// 3. Recordatorio sobre preservación de personalizaciones
// 4. (verbose) Árbol detallado de modelos y métodos generados
//
// FORMATO DEL ÁRBOL (verbose):
//
// Modelos generados: 25
// Administrativo\Facultad (3 métodos)
// ├── departamentos() hasMany Departamento (id_facultad)      [rojo = auto]
// ├── contexto() hasOne Contexto (id_facultad)                [rojo = auto]
// └── misDepartamentos() hasMany Departamento (id_facultad)   [verde = custom]
// Usuario\Usuario (5 métodos)
// ├── receptor() belongsTo Usuario (id_usuario)                [verde = custom]
// ├── rolesAsignados() belongsToMany Rol via ... (id_usuario > id_rol) [verde = custom]
// └── ...
//
// COLORES DE MÉTODOS:
// - Rojo  (\033[31m): Método autogenerado (nombre derivado automáticamente de la tabla)
// - Azul  (\033[34m): Método con auto_suffix (nombre automático + sufijo de pivot)
// - Verde (\033[32m): Método custom (nombre definido explícitamente en configuración)
//
// COMANDOS ÚTILES:
// - php generate_models.php           ← Regenerar todos los modelos
// - php generate_models.php --verbose ← Regenerar con detalle de métodos
// - php generate_models.php --dry-run ← Simular sin escribir archivos
// - php artisan tinker                ← Probar modelos interactivamente
// ==================================================================================

echo "\n" . color("✅ Base Models generados en app/Models/Base/", 'green') . "\n";
echo color("✅ Modelos extendidos en app/Models/", 'green') . "\n";

if ($dryRun) {
  echo "\n" . color("📝 MODO DRY-RUN: Los archivos NO fueron creados/modificados", 'yellow') . "\n";
  echo "   Para aplicar los cambios, ejecuta:\n";
  echo "   php scripts/generate_models.php\n";
} else {
  echo "\n📝 Nota: Los modelos en app/Models/ solo se crean si no existen.\n";
  echo "   Puedes personalizarlos sin que se sobrescriban al regenerar.\n";
}

// ==================================================================================
// RESUMEN VERBOSE: ÁRBOL DE MODELOS Y MÉTODOS GENERADOS
// ==================================================================================
//
// Solo se muestra si se pasa --verbose.
//
// Imprime un árbol estilo directorio con:
// - Nivel 1: Nombre del modelo (Schema\Clase)
// - Nivel 2: Cada método de relación con su tipo, modelo relacionado, claves y color
//
// ESTRUCTURA DE $modelSummary:
// [
//   [
//     'schema'  => 'Administrativo',
//     'class'   => 'Facultad',
//     'methods' => [
//       [
//         'method'  => 'departamentos',      // Nombre del método
//         'type'    => 'hasMany',             // Tipo de relación Eloquent
//         'related' => 'Departamento',        // Modelo relacionado
//         'keys'    => 'id_facultad',         // Claves usadas (local > foreign si difieren)
//         'pivot'   => 'Inscripcion_Curso',   // (solo belongsToMany) nombre de tabla pivot
//         'origin'  => 'auto|custom|auto_suffix',  // Origen del nombre del método
//       ],
//       ...
//     ],
//   ],
//   ...
// ]
//
// COLORES (definidos en method_color()):
// - 'auto'        → rojo:  nombre generado automáticamente
// - 'auto_suffix' → azul:  nombre automático con sufijo de pivot
// - 'custom'      → verde: nombre definido en $relationNames o $manualPivotTables
// ==================================================================================

if ($verbose) {
  echo "\n" . color("Modelos generados: " . count($modelSummary), 'bold') . "\n";

  // Leyenda de colores
  echo color("  ● auto", 'red') . "  "
    . color("● auto_suffix", 'blue') . "  "
    . color("● custom", 'green') . "\n\n";

  $modelTotal = count($modelSummary);
  foreach ($modelSummary as $mIdx => $model) {
    $isLastModel = ($mIdx === $modelTotal - 1);
    $methodCount = count($model['methods']);
    $label = $model['schema'] . '\\' . $model['class'];

    if ($methodCount > 0) {
      $label .= ' ' . color("({$methodCount} métodos)", 'gray');
    }

    // Imprimir nombre del modelo sin símbolos de árbol (primer nivel)
    echo color($label, 'bold') . "\n";

    // Imprimir cada método como sub-rama
    $modelPrefix = '';
    foreach ($model['methods'] as $rIdx => $mth) {
      $isLastMethod = ($rIdx === $methodCount - 1);

      // Construir línea del método:
      // nombre() tipo Modelo (claves) [via PivotTable si aplica]
      $methodColored = color($mth['method'] . '()', method_color($mth['origin']));
      $typeStr = color($mth['type'], relation_type_color($mth['type']));
      $keysStr = color("({$mth['keys']})", 'gray');

      $viaStr = '';
      if (isset($mth['pivot'])) {
        $viaStr = ' ' . color("via {$mth['pivot']}", 'gray');
      }

      tree_print($modelPrefix, $isLastMethod, "{$methodColored} {$typeStr} {$mth['related']} {$keysStr}{$viaStr}");
    }
  }
}

// ==================================================================================
// GENERAR JSON DE CONTEXT MAPPINGS
// ==================================================================================
//
// OBJETIVO: Procesar la configuración de contextos (config/context-hierarchies.php)
// y cruzarla con las relaciones reales generadas en $tableRelationMappings
//
// RESULTADO: storage/app/context-mappings.json con las rutas de contextos
// ==================================================================================

echo "\n" . color("Generando context mappings...", 'bold') . "\n";

$contextConfigPath = config_path('generated-context-mappings.php');
$contextHierarchies = [];

if (file_exists($contextConfigPath)) {
  $contextHierarchies = include $contextConfigPath;
  echo color("✓ Configuración de contextos cargada\n", 'green');
} else {
  echo color("⚠ Configuración de contextos no encontrada\n", 'yellow');
}

// Construir índices para contextos jerárquicos:
// 1. table_name -> schema.tabla (formato minúsculas para tableRelationMappings)
// 2. ModelName -> table_name (para conversión inversa)
$tableNameToFullKey = [];
$modelNameToTableName = [];
foreach ($tables as $t) {
  $modelName = Str::studly($t->table_name);
  $tableNameToFullKey[$t->table_name] = $t->table_schema . '.' . $t->table_name;  // Usar formato minúsculas
  $modelNameToTableName[$modelName] = $t->table_name;
}

// Procesar contextos jerárquicos
$contextMappings = [];

// Agregar contextos 'direct'
foreach ($contextConfig['direct'] ?? [] as $modelKey => $contextType) {
  // Convertir clave a formato de modelo Laravel (Schema\ModelName)
  // Formato entrada: 'administrativo.carrera' (2 partes)
  $parts = explode('.', $modelKey);
  if (count($parts) === 2) {
    $schemaName = Str::studly($parts[0]); // administrativo -> Administrativo
    $tableName = $parts[1];
    $modelName = Str::studly($tableName); // carrera -> Carrera
    $modelKeyFormatted = $schemaName . '\\' . $modelName; // Administrativo\Carrera (var_export lo escapa)
  } else {
    // Fallback para formato antiguo de 3 partes
    $modelKeyFormatted = $modelKey;
  }

  $contextMappings[$modelKeyFormatted] = [
    'type' => 'direct',
    'paths' => []
  ];
}

// Agregar contextos 'global'
foreach ($contextConfig['global'] ?? [] as $modelKey => $contextType) {
  // Convertir clave a formato de modelo Laravel (Schema\ModelName)
  // Formato entrada: 'usuario.estudiante' (2 partes)
  $parts = explode('.', $modelKey);
  if (count($parts) === 2) {
    $schemaName = Str::studly($parts[0]); // usuario -> Usuario
    $tableName = $parts[1];
    $modelName = Str::studly($tableName); // estudiante -> Estudiante
    $modelKeyFormatted = $schemaName . '\\' . $modelName; // Usuario\Estudiante (var_export lo escapa)
  } else {
    // Fallback para formato antiguo de 3 partes
    $modelKeyFormatted = $modelKey;
  }

  $contextMappings[$modelKeyFormatted] = [
    'type' => 'global',
    'paths' => []
  ];
}

// Procesar contextos 'hierarchical'
foreach ($contextConfig['hierarchical'] ?? [] as $modelKey => $configPaths) {
  $mapping = [
    'type' => 'hierarchical',
    'paths' => []
  ];

  $configPathsArray = is_array($configPaths) ? $configPaths : [$configPaths];

  // Por cada camino detectado...
  foreach ($configPathsArray as $pathConfig) {
    $pathSteps = [];
    $path = is_array($pathConfig) ? $pathConfig : [$pathConfig];

    // Seguir el path en las relaciones generadas
    // $modelKey viene con formato schema.tabla (ej: 'administrativo.asignacion_plan')
    // Este formato ya coincide con tableRelationMappings
    $currentTableKey = $modelKey;
    $pathValid = true;

    // Por cada paso en el camino... (ej: ['Plan', 'Carrera', 'Departamento'])
    foreach ($path as $stepIdx => $targetTableName) {
      // targetTableName es el nombre de tabla real (ej: 'Plan')
      // Buscar en $tableRelationMappings[currentTableKey] qué método va a esa tabla
      $foundMethodName = null;
      $targetTableFullKey = null;

      if (isset($tableRelationMappings[$currentTableKey]['methods'])) {
        foreach ($tableRelationMappings[$currentTableKey]['methods'] as $methodName => $targetModel) {
          // targetModel es el nombre de modelo (ej: 'Plan')
          // Convertir targetTableName a modelo para comparar
          $expectedModel = Str::studly($targetTableName);
          if ($targetModel === $expectedModel) {
            $foundMethodName = $methodName;
            // Convertir nombre de modelo a nombre de tabla, luego a clave completa
            $actualTableName = $modelNameToTableName[$targetModel] ?? $targetTableName;
            $targetTableFullKey = $tableNameToFullKey[$actualTableName] ?? null;
            break;
          }
        }
      }

      if ($foundMethodName === null) {
        // Método no encontrado, marcar path como inválido
        echo color("  ⚠ {$modelKey}: No encontrado método hacia {$targetTableName} desde {$currentTableKey}\n", 'yellow');
        $pathValid = false;
        break;
      }

      if ($targetTableFullKey === null) {
        // Tabla no encontrada en el índice, marcar path como inválido
        echo color("  ⚠ {$modelKey}: Tabla {$targetTableName} no encontrada en el esquema\n", 'yellow');
        $pathValid = false;
        break;
      }

      // Agregar paso
      $pathSteps[] = [
        'target' => Str::studly($targetTableName), // Guardar nombre de modelo
        'method' => $foundMethodName
      ];

      // Actualizar clave para siguiente iteración
      $currentTableKey = $targetTableFullKey;
    }

    if ($pathValid) {
      $mapping['paths'][] = $pathSteps;
      echo color("  ✓ {$modelKey}: Path válido → " . implode(' → ', array_column($pathSteps, 'target')) . "\n", 'green');
    }
  }

  // Convertir clave a formato de modelo Laravel (Schema\ModelName) al guardar
  $parts = explode('.', $modelKey);
  if (count($parts) === 2) {
    $schemaName = Str::studly($parts[0]); // administrativo -> Administrativo
    $tableName = $parts[1];
    $modelName = Str::studly($tableName); // carrera -> Carrera
    $modelKeyFormatted = $schemaName . '\\' . $modelName; // Administrativo\Carrera (var_export lo escapa)
  } else {
    // Fallback para formato antiguo de 3 partes
    $modelKeyFormatted = $modelKey;
  }

  $contextMappings[$modelKeyFormatted] = $mapping;
}

// Guardar como archivo PHP en config/
if (!$dryRun) {
  $phpContent = "<?php\n\nreturn " . var_export($contextMappings, true) . ";\n";

  if (file_put_contents($contextConfigPath, $phpContent)) {
    echo color("✓ Context mappings guardados en: {$contextConfigPath}\n", 'green');
  } else {
    echo color("⚠ Error al guardar context mappings\n", 'red');
  }
} else {
  echo "[DRY-RUN] Context mappings: {$contextConfigPath}\n";
}

echo "\n";
