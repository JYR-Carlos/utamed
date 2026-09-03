<?php

use App\Http\Requests\Archive\ActivityFileRequest;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;
use App\Services\Archive\ActivityArchiveService;
use App\Services\Archive\Handlers\ActivityArchiveHandler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ============================================================================
// SETUP
// ============================================================================

beforeEach(function () {
  // Usar storage fake para no afectar sistema de archivos real
  Storage::fake('local');
});

// ============================================================================
// TESTS: ActivityFileRequest Validation Rules
// ============================================================================

describe('ActivityFileRequest - Validation Rules', function () {
  test('includes required fields in validation rules', function () {
    $request = new ActivityFileRequest();
    $rules = $request->rules();

    expect($rules)->toHaveKey('archivo');
  });

  test('includes optional metadata fields', function () {
    $request = new ActivityFileRequest();
    $rules = $request->rules();

    // Comunes
    expect($rules)->toHaveKey('titulo');
    expect($rules)->toHaveKey('descripcion');

    // Imagen
    expect($rules)->toHaveKey('etiquetas');
    expect($rules)->toHaveKey('etiquetas.*');

    // PDF
    expect($rules)->toHaveKey('autor');
    expect($rules)->toHaveKey('numero_paginas');

    // Nombre personalizado
    expect($rules)->toHaveKey('nombre_archivo');
  });

  test('archivo field includes required validation', function () {
    $request = new ActivityFileRequest();
    $rules = $request->rules();

    $archivoRules = $rules['archivo'];

    expect($archivoRules)->toContain('required');
    expect($archivoRules)->toContain('file');
  });

  test('numero_paginas has min and max constraints', function () {
    $request = new ActivityFileRequest();
    $rules = $request->rules();

    $paginasRules = $rules['numero_paginas'];

    expect($paginasRules)->toContain('nullable');
    expect($paginasRules)->toContain('integer');
    expect($paginasRules)->toContain('min:1');
    expect($paginasRules)->toContain('max:5000');
  });

  test('etiquetas allows max 10 entries', function () {
    $request = new ActivityFileRequest();
    $rules = $request->rules();

    $etiquetasRules = $rules['etiquetas'];

    expect($etiquetasRules)->toContain('max:10');
  });

  test('titulo and descripcion have max length constraints', function () {
    $request = new ActivityFileRequest();
    $rules = $request->rules();

    expect($rules['titulo'])->toContain('max:255');
    expect($rules['descripcion'])->toContain('max:1000');
  });
});

// ============================================================================
// TESTS: ActivityFileRequest Helper Methods
// ============================================================================

describe('ActivityFileRequest - Helper Methods', function () {
  test('getFileName returns custom name or null', function () {
    $request = new ActivityFileRequest();

    // Sin nombre
    $request->merge(['nombre_archivo' => null]);
    expect($request->getFileName())->toBeNull();

    // Con nombre
    $request->merge(['nombre_archivo' => 'my-file.pdf']);
    expect($request->getFileName())->toBe('my-file.pdf');
  });

  test('getTitle returns title or null', function () {
    $request = new ActivityFileRequest();

    $request->merge(['titulo' => null]);
    expect($request->getTitle())->toBeNull();

    $request->merge(['titulo' => 'My Title']);
    expect($request->getTitle())->toBe('My Title');
  });

  test('getDescription returns description or null', function () {
    $request = new ActivityFileRequest();

    $request->merge(['descripcion' => null]);
    expect($request->getDescription())->toBeNull();

    $request->merge(['descripcion' => 'My Description']);
    expect($request->getDescription())->toBe('My Description');
  });
});

// ============================================================================
// TESTS: ActivityArchiveService preValidate Coverage
// ============================================================================

describe('ActivityArchiveService - preValidate Coverage', function () {
  test('preValidate accepts valid PNG image', function () {
    $service = new ActivityArchiveService();

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = UploadedFile::fake()->create('test.png', 100, 'image/png');

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->not->toThrow(\TypeError::class);
  });

  test('preValidate accepts valid JPEG image', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->not->toThrow(\TypeError::class);
  });

  test('preValidate accepts valid PDF', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->not->toThrow(\TypeError::class);
  });

  test('preValidate rejects unsupported file type', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = UploadedFile::fake()->create('datos.xlsx', 100, 'application/vnd.ms-excel');

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->toThrow(\TypeError::class);
  });

  test('preValidate rejects image exceeding size limit', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    config(['filetypes.image.max_size' => 52428800]); // 50MB
    $file = UploadedFile::fake()->create('huge.jpg', 52428800 / 1024 + 1, 'image/jpeg');

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->toThrow(\TypeError::class);
  });

  test('preValidate rejects PDF exceeding size limit', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    config(['filetypes.pdf.max_size' => 5242880]); // 5MB limit
    $file = UploadedFile::fake()->create('huge.pdf', 5242880 / 1024 + 1, 'application/pdf');

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->toThrow(\TypeError::class);
  });

  test('preValidate rejects empty file', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = UploadedFile::fake()->create('empty.jpg', 0, 'image/jpeg');

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->toThrow(\TypeError::class);
  });

  test('preValidate rejects MIME type mismatch', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = UploadedFile::fake()->create('falso.jpg', 100, 'text/plain');

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->toThrow(\TypeError::class);
  });
});

// ============================================================================
// TESTS: ActivityArchiveHandler Path Generation
// ============================================================================

describe('ActivityArchiveHandler - Path Generation', function () {
  test('generates correct path with all relationships', function () {
    $curso = new Curso(['nombre' => 'Matemática Avanzada']);
    
    $tipoComponente = new class {
      public $tipo = 'Evaluación';
    };

    $componente = new Componente();
    $componente->setRelation('tipoComponente', $tipoComponente);
    $componente->setRelation('curso', $curso);

    $unidad = new Unidad(['nombre' => 'Unidad 1']);
    $unidad->setRelation('curso', $curso);

    $actividad = new Actividad(['nombre' => 'Examen Final']);
    $actividad->id_actividad = 1;
    $actividad->setRelation('componente', $componente);
    $actividad->setRelation('unidad', $unidad);

    $reflection = new ReflectionClass(ActivityArchiveHandler::class);
    $method = $reflection->getMethod('buildPath');
    $method->setAccessible(true);

    $path = $method->invoke(null, $actividad);

    // Esperado: actividad/matematica-avanzada/evaluacion/unidad-1/examen-final
    expect($path)->toBe('actividad/matematica-avanzada/evaluacion/unidad-1/examen-final');
  });

  test('generates filename with correct format', function () {
    $file = UploadedFile::fake()->create('test.png', 100, 'image/png');

    $reflection = new ReflectionClass(ActivityArchiveHandler::class);
    $method = $reflection->getMethod('generateFileName');
    $method->setAccessible(true);

    $fileName = $method->invoke(null, $file);

    // Esperado formato determinístico sin extensión: activity_{timestamp}_{random}
    expect($fileName)->toMatch('/^activity_\d+_[a-zA-Z0-9]{8}$/');
  });

  test('throws InvalidArgumentException if componente missing', function () {
    $curso = new Curso(['nombre' => 'Matemática']);
    $unidad = new Unidad(['nombre' => 'Unidad 1']);
    $unidad->setRelation('curso', $curso);

    $actividad = new Actividad(['nombre' => 'Tarea 1']);
    $actividad->id_actividad = 123;
    $actividad->setRelation('unidad', $unidad);
    $actividad->setRelation('componente', null);

    $reflection = new ReflectionClass(ActivityArchiveHandler::class);
    $method = $reflection->getMethod('buildPath');
    $method->setAccessible(true);

    expect(function () use ($method, $actividad) {
      $method->invoke(null, $actividad);
    })->toThrow(InvalidArgumentException::class);
  });

  test('throws InvalidArgumentException if unidad missing', function () {
    $curso = new Curso(['nombre' => 'Matemática']);
    $componente = new Componente();
    $componente->setRelation('curso', $curso);

    $actividad = new Actividad(['nombre' => 'Tarea 1']);
    $actividad->id_actividad = 123;
    $actividad->setRelation('componente', $componente);
    $actividad->setRelation('unidad', null);

    $reflection = new ReflectionClass(ActivityArchiveHandler::class);
    $method = $reflection->getMethod('buildPath');
    $method->setAccessible(true);

    expect(function () use ($method, $actividad) {
      $method->invoke(null, $actividad);
    })->toThrow(InvalidArgumentException::class);
  });

  test('path segments are slugified correctly', function () {
    $curso = new Curso(['nombre' => 'Matemática Avanzada II']);

    $tipoComponente = new class {
      public $tipo = 'Evaluación Parcial';
    };

    $componente = new Componente();
    $componente->setRelation('tipoComponente', $tipoComponente);
    $componente->setRelation('curso', $curso);

    $unidad = new Unidad(['nombre' => 'Unidad 1 - Introducción']);
    $unidad->setRelation('curso', $curso);

    $actividad = new Actividad(['nombre' => 'Examen Final 2024']);
    $actividad->id_actividad = 1;
    $actividad->setRelation('componente', $componente);
    $actividad->setRelation('unidad', $unidad);

    $reflection = new ReflectionClass(ActivityArchiveHandler::class);
    $method = $reflection->getMethod('buildPath');
    $method->setAccessible(true);

    $path = $method->invoke(null, $actividad);

    expect($path)->toContain('matematica-avanzada-ii');
    expect($path)->toContain('evaluacion-parcial');
    expect($path)->toContain('unidad-1-introduccion');
    expect($path)->toContain('examen-final-2024');
  });
});
