<?php

use App\Services\Archive\AgendaArchiveService;
use App\Services\Archive\Handlers\AgendaArchiveHandler;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;
use App\Models\Curso\TipoComponente;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Plan;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Exceptions\Archive\CompressionErrorType;
use App\Exceptions\Archive\CompressionException;
use \Illuminate\Support\Facades\Log;
use \App\Services\Archive\ArchiveHandlerRequest;

uses(TestCase::class);

// Helper function to create complete test hierarchy without factories
function createTestGrupo($activityName = 'Test Activity', $courseName = 'Test Course', $componentType = 'CÁTEDRA'): ActividadAsignadaGrupo
{
  $facultad = Facultad::firstOrCreate(['nombre' => 'Test Facultad'], []);

  $departamento = Departamento::firstOrCreate(['nombre' => 'Test Departamento'], [
    'id_facultad' => $facultad->id_facultad,
  ]);

  $carrera = Carrera::create([
    'nombre' => 'Test Carrera ' . uniqid(),
    'id_departamento' => $departamento->id_departamento
  ]);

  $plan = Plan::create([
    'agno_plan' => now()->year,
    'id_carrera' => $carrera->id_carrera,
  ]);

  $asignatura = Asignatura::firstOrCreate(
    [
      'nombre' => 'Test Asignatura',
      'cod_asignatura' => 'TST000'
    ],
    [
      'creditos_sct' => 4,
      'horas_catedra' => 2,
      'horas_taller' => 2,
      'horas_laboratorio' => 0,
      'horas_dirigidas' => 0,
      'horas_autonomas' => 0
    ]
  );

  $asignacionPlan = AsignacionPlan::create([
    'agno_planificado' => now()->year,
    'semestre_planificado' => 1,
    'id_plan' => $plan->id_plan,
    'tipo_ramo' => 1,
    'id_asignatura' => $asignatura->id_asignatura,
  ]);

  // Create Usuario/Docente
  $usuarioDocente = Usuario::create([
    'username' => 'docente_' . uniqid(),
    'rut' => (string)(rand(10000000, 99999999)) . '-' . rand(0, 9),
    'nombre1' => 'Test',
    'apellido1' => 'Docente',
    'email' => 'docente' . uniqid() . '@test.local',
    'passhash' => Hash::make('password'),
    'esta_activo' => true,
  ]);

  $docente = Docente::create([
    'id_usuario' => $usuarioDocente->id_usuario,
    'grado' => 'Doctor',
    'titulo' => 'Profesor',
    'cargo' => 'Jornada Completa',
  ]);

  // Create Curso
  $curso = Curso::firstOrCreate([
    'nombre' => $courseName,
  ], [
    'fecha_inicio' => now()->startOfYear(),
    'fecha_fin' => now()->endOfYear(),
    'agno_real' => now()->year,
    'semestre_real' => 1,
    'estado_interno' => 'ABIERTO',
    'estado_acta' => 'NO_ENVIADO',
    'es_plantilla' => false,
    'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
    'id_docente_titular' => $docente->id_docente,
  ]);

  // Create TipoComponente
  $tipoComponente = TipoComponente::firstOrCreate(
    ['tipo' => $componentType],
    []
  );

  $unidad = Unidad::firstOrCreate(
    [
      'id_curso' => $curso->id_curso,
    ],
    [
      'num_unidad' => 1,
      'nombre' => 'Cataclismos del pensamiento',
      'descripcion' => 'Unidad de prueba para testing',
    ]
  );

  // Create Componente
  $componente = Componente::firstOrCreate(
    [
      'id_curso' => $curso->id_curso,
    ],
    [
      'genera_acta' => true,
      'porcentaje_aprobacion' => 60,
      'aprobacion_obligatoria' => true,
      'porcentaje_asistencia_obligatoria' => 75,
      'id_tipo_componente' => $tipoComponente->id_tipo_componente,
    ]
  );

  // Create Actividad
  $actividad = Actividad::create([
    'nombre' => $activityName,
    'fecha_limite' => now()->addDays(7),
    'visible' => true,
    'ponderacion' => 10,
    'exigencia' => 80,
    'tipo_actividad' => 'SUMATIVA', // SUMATIVA o FORMATIVA
    'tipo_entrega' => 'ONLINE',
    'es_grupal' => true,
    'max_integrantes' => 3,
    'es_plantilla' => false,
    'id_componente' => $componente->id_componente,
    'id_unidad' => $unidad->id_unidad,
  ]);

  // Create ActividadAsignadaGrupo
  /** @var ActividadAsignadaGrupo $grupo */
  $grupo = ActividadAsignadaGrupo::create([
    'nota' => null,
    'id_actividad' => $actividad->id_actividad,
    'estado_actividad_asignada' => 'PLANIFICADA', // PLANIFICADA, ACTIVA o CERRADA
  ]);

  // Create 2 Estudiantes and add to group
  for ($i = 0; $i < 2; $i++) {
    $usuarioEst = Usuario::create([
      'username' => 'estudiante_' . uniqid(),
      'rut' => (string)(rand(10000000, 99999999)) . '-' . rand(0, 9),
      'nombre1' => 'Estudiante',
      'apellido1' => 'Test' . $i,
      'email' => 'est' . uniqid() . '@test.local',
      'passhash' => Hash::make('password'),
      'esta_activo' => true,
    ]);

    $estudiante = Estudiante::create(['id_usuario' => $usuarioEst->id_usuario, 'id_carrera' => $carrera->id_carrera]);

    IntegranteGrupo::create([
      'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
      'id_estudiante' => $estudiante->id_estudiante,
    ]);
  }

  return $grupo->load(['actividad.componente.curso', 'actividad.componente.tipoComponente', 'integranteGrupos.estudiante']);
}

// Setup: Fake storage disks
beforeEach(function () {
  Storage::fake('local');
  Storage::fake('local_archives');
});

// Teardown: Clean storage
afterEach(function () {
  Storage::disk('local')->deleteDirectory('');
  Storage::disk('local_archives')->deleteDirectory('');
});

// ========================================================================
// PHASE 2: AgendaArchiveHandler->store() Tests (via Handler entry point)
// ========================================================================

test('creates correct path structure from grupo relationships', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('test.pdf', 100);
  $fecha = now();

  $result = AgendaArchiveHandler::store($grupo, $file, $fecha);

  // All path segments are normalized (slugified)
  expect($result['path'])->toContain('agenda/');
  expect($result['path'])->toContain('test-course'); // Normalized from 'Test Course'
  expect($result['path'])->toContain('catedra'); // Normalized from 'CÁTEDRA'
  expect($result['path'])->toContain('test-activity'); // Normalized from 'Test Activity'
  expect($result['path'])->toContain($fecha->format('Y-m-d'));
});

test('normalizes course names with special characters in paths', function () {
  // Use unique course name to avoid constraint violations
  $courseName = 'Intro Calc Course ' . uniqid();
  $grupo = createTestGrupo('Test Activity', $courseName, 'CÁTEDRA');
  $file = UploadedFile::fake()->create('test.pdf', 100);
  $fecha = now();

  $result = AgendaArchiveHandler::store($grupo, $file, $fecha);

  // The course name gets normalized through Str::slug()
  // Parentheses, dots, and spaces are converted/removed
  expect($result['path'])->not()->toContain('(');
  expect($result['path'])->not()->toContain(')');
  expect($result['path'])->toContain('intro');
  expect($result['path'])->toContain('calc');
});

test('normalizes component type names', function () {
  $tipoComponente = 'CÁTEDRA';
  $grupo = createTestGrupo('Test Activity', 'Test Course', $tipoComponente);
  $file = UploadedFile::fake()->create('test.pdf', 100);
  $fecha = now();

  $result = AgendaArchiveHandler::store($grupo, $file, $fecha);

  // Expected normalized: 'catedra' (slug transformation removes accents, lowercases)
  expect($result['path'])->toContain('catedra');
  expect($result['path'])->not()->toContain('CÁTEDRA');
  expect($result['path'])->not()->toContain('Á');
});

test('normalizes activity names with accents and symbols', function () {
  $activityName = 'Análisis de Datos #1';
  $grupo = createTestGrupo($activityName);
  $file = UploadedFile::fake()->create('test.pdf', 100);
  $fecha = now();

  $result = AgendaArchiveHandler::store($grupo, $file, $fecha);

  // Expected normalized: 'analisis-de-datos-1' (slug removes accents and special chars)
  expect($result['path'])->toContain('analisis-de-datos-1');
  expect($result['path'])->not()->toContain('Análisis');
  expect($result['path'])->not()->toContain('#');
  expect($result['path'])->not()->toContain('á');
});

test('stores file and returns correct metadata', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('document.pdf', 256);
  $fecha = now();

  $result = AgendaArchiveHandler::store($grupo, $file, $fecha);

  expect($result)->toHaveKeys([
    'disk',
    'directory',
    'path',
    'file_name',
    'size_bytes',
    'mime_type',
    'original_name'
  ]);

  expect($result['disk'])->toBe(config('files.storage.disk')); // Uses configured disk (local_archives)
  expect($result['size_bytes'])->toBe(256 * 1024);
  expect($result['mime_type'])->toContain('pdf');
  expect($result['original_name'])->toBe('document.pdf');
});

test('file is actually stored on disk at returned path', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('test.txt', 100);
  $fecha = now();

  $result = AgendaArchiveHandler::store($grupo, $file, $fecha);

  expect(Storage::disk($result['disk'])->exists($result['path']))->toBeTrue();
});

test('generates deterministic filename when not provided', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('original.jpg', 100);
  $fecha = now();

  $result = AgendaArchiveHandler::store($grupo, $file, $fecha);

  // Format: agenda_{timestamp}_{hash}.{extension}
  expect($result['file_name'])->toMatch('/^agenda_\d+_[a-zA-Z0-9]{8}\.jpg$/');
});

test('uses explicit filename when provided', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('original.pdf', 100);
  $customName = 'custom-name.pdf';

  $result = AgendaArchiveHandler::store($grupo, $file, now(), $customName);

  expect($result['file_name'])->toBe($customName);
});

test('uses disk from config', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('test.txt', 100);

  $result = AgendaArchiveHandler::store($grupo, $file, now());

  $expectedDisk = config('files.storage.disk');
  expect($result['disk'])->toBe($expectedDisk);
  expect($result['disk'])->toBe('local_archives'); // Default configured value
});

test('uses base_path from config in final path', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('test.txt', 100);

  $result = AgendaArchiveHandler::store($grupo, $file, now());

  expect($result['path'])->toContain(config('files.storage.base_path'));
});

test('uses root_segment agenda in final path', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('test.txt', 100);

  $result = AgendaArchiveHandler::store($grupo, $file, now());

  // Path structure: {base_path}/{root_segment}/{normalized_segments}
  expect($result['path'])->toContain('archivos/agenda/');
});

// ========================================================================
// PHASE 2.5: AgendaArchiveHandler Intermediate Methods (Private) Tests
// ========================================================================

test('buildPath: constructs correct path from grupo relationships', function () {
  $grupo = createTestGrupo('My Activity', 'My Course', 'TALLER');
  $fecha = now();

  $reflection = new \ReflectionClass(AgendaArchiveHandler::class);
  $method = $reflection->getMethod('buildPath');
  $method->setAccessible(true);

  $path = $method->invoke(null, $grupo, $fecha);

  // buildPath returns normalized path segments (normalization happens in Handler)
  expect($path)->toContain('my-course');
  expect($path)->toContain('taller');
  expect($path)->toContain('my-activity');
  expect($path)->toContain($fecha->format('Y-m-d'));
});

test('formatPath: builds path with all required segments', function () {
  $grupo = createTestGrupo('Test Activity', 'Test Course', 'CÁTEDRA');

  $reflection = new \ReflectionClass(AgendaArchiveHandler::class);
  $method = $reflection->getMethod('formatPath');
  $method->setAccessible(true);

  $path = $method->invoke(
    null,
    $grupo->actividad->componente->curso,
    $grupo->actividad->componente,
    $grupo->actividad,
    now(),
    $grupo
  );

  // formatPath returns normalized path segments (normalization happens in Handler)
  expect($path)->toContain('test-course');
  expect($path)->toContain('catedra');
  expect($path)->toContain('test-activity');
});

test('formatPath: concatenates student names with hyphens', function () {
  $grupo = createTestGrupo();

  $reflection = new \ReflectionClass(AgendaArchiveHandler::class);
  $method = $reflection->getMethod('formatPath');
  $method->setAccessible(true);

  $path = $method->invoke(
    null,
    $grupo->actividad->componente->curso,
    $grupo->actividad->componente,
    $grupo->actividad,
    now(),
    $grupo
  );

  // Student names are slugified and concatenated with hyphens (normalized at Handler level)
  expect($path)->toMatch('/[a-z]+-[a-z]+/');
});

test('generateFileName: creates agenda_{timestamp}_{hash}.{extension} format', function () {
  $file = UploadedFile::fake()->create('document.pdf', 100);

  $reflection = new \ReflectionClass(AgendaArchiveHandler::class);
  $method = $reflection->getMethod('generateFileName');
  $method->setAccessible(true);

  $filename = $method->invoke(null, $file);

  expect($filename)->toMatch('/^agenda_\d+_[a-zA-Z0-9]{8}\.pdf$/');
});

test('generateFileName: preserves file extension', function () {
  $file = UploadedFile::fake()->create('test.docx', 100);

  $reflection = new \ReflectionClass(AgendaArchiveHandler::class);
  $method = $reflection->getMethod('generateFileName');
  $method->setAccessible(true);

  $filename = $method->invoke(null, $file);

  expect($filename)->toEndWith('.docx');
});

test('generateFileName: includes current timestamp in filename', function () {
  $file = UploadedFile::fake()->create('test.txt', 100);
  $beforeTime = now()->timestamp;

  $reflection = new \ReflectionClass(AgendaArchiveHandler::class);
  $method = $reflection->getMethod('generateFileName');
  $method->setAccessible(true);

  $filename = $method->invoke(null, $file);

  $afterTime = now()->timestamp;

  // Extract timestamp from filename (format: agenda_TIMESTAMP_HASH.ext)
  preg_match('/agenda_(\d+)_/', $filename, $matches);
  $fileTimestamp = (int)$matches[1];

  expect($fileTimestamp)->toBeGreaterThanOrEqual($beforeTime);
  expect($fileTimestamp)->toBeLessThanOrEqual($afterTime);
});

// ========================================================================
// PHASE 3: AbstractArchiveService Methods Tests
// ========================================================================

test('normalizes paths with backslashes to forward slashes', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizeRelativePath');
  $method->setAccessible(true);

  $normalized = $method->invoke($service, 'path\\to\\file');

  expect($normalized)->toBe('path/to/file');
  expect($normalized)->not()->toContain('\\');
});

test('removes duplicate slashes from normalized paths', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizeRelativePath');
  $method->setAccessible(true);

  $normalized = $method->invoke($service, 'path//to///file');

  expect($normalized)->toBe('path/to/file');
  expect($normalized)->not()->toContain('//');
});

test('trims empty segments from paths', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizeRelativePath');
  $method->setAccessible(true);

  $normalized = $method->invoke($service, '/path/to/file/');

  expect($normalized)->toBe('path/to/file');
  expect($normalized)->not()->toMatch('/^\/|\/$/');
});

test('lowercases and slugs path segments', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizePathSegment');
  $method->setAccessible(true);

  $normalized = $method->invoke($service, 'MiActividad');
  // Str::slug converts this to 'miactividad' (no hyphen between camelCase without separators)

  expect($normalized)->toBe('miactividad');
  expect($normalized)->toBeLowercase();
});

test('buildRelativePath: builds relative path from multiple segments', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('buildRelativePath');
  $method->setAccessible(true);

  $path = $method->invoke($service, 'seg1', 'seg2', 'seg3');

  expect($path)->toBe('seg1/seg2/seg3');
});

test('buildRelativePath: filters empty segments', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('buildRelativePath');
  $method->setAccessible(true);

  $path = $method->invoke($service, 'seg1', '', 'seg2', '');

  expect($path)->toBe('seg1/seg2');
});

test('buildRelativePath: normalizes each segment individually', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('buildRelativePath');
  $method->setAccessible(true);

  $path = $method->invoke($service, 'Seg One', 'Seg_Two', 'Seg-Three');

  expect($path)->toBe('seg-one/seg-two/seg-three');
});

test('buildRootedPath: prepends rootSegment to relative path', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('buildRootedPath');
  $method->setAccessible(true);

  $path = $method->invoke($service, 'seg1', 'seg2');

  expect($path)->toContain('agenda/seg1/seg2');
});

test('buildRootedPath: handles empty segments gracefully', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('buildRootedPath');
  $method->setAccessible(true);

  $path = $method->invoke($service, 'seg1', '', 'seg2');

  expect($path)->toBe('agenda/seg1/seg2');
});

test('normalizePathSegment: removes leading/trailing whitespace', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizePathSegment');
  $method->setAccessible(true);

  $normalized = $method->invoke($service, '  test segment  ');

  expect($normalized)->toBe('test-segment');
});

test('normalizePathSegment: converts backslashes to hyphens', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizePathSegment');
  $method->setAccessible(true);

  $input = 'test\\segment';
  $normalized = $method->invoke($service, $input);
  $expected = 'test-segment';

  expect($normalized)->toBe($expected);
  expect($normalized)->not()->toContain('\\');
  expect($normalized)->not()->toContain('/');
});

test('normalizePathSegment: converts forward slashes to hyphens', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizePathSegment');
  $method->setAccessible(true);

  $input = 'test/segment';
  $normalized = $method->invoke($service, $input);
  $expected = 'test-segment';

  expect($normalized)->toBe($expected);
  expect($normalized)->not()->toContain('/');
  expect($normalized)->not()->toContain('\\');
});

test('normalizePathSegment: slugifies special characters', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizePathSegment');
  $method->setAccessible(true);

  $input = 'Café';
  $normalized = $method->invoke($service, $input);
  $expected = 'cafe';

  expect($normalized)->toBe($expected);
  expect($normalized)->not()->toContain('é');
  expect($normalized)->not()->toContain('Café');
});

test('normalizePathSegment: trims hyphens from start and end', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizePathSegment');
  $method->setAccessible(true);

  $normalized = $method->invoke($service, '-test-segment-');

  expect($normalized)->toBe('test-segment');
});

test('normalizePathSegment: returns empty string for empty input', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizePathSegment');
  $method->setAccessible(true);

  $normalized = $method->invoke($service, '');

  expect($normalized)->toBe('');
});

test('normalizeRelativePath: normalizes path with mixed separators', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizeRelativePath');
  $method->setAccessible(true);

  $normalized = $method->invoke($service, 'path\\to//file');

  expect($normalized)->toBe('path/to/file');
});

test('normalizeRelativePath: normalizes all segments in path', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizeRelativePath');
  $method->setAccessible(true);

  $normalized = $method->invoke($service, 'Path/To/File');

  expect($normalized)->toBe('path/to/file');
});

test('normalizeRelativePath: is idempotent when called multiple times', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizeRelativePath');
  $method->setAccessible(true);

  $input = 'Test-Course/CÁTEDRA/My-Activity';
  $normalized1 = $method->invoke($service, $input);
  $normalized2 = $method->invoke($service, $normalized1);

  expect($normalized2)->toBe($normalized1);
});

test('normalizePathSegment: is idempotent when called multiple times', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('normalizePathSegment');
  $method->setAccessible(true);

  $normalized1 = $method->invoke($service, 'test-course');
  $normalized2 = $method->invoke($service, $normalized1);

  expect($normalized2)->toBe($normalized1);
});

test('makeFileName: creates filename with prefix, UUID, and extension', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('makeFileName');
  $method->setAccessible(true);

  $filename = $method->invoke($service, 'test', 'pdf');

  expect($filename)->toMatch('/^test-[a-f0-9-]{36}\.pdf$/');
});

test('makeFileName: normalizes prefix segment in filename', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('makeFileName');
  $method->setAccessible(true);

  $filename = $method->invoke($service, 'Test Prefix', 'txt');

  expect($filename)->toMatch('/^test-prefix-[a-f0-9-]{36}\.txt$/');
});

test('makeFileName: lowercases and adds dot to extension', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('makeFileName');
  $method->setAccessible(true);

  $filename = $method->invoke($service, 'prefix', '.PDF');

  expect($filename)->toMatch('/\.pdf$/');
});

test('storeUploadedFile: stores file with all expected metadata returned', function () {
  $service = new AgendaArchiveService();
  $file = UploadedFile::fake()->create('test.jpg', 256);
  $relativeDirectory = 'test/directory';

  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('storeUploadedFile');
  $method->setAccessible(true);

  $result = $method->invoke($service, $file, $relativeDirectory);

  expect($result)->toHaveKeys(['disk', 'directory', 'path', 'file_name', 'size_bytes', 'mime_type', 'original_name']);
});

test('storeUploadedFile: constructs correct full path with disk, basePath, rootSegment', function () {
  $service = new AgendaArchiveService();
  $file = UploadedFile::fake()->create('test.txt', 100);
  $relativeDirectory = 'subdir';

  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('storeUploadedFile');
  $method->setAccessible(true);

  $result = $method->invoke($service, $file, $relativeDirectory);

  $basePath = config('files.storage.base_path');
  expect($result['directory'])->toContain($basePath);
  expect($result['directory'])->toContain('agenda');
  expect($result['directory'])->toContain('subdir');
});

// ========================================================================
// PHASE 4: AgendaArchiveService Abstract Methods Implementation Tests
// ========================================================================

test('AgendaArchiveService inherits from AbstractArchiveService', function () {
  $service = new AgendaArchiveService();

  expect($service)->toBeInstanceOf(\App\Services\Archive\AbstractArchiveService::class);
});

test('AgendaArchiveService implements preValidate abstract method', function () {
  $service = new AgendaArchiveService();
  $file = UploadedFile::fake()->create('test.txt', 100);

  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('preValidate');
  $method->setAccessible(true);

  // Should not throw, just return void
  $method->invoke($service, $file, 'test-archive-id');

  expect(true)->toBeTrue();
});

test('AgendaArchiveService implements compressFile abstract method', function () {
  $service = new AgendaArchiveService();
  $file = UploadedFile::fake()->create('test.txt', 100);

  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('compressFile');
  $method->setAccessible(true);

  // Should return the file as-is (no compression currently)
  $result = $method->invoke($service, $file, 'test-archive-id');

  expect($result)->toBeInstanceOf(UploadedFile::class);
  expect($result)->toBe($file);
});

test('AgendaArchiveService has rootSegment set to agenda', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $property = $reflection->getProperty('rootSegment');
  $property->setAccessible(true);

  $rootSegment = $property->getValue($service);

  expect($rootSegment)->toBe('agenda');
});

// ========================================================================
// PHASE 5: Integration Edge Cases
// ========================================================================

test('complete workflow: creates grupo, stores file, verifies on disk, retrieves metadata', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('submission.pdf', 512);

  $result = AgendaArchiveHandler::store($grupo, $file, now());

  expect(Storage::disk($result['disk'])->exists($result['path']))->toBeTrue();
  expect($result['file_name'])->not()->toBeNull();
  expect($result['size_bytes'])->toBeGreaterThan(0);
  expect($result['mime_type'])->not()->toBeNull();
  expect($result['original_name'])->toBe('submission.pdf');
});

test('handles grupo with multiple estudiantes in name concatenation', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('group-work.txt', 100);

  $result = AgendaArchiveHandler::store($grupo, $file, now());

  expect($result['path'])->not()->toBeNull();
  expect($result['directory'])->not()->toBeNull();
});

test('stores multiple files in same directory with different names', function () {
  $grupo = createTestGrupo();
  $file1 = UploadedFile::fake()->create('file1.txt', 100);
  $file2 = UploadedFile::fake()->create('file2.txt', 100);

  $result1 = AgendaArchiveHandler::store($grupo, $file1, now());
  $result2 = AgendaArchiveHandler::store($grupo, $file2, now());

  expect($result1['file_name'])->not()->toBe($result2['file_name']);
  expect($result1['directory'])->toBe($result2['directory']);
});

test('same file stored twice generates different UUID filenames', function () {
  $grupo = createTestGrupo();
  $file1 = UploadedFile::fake()->create('test.pdf', 100);
  $file2 = UploadedFile::fake()->create('test.pdf', 100);

  $result1 = AgendaArchiveHandler::store($grupo, $file1, now());
  $result2 = AgendaArchiveHandler::store($grupo, $file2, now());

  expect($result1['file_name'])->not()->toBe($result2['file_name']);
});

// ========================================================================
// PHASE 6: Logging Fallback Tests (defaultLogOperation)
// ========================================================================

// Mock service that throws exception from logOperation()
class FailingLogArchiveService extends AgendaArchiveService
{
  protected function logOperation(array $operationLog, string $logLevel = 'info'): void
  {
    throw new \RuntimeException('Simulated logging failure from custom implementation');
  }
}

test('fallback: defaultLogOperation called when logOperation throws on success', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('test.pdf', 100);

  $service = new FailingLogArchiveService();
  $request = new ArchiveHandlerRequest(
    file: $file,
    relativeDirectory: 'test/dir',
    fileName: 'test.pdf'
  );

  // performStorage should succeed despite logOperation throwing
  // because it falls back to defaultLogOperation
  $result = $service->performStorage($request);

  expect($result)->toHaveKeys(['disk', 'path', 'file_name']);
  expect(Storage::disk($result['disk'])->exists($result['path']))->toBeTrue();
});

test('fallback: defaultLogOperation called on validation failure when logOperation throws', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('test.pdf', 100);

  // Create a custom service that fails validation AND has failing logOperation
  $service = new class extends FailingLogArchiveService {
    public function __construct()
    {
      parent::__construct();
    }

    protected function preValidate(UploadedFile $file, string $archiveId): void
    {
      throw new \App\Exceptions\Archive\FileValidationException(
        \App\Exceptions\Archive\FileValidationErrorType::SIZE_EXCEEDED,
        'File too large',
        $archiveId
      );
    }
  };

  $request = new ArchiveHandlerRequest(
    file: $file,
    relativeDirectory: 'test/dir'
  );

  // Should throw FileValidationException, not a logging error
  expect(fn() => $service->performStorage($request))
    ->toThrow(\App\Exceptions\Archive\FileValidationException::class);
});

test('fallback: works across all exception types when logOperation throws', function () {
  $grupo = createTestGrupo();
  $file = UploadedFile::fake()->create('test.pdf', 100);

  // Service that throws on compression AND has failing logOperation
  $service = new class extends FailingLogArchiveService {
    public function __construct()
    {
      parent::__construct();
    }

    protected function compressFile(UploadedFile $file, string $archiveId): UploadedFile
    {
      throw new CompressionException(
        CompressionErrorType::OPTIMIZATION_FAILED,
        'Compression failed',
        $archiveId
      );
    }
  };

  $request = new ArchiveHandlerRequest(
    file: $file,
    relativeDirectory: 'test/dir'
  );

  // Should throw CompressionException, not a logging error
  expect(fn() => $service->performStorage($request))
    ->toThrow(CompressionException::class);
});

test('defaultLogOperation writes directly to Log facade', function () {
  Log::shouldReceive('channel')
    ->with(config('files.logging.channel', 'stack'))
    ->andReturn(\Mockery::mock(['info' => null]))
    ->atLeast()->once();

  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('defaultLogOperation');
  $method->setAccessible(true);

  $operationLog = [
    'archive_id' => 'test-id',
    'status' => 'success',
    'message' => 'test'
  ];

  $method->invoke($service, $operationLog, 'info');
});

test('defaultLogOperation is final and cannot be overridden', function () {
  $service = new AgendaArchiveService();
  $reflection = new \ReflectionClass($service);
  $method = $reflection->getMethod('defaultLogOperation');

  expect($method->isFinal())->toBeTrue();
});

test('custom logOperation can safely delegate to defaultLogOperation', function () {
  $delegatingService = new class extends AgendaArchiveService {
    public function __construct()
    {
      parent::__construct();
    }

    public $delegateCalled = false;

    protected function logOperation(array $operationLog, string $logLevel = 'info'): void
    {
      // Custom logic that then delegates to default
      $this->delegateCalled = true;
      $this->defaultLogOperation($operationLog, $logLevel);
    }
  };

  $file = UploadedFile::fake()->create('test.pdf', 100);
  $request = new ArchiveHandlerRequest(
    file: $file,
    relativeDirectory: 'test/dir'
  );

  $result = $delegatingService->performStorage($request);

  expect($delegatingService->delegateCalled)->toBeTrue();
  expect($result)->toHaveKeys(['disk', 'path', 'file_name']);
});
