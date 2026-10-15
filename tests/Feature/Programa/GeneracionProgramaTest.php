<?php
 
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Docente;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Services\ProgramaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Crear usuario docente autenticado para cada test
    $this->usuario = Usuario::factory()->withRolDocente()->create([
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ]);

    $this->docente = $this->usuario->docente;
    $this->docente->update([
        'grado' => 'Magíster',
        'titulo' => 'Ingeniero',
        'cargo' => 'Profesor',
    ]);

    $this->actingAs($this->usuario);
});

test('docente puede acceder a la página de cursos', function () {
    $response = $this->get('/docente/cursos');

    $response->assertStatus(200);
});

test('docente puede generar programa para curso sin programa', function () {
    $curso = Curso::factory()->create([
        'es_plantilla' => false,
        'id_contexto' => 1,
    ]);

    $programa = ProgramaService::generateProgramaWithSyllabus($curso, $this->usuario);

    // Verificar que el programa fue creado
    expect(Programa::where('id_curso', $curso->id_curso)->exists())->toBeTrue();
    expect($programa->id_curso)->toBe($curso->id_curso);
});

test('programa generado tiene version 1 si es el primero', function () {
    $curso = Curso::factory()->create();

    $programa = ProgramaService::generateProgramaWithSyllabus($curso, $this->usuario);

    expect($programa->version_programa)->toBe(1);
});

test('programa generado tiene estructura por defecto', function () {
    $curso = Curso::factory()->create([
        'es_plantilla' => false
    ]);

    $programa = ProgramaService::generateProgramaWithSyllabus($curso, $this->usuario);

    $actual = Programa::where('id_curso', $curso->id_curso)
        ->where('es_actual', true)
        ->first();

    expect($actual)->not->toBeNull();
    expect($actual->data_syllabus)->not->toBeNull();
});
