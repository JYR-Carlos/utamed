<?php

use App\Enums\DB\EstadoRubrica;
use App\Enums\DB\TipoActividad;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Curso;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

function fixtureActividadEvaluacion(): ?array
{
    $row = DB::selectOne("
        SELECT c.id_curso, c.id_docente_titular, d.id_usuario, a.id_actividad, a.tipo_actividad, a.es_grupal, g.id_actividad_asignada_grupo
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        JOIN agenda.actividad a ON a.id_componente IN (SELECT id_componente FROM curso.componente WHERE id_curso = c.id_curso)
        JOIN agenda.actividad_asignada_grupo g ON g.id_actividad = a.id_actividad
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido', 'Docente Componente')
        ORDER BY a.id_actividad
        LIMIT 1
    ");

    if (!$row) {
        return null;
    }

    return (array) $row;
}

function dummyRubricaPayload(int $idActividad, string $criterioNombre = 'Criterio Original'): array
{
    return [
        'id_actividad' => $idActividad,
        'rubrica' => [
            'columnas' => [
                ['id' => 'col1', 'nombre' => 'Insuficiente', 'puntos' => 10],
                ['id' => 'col2', 'nombre' => 'Bueno', 'puntos' => 20],
            ],
            'niveles' => [
                [
                    'id' => 'niv1',
                    'nombre' => $criterioNombre,
                    'descripcion' => 'Descripción del criterio',
                    'ponderacion' => 100,
                    'nro_escalas' => 2,
                    'puntaje_total' => 20,
                    'puntaje_minimo' => 0,
                    'escalas' => [
                        ['id' => 'esc1', 'puntos' => 10, 'criterio' => 'Insuficiente desc'],
                        ['id' => 'esc2', 'puntos' => 20, 'criterio' => 'Bueno desc'],
                    ],
                ],
            ],
            'detalles_evaluacion' => [
                'puntaje_total' => 20,
                'escala_evaluacion' => [],
            ],
        ],
    ];
}

test('docente titular puede crear y luego editar la rúbrica si no hay evaluaciones', function () {
    $f = fixtureActividadEvaluacion();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = Usuario::findOrFail($f['id_usuario']);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    // Limpiar rúbricas previas de la actividad de prueba
    Rubrica::where('id_actividad', $f['id_actividad'])->delete();

    // 1. Crear rúbrica
    $payload = dummyRubricaPayload($f['id_actividad'], 'Criterio Inicial');
    $response = $this->actingAs($usuario)
        ->post("/docente/cursos/{$f['id_curso']}/rubrica", $payload);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $rubrica = Rubrica::where('id_actividad', $f['id_actividad'])->first();
    expect($rubrica)->not->toBeNull();
    expect($rubrica->estado_rubrica)->toBe(EstadoRubrica::POSTULADA);
    expect($rubrica->rubrica['niveles'][0]['nombre'])->toBe('Criterio Inicial');

    // 2. Editar rúbrica (antes de evaluar)
    $payloadEdit = dummyRubricaPayload($f['id_actividad'], 'Criterio Modificado');
    $responseEdit = $this->actingAs($usuario)
        ->post("/docente/cursos/{$f['id_curso']}/rubrica", $payloadEdit);

    $responseEdit->assertSessionHasNoErrors();

    // Debe existir una única rúbrica actualizada
    $rubricasCount = Rubrica::where('id_actividad', $f['id_actividad'])->count();
    expect($rubricasCount)->toBe(1);

    $rubrica->refresh();
    expect($rubrica->rubrica['niveles'][0]['nombre'])->toBe('Criterio Modificado');
});

test('primera evaluación transiciona la rúbrica a CERRADA y bloquea edición posterior', function () {
    $f = fixtureActividadEvaluacion();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = Usuario::findOrFail($f['id_usuario']);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    // Asegurar rúbrica en POSTULADA
    Rubrica::where('id_actividad', $f['id_actividad'])->delete();
    $rubrica = Rubrica::create([
        'id_actividad' => $f['id_actividad'],
        'rubrica' => dummyRubricaPayload($f['id_actividad'], 'Criterio Fijo')['rubrica'],
        'estado_rubrica' => EstadoRubrica::POSTULADA,
    ]);

    $actividad = Actividad::findOrFail($f['id_actividad']);
    $esSumativa = $actividad->tipo_actividad === TipoActividad::SUMATIVA;

    // Registrar evaluación
    $evalPayload = [
        'id_rubrica' => $rubrica->id_rubrica,
        'mensaje' => 'Retroalimentación de prueba',
        'puntaje_obtenido' => 18,
        'resultado' => ['niv1' => 'esc2'],
        'nota' => $esSumativa ? 6.5 : null,
        'evaluacion_obtenida' => $esSumativa ? null : 'Aprobado',
    ];

    $responseEval = $this->actingAs($usuario)
        ->post("/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/grupos/{$f['id_actividad_asignada_grupo']}/evaluacion", $evalPayload);

    $responseEval->assertSessionHasNoErrors();
    $responseEval->assertRedirect();

    // Rúbrica ahora debe ser CERRADA
    $rubrica->refresh();
    expect($rubrica->estado_rubrica)->toBe(EstadoRubrica::CERRADA);
    expect($actividad->fresh()->hanComenzadoEvaluaciones())->toBeTrue();

    // Intentar editar la rúbrica -> debe ser RECHAZADO
    $payloadIntento = dummyRubricaPayload($f['id_actividad'], 'Intento Modificar Cerrada');
    $responseIntento = $this->actingAs($usuario)
        ->post("/docente/cursos/{$f['id_curso']}/rubrica", $payloadIntento);

    $responseIntento->assertSessionHasErrors(['error', 'rubrica']);

    // La rúbrica no debió cambiar y no debe haberse creado una segunda rúbrica
    $rubrica->refresh();
    expect($rubrica->rubrica['niveles'][0]['nombre'])->toBe('Criterio Fijo');
    expect(Rubrica::where('id_actividad', $f['id_actividad'])->count())->toBe(1);
});

test('showEvaluacion expone tiene_evaluaciones = true y puede_editar_rubrica = false cuando ya hay evaluaciones', function () {
    $f = fixtureActividadEvaluacion();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = Usuario::findOrFail($f['id_usuario']);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    // Crear rúbrica CERRADA
    Rubrica::where('id_actividad', $f['id_actividad'])->delete();
    $rubrica = Rubrica::create([
        'id_actividad' => $f['id_actividad'],
        'rubrica' => dummyRubricaPayload($f['id_actividad'])['rubrica'],
        'estado_rubrica' => EstadoRubrica::CERRADA,
    ]);

    $response = $this->actingAs($usuario)
        ->get("/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/evaluacion");

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('docente/Activities/Index')
        ->where('tiene_evaluaciones', true)
        ->where('puede_editar_rubrica', false)
        ->where('estado_rubrica', 'CERRADA')
    );
});

test('storeEvaluacion rechaza rúbricas que pertenecen a otra actividad', function () {
    $f = fixtureActividadEvaluacion();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = Usuario::findOrFail($f['id_usuario']);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    // Crear otra actividad ajena o usar otra actividad existente
    $otraActividad = Actividad::where('id_actividad', '!=', $f['id_actividad'])->first();
    if (!$otraActividad) {
        $this->markTestSkipped('Se requiere al menos otra actividad en la base de datos.');
    }

    $rubricaAjena = Rubrica::create([
        'id_actividad' => $otraActividad->id_actividad,
        'rubrica' => dummyRubricaPayload($otraActividad->id_actividad)['rubrica'],
        'estado_rubrica' => EstadoRubrica::POSTULADA,
    ]);

    $actividad = Actividad::findOrFail($f['id_actividad']);
    $esSumativa = $actividad->tipo_actividad === TipoActividad::SUMATIVA;

    // Intentar evaluar en la actividad $f con la rúbrica de $otraActividad
    $evalPayload = [
        'id_rubrica' => $rubricaAjena->id_rubrica,
        'mensaje' => 'Intento con rúbrica ajena',
        'puntaje_obtenido' => 15,
        'nota' => $esSumativa ? 5.0 : null,
        'evaluacion_obtenida' => $esSumativa ? null : 'Aprobado',
    ];

    $response = $this->actingAs($usuario)
        ->post("/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/grupos/{$f['id_actividad_asignada_grupo']}/evaluacion", $evalPayload);

    $response->assertSessionHasErrors(['id_rubrica']);
});
