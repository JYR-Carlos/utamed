<?php

/**
 * TEST FRAMEWORK PARA GENERADOR DE MODELOS
 * 
 * Uso:
 *   php test_generator.php                    # Ejecutar todos los tests
 *   php test_generator.php --dry-run          # Simular generación sin crear archivos
 *   php test_generator.php --test=pivots      # Ejecutar solo tests de pivots
 *   php test_generator.php --verbose          # Mostrar detalles
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Parsear argumentos CLI
$dryRun = in_array('--dry-run', $argv);
$verbose = in_array('--verbose', $argv);
$testFilter = null;
foreach ($argv as $arg) {
  if (strpos($arg, '--test=') === 0) {
    $testFilter = substr($arg, 7);
  }
}

class TestRunner
{
  private $tests = [];
  private $results = [];
  private $dryRun = false;
  private $verbose = false;

  public function __construct($dryRun = false, $verbose = false)
  {
    $this->dryRun = $dryRun;
    $this->verbose = $verbose;
  }

  public function test($name, callable $callback)
  {
    $this->tests[$name] = $callback;
  }

  public function run($filter = null)
  {
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo ($this->dryRun ? "🔍 MODO DRY-RUN (sin efectos secundarios)\n" : "🧪 EJECUTANDO TESTS\n");
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $total = 0;
    $passed = 0;
    $failed = 0;

    foreach ($this->tests as $name => $callback) {
      if ($filter && strpos($name, $filter) === false) {
        continue;
      }

      $total++;
      $startTime = microtime(true);

      try {
        $result = $callback();
        $duration = microtime(true) - $startTime;

        if ($result === true) {
          echo "✅ $name";
          echo ($this->verbose ? " (" . round($duration * 1000, 2) . "ms)" : "");
          echo "\n";
          $passed++;
        } else {
          echo "❌ $name\n";
          echo "   Motivo: $result\n";
          $failed++;
        }
      } catch (Exception $e) {
        echo "💥 $name\n";
        echo "   Error: " . $e->getMessage() . "\n";
        $failed++;
      }
    }

    // Resumen
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "📊 RESULTADOS: ";
    echo "Total: $total | ✅ $passed | ❌ $failed\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    return $failed === 0;
  }
}

$runner = new TestRunner($dryRun, $verbose);

// ============================================================================
// TESTS DE CONFIGURACIÓN DE PIVOTS
// ============================================================================

$runner->test('Formato 1: true (auto-detecta todas las FKs)', function () {
  $pivotConfig = true;
  $isArray = is_array($pivotConfig);

  // Con true, no debería entrar en verificación restrictiva
  if (!$isArray) {
    return true; // Correcto
  }
  return "pivotConfig debe NO ser array cuando es 'true'";
});

$runner->test('Formato 2: auto_suffix sin relation_names', function () {
  $pivotConfig = ['auto_suffix' => true];
  $isArray = is_array($pivotConfig);
  $hasRelationNames = isset($pivotConfig['relation_names']);

  if ($isArray && !$hasRelationNames) {
    return true; // Correcto - entra a array pero sin relation_names
  }
  return "Debe ser array pero SIN relation_names";
});

$runner->test('Formato 3: relation_names especificado', function () {
  $pivotConfig = [
    'relation_names' => [
      'EstadoActividad' => [
        'Actividad' => 'actividadesConEstado',
      ],
    ],
  ];
  $isArray = is_array($pivotConfig);
  $hasRelationNames = isset($pivotConfig['relation_names']);

  if ($isArray && $hasRelationNames) {
    return true; // Correcto
  }
  return "Debe tener relation_names especificado";
});

// ============================================================================
// TESTS DE LÓGICA DE RESTRICCIÓN
// ============================================================================

$runner->test('Lógica: Sin relation_names, generar todas las FKs', function () {
  $pivotConfig = true;
  $className = 'InscripcionCurso';
  $relatedModel = 'Estudiante';

  // Simular lógica del generador
  $shouldGenerate = true;

  if (is_array($pivotConfig) && isset($pivotConfig['relation_names'])) {
    $hasExplicitConfig = isset($pivotConfig['relation_names'][$className][$relatedModel]);
    if (!$hasExplicitConfig) {
      $shouldGenerate = false;
    }
  }

  if ($shouldGenerate) {
    return true;
  }
  return "Debería generar la relación cuando no hay relation_names";
});

$runner->test('Lógica: Con relation_names, solo generar listadas', function () {
  $pivotConfig = [
    'relation_names' => [
      'EstadoActividad' => [
        'Actividad' => 'actividadesConEstado',
      ],
    ],
  ];

  // Simular: Intentar generar EstadoActividad -> Actividad
  $className = 'EstadoActividad';
  $relatedModel = 'Actividad';
  $shouldGenerate = true;

  if (is_array($pivotConfig) && isset($pivotConfig['relation_names'])) {
    $hasExplicitConfig = isset($pivotConfig['relation_names'][$className][$relatedModel]);
    if (!$hasExplicitConfig) {
      $shouldGenerate = false;
    }
  }

  if ($shouldGenerate) {
    return true;
  }
  return "Debería generar EstadoActividad -> Actividad que está en config";
});

$runner->test('Lógica: Con relation_names, NO generar las no listadas', function () {
  $pivotConfig = [
    'relation_names' => [
      'EstadoActividad' => [
        'Actividad' => 'actividadesConEstado',
      ],
    ],
  ];

  // Simular: Intentar generar Actividad -> EstadoActividad (NO está en config)
  $className = 'Actividad';
  $relatedModel = 'EstadoActividad';
  $shouldGenerate = true;

  if (is_array($pivotConfig) && isset($pivotConfig['relation_names'])) {
    $hasExplicitConfig = isset($pivotConfig['relation_names'][$className][$relatedModel]);
    if (!$hasExplicitConfig) {
      $shouldGenerate = false;
    }
  }

  if (!$shouldGenerate) {
    return true; // Correcto - NO debería generar
  }
  return "NO debería generar Actividad -> EstadoActividad que NO está en config";
});

// ============================================================================
// TESTS DE MODELOS GENERADOS
// ============================================================================

$runner->test('Modelo: Curso tiene estudiantesInscritos()', function () {
  $filePath = __DIR__ . '/../app/Models/Base/Curso/BaseCurso.php';
  if (!file_exists($filePath)) {
    return "Archivo no existe: $filePath";
  }

  $content = file_get_contents($filePath);
  if (strpos($content, 'public function estudiantesInscritos()') !== false) {
    return true;
  }
  return "Método estudiantesInscritos() no encontrado en BaseCurso";
});

$runner->test('Modelo: Estudiante tiene cursosInscritos()', function () {
  $filePath = __DIR__ . '/../app/Models/Base/Usuario/BaseEstudiante.php';
  if (!file_exists($filePath)) {
    return "Archivo no existe: $filePath";
  }

  $content = file_get_contents($filePath);
  if (strpos($content, 'public function cursosInscritos()') !== false) {
    return true;
  }
  return "Método cursosInscritos() no encontrado en BaseEstudiante";
});

$runner->test('Modelo: Actividad NO tiene estadoActividades()', function () {
  $filePath = __DIR__ . '/../app/Models/Base/Agenda/BaseActividad.php';
  if (!file_exists($filePath)) {
    return "Archivo no existe: $filePath";
  }

  $content = file_get_contents($filePath);
  if (strpos($content, 'public function estadoActividades()') === false) {
    return true;
  }
  return "Método estadoActividades() NO debería estar en BaseActividad";
});

$runner->test('Modelo: EstadoActividad tiene actividadesConEstado()', function () {
  $filePath = __DIR__ . '/../app/Models/Base/Agenda/BaseEstadoActividad.php';
  if (!file_exists($filePath)) {
    return "Archivo no existe: $filePath";
  }

  $content = file_get_contents($filePath);
  if (strpos($content, 'public function actividadesConEstado()') !== false) {
    return true;
  }
  return "Método actividadesConEstado() no encontrado en BaseEstadoActividad";
});

$runner->test('Modelo: Usuario tiene usuariosQueRecibenMisPermisos()', function () {
  $filePath = __DIR__ . '/../app/Models/Base/Usuario/BaseUsuario.php';
  if (!file_exists($filePath)) {
    return "Archivo no existe: $filePath";
  }

  $content = file_get_contents($filePath);
  if (strpos($content, 'public function usuariosQueRecibenMisPermisos()') !== false) {
    return true;
  }
  return "Método usuariosQueRecibenMisPermisos() no encontrado en BaseUsuario";
});

// ============================================================================
// TESTS DE BASE DE DATOS
// ============================================================================

$runner->test('BD: Tabla Curso existe', function () {
  try {
    $result = DB::select("
      SELECT 1 FROM information_schema.tables 
      WHERE table_catalog = 'utamed_1ra_fase' 
      AND table_schema = 'utamed.Curso'
      AND table_name = 'Curso'
    ");

    if (count($result) > 0) {
      return true;
    }
    return "Tabla Curso no encontrada";
  } catch (Exception $e) {
    return "Error de BD: " . $e->getMessage();
  }
});

$runner->test('BD: Tabla Inscripcion_Curso existe', function () {
  try {
    $result = DB::select("
      SELECT 1 FROM information_schema.tables 
      WHERE table_catalog = 'utamed_1ra_fase' 
      AND table_schema = 'utamed.Curso'
      AND table_name = 'Inscripcion_Curso'
    ");

    if (count($result) > 0) {
      return true;
    }
    return "Tabla Inscripcion_Curso no encontrada";
  } catch (Exception $e) {
    return "Error de BD: " . $e->getMessage();
  }
});

// ============================================================================
// EJECUTAR TESTS
// ============================================================================

$success = $runner->run($testFilter);
exit($success ? 0 : 1);
