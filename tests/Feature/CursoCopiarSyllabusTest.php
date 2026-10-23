<?php

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Curso\Bibliografia;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Usuario;
use App\Services\CursoService;
use App\Services\ProgramaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->cursoService = app(CursoService::class);
    $this->usuario = Usuario::where('esta_activo', true)->first() ?? Usuario::factory()->create(['esta_activo' => true]);
    Auth::login($this->usuario);
});

test('falla si los cursos pertenecen a asignaturas distintas', function () {
    // Buscar dos asignacion_plan con asignaturas distintas
    $planA = AsignacionPlan::first();
    $planB = AsignacionPlan::where('id_asignatura', '!=', $planA->id_asignatura)->first();

    if (!$planB) {
        $this->markTestSkipped('No hay al menos dos asignaturas distintas en la BD.');
    }

    $cursoA = Curso::factory()->create(['id_asignacion_plan' => $planA->id_asignacion_plan]);
    $cursoB = Curso::factory()->create(['id_asignacion_plan' => $planB->id_asignacion_plan]);

    // Crear programa en cursoA
    ProgramaService::generateProgramaWithSyllabus($cursoA, $this->usuario);

    expect(fn () => $this->cursoService->copiarSyllabus($cursoA, $cursoB, $this->usuario))
        ->toThrow(\InvalidArgumentException::class, 'Los cursos deben compartir la misma asignatura para copiar el syllabus.');
});

test('falla si el curso de origen no posee un programa o syllabus', function () {
    $plan = AsignacionPlan::first();
    $cursoA = Curso::factory()->create(['id_asignacion_plan' => $plan->id_asignacion_plan]);
    $cursoB = Curso::factory()->create(['id_asignacion_plan' => $plan->id_asignacion_plan]);

    expect(fn () => $this->cursoService->copiarSyllabus($cursoA, $cursoB, $this->usuario))
        ->toThrow(\InvalidArgumentException::class, 'El curso de origen no posee un programa o syllabus para copiar.');
});

test('copia el syllabus exitosamente entre cursos con la misma asignatura', function () {
    $plan = AsignacionPlan::with('asignatura')->first();
    $cursoOrigen = Curso::factory()->create([
        'id_asignacion_plan' => $plan->id_asignacion_plan,
        'agno_real' => 2026,
        'semestre_real' => 1,
    ]);
    $cursoDestino = Curso::factory()->create([
        'id_asignacion_plan' => $plan->id_asignacion_plan,
        'agno_real' => 2026,
        'semestre_real' => 2,
    ]);

    // Crear programa en origen con datos estructurados
    $programaOrigen = ProgramaService::generateProgramaWithSyllabus($cursoOrigen, $this->usuario);
    $programaOrigen->update([
        'estado' => 'APROBADO',
        'revisado_por' => $this->usuario->id_usuario,
    ]);

    // Crear una unidad en el curso origen
    $unidadOrigen = Unidad::create([
        'id_curso' => $cursoOrigen->id_curso,
        'num_unidad' => 1,
        'nombre' => 'Unidad 1: Fundamentos',
        'descripcion' => 'Introducción general',
    ]);

    // Crear una bibliografía en origen ligada a la unidad
    $bibOrigen = Bibliografia::create([
        'id_programa' => $programaOrigen->id_programa,
        'id_unidad' => $unidadOrigen->id_unidad,
        'titulo' => 'Libro de Prueba Copia',
        'autor' => 'Autor Ejemplo',
        'agno' => 2024,
        'es_bibliografia_uta' => true,
        'agregado_por' => $this->usuario->id_usuario,
    ]);

    // Ejecutar copia
    $nuevoPrograma = $this->cursoService->copiarSyllabus($cursoOrigen, $cursoDestino, $this->usuario);

    // 1. Programa creado en destino
    expect($nuevoPrograma)->toBeInstanceOf(Programa::class)
        ->and($nuevoPrograma->id_curso)->toBe($cursoDestino->id_curso)
        ->and($nuevoPrograma->version_programa)->toBe(1)
        ->and($nuevoPrograma->es_actual)->toBeTrue()
        ->and($nuevoPrograma->estado)->toBe('BORRADOR') // No hereda 'APROBADO'
        ->and($nuevoPrograma->revisado_por)->toBeNull()  // Sin revisor previo
        ->and($nuevoPrograma->fecha_entrega)->toBeNull();

    // 2. Metadata adaptada al curso destino
    $metadata = $nuevoPrograma->data_syllabus['metadata'] ?? [];
    expect($metadata['curso']['id_curso'])->toBe($cursoDestino->id_curso)
        ->and($metadata['curso']['semestre'])->toBe($cursoDestino->semestre_real)
        ->and($metadata['curso']['año_academico'])->toBe($cursoDestino->agno_real);

    // 3. Unidades sincronizadas en el curso destino
    $unidadDestino = Unidad::where('id_curso', $cursoDestino->id_curso)
        ->where('num_unidad', 1)
        ->first();
    expect($unidadDestino)->not->toBeNull()
        ->and($unidadDestino->nombre)->toBe('Unidad 1: Fundamentos');

    // 4. Bibliografías clonadas con nuevos UUIDs y remapeadas
    $bibDestino = Bibliografia::where('id_programa', $nuevoPrograma->id_programa)->first();
    expect($bibDestino)->not->toBeNull()
        ->and($bibDestino->uuid_bibliografia)->not->toBe($bibOrigen->uuid_bibliografia)
        ->and($bibDestino->titulo)->toBe('Libro de Prueba Copia')
        ->and($bibDestino->id_unidad)->toBe($unidadDestino->id_unidad);
});

test('desmarca el programa actual anterior e incrementa version en curso destino', function () {
    $plan = AsignacionPlan::first();
    $cursoOrigen = Curso::factory()->create(['id_asignacion_plan' => $plan->id_asignacion_plan]);
    $cursoDestino = Curso::factory()->create(['id_asignacion_plan' => $plan->id_asignacion_plan]);

    // Generar programas iniciales
    ProgramaService::generateProgramaWithSyllabus($cursoOrigen, $this->usuario);
    $programaPrevioDestino = ProgramaService::generateProgramaWithSyllabus($cursoDestino, $this->usuario);

    expect($programaPrevioDestino->es_actual)->toBeTrue()
        ->and($programaPrevioDestino->version_programa)->toBe(1);

    // Ejecutar copia de syllabus
    $nuevoPrograma = $this->cursoService->copiarSyllabus($cursoOrigen, $cursoDestino, $this->usuario);

    $programaPrevioDestino->refresh();
    expect($programaPrevioDestino->es_actual)->toBeFalse()
        ->and($nuevoPrograma->es_actual)->toBeTrue()
        ->and($nuevoPrograma->version_programa)->toBe(2);
});

test('copia con bibliografias definidas en seccion VIII del json sincronizando sus ids', function () {
    $plan = AsignacionPlan::first();
    $cursoOrigen = Curso::factory()->create(['id_asignacion_plan' => $plan->id_asignacion_plan]);
    $cursoDestino = Curso::factory()->create(['id_asignacion_plan' => $plan->id_asignacion_plan]);

    $programaOrigen = ProgramaService::generateProgramaWithSyllabus($cursoOrigen, $this->usuario);

    // Inyectar sección VIII en formato asociativo en el data_syllabus de origen
    $dataSyllabus = $programaOrigen->data_syllabus;
    $dataSyllabus['secciones'] = [
        'VI' => [
            'contenido' => [
                'unidades' => [
                    ['numero' => 1, 'titulo' => 'Unidad JSON 1', 'contenidos_items' => [['item' => 'Item 1']]],
                ],
            ],
        ],
        'VIII' => [
            'contenido' => [
                'bibliografias' => [
                    [
                        'id_bibliografia' => null,
                        'titulo' => 'Libro desde JSON',
                        'autor' => 'Autor JSON',
                        'anio' => 2025,
                        'es_bibliografia_uta' => false,
                        'url' => 'https://ejemplo.com',
                        'id_unidad' => 1,
                    ],
                ],
            ],
        ],
    ];
    $programaOrigen->update(['data_syllabus' => $dataSyllabus]);

    $nuevoPrograma = $this->cursoService->copiarSyllabus($cursoOrigen, $cursoDestino, $this->usuario);

    // Debe haberse creado en la tabla curso.bibliografia
    $bibDestino = Bibliografia::where('id_programa', $nuevoPrograma->id_programa)->first();
    expect($bibDestino)->not->toBeNull()
        ->and($bibDestino->titulo)->toBe('Libro desde JSON');

    // Debe haberse sincronizado el id_bibliografia en el JSON destino
    $seccionesDestino = $nuevoPrograma->data_syllabus['secciones'];
    expect($seccionesDestino['VIII']['contenido']['bibliografias'][0]['id_bibliografia'])
        ->toBe($bibDestino->uuid_bibliografia);
});

test('permite copiar un programa de origen especifico en lugar del actual', function () {
    $plan = AsignacionPlan::first();
    $cursoOrigen = Curso::factory()->create(['id_asignacion_plan' => $plan->id_asignacion_plan]);
    $cursoDestino = Curso::factory()->create(['id_asignacion_plan' => $plan->id_asignacion_plan]);

    // Crear dos programas en origen: v1 (antiguo, no actual) y v2 (actual)
    $programaV1 = ProgramaService::generateProgramaWithSyllabus($cursoOrigen, $this->usuario);
    $dataV1 = $programaV1->data_syllabus;
    $dataV1['secciones'] = ['I' => ['contenido' => ['nombre_asignatura' => 'Version 1 Personalizada']]];
    $programaV1->update(['data_syllabus' => $dataV1]);

    $programaV2 = ProgramaService::generateProgramaWithSyllabus($cursoOrigen, $this->usuario);
    $dataV2 = $programaV2->data_syllabus;
    $dataV2['secciones'] = ['I' => ['contenido' => ['nombre_asignatura' => 'Version 2 Actual']]];
    $programaV2->update(['data_syllabus' => $dataV2]);

    // Copiar explícitamente la v1
    $nuevoPrograma = $this->cursoService->copiarSyllabus($cursoOrigen, $cursoDestino, $this->usuario, $programaV1);

    expect($nuevoPrograma->data_syllabus['secciones']['I']['contenido']['nombre_asignatura'])
        ->toBe('Version 1 Personalizada');
});
