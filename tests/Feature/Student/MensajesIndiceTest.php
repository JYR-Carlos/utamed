<?php

/**
 * Buzón del estudiante (OBS-48, OBS-54): la mensajería de sus ramos y la
 * agenda de sus actividades en una lista, cada fila con su origen y el enlace
 * a la conversación. Abrir el buzón no marca nada como leído.
 */

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Agenda;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * Un alumno (rol Estudiante, inscrito en el curso y en el componente) con un
 * grupo en una actividad visible, el titular del curso y otro alumno del
 * mismo componente. Deja al alumno con algo sin leer de cada origen: feedback
 * en la agenda, un mensaje privado del titular y un aviso del curso.
 *
 * @return array{alumno: Usuario, otro: int, titular: int, curso: int, componente: int, actividad: int, grupo: int}
 */
function fixtureBuzon($test): array
{
    $row = DB::selectOne("
        SELECT g.id_actividad_asignada_grupo AS grupo, a.id_actividad, a.id_componente, c.id_curso, e.id_usuario, d.id_usuario AS titular
        FROM agenda.actividad_asignada_grupo g
        JOIN agenda.actividad a ON a.id_actividad = g.id_actividad
        JOIN curso.componente co ON co.id_componente = a.id_componente
        JOIN curso.curso c ON c.id_curso = co.id_curso
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN agenda.integrante_grupo ig ON ig.id_actividad_asignada_grupo = g.id_actividad_asignada_grupo
        JOIN usuario.estudiante e ON e.id_estudiante = ig.id_estudiante
        JOIN curso.inscripcion_curso ic ON ic.id_estudiante = e.id_estudiante AND ic.id_curso = c.id_curso
             AND ic.estado_inscripcion = 'INSCRITO'
        JOIN curso.inscripcion_componente icm ON icm.id_estudiante = e.id_estudiante AND icm.id_componente = co.id_componente
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = e.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE ura.esta_activo AND NOT ura.fue_eliminado AND r.nombre = 'Estudiante'
          AND a.visible AND NOT a.es_plantilla
        ORDER BY g.id_actividad_asignada_grupo
        LIMIT 1
    ");
    if (!$row) {
        $test->markTestSkipped('No hay un grupo visible con un alumno inscrito en el curso y el componente.');
    }

    $otro = DB::table('curso.inscripcion_componente as icm')
        ->join('usuario.estudiante as e', 'e.id_estudiante', '=', 'icm.id_estudiante')
        ->where('icm.id_componente', $row->id_componente)
        ->where('e.id_usuario', '<>', $row->id_usuario)
        ->value('e.id_usuario');
    if (!$otro) {
        $test->markTestSkipped('El componente no tiene un segundo alumno.');
    }

    $alumno = Usuario::findOrFail($row->id_usuario);
    $alumno->fecha_cambio_passhash = now();
    $alumno->save();

    $ahora = now()->addMinute();

    Agenda::create([
        'fecha_envio'                 => $ahora->toDateTimeString(),
        'tipo_mensaje'                => TipoMensaje::FEEDBACK,
        'mensaje'                     => 'Revisa la bibliografía (test buzón)',
        'id_usuario_emisor'           => (int) $row->titular,
        'id_actividad_asignada_grupo' => (int) $row->grupo,
    ]);

    $mensaje = fn (string $tipo, ?int $receptor, string $tema, string $texto) => DB::table('curso.mensaje')->insert([
        'id_componente'       => $row->id_componente,
        'id_usuario_emisor'   => $row->titular,
        'id_usuario_receptor' => $receptor,
        'tipo_mensaje'        => $tipo,
        'tema'                => $tema,
        'mensaje'             => $texto,
        'fecha_creacion'      => $ahora,
    ]);
    $mensaje('MENSAJE_INDIVIDUAL', (int) $row->id_usuario, 'Tema privado (test buzón)', 'Hola, ¿cómo vas?');
    $mensaje('MENSAJE_PARA_TODO_EL_CURSO', null, 'Aviso (test buzón)', 'Mañana no hay clases.');
    $mensaje('MENSAJE_INDIVIDUAL', (int) $otro, 'Tema ajeno (test buzón)', 'Esto es sólo para el otro alumno.');

    return [
        'alumno'     => $alumno,
        'otro'       => (int) $otro,
        'titular'    => (int) $row->titular,
        'curso'      => (int) $row->id_curso,
        'componente' => (int) $row->id_componente,
        'actividad'  => (int) $row->id_actividad,
        'grupo'      => (int) $row->grupo,
    ];
}

/** Las filas del buzón que ve el alumno. */
function hilosDelBuzon($test, Usuario $alumno): array
{
    $respuesta = $test->actingAs($alumno)->get('/estudiante/mensajes');
    $respuesta->assertOk()->assertInertia(fn (Assert $page) => $page->component('student/Mensajes')->has('hilos'));

    return $respuesta->viewData('page')['props']['hilos'];
}

test('el buzón junta la agenda de la actividad, la conversación privada y los avisos, cada uno con su origen', function () {
    $f = fixtureBuzon($this);
    $hilos = collect(hilosDelBuzon($this, $f['alumno']))->keyBy('clave');

    $actividad = $hilos["g-{$f['grupo']}"] ?? null;
    expect($actividad)->not->toBeNull()
        ->and($actividad['origen'])->toBe('actividad')
        ->and($actividad['tipo'])->toBe('Feedback')
        ->and($actividad['no_leidos'])->toBeGreaterThanOrEqual(1)
        ->and($actividad['href'])->toBe("/estudiante/cursos/{$f['curso']}/actividad/{$f['actividad']}?seccion=agenda");

    $privado = $hilos["m-{$f['componente']}-{$f['titular']}"] ?? null;
    expect($privado)->not->toBeNull()
        ->and($privado['origen'])->toBe('mensaje')
        ->and($privado['tema'])->toBe('Tema privado (test buzón)')
        ->and($privado['es_mio'])->toBeFalse()
        ->and($privado['no_leidos'])->toBeGreaterThanOrEqual(1)
        ->and($privado['href'])->toContain("componente_id={$f['componente']}&interlocutor_id={$f['titular']}");

    $avisos = $hilos["a-{$f['componente']}"] ?? null;
    expect($avisos)->not->toBeNull()
        ->and($avisos['origen'])->toBe('aviso')
        ->and($avisos['tema'])->toBe('Aviso (test buzón)')
        ->and($avisos['href'])->toContain('vista=avisos');

    // Lo que tiene algo sin leer va antes que lo leído.
    $orden = array_column($hilos->values()->all(), 'no_leidos');
    $primeroLeido = array_search(0, $orden, true);
    if ($primeroLeido !== false) {
        expect(array_filter(array_slice($orden, $primeroLeido)))->toBeEmpty();
    }
});

test('el buzón no muestra conversaciones privadas de otros alumnos', function () {
    $f = fixtureBuzon($this);
    $hilos = hilosDelBuzon($this, $f['alumno']);

    expect(array_column($hilos, 'tema'))->not->toContain('Tema ajeno (test buzón)')
        ->and(array_column($hilos, 'texto'))->not->toContain('Esto es sólo para el otro alumno.');
});

test('abrir el buzón no marca nada como leído y la cifra del menú suma lo de las filas', function () {
    $f = fixtureBuzon($this);
    $id = $f['alumno']->id_usuario;

    $lecturas = fn () => [
        DB::table('agenda.lectura_agenda')->where('id_usuario_lector', $id)->count(),
        DB::table('curso.interaccion_mensaje')->where('id_usuario_lector', $id)->count(),
    ];
    $antes = $lecturas();

    $hilos = hilosDelBuzon($this, $f['alumno']);

    expect($lecturas())->toBe($antes);

    $this->actingAs($f['alumno'])
        ->get('/estudiante/mensajes')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.estudiante_buzon_no_leidos', array_sum(array_column($hilos, 'no_leidos'))));
});

test('quien no es estudiante no entra al buzón del alumno', function () {
    $row = DB::selectOne("
        SELECT u.id_usuario
        FROM usuario.usuario u
        WHERE NOT EXISTS (SELECT 1 FROM usuario.estudiante e WHERE e.id_usuario = u.id_usuario)
        ORDER BY u.id_usuario
        LIMIT 1
    ");
    if (!$row) {
        $this->markTestSkipped('No hay un usuario que no sea estudiante.');
    }

    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    expect($this->actingAs($usuario)->get('/estudiante/mensajes')->status())->not->toBe(200);
});
