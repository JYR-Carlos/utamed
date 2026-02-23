<?php
// ==================================================================================
// FUNCIONES DE IMPRESIÓN CON FORMATO DE ÁRBOL DE DIRECTORIO
// ==================================================================================
//
// SISTEMA DE IMPRESIÓN:
// Usa caracteres Unicode de box-drawing para renderizar árboles en consola.
// La indentación se construye composicionalmente con prefijos.
//
// CARACTERES USADOS:
//   ├── : Rama intermedia (tiene hermanos después)
//   └── : Última rama (no tiene más hermanos)
//   │   : Línea vertical de continuación
//       : Espacio vacío (4 chars) para ramas hijas de último nodo
//
// COLORES ANSI:
//   \033[31m : Rojo    → Método autogenerado (nombre automático, sin config)
//   \033[34m : Azul    → Método con auto-prefix/auto-suffix (renombrado automático)
//   \033[32m : Verde   → Método custom (nombre definido en configuración)
//   \033[33m : Amarillo→ Advertencias o etiquetas informativas
//   \033[90m : Gris    → Info secundaria (claves, tipos)
//   \033[0m  : Reset   → Restaurar color por defecto
//   \033[1m  : Bold    → Texto en negrita
//
// EJEMPLO DE SALIDA (verbose):
//
// Modelos generados:
// ├── Administrativo\Facultad
// │   ├── departamentos() hasMany Departamento (id_facultad)
// │   └── programas() hasMany Programa (id_facultad)
// └── Usuario\Usuario
//     ├── asignacionesRolRecibidas() hasMany UsuarioRolAsignación (id_usuario)
//     └── rolesAsignados() belongsToMany Rol via Usuario_Rol_Asignación (id_usuario > id_rol)
//
// USO DE LAS FUNCIONES:
//   tree_branch($isLast)      → Devuelve '├── ' o '└── '
//   tree_prefix($isLast)      → Devuelve '│   ' o '    ' (para hijos)
//   color($text, $colorCode)  → Envuelve texto con código ANSI
//   tree_print(...)           → Imprime una línea del árbol con indentación
//
// MANTENIMIENTO:
// - Para agregar un nuevo color, definirlo en la constante COLORS
// - Para cambiar el estilo de las ramas, modificar TREE_CHARS
// - tree_print() es la función principal; recibe prefix + isLast + texto
// ==================================================================================

// Constantes de caracteres de árbol para evitar strings mágicos
define('TREE_BRANCH', '├── ');   // Rama intermedia
define('TREE_LAST', '└── ');   // Última rama
define('TREE_PIPE', '│   ');   // Continuación vertical
define('TREE_SPACE', '    ');   // Espacio vacío (misma longitud que TREE_PIPE)

// Constantes de colores ANSI
define('COLORS', [
  'red' => "\033[31m",   // Método autogenerado
  'blue' => "\033[34m",   // Método auto-prefix/auto-suffix
  'green' => "\033[32m",   // Método custom (configuración explícita)
  'yellow' => "\033[33m",   // Advertencias / etiquetas
  'gray' => "\033[90m",   // Info secundaria
  'bold' => "\033[1m",    // Negrita
  'reset' => "\033[0m",    // Reset color

  // Colores brillantes para tipos de relación
  'bright_yellow' => "\033[93m",  // belongsTo (amarillo fosforescente)
  'bright_cyan' => "\033[96m",  // hasOne (cyan brillante)
  'bright_red' => "\033[91m",  // hasMany (naranja brillante)
  'bright_magenta' => "\033[95m",  // belongsToMany (morado brillante)
]);

/**
 * Devuelve el conector de rama del árbol según si es el último elemento.
 *
 * @param bool $isLast Si es el último hijo en la rama
 * @return string '├── ' o '└── '
 */
function tree_branch(bool $isLast): string
{
  return $isLast ? TREE_LAST : TREE_BRANCH;
}

/**
 * Devuelve el prefijo de continuación para hijos según si el padre era último.
 *
 * @param bool $parentIsLast Si el padre era el último en su nivel
 * @return string '│   ' o '    '
 */
function tree_prefix(bool $parentIsLast): string
{
  return $parentIsLast ? TREE_SPACE : TREE_PIPE;
}

/**
 * Envuelve texto con un color ANSI.
 *
 * @param string $text     Texto a colorear
 * @param string $colorKey Clave del color en COLORS ('red', 'green', 'blue', etc.)
 * @return string Texto con códigos ANSI
 */
function color(string $text, string $colorKey): string
{
  return (COLORS[$colorKey] ?? '') . $text . COLORS['reset'];
}

/**
 * Imprime una línea del árbol con prefijo e indentación.
 *
 * @param string $prefix  Prefijo acumulado de niveles superiores ('│   ', '    ', etc.)
 * @param bool   $isLast  Si este nodo es el último en su nivel
 * @param string $text    Contenido de la línea (ya coloreado si es necesario)
 */
function tree_print(string $prefix, bool $isLast, string $text): void
{
  echo $prefix . tree_branch($isLast) . $text . "\n";
}

/**
 * Determina el color de un método según su origen.
 *
 * CONVENCIÓN DE COLORES:
 * - 'green' : Método con nombre definido explícitamente en $relationNames o $manualPivotTables
 * - 'blue'  : Método con nombre modificado automáticamente (auto_suffix, deduplicación numérica)
 * - 'red'   : Método con nombre 100% automático (derivado del nombre de tabla)
 *
 * @param string $origin Origen del método: 'custom', 'auto_suffix', 'auto'
 * @return string Clave de color para usar con color()
 */
function method_color(string $origin): string
{
  return match ($origin) {
    'custom' => 'green',
    'auto_suffix' => 'blue',
    default => 'red',
  };
}

/**
 * Determina el color de un tipo de relación Eloquent.
 *
 * CONVENCIÓN DE COLORES:
 * - belongsTo      → amarillo fosforescente (bright_yellow)
 * - hasOne         → cyan brillante (bright_cyan)
 * - hasMany        → naranja brillante (bright_red)
 * - belongsToMany  → morado brillante (bright_magenta)
 *
 * @param string $relationType Tipo de relación: 'belongsTo', 'hasOne', 'hasMany', 'belongsToMany'
 * @return string Clave de color para usar con color()
 */
function relation_type_color(string $relationType): string
{
  return match ($relationType) {
    'belongsTo' => 'bright_yellow',
    'hasOne' => 'bright_cyan',
    'hasMany' => 'bright_red',
    'belongsToMany' => 'bright_magenta',
    default => 'gray',
  };
}

// ==================================================================================
// FUNCIONES DE SECCIÓN Y PASOS
// ==================================================================================

/**
 * Imprime una sección con título y datos opcionales
 * 
 * @param string $title Título de la sección
 * @param array $data Array asociativo con [clave => valor]
 */
function section(string $title, array $data = []): void
{
  echo "\n" . color($title, 'bright_green') . "\n";

  foreach ($data as $key => $value) {
    echo "  • $key: " . color((string) $value, 'blue') . "\n";
  }

  if (!empty($data)) {
    echo "\n";
  }
}

/**
 * Imprime un paso dentro de una sección
 * 
 * @param string $message Mensaje del paso
 */
function step(string $message): void
{
  echo "  ✓ $message\n";
}

// ==================================================================================
// FUNCIÓN DE RUTAS RELATIVAS
// ==================================================================================

/**
 * Convierte una ruta absoluta a una ruta relativa desde la raíz del proyecto.
 *
 * @param string $absolutePath Ruta absoluta (normalmente desde app_path, base_path, etc.)
 * @param string $projectRoot  Ruta raíz del proyecto (ej: c:\Users\user\Code\utamed)
 * @return string Ruta relativa desde la raíz (ej: scripts/generate_models.php)
 *
 * @example
 *   $absolute = 'c:\Users\user\Code\utamed\app\Models\Base\Administrativo\BaseFacultad.php';
 *   $relative = relativePath($absolute, 'c:\Users\user\Code\utamed');
 *   // Resultado: app/Models/Base/Administrativo/BaseFacultad.php
 */
function relativePath(string $absolutePath, string $projectRoot): string
{
  // Normalizar separadores de ruta a forward slashes
  $absolutePath = str_replace('\\', '/', $absolutePath);
  $projectRoot = str_replace('\\', '/', $projectRoot);

  // Si la ruta comienza con el root del proyecto, extraer la parte relativa
  if (strpos($absolutePath, $projectRoot) === 0) {
    $relative = substr($absolutePath, strlen($projectRoot) + 1);
    return $relative;
  }

  // Si no, devolver la ruta tal como está
  return $absolutePath;
}
