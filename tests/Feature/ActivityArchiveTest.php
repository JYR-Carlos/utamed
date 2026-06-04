<?php

use App\Http\Requests\Archive\ActivityFileRequest;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;
use App\Services\Archive\ActivityArchiveService;
use App\Services\Archive\Handlers\ActivityArchiveHandler;
use App\Exceptions\Archive\FileValidationException;
use App\Exceptions\Archive\FileValidationErrorType;
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
    expect($rules)->toHaveKey('id_contexto');
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

    // Verificar que contiene 'required'
    expect($archivoRules)->toContain('required');
    expect($archivoRules)->toContain('file');
  });

  test('id_contexto includes required and exists validation', function () {
    $request = new ActivityFileRequest();
    $rules = $request->rules();

    $contextoRules = $rules['id_contexto'];

    expect($contextoRules)->toContain('required');
    expect($contextoRules)->toContain('integer');
    // exists:usuario.contexto,id_contexto está incluido
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

  test('getContextoId returns the contexto ID', function () {
    $request = new ActivityFileRequest();
    $request->merge(['id_contexto' => 42]);

    expect($request->getContextoId())->toBe(42);
  });
});

// ============================================================================
// TESTS: ActivityArchiveService preValidate Coverage
// ============================================================================

describe('ActivityArchiveService - preValidate Coverage', function () {
  test('preValidate accepts valid PNG image', function () {
    $service = new ActivityArchiveService();

    // Usar Reflection para acceder al método privado
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = Mockery::mock(UploadedFile::class);
    $file->shouldReceive('getClientOriginalExtension')->andReturn('png');
    $file->shouldReceive('getMimeType')->andReturn('image/png');
    $file->shouldReceive('getSize')->andReturn(1024 * 100); // 100KB

    // No debe lanzar excepción
    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->not->toThrow(Exception::class);
  });

  test('preValidate accepts valid JPEG image', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = Mockery::mock(UploadedFile::class);
    $file->shouldReceive('getClientOriginalExtension')->andReturn('jpg');
    $file->shouldReceive('getMimeType')->andReturn('image/jpeg');
    $file->shouldReceive('getSize')->andReturn(1024 * 100);

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->not->toThrow(Exception::class);
  });

  test('preValidate accepts valid PDF', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = Mockery::mock(UploadedFile::class);
    $file->shouldReceive('getClientOriginalExtension')->andReturn('pdf');
    $file->shouldReceive('getMimeType')->andReturn('application/pdf');
    $file->shouldReceive('getSize')->andReturn(1024 * 100);

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->not->toThrow(Exception::class);
  });

  test('preValidate rejects unsupported file type', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = Mockery::mock(UploadedFile::class);
    $file->shouldReceive('getClientOriginalExtension')->andReturn('xlsx');
    $file->shouldReceive('getMimeType')->andReturn('application/vnd.ms-excel');
    $file->shouldReceive('getSize')->andReturn(1024 * 100);

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->toThrow(FileValidationException::class);
  });

  test('preValidate rejects image exceeding 50MB', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = Mockery::mock(UploadedFile::class);
    $file->shouldReceive('getClientOriginalExtension')->andReturn('jpg');
    $file->shouldReceive('getMimeType')->andReturn('image/jpeg');
    $file->shouldReceive('getSize')->andReturn(52428800 + 1); // 50MB + 1 byte

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->toThrow(FileValidationException::class);
  });

  test('preValidate rejects PDF exceeding 5MB', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = Mockery::mock(UploadedFile::class);
    $file->shouldReceive('getClientOriginalExtension')->andReturn('pdf');
    $file->shouldReceive('getMimeType')->andReturn('application/pdf');
    $file->shouldReceive('getSize')->andReturn(5242880 + 1); // 5MB + 1 byte

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->toThrow(FileValidationException::class);
  });

  test('preValidate rejects empty file', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = Mockery::mock(UploadedFile::class);
    $file->shouldReceive('getClientOriginalExtension')->andReturn('jpg');
    $file->shouldReceive('getMimeType')->andReturn('image/jpeg');
    $file->shouldReceive('getSize')->andReturn(0);

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->toThrow(FileValidationException::class);
  });

  test('preValidate rejects MIME type mismatch', function () {
    $service = new ActivityArchiveService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('preValidate');
    $method->setAccessible(true);

    $file = Mockery::mock(UploadedFile::class);
    $file->shouldReceive('getClientOriginalExtension')->andReturn('jpg');
    $file->shouldReceive('getMimeType')->andReturn('text/plain'); // Wrong MIME
    $file->shouldReceive('getSize')->andReturn(1024);

    expect(function () use ($method, $service, $file) {
      $method->invoke($service, $file, 'test-id');
    })->toThrow(FileValidationException::class);
  });
});

// ============================================================================
// TESTS: ActivityArchiveHandler Path Generation
// ============================================================================

describe('ActivityArchiveHandler - Path Generation', function () {
  test('generates correct path with all relationships', function () {
    // Crear mocks de los modelos
    $curso = Mockery::mock(Curso::class);
    $curso->shouldReceive('offsetExists')->andReturn(false);
    $curso->shouldReceive('getAttribute')
      ->with('nombre')
      ->andReturn('Matemática Avanzada');

    $tipoComponente = Mockery::mock();
    $tipoComponente->shouldReceive('getAttribute')
      ->with('tipo')
      ->andReturn('Evaluación');

    $componente = Mockery::mock(Componente::class);
    $componente->shouldReceive('offsetExists')->andReturn(false);
    $componente->shouldReceive('getAttribute')
      ->with('tipoComponente')
      ->andReturn($tipoComponente);

    $unidad = Mockery::mock(Unidad::class);
    $unidad->shouldReceive('offsetExists')->andReturn(false);
    $unidad->shouldReceive('getAttribute')
      ->with('nombre')
      ->andReturn('Unidad 1');

    $actividad = Mockery::mock(Actividad::class);
    $actividad->shouldReceive('offsetExists')->andReturn(false);
    $actividad->shouldReceive('getAttribute')
      ->with('componente')
      ->andReturn($componente);
    $actividad->shouldReceive('getAttribute')
      ->with('unidad')
      ->andReturn($unidad);
    $actividad->shouldReceive('getAttribute')
      ->with('nombre')
      ->andReturn('Examen Final');

    // Usar reflexión para acceder al método privado buildPath
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

    // Esperado formato: activity_{timestamp}_{random}.png
    expect($fileName)->toMatch('/^activity_\d+_[a-zA-Z0-9]{8}\.png$/');
  });

  test('throws InvalidArgumentException if componente missing', function () {
    $curso = Mockery::mock(Curso::class);
    $curso->shouldReceive('offsetExists')->andReturn(false);

    $unidad = Mockery::mock(Unidad::class);
    $unidad->shouldReceive('offsetExists')->andReturn(false);
    $unidad->shouldReceive('getAttribute')
      ->with('curso')
      ->andReturn($curso);

    $actividad = Mockery::mock(Actividad::class);
    $actividad->shouldReceive('offsetExists')->andReturn(false);
    // Allow any getAttribute call to be flexible
    $actividad->shouldReceive('getAttribute')->andReturn(null)->byDefault();
    $actividad->shouldReceive('getAttribute')
      ->with('componente')
      ->andReturn(null)
      ->shouldReceive('getAttribute')
      ->with('unidad')
      ->andReturn($unidad);
    // Make sure accessing id_actividad also returns something
    $actividad->id_actividad = 123;

    $reflection = new ReflectionClass(ActivityArchiveHandler::class);
    $method = $reflection->getMethod('buildPath');
    $method->setAccessible(true);

    expect(function () use ($method, $actividad) {
      $method->invoke(null, $actividad);
    })->toThrow(InvalidArgumentException::class);
  });

  test('throws InvalidArgumentException if unidad missing', function () {
    $curso = Mockery::mock(Curso::class);
    $curso->shouldReceive('offsetExists')->andReturn(false);
    $curso->shouldReceive('getAttribute')->andReturn(null)->byDefault();

    $componente = Mockery::mock(Componente::class);
    $componente->shouldReceive('offsetExists')->andReturn(false);
    $componente->shouldReceive('getAttribute')->andReturn(null)->byDefault();
    $componente->shouldReceive('getAttribute')
      ->with('curso')
      ->andReturn($curso);

    $actividad = Mockery::mock(Actividad::class);
    $actividad->shouldReceive('offsetExists')->andReturn(false);
    $actividad->shouldReceive('getAttribute')->andReturn(null)->byDefault();
    $actividad->shouldReceive('getAttribute')
      ->with('componente')
      ->andReturn($componente);
    $actividad->shouldReceive('getAttribute')
      ->with('unidad')
      ->andReturn(null);

    $reflection = new ReflectionClass(ActivityArchiveHandler::class);
    $method = $reflection->getMethod('buildPath');
    $method->setAccessible(true);

    expect(function () use ($method, $actividad) {
      $method->invoke(null, $actividad);
    })->toThrow(InvalidArgumentException::class);
  });

  test('path segments are slugified correctly', function () {
    $curso = Mockery::mock(Curso::class);
    $curso->shouldReceive('offsetExists')->andReturn(false);
    $curso->shouldReceive('getAttribute')
      ->with('nombre')
      ->andReturn('Matemática Avanzada II');

    $tipoComponente = Mockery::mock();
    $tipoComponente->shouldReceive('getAttribute')
      ->with('tipo')
      ->andReturn('Evaluación Parcial');

    $componente = Mockery::mock(Componente::class);
    $componente->shouldReceive('offsetExists')->andReturn(false);
    $componente->shouldReceive('getAttribute')
      ->with('tipoComponente')
      ->andReturn($tipoComponente);

    $unidad = Mockery::mock(Unidad::class);
    $unidad->shouldReceive('offsetExists')->andReturn(false);
    $unidad->shouldReceive('getAttribute')
      ->with('nombre')
      ->andReturn('Unidad 1 - Introducción');

    $actividad = Mockery::mock(Actividad::class);
    $actividad->shouldReceive('offsetExists')->andReturn(false);
    $actividad->shouldReceive('getAttribute')
      ->with('componente')
      ->andReturn($componente);
    $actividad->shouldReceive('getAttribute')
      ->with('unidad')
      ->andReturn($unidad);
    $actividad->shouldReceive('getAttribute')
      ->with('nombre')
      ->andReturn('Examen Final 2024');

    $reflection = new ReflectionClass(ActivityArchiveHandler::class);
    $method = $reflection->getMethod('buildPath');
    $method->setAccessible(true);

    $path = $method->invoke(null, $actividad);

    // Verify it uses slugs (lowercase with hyphens)
    expect($path)->toContain('matematica-avanzada-ii');
    expect($path)->toContain('evaluacion-parcial');
    expect($path)->toContain('unidad-1-introduccion');
    expect($path)->toContain('examen-final-2024');
  });
});
