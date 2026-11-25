<?php

/**
 * Hilo de la agenda de un grupo (GET …/actividades/{a}/grupos/{g}/mensajes): lo
 * que la agenda del docente pide cada pocos segundos mientras está abierta. Por
 * eso tiene que ser liviano y no escribir nada si no hay mensajes nuevos (FIX-20).
 */

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/** @return array{docente: Usuario, url: string, curso: int, actividad: int, mensaje: Agenda} */
function fixtureHiloAgendaGrupo($test): array
{
    $row = DB::selectOne("
        SELECT c.id_curso, d.id_usuario, a.id_actividad, aag.id_actividad_asignada_grupo AS id_grupo, e.id_usuario AS id_alumno
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        JOIN curso.componente co ON co.id_curso = c.id_curso
        JOIN agenda.actividad a ON a.id_componente = co.id_componente
        JOIN agenda.actividad_asignada_grupo aag ON aag.id_actividad = a.id_actividad
        JOIN agenda.integrante_grupo ig ON ig.id_actividad_asignada_grupo = aag.id_actividad_asignada_grupo
        JOIN usuario.estudiante e ON e.id_estudiante = ig.id_estudiante
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido')
        ORDER BY a.id_actividad, aag.id_actividad_asignada_grupo
        LIMIT 1
    ") ?? $test->markTestSkipped('Sin curso con titular activo y grupo con integrantes.');

    $docente = Usuario::findOrFail($row->id_usuario);
    $docente->fecha_cambio_passhash = now();
    $docente->save();

    return [
        'docente' => $docente,
        'url' => "/docente/cursos/{$row->id_curso}/actividades/{$row->id_actividad}/grupos/{$row->id_grupo}/mensajes",
        'curso' => (int) $row->id_curso,
        'actividad' => (int) $row->id_actividad,
        'mensaje' => Agenda::create([
            'fecha_envio' => now()->toDateTimeString(),
            'tipo_mensaje' => TipoMensaje::MENSAJE_AL_PROFESOR,
            'mensaje' => 'consulta del alumno (test)',
            'id_usuario_emisor' => $row->id_alumno,
            'id_actividad_asignada_grupo' => $row->id_grupo,
        ]),
    ];
}

test('el hilo trae los mensajes del grupo y marca como visto lo que el docente no había leído', function () {
    $f = fixtureHiloAgendaGrupo($this);

    $this->actingAs($f['docente'])->getJson($f['url'])
        ->assertOk()
        ->assertJsonFragment(['id_interaccion' => $f['mensaje']->id_agenda, 'mensaje' => 'consulta del alumno (test)']);

    expect(DB::table('agenda.lectura_agenda')
        ->where('id_agenda', $f['mensaje']->id_agenda)
        ->where('id_usuario_lector', $f['docente']->id_usuario)->count())->toBe(1);
});

test('refrescar un hilo sin mensajes nuevos no escribe ni gasta ids de lectura_agenda', function () {
    $f = fixtureHiloAgendaGrupo($this);
    $estado = fn () => [
        DB::table('agenda.lectura_agenda')->count(),
        DB::scalar('SELECT last_value FROM agenda.lectura_agenda_id_lectura_agenda_seq'),
    ];

    $this->actingAs($f['docente'])->getJson($f['url'])->assertOk();
    $antes = $estado();
    $this->actingAs($f['docente'])->getJson($f['url'])->assertOk();

    expect($estado())->toBe($antes);
});

test('el hilo de un grupo de otra actividad devuelve 404', function () {
    $f = fixtureHiloAgendaGrupo($this);
    $ajeno = ActividadAsignadaGrupo::where('id_actividad', '!=', $f['actividad'])->value('id_actividad_asignada_grupo')
        ?? $this->markTestSkipped('No hay grupos de otra actividad.');

    $this->actingAs($f['docente'])
        ->getJson("/docente/cursos/{$f['curso']}/actividades/{$f['actividad']}/grupos/{$ajeno}/mensajes")
        ->assertNotFound();
});
