<?php

/**
 * Feature test para la evaluación de actividades formativas por parte del docente.
 *
 * En actividades formativas:
 * - No se requiere rúbrica (id_rubrica es nullable).
 * - No se permite nota numérica (nota es prohibited).
 * - Se exige evaluacion_obtenida en ('Bueno', 'Regular', 'Malo').
 * - Se exige un mensaje de evaluación (mensaje hasta 4000 caracteres).
 */

use App\Enums\DB\TipoActividad;
use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\Evaluacion;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

function fixtureDocenteEvaluacionFormativa(): ?array
{
    $row = DB::selectOne("
        SELECT c.id_curso, c.id_docente_titular, d.id_usuario, a.id_actividad, g.id_actividad_asignada_grupo
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

    return $row ? (array) $row : null;
}

function docenteEvaluacionFormativa(array $f): Usuario
{
    $usuario = Usuario::findOrFail($f['id_usuario']);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    return $usuario;
}

test('storeEvaluacion registra exitosamente una evaluacion formativa sin rubrica ni nota numerica', function () {
    $f = fixtureDocenteEvaluacionFormativa();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = docenteEvaluacionFormativa($f);
    $actividad = Actividad::findOrFail($f['id_actividad']);
    $actividad->tipo_actividad = TipoActividad::FORMATIVA;
    $actividad->save();

    $grupo = ActividadAsignadaGrupo::findOrFail($f['id_actividad_asignada_grupo']);
    $grupo->nota = null;
    $grupo->save();

    $mensajeLargo = trim('Excelente progreso formativo. ' . str_repeat('Detalle de retroalimentacion pedagógica sobre el trabajo realizado. ', 5));

    $response = $this->actingAs($usuario)
        ->post(
            "/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/grupos/{$f['id_actividad_asignada_grupo']}/evaluacion",
            [
                'id_agenda_entrega' => null,
                'id_rubrica' => null,
                'evaluacion_obtenida' => 'Bueno',
                'mensaje' => $mensajeLargo,
                'nota' => null,
            ]
        );

    $response->assertSessionDoesntHaveErrors();
    $response->assertSessionHas('success', 'Evaluación registrada correctamente.');

    // Verificar en agenda.agenda
    $agendaItem = Agenda::where('id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->where('tipo_mensaje', TipoMensaje::EVALUACIÓN->value)
        ->orderByDesc('id_agenda')
        ->first();

    expect($agendaItem)->not->toBeNull()
        ->and($agendaItem->mensaje)->toBe($mensajeLargo)
        ->and($agendaItem->id_usuario_emisor)->toBe($usuario->id_usuario);

    // Verificar en agenda.evaluacion
    $evaluacion = Evaluacion::where('id_agenda', $agendaItem->id_agenda)->first();
    expect($evaluacion)->not->toBeNull()
        ->and($evaluacion->evaluacion_obtenida)->toBe('Bueno')
        ->and($evaluacion->id_rubrica)->toBeNull()
        ->and($evaluacion->puntaje_obtenido)->toBeNull()
        ->and($evaluacion->resultado)->toBeNull();

    // La nota del grupo debe permanecer null
    $grupo->refresh();
    expect($grupo->nota)->toBeNull();
});

test('storeEvaluacion rechaza nota numerica en una actividad formativa', function () {
    $f = fixtureDocenteEvaluacionFormativa();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = docenteEvaluacionFormativa($f);
    $actividad = Actividad::findOrFail($f['id_actividad']);
    $actividad->tipo_actividad = TipoActividad::FORMATIVA;
    $actividad->save();

    $response = $this->actingAs($usuario)
        ->post(
            "/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/grupos/{$f['id_actividad_asignada_grupo']}/evaluacion",
            [
                'id_agenda_entrega' => null,
                'id_rubrica' => null,
                'evaluacion_obtenida' => 'Bueno',
                'mensaje' => 'Retroalimentación formativa',
                'nota' => 6.5,
            ]
        );

    $response->assertSessionHasErrors(['nota']);
});

test('storeEvaluacion rechaza evaluacion_obtenida fuera de Bueno, Regular, Malo en formativas', function () {
    $f = fixtureDocenteEvaluacionFormativa();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = docenteEvaluacionFormativa($f);
    $actividad = Actividad::findOrFail($f['id_actividad']);
    $actividad->tipo_actividad = TipoActividad::FORMATIVA;
    $actividad->save();

    $response = $this->actingAs($usuario)
        ->post(
            "/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/grupos/{$f['id_actividad_asignada_grupo']}/evaluacion",
            [
                'id_agenda_entrega' => null,
                'id_rubrica' => null,
                'evaluacion_obtenida' => 'Excelente',
                'mensaje' => 'Retroalimentación formativa',
            ]
        );

    $response->assertSessionHasErrors(['evaluacion_obtenida']);
});

test('storeEvaluacion rechaza mensaje vacio en una actividad formativa', function () {
    $f = fixtureDocenteEvaluacionFormativa();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = docenteEvaluacionFormativa($f);
    $actividad = Actividad::findOrFail($f['id_actividad']);
    $actividad->tipo_actividad = TipoActividad::FORMATIVA;
    $actividad->save();

    $response = $this->actingAs($usuario)
        ->post(
            "/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/grupos/{$f['id_actividad_asignada_grupo']}/evaluacion",
            [
                'id_agenda_entrega' => null,
                'id_rubrica' => null,
                'evaluacion_obtenida' => 'Regular',
                'mensaje' => '',
            ]
        );

    $response->assertSessionHasErrors(['mensaje']);
});
