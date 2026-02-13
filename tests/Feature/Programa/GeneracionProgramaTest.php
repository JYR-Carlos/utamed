<?php

use App\Models\Usuario\Usuario;
use App\Models\Usuario\Docente;
use App\Models\Curso\Curso;
use App\Models\Administrativo\Programa;
use App\Models\Administrativo\EstructuraPrograma;

beforeEach(function () {
    // Crear usuario docente autenticado para cada test
    $this->usuario = Usuario::factory()->create([
        'esta_activo' => true,
    ]);

    $this->docente = Docente::factory()->create([
        'id_usuario' => $this->usuario->id_usuario,
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

    $response = $this->post("/docente/cursos/{$curso->id_curso}/programa");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verificar que el programa fue creado
    expect(Programa::where('id_curso', $curso->id_curso)->exists())->toBeTrue();
});

test('programa generado tiene version 1 si es el primero', function () {
    $curso = Curso::factory()->create();

    $this->post("/docente/cursos/{$curso->id_curso}/programa");

    $programa = Programa::where('id_curso', $curso->id_curso)->first();

    expect($programa->version_programa)->toBe(1);
});

test('programa generado tiene estructura por defecto', function () {
    $curso = Curso::factory()->create([
        'es_plantilla' => false
    ]);

    $this->post("/docente/cursos/{$curso->id_curso}/programa");

    $programa = Programa::where('id_curso', $curso->id_curso)
        ->where('es_actual', true)
        ->first();

    expect($programa)->not->toBeNull();
    expect($programa->secciones)->toHaveCount(6); // 6 secciones por defecto
});
