<?php

use App\Enums\DB\EstadoRubrica;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

function fixtureCursosHermanosParaCopiar(): ?array
{
    $row = DB::selectOne('
        SELECT c1.id_curso as id_curso_origen,
               c2.id_curso as id_curso_destino,
               c1.id_asignacion_plan,
               d.id_usuario,
               a.id_actividad,
               comp_dest.id_componente as id_componente_destino,
               un_dest.id_unidad as id_unidad_destino
        FROM curso.curso c1
        JOIN curso.curso c2 ON c2.id_asignacion_plan = c1.id_asignacion_plan AND c2.id_curso != c1.id_curso
        JOIN usuario.docente d ON d.id_docente = c1.id_docente_titular
        JOIN usuario.usuario u ON u.id_usuario = d.id_usuario
        JOIN curso.componente comp_orig ON comp_orig.id_curso = c1.id_curso
        JOIN agenda.actividad a ON a.id_componente = comp_orig.id_componente
        JOIN curso.componente comp_dest ON comp_dest.id_curso = c2.id_curso
        JOIN curso.unidad un_dest ON un_dest.id_curso = c2.id_curso
        WHERE u.esta_activo
        LIMIT 1
    ');

    return $row ? (array) $row : null;
}

function crearArchivoEnBd(): string
{
    $uuid = (string) Str::uuid();

    DB::table('operaciones.archivo')->insert([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "tests/enunciados/{$uuid}.pdf",
        'nombre_original' => 'enunciado-test.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 2048,
    ]);

    return $uuid;
}

test('cursos-hermanos lista hermanos y excluye el curso actual', function () {
    $fixture = fixtureCursosHermanosParaCopiar();
    expect($fixture)->not->toBeNull();

    $usuario = Usuario::findOrFail($fixture['id_usuario']);
    $cursoOrigen = Curso::findOrFail($fixture['id_curso_origen']);

    $response = $this->actingAs($usuario)
        ->getJson(route('docente.cursos.actividades.cursos-hermanos', ['curso' => $cursoOrigen->id_curso]));

    $response->assertOk();
    $data = $response->json();

    expect(collect($data)->pluck('id_curso'))->not->toContain($cursoOrigen->id_curso);
    expect(collect($data)->pluck('id_curso'))->toContain($fixture['id_curso_destino']);
});

test('copiar crea actividad en destino con componente/unidad homologos, visible=false, copia uuid y rubrica, y genera grupos si es individual', function () {
    $fixture = fixtureCursosHermanosParaCopiar();
    expect($fixture)->not->toBeNull();

    $usuario = Usuario::findOrFail($fixture['id_usuario']);
    $cursoOrigen = Curso::findOrFail($fixture['id_curso_origen']);
    $cursoDestino = Curso::findOrFail($fixture['id_curso_destino']);

    // Asignar archivo enunciado y rúbrica a la actividad origen
    $uuidArchivo = crearArchivoEnBd();
    $actividadOrigen = Actividad::findOrFail($fixture['id_actividad']);
    $actividadOrigen->uuid_archivo_enunciado = $uuidArchivo;
    $actividadOrigen->es_grupal = false; // Individual para verificar grupos automáticos
    $actividadOrigen->save();

    Rubrica::updateOrCreate(
        ['id_actividad' => $actividadOrigen->id_actividad],
        [
            'rubrica' => ['criterios' => ['calidad' => 10]],
            'estado_rubrica' => EstadoRubrica::POSTULADA,
        ]
    );

    // Asegurar que el curso destino tenga al menos un estudiante inscrito
    $estudiante = DB::table('usuario.estudiante')->first();
    if ($estudiante) {
        DB::table('curso.inscripcion_curso')->updateOrInsert(
            [
                'id_curso' => $cursoDestino->id_curso,
                'id_estudiante' => $estudiante->id_estudiante,
            ],
            [
                'fecha_inscripcion' => now(),
            ]
        );
    }

    $payload = [
        'id_curso_destino' => $cursoDestino->id_curso,
        'id_componente_destino' => $fixture['id_componente_destino'],
        'id_unidad_destino' => $fixture['id_unidad_destino'],
    ];

    $response = $this->actingAs($usuario)
        ->post(route('docente.cursos.actividades.copiar', [
            'curso' => $cursoOrigen->id_curso,
            'actividad' => $actividadOrigen->id_actividad,
        ]), $payload);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    // Verificar que la nueva actividad existe en destino
    $copia = Actividad::where('id_componente', $fixture['id_componente_destino'])
        ->where('nombre', $actividadOrigen->nombre)
        ->latest('id_actividad')
        ->first();

    expect($copia)->not->toBeNull();
    expect($copia->visible)->toBeFalse();
    expect($copia->id_unidad)->toBe($fixture['id_unidad_destino']);
    expect($copia->uuid_archivo_enunciado)->toBe($uuidArchivo);

    // Verificar copia de rúbrica
    $rubricaCopia = Rubrica::where('id_actividad', $copia->id_actividad)->first();
    expect($rubricaCopia)->not->toBeNull();
    expect($rubricaCopia->rubrica)->toBe(['criterios' => ['calidad' => 10]]);

    // Verificar grupos automáticos para individual
    $gruposCount = DB::table('agenda.actividad_asignada_grupo')
        ->where('id_actividad', $copia->id_actividad)
        ->count();
    expect($gruposCount)->toBeGreaterThan(0);
});

test('rechaza destino de otra asignacion', function () {
    $fixture = fixtureCursosHermanosParaCopiar();
    expect($fixture)->not->toBeNull();

    $usuario = Usuario::findOrFail($fixture['id_usuario']);
    $cursoOrigen = Curso::findOrFail($fixture['id_curso_origen']);

    // Buscar un curso con distinta asignación de plan
    $cursoAjeno = Curso::where('id_asignacion_plan', '!=', $cursoOrigen->id_asignacion_plan)->first();
    expect($cursoAjeno)->not->toBeNull();

    $componenteAjeno = Componente::where('id_curso', $cursoAjeno->id_curso)->first();
    $unidadAjena = Unidad::where('id_curso', $cursoAjeno->id_curso)->first();

    $payload = [
        'id_curso_destino' => $cursoAjeno->id_curso,
        'id_componente_destino' => $componenteAjeno?->id_componente ?? 999999,
        'id_unidad_destino' => $unidadAjena?->id_unidad ?? 999999,
    ];

    $response = $this->actingAs($usuario)
        ->post(route('docente.cursos.actividades.copiar', [
            'curso' => $cursoOrigen->id_curso,
            'actividad' => $fixture['id_actividad'],
        ]), $payload);

    $response->assertSessionHas('error');
});

test('responde 404 si la actividad no pertenece al curso', function () {
    $fixture = fixtureCursosHermanosParaCopiar();
    expect($fixture)->not->toBeNull();

    $usuario = Usuario::findOrFail($fixture['id_usuario']);
    $cursoOrigen = Curso::findOrFail($fixture['id_curso_origen']);

    // Tomar una actividad de otro curso
    $actividadAjena = Actividad::whereNotIn('id_componente', function ($q) use ($cursoOrigen) {
        $q->select('id_componente')->from('curso.componente')->where('id_curso', $cursoOrigen->id_curso);
    })->first();

    expect($actividadAjena)->not->toBeNull();

    $payload = [
        'id_curso_destino' => $fixture['id_curso_destino'],
        'id_componente_destino' => $fixture['id_componente_destino'],
        'id_unidad_destino' => $fixture['id_unidad_destino'],
    ];

    $response = $this->actingAs($usuario)
        ->post(route('docente.cursos.actividades.copiar', [
            'curso' => $cursoOrigen->id_curso,
            'actividad' => $actividadAjena->id_actividad,
        ]), $payload);

    $response->assertNotFound();
});
