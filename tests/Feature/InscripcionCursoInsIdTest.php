<?php

use App\Http\Resources\InscripcionCursoResource;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Services\InscripcionCursoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('InscripcionCurso resuelve ins_id desde inscripcion_componente del curso consultado', function () {
    $curso = Curso::first();
    expect($curso)->not->toBeNull();

    $estudiante = Estudiante::first();
    expect($estudiante)->not->toBeNull();

    $componente = Componente::firstOrCreate(
        ['id_curso' => $curso->id_curso],
        ['id_tipo_componente' => 1]
    );

    $insIdTest = 543210;
    $inscComponente = InscripcionComponente::updateOrCreate(
        [
            'id_componente' => $componente->id_componente,
            'id_estudiante' => $estudiante->id_estudiante,
        ],
        [
            'cod_inscripcion_curso_uta' => $insIdTest,
        ]
    );

    $inscCurso = InscripcionCurso::updateOrCreate(
        [
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
        ],
        [
            'fecha_inscripcion' => now()->toDateString(),
            'estado_inscripcion' => 'INSCRITO',
            'num_intento' => 1,
        ]
    );

    // 1. Verificar accessor ins_id en modelo individual
    expect($inscComponente->ins_id)->toBe($insIdTest);
    expect($inscCurso->ins_id)->toBe($insIdTest);

    // 2. Verificar a través de InscripcionCursoService con eager loading
    $service = app(InscripcionCursoService::class);
    $inscripciones = $service->getInscripcionesByCurso($curso->id_curso);
    $item = $inscripciones->firstWhere('id_inscripcion_curso', $inscCurso->id_inscripcion_curso);

    expect($item)->not->toBeNull();
    expect($item->ins_id)->toBe($insIdTest);

    // 3. Verificar serialización en InscripcionCursoResource
    $serialized = (new InscripcionCursoResource($item))->resolve();
    expect($serialized)->toHaveKey('ins_id');
    expect($serialized['ins_id'])->toBe($insIdTest);
});

test('endpoint ajax by-curso retorna ins_id en las inscripciones', function () {
    $admin = Usuario::where('username', 'superadmin')->first();
    expect($admin)->not->toBeNull();
    $admin->fecha_cambio_passhash = now();
    $admin->fecha_verificacion_email = now();
    $admin->save();

    $curso = Curso::first();
    $estudiante = Estudiante::first();
    $componente = Componente::firstOrCreate(
        ['id_curso' => $curso->id_curso],
        ['id_tipo_componente' => 1]
    );

    $insIdTest = 765432;
    InscripcionComponente::updateOrCreate(
        [
            'id_componente' => $componente->id_componente,
            'id_estudiante' => $estudiante->id_estudiante,
        ],
        [
            'cod_inscripcion_curso_uta' => $insIdTest,
        ]
    );

    $inscCurso = InscripcionCurso::updateOrCreate(
        [
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
        ],
        [
            'fecha_inscripcion' => now()->toDateString(),
            'estado_inscripcion' => 'INSCRITO',
            'num_intento' => 1,
        ]
    );

    $response = $this->actingAs($admin)
        ->getJson("/admin/inscripciones_cursos/ajax/by-curso?id_curso={$curso->id_curso}");

    $response->assertOk()
        ->assertJsonStructure([
            'inscripciones' => [
                '*' => [
                    'id_inscripcion_curso',
                    'id_curso',
                    'id_estudiante',
                    'ins_id',
                ],
            ],
        ]);

    $data = $response->json('inscripciones');
    expect($data)->not->toBeEmpty();
    $item = collect($data)->firstWhere('id_inscripcion_curso', $inscCurso->id_inscripcion_curso);
    expect($item)->not->toBeNull();
    expect($item['ins_id'])->toBe($insIdTest);
});
