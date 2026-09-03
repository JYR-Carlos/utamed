<?php

use App\Http\Requests\Archive\BibliografiaFileRequest;
use App\Models\Curso\Bibliografia;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Curso\Unidad;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Usuario;
use App\Services\Archive\Handlers\SyllabusArchiveHandler;
use App\Services\Archive\SyllabusArchiveService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

// ============================================================================
// SETUP
// ============================================================================

beforeEach(function () {
  $this->disk = config('files.storage.disk', 'local_archives');
  Storage::fake($this->disk);
  config(['database.connections.pgsql.search_path' => 'usuario, agenda, administrativo, curso, public, auditoria, operaciones']);
  \Illuminate\Support\Facades\DB::purge('pgsql');
});

// ============================================================================
// TESTS: BibliografiaFileRequest Validation Rules & Helpers
// ============================================================================

describe('BibliografiaFileRequest - Validation Rules & Structure', function () {
  test('request includes required fields: archivo and id_curso', function () {
    $request = new BibliografiaFileRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKey('archivo');
    expect($rules)->toHaveKey('id_curso');

    $archivoRules = $rules['archivo'];
    expect($archivoRules)->toContain('required');
    expect($archivoRules)->toContain('file');

    $cursoRules = $rules['id_curso'];
    expect($cursoRules)->toContain('required');
    expect($cursoRules)->toContain('integer');
  });

  test('request includes optional metadata: id_unidad, id_programa, nombre_archivo, titulo, autor', function () {
    $request = new BibliografiaFileRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKey('id_unidad');
    expect($rules)->toHaveKey('id_programa');
    expect($rules)->toHaveKey('nombre_archivo');
    expect($rules)->toHaveKey('titulo');
    expect($rules)->toHaveKey('autor');

    expect($rules['id_unidad'])->toContain('nullable');
    expect($rules['id_programa'])->toContain('nullable');
    expect($rules['titulo'])->toContain('max:255');
    expect($rules['autor'])->toContain('max:255');
  });

  test('nombre_archivo validates allowed characters and rejects path traversal', function () {
    $request = new BibliografiaFileRequest();
    $rules = $request->rules();

    $nameRules = ['nombre_archivo' => $rules['nombre_archivo']];

    // Nombres válidos
    $validValidator = Validator::make(['nombre_archivo' => 'Libro_Medicina-2026 v1.0.pdf'], $nameRules);
    expect($validValidator->passes())->toBeTrue();

    // Intentos de path traversal y caracteres peligrosos
    $invalid1 = Validator::make(['nombre_archivo' => '../../etc/passwd'], $nameRules);
    expect($invalid1->fails())->toBeTrue();

    $invalid2 = Validator::make(['nombre_archivo' => '<script>alert(1)</script>'], $nameRules);
    expect($invalid2->fails())->toBeTrue();

    $invalid3 = Validator::make(['nombre_archivo' => 'archivo;rm -rf /'], $nameRules);
    expect($invalid3->fails())->toBeTrue();
  });

  test('helper methods correctly extract typed input values', function () {
    $request = new BibliografiaFileRequest();
    $request->merge([
      'id_curso' => '42',
      'id_unidad' => '3',
      'id_programa' => '7',
      'nombre_archivo' => 'bioquimica_clinica',
    ]);

    expect($request->getCursoId())->toBe(42);
    expect($request->getUnidadId())->toBe(3);
    expect($request->getProgramaId())->toBe(7);
    expect($request->getFileName())->toBe('bioquimica_clinica');
  });
});

// ============================================================================
// TESTS: SyllabusArchiveService preValidate - Allowed Document Types
// ============================================================================

describe('SyllabusArchiveService - preValidate Allowed Academic Types', function () {
  test('preValidate accepts standard PDF files', function () {
    $service = new SyllabusArchiveService();
    $reflector = new ReflectionMethod($service, 'preValidate');
    $reflector->setAccessible(true);

    $pdf = UploadedFile::fake()->create('guia_clinica.pdf', 1024, 'application/pdf');

    expect(fn () => $reflector->invoke($service, $pdf, 'archive-test-1'))->not->toThrow(\Throwable::class);
  });

  test('preValidate accepts modern Word documents (.docx)', function () {
    $service = new SyllabusArchiveService();
    $reflector = new ReflectionMethod($service, 'preValidate');
    $reflector->setAccessible(true);

    $docx = UploadedFile::fake()->create(
      'protocolo.docx',
      512,
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    );

    expect(fn () => $reflector->invoke($service, $docx, 'archive-test-2'))->not->toThrow(\Throwable::class);
  });

  test('preValidate accepts legacy Word documents (.doc)', function () {
    $service = new SyllabusArchiveService();
    $reflector = new ReflectionMethod($service, 'preValidate');
    $reflector->setAccessible(true);

    $doc = UploadedFile::fake()->create('apuntes.doc', 256, 'application/msword');

    expect(fn () => $reflector->invoke($service, $doc, 'archive-test-3'))->not->toThrow(\Throwable::class);
  });

  test('preValidate accepts PowerPoint presentations (.pptx and .ppt)', function () {
    $service = new SyllabusArchiveService();
    $reflector = new ReflectionMethod($service, 'preValidate');
    $reflector->setAccessible(true);

    $pptx = UploadedFile::fake()->create(
      'clase_cardiologia.pptx',
      2048,
      'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    );
    expect(fn () => $reflector->invoke($service, $pptx, 'archive-test-4'))->not->toThrow(\Throwable::class);

    $ppt = UploadedFile::fake()->create('clase_neurologia.ppt', 1024, 'application/vnd.ms-powerpoint');
    expect(fn () => $reflector->invoke($service, $ppt, 'archive-test-5'))->not->toThrow(\Throwable::class);
  });

  test('preValidate accepts digital publications (.epub)', function () {
    $service = new SyllabusArchiveService();
    $reflector = new ReflectionMethod($service, 'preValidate');
    $reflector->setAccessible(true);

    $epub = UploadedFile::fake()->create('manual.epub', 512, 'application/epub+zip');

    expect(fn () => $reflector->invoke($service, $epub, 'archive-test-6'))->not->toThrow(\Throwable::class);
  });

  test('preValidate accepts OpenDocument text files (.odt)', function () {
    $service = new SyllabusArchiveService();
    $reflector = new ReflectionMethod($service, 'preValidate');
    $reflector->setAccessible(true);

    $odt = UploadedFile::fake()->create('resumen.odt', 300, 'application/vnd.oasis.opendocument.text');

    expect(fn () => $reflector->invoke($service, $odt, 'archive-test-7'))->not->toThrow(\Throwable::class);
  });
});

// ============================================================================
// TESTS: SyllabusArchiveService preValidate - Rejected Unsafe or Bad Types
// ============================================================================

describe('SyllabusArchiveService - preValidate Rejections & Security', function () {
  test('preValidate rejects executable binaries (.exe)', function () {
    $service = new SyllabusArchiveService();
    $reflector = new ReflectionMethod($service, 'preValidate');
    $reflector->setAccessible(true);

    $exe = UploadedFile::fake()->create('virus.exe', 1024, 'application/x-msdownload');

    expect(fn () => $reflector->invoke($service, $exe, 'test-sec-1'))->toThrow(\TypeError::class);
  });

  test('preValidate rejects PHP server scripts (.php)', function () {
    $service = new SyllabusArchiveService();
    $reflector = new ReflectionMethod($service, 'preValidate');
    $reflector->setAccessible(true);

    $php = UploadedFile::fake()->create('backdoor.php', 10, 'application/x-php');

    expect(fn () => $reflector->invoke($service, $php, 'test-sec-2'))->toThrow(\TypeError::class);
  });

  test('preValidate rejects Shell script files (.sh)', function () {
    $service = new SyllabusArchiveService();
    $reflector = new ReflectionMethod($service, 'preValidate');
    $reflector->setAccessible(true);

    $sh = UploadedFile::fake()->create('deploy.sh', 10, 'text/x-shellscript');

    expect(fn () => $reflector->invoke($service, $sh, 'test-sec-3'))->toThrow(\TypeError::class);
  });

  test('preValidate rejects completely empty files (0 bytes)', function () {
    $service = new SyllabusArchiveService();
    $reflector = new ReflectionMethod($service, 'preValidate');
    $reflector->setAccessible(true);

    $empty = UploadedFile::fake()->create('vacio.pdf', 0, 'application/pdf');

    expect(fn () => $reflector->invoke($service, $empty, 'test-sec-4'))->toThrow(\TypeError::class);
  });

  test('preValidate rejects files exceeding category max size (> 100MB)', function () {
    $service = new SyllabusArchiveService();
    $reflector = new ReflectionMethod($service, 'preValidate');
    $reflector->setAccessible(true);

    // Archivo que excede el límite máximo de 100MB
    $huge = UploadedFile::fake()->create('pesado.pdf', (104857600 / 1024) + 1024, 'application/pdf');

    expect(fn () => $reflector->invoke($service, $huge, 'test-sec-5'))->toThrow(\TypeError::class);
  });

  test('preValidate rejects file extension and MIME type contradictions', function () {
    $service = new SyllabusArchiveService();
    $reflector = new ReflectionMethod($service, 'preValidate');
    $reflector->setAccessible(true);

    // Archivo con extensión .pdf pero MIME image/jpeg (no permitido para documentos)
    $spoofed = UploadedFile::fake()->create('falso.pdf', 100, 'image/jpeg');

    expect(fn () => $reflector->invoke($service, $spoofed, 'test-sec-6'))->toThrow(\TypeError::class);
  });
});

// ============================================================================
// TESTS: SyllabusArchiveHandler Path Generation & Structure
// ============================================================================

describe('SyllabusArchiveHandler - Path Hierarchy & Slugification', function () {
  test('generates canonical hierarchical path with course, unit, and versioned program', function () {
    $curso = new Curso([
      'nombre' => 'Medicina Interna I',
      'agno_real' => 2026,
      'semestre_real' => 1,
    ]);
    $curso->id_curso = 10;

    $programa = new Programa([
      'version_programa' => '1.0',
    ]);
    $programa->id_programa = 5;

    $unidad = new Unidad([
      'num_unidad' => 2,
      'nombre' => 'Cardiología Clínica',
    ]);

    $path = SyllabusArchiveHandler::buildPath($curso, $unidad, $programa);

    expect($path)->toBe('2026-s1/medicina-interna-i/programa-10/bibliografias/u2-cardiologia-clinica');
  });

  test('cleans and slugifies complex names with accents, symbols, and irregular spacing', function () {
    $curso = new Curso([
      'nombre' => '  Farmacología & Terapéutica Clínica Avanzada  ',
      'agno_real' => 2025,
      'semestre_real' => 2,
    ]);
    $curso->id_curso = 15;

    $programa = new Programa([
      'version_programa' => '2.1-final',
    ]);
    $programa->id_programa = 8;

    $unidad = new Unidad([
      'num_unidad' => 4,
      'nombre' => 'Antiinflamatorios, Analgésicos & Opioides (Módulo IV)',
    ]);

    $path = SyllabusArchiveHandler::buildPath($curso, $unidad, $programa);

    expect($path)->toBe('2025-s2/farmacologia-terapeutica-clinica-avanzada/programa-21-final/bibliografias/u4-antiinflamatorios-analgesicos-opioides-modulo-iv');
  });

  test('handles draft syllabus state with fallback to borrador', function () {
    $curso = new Curso([
      'nombre' => 'Cirugía General',
      'agno_real' => 2026,
      'semestre_real' => 1,
    ]);
    $curso->id_curso = 20;

    $unidad = new Unidad([
      'num_unidad' => 1,
      'nombre' => 'Técnicas Quirúrgicas',
    ]);

    // Programa es null porque el syllabus aún se está diseñando
    $path = SyllabusArchiveHandler::buildPath($curso, $unidad, null);

    expect($path)->toBe('2026-s1/cirugia-general/borrador/bibliografias/u1-tecnicas-quirurgicas');
  });

  test('handles general course bibliography when no unit is selected', function () {
    $curso = new Curso([
      'nombre' => 'Bioética Médica',
      'agno_real' => 2026,
      'semestre_real' => 2,
    ]);
    $curso->id_curso = 30;

    $programa = new Programa([
      'version_programa' => '1.0',
    ]);
    $programa->id_programa = 12;

    // Unidad es null (aplica para todo el curso)
    $path = SyllabusArchiveHandler::buildPath($curso, null, $programa);

    expect($path)->toBe('2026-s2/bioetica-medica/programa-10/bibliografias/general');
  });

  test('handles missing academic period attributes with fallback to general', function () {
    $curso = new Curso([
      'nombre' => 'Curso Intersemestral',
      'agno_real' => null,
      'semestre_real' => null,
    ]);
    $curso->id_curso = 40;

    $path = SyllabusArchiveHandler::buildPath($curso, null, null);

    expect($path)->toBe('general/curso-intersemestral/borrador/bibliografias/general');
  });

  test('generates secure deterministic filename matching bib_{timestamp}_{hash}', function () {
    $reflection = new ReflectionClass(SyllabusArchiveHandler::class);
    $method = $reflection->getMethod('generateFileName');
    $method->setAccessible(true);

    $name1 = $method->invoke(null);
    $name2 = $method->invoke(null);

    // Formato: bib_{timestamp}_{random8}
    expect($name1)->toMatch('/^bib_\d+_[a-zA-Z0-9]{8}$/');
    expect($name2)->toMatch('/^bib_\d+_[a-zA-Z0-9]{8}$/');
    expect($name1)->not->toBe($name2);
  });
});

// ============================================================================
// TESTS: End-to-End Pipeline Execution (storeBibliografia)
// ============================================================================

describe('SyllabusArchiveHandler - End-to-End Store Execution', function () {
  test('storeBibliografia saves file to disk and registers Archivo in database', function () {
    $curso = new Curso([
      'nombre' => 'Patología Especial',
      'agno_real' => 2026,
      'semestre_real' => 1,
    ]);
    $curso->id_curso = 10;

    $programa = new Programa([
      'id_curso' => 10,
      'version_programa' => '1.0',
    ]);
    $programa->id_programa = 5;

    $unidad = new Unidad([
      'id_curso' => 10,
      'num_unidad' => 1,
      'nombre' => 'Patología Cardiovascular',
    ]);
    $unidad->id_unidad = 2;

    $pdfContent = '%PDF-1.4 Fake PDF Content for Unit Test';
    $file = UploadedFile::fake()->createWithContent('libro_patologia.pdf', $pdfContent);

    $result = SyllabusArchiveHandler::storeBibliografia(
      curso: $curso,
      file: $file,
      unidad: $unidad,
      programa: $programa,
      fileName: 'patologia_cardiovascular_2026'
    );

    // 1. Verificar resultado de almacenamiento
    expect($result->uuidArchivo)->toBeString();
    expect($result->originalName)->toBe('libro_patologia.pdf');
    expect($result->mimeType)->toBe('application/pdf');
    expect($result->sizeBytes)->toBeGreaterThan(0);

    // 2. Verificar que el archivo físico fue escrito en el disco correcto
    $disk = config('files.storage.disk', 'local_archives');
    expect(Storage::disk($disk)->exists($result->path))->toBeTrue();

    // 3. Verificar que se creó el registro correspondiente en la tabla archivo
    $archivo = Archivo::find($result->uuidArchivo);
    expect($archivo)->not->toBeNull();
    expect($archivo->uuid_archivo)->toBe($result->uuidArchivo);
    expect($archivo->ruta_fisica)->toBe($result->path);
    expect($archivo->nombre_original)->toBe('libro_patologia.pdf');
    expect($archivo->mime_type)->toBe('application/pdf');
    expect($archivo->pendiente_de_borrado)->toBeFalse();
  });

  test('storeBibliografia with null custom filename uses secure autogenerated name', function () {
    $curso = new Curso([
      'nombre' => 'Microbiología Clínica',
      'agno_real' => 2026,
      'semestre_real' => 2,
    ]);
    $curso->id_curso = 11;

    $file = UploadedFile::fake()->create('guia_laboratorio.pdf', 300, 'application/pdf');

    $result = SyllabusArchiveHandler::storeBibliografia(
      curso: $curso,
      file: $file,
      unidad: null,
      programa: null,
      fileName: null
    );

    expect($result->fileName)->toMatch('/^bib_\d+_[a-zA-Z0-9]{8}\.pdf$/');
    $disk = config('files.storage.disk', 'local_archives');
    expect(Storage::disk($disk)->exists($result->path))->toBeTrue();
  });

  test('storeBibliografia preserves binary content and byte size exactly', function () {
    $curso = new Curso([
      'nombre' => 'Neurología Básica',
      'agno_real' => 2026,
      'semestre_real' => 1,
    ]);
    $curso->id_curso = 12;

    $exactBinaryContent = "%PDF-1.7\n%Exact binary content for medical syllabus reference\n%%EOF";
    $file = UploadedFile::fake()->createWithContent('neuro_atlas.pdf', $exactBinaryContent);

    $result = SyllabusArchiveHandler::storeBibliografia(
      curso: $curso,
      file: $file
    );

    $disk = config('files.storage.disk', 'local_archives');
    $storedContent = Storage::disk($disk)->get($result->path);

    expect($storedContent)->toBe($exactBinaryContent);
    expect($result->sizeBytes)->toBe(strlen($exactBinaryContent));
  });

  test('storeBibliografia successfully processes and stores Word (.docx) documents', function () {
    $curso = new Curso([
      'nombre' => 'Epidemiología y Salud Pública',
      'agno_real' => 2026,
      'semestre_real' => 1,
    ]);
    $curso->id_curso = 13;

    $file = UploadedFile::fake()->create(
      'caso_clinico_brote.docx',
      800,
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    );

    $result = SyllabusArchiveHandler::storeBibliografia(
      curso: $curso,
      file: $file,
      fileName: 'caso_estudio_dengue'
    );

    expect($result->mimeType)->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    expect($result->fileName)->toBe('caso_estudio_dengue.docx');

    $disk = config('files.storage.disk', 'local_archives');
    expect(Storage::disk($disk)->exists($result->path))->toBeTrue();
  });

  test('storeBibliografia successfully processes and stores PowerPoint (.pptx) presentations', function () {
    $curso = new Curso([
      'nombre' => 'Pediatría Integral',
      'agno_real' => 2026,
      'semestre_real' => 2,
    ]);
    $curso->id_curso = 14;

    $file = UploadedFile::fake()->create(
      'diapositivas_vacunacion.pptx',
      1200,
      'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    );

    $result = SyllabusArchiveHandler::storeBibliografia(
      curso: $curso,
      file: $file,
      fileName: 'clase_inmunizaciones_pediatricas'
    );

    expect($result->mimeType)->toBe('application/vnd.openxmlformats-officedocument.presentationml.presentation');
    expect($result->fileName)->toBe('clase_inmunizaciones_pediatricas.pptx');

    $disk = config('files.storage.disk', 'local_archives');
    expect(Storage::disk($disk)->exists($result->path))->toBeTrue();
  });

  test('orphaned physical file tracking can flag obsolete archives for garbage collection', function () {
    $curso = new Curso([
      'nombre' => 'Histología Médica',
      'agno_real' => 2026,
      'semestre_real' => 1,
    ]);
    $curso->id_curso = 15;

    $file = UploadedFile::fake()->create('atlas_primer_borrador.pdf', 400, 'application/pdf');

    $result = SyllabusArchiveHandler::storeBibliografia(curso: $curso, file: $file);
    $archivo = Archivo::find($result->uuidArchivo);

    expect($archivo->pendiente_de_borrado)->toBeFalse();

    // Simular reemplazo de archivo en el syllabus
    $archivo->update(['pendiente_de_borrado' => true]);
    $archivo->refresh();

    expect($archivo->pendiente_de_borrado)->toBeTrue();
  });
});
