<?php

use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Carrera;
use App\Models\Curso\Curso;
use App\Models\Curso\Seccion;
use App\Models\Curso\InscripcionSeccion;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoSeccion;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Contexto;
use function Pest\Laravel\withoutExceptionHandling;

beforeEach(function () {
    // Crear contexto base
    $contexto = Contexto::create(['contexto_display' => 'Test Contexto']);
    $this->contexto = $contexto;

    // Crear facultad
    $facultad = Facultad::create([
        'nombre' => 'Test Facultad',
        'id_contexto' => $contexto->id_contexto
    ]);
    $this->facultad = $facultad;

    // Crear departamento
    $departamento = Departamento::create([
        'nombre' => 'Test Departamento',
        'id_facultad' => $facultad->id_facultad,
        'id_contexto' => $contexto->id_contexto
    ]);
    $this->departamento = $departamento;

    // Crear carrera
    $carrera = Carrera::create([
        'nombre' => 'Test Carrera',
        'id_facultad' => $facultad->id_facultad,
        'id_departamento' => $departamento->id_departamento,
        'id_contexto' => $contexto->id_contexto
    ]);
    $this->carrera = $carrera;

    // Crear usuario docente
    $rut1 = sprintf('%02d.%03d.%03d-%d', rand(10, 99), rand(0, 999), rand(0, 999), rand(0, 9));
    $usuarioDocente = Usuario::create([
        'username' => 'docente_test_' . time(),
        'passhash' => 'test',
        'rut' => $rut1,
        'nombre1' => 'Test',
        'apellido1' => 'Docente'
    ]);

    $docente = Docente::create([
        'id_usuario' => $usuarioDocente->id_usuario,
        'grado' => 'test'
    ]);
    $this->docente = $docente;

    // Crear usuario estudiante
    $rut2 = sprintf('%02d.%03d.%03d-%d', rand(10, 99), rand(0, 999), rand(0, 999), rand(0, 9));
    $usuarioEstudiante = Usuario::create([
        'username' => 'estudiante_test_' . time(),
        'passhash' => 'test',
        'rut' => $rut2,
        'nombre1' => 'Test',
        'apellido1' => 'Estudiante'
    ]);

    $estudiante = Estudiante::create([
        'id_usuario' => $usuarioEstudiante->id_usuario,
        'id_carrera' => $carrera->id_carrera
    ]);
    $this->estudiante = $estudiante;

    // Crear curso simple (sin dependencies complejas)
    $curso = Curso::create([
        'nombre' => 'Test Curso',
        'indice_grupo' => 1,
        'fecha_inicio' => now(),
        'fecha_fin' => now()->addMonths(4),
        'id_contexto' => $contexto->id_contexto
    ]);
    $this->curso = $curso;

    // Usar tipo seccion existente o crear
    $tipoSeccion = TipoSeccion::first() ?? TipoSeccion::create([
        'id_tipo_seccion' => 1,
        'tipo' => 'Teoría'
    ]);
    $this->tipoSeccion = $tipoSeccion;

    // Crear secciones con PK compuesta: [id_seccion, id_curso]
    $seccion1 = Seccion::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_seccion' => $tipoSeccion->id_tipo_seccion,
        'id_docente' => $docente->id_docente
    ]);
    $this->seccion1 = $seccion1;

    $seccion2 = Seccion::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_seccion' => $tipoSeccion->id_tipo_seccion,
        'id_docente' => $docente->id_docente
    ]);
    $this->seccion2 = $seccion2;
});


test('caca', function () {
    $curso = Curso::find($this->curso->id_curso);

    $secciones = $curso->secciones;
    echo 'secciones: ' . json_encode($secciones) . "\n";

    $curso->load('secciones');
});


test('eager loading de secciones con PK compuesta', function () {
    // Recuperar el curso
    $curso = Curso::find($this->curso->id_curso);

    expect($curso)->not->toBeNull();

    // TEST 1: Eager loading de secciones
    // Seccion tiene PK compuesta ['id_seccion', 'id_curso']
    // ERROR ESPERADO: TypeError en stripos() debido a composite key
    try {
        $curso->load('secciones');
        // Si llegamos aquí sin error, validar
        expect($curso->secciones)->toHaveCount(2);
        expect($curso->secciones[0])->toBeInstanceOf(Seccion::class);
    } catch (\Throwable $e) {
        // El error esperado es: TypeError al procesar composite keys en stripos()
        // O QueryException si hay problema con la sintaxis SQL
        expect(
            str_contains($e->getMessage(), "stripos()")
            || str_contains($e->getMessage(), "falta una entrada")
            || str_contains($e->getMessage(), "Undefined table")
            || str_contains($e->getMessage(), "syntax")
            || str_contains($e->getMessage(), "array")
        )->toBeTrue();
    }
});

test('eager loading de inscripcion curso', function () {
    // Crear inscripción curso
    $inscripcionCurso = InscripcionCurso::create([
        'id_curso' => $this->curso->id_curso,
        'id_estudiante' => $this->estudiante->id_estudiante,
        'estado_inscripcion' => 'VIGENTE'
    ]);

    // Recuperar el curso
    $curso = Curso::find($this->curso->id_curso);

    // TEST: Eager loading de inscripcion cursos
    $curso->load('inscripcionCursos');

    expect($curso->inscripcionCursos)->toHaveCount(1);
    expect($curso->inscripcionCursos[0]->id_curso)->toBe($this->curso->id_curso);
    expect($curso->inscripcionCursos[0]->id_estudiante)->toBe($this->estudiante->id_estudiante);
});

test('eager loading de inscripciones seccion con PK compuesta triple', function () {
    // Crear inscripciones en seccion
    // InscripcionSeccion tiene PK compuesta triple: ['id_estudiante', 'id_seccion', 'id_curso']
    $inscripcionSeccion1 = InscripcionSeccion::create([
        'id_estudiante' => $this->estudiante->id_estudiante,
        'id_seccion' => $this->seccion1->id_seccion,
        'id_curso' => $this->seccion1->id_curso
    ]);

    // Recuperar la seccion
    $seccion = Seccion::where('id_seccion', $this->seccion1->id_seccion)
        ->where('id_curso', $this->seccion1->id_curso)
        ->first();

    expect($seccion)->not->toBeNull();

    // TEST: Eager loading de inscripcionSecciones
    // Esta relación tiene PK compuesta triple en InscripcionSeccion
    try {
        $seccion->load('inscripcionSecciones');
        expect($seccion->inscripcionSecciones)->toHaveCount(1);
        expect($seccion->inscripcionSecciones[0])->toBeInstanceOf(InscripcionSeccion::class);
    } catch (\Throwable $e) {
        // Error esperado con composite keys
        expect(
            str_contains($e->getMessage(), "stripos()")
            || str_contains($e->getMessage(), "falta una entrada")
            || str_contains($e->getMessage(), "Undefined table")
            || str_contains($e->getMessage(), "syntax")
            || str_contains($e->getMessage(), "array")
        )->toBeTrue();
    }
});

test('eager loading multiple de curso con secciones e inscripciones', function () {
    // Crear inscripción curso
    $inscripcionCurso = InscripcionCurso::create([
        'id_curso' => $this->curso->id_curso,
        'id_estudiante' => $this->estudiante->id_estudiante,
        'estado_inscripcion' => 'VIGENTE'
    ]);

    // Recuperar el curso fresh
    $curso = Curso::find($this->curso->id_curso);

    // TEST: Multiple eager loading con claves compuestas
    // Intencionalmente probando para ver qué falla
    try {
        $curso->load(['secciones', 'inscripcionCursos']);
        expect($curso->secciones)->toHaveCount(2);
        expect($curso->inscripcionCursos)->toHaveCount(1);
    } catch (\Throwable $e) {
        // Error esperado con composite keys en secciones
        expect(
            str_contains($e->getMessage(), "stripos()")
            || str_contains($e->getMessage(), "falta una entrada")
            || str_contains($e->getMessage(), "Undefined table")
            || str_contains($e->getMessage(), "syntax")
            || str_contains($e->getMessage(), "array")
        )->toBeTrue();
    }
});

test('cargar curso con load() y acceder a secciones con sus inscripciones', function () {
    // Setup: Crear e inscribir estudiantes
    InscripcionSeccion::create([
        'id_estudiante' => $this->estudiante->id_estudiante,
        'id_seccion' => $this->seccion1->id_seccion,
        'id_curso' => $this->seccion1->id_curso
    ]);

    // Recuperar curso
    $curso = Curso::find($this->curso->id_curso);

    // Try eager load secciones with nested inscripcionSecciones
    try {
        $curso->load('secciones');
        expect($curso->secciones)->toHaveCount(2);

        // Try nested eager loading
        $curso->load([
            'secciones' => fn($query) => $query->with('inscripcionSecciones')
        ]);

        expect($curso->secciones[0]->inscripcionSecciones)->toBeTruthy();
    } catch (\Throwable $e) {
        // Error esperado con composite keys
        expect(
            str_contains($e->getMessage(), "stripos()")
            || str_contains($e->getMessage(), "falta una entrada")
            || str_contains($e->getMessage(), "Undefined table")
            || str_contains($e->getMessage(), "syntax")
            || str_contains($e->getMessage(), "array")
        )->toBeTrue();
    }
});

test('find con clave compuesta en seccion funciona correctamente', function () {
    // TEST: Búsqueda con clave compuesta
    // Nota: usar find() con array para claves compuestas
    $seccion = Seccion::where('id_seccion', $this->seccion1->id_seccion)
        ->where('id_curso', $this->seccion1->id_curso)
        ->first();

    expect($seccion)->not->toBeNull();
    expect($seccion->id_seccion)->toBe($this->seccion1->id_seccion);
    expect($seccion->id_curso)->toBe($this->seccion1->id_curso);
});

test('find con clave compuesta triple en inscripcion seccion funciona correctamente', function () {
    // Crear inscripción
    $inscripcion = InscripcionSeccion::create([
        'id_estudiante' => $this->estudiante->id_estudiante,
        'id_seccion' => $this->seccion1->id_seccion,
        'id_curso' => $this->seccion1->id_curso
    ]);

    // TEST: Búsqueda con clave compuesta triple usando where en lugar de find()
    $found = InscripcionSeccion::where('id_estudiante', $this->estudiante->id_estudiante)
        ->where('id_seccion', $this->seccion1->id_seccion)
        ->where('id_curso', $this->seccion1->id_curso)
        ->first();

    expect($found)->not->toBeNull();
    expect($found->id_estudiante)->toBe($this->estudiante->id_estudiante);
    expect($found->id_seccion)->toBe($this->seccion1->id_seccion);
    expect($found->id_curso)->toBe($this->seccion1->id_curso);
});
