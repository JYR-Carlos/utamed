<?php

/**
 * Buzón del docente (/docente/mensajes): la mensajería de sus cursos y la
 * agenda de sus actividades en una lista, como el del alumno. Cada fila con su
 * origen y el enlace a la conversación; sólo las conversaciones propias; abrir
 * el buzón no marca nada como leído.
 */

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Agenda;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * El titular de un curso con un grupo en una actividad, un alumno de ese grupo
 * inscrito en el componente y otro alumno del mismo componente. Deja al
 * titular con algo sin leer de cada origen (una consulta en la agenda y un
 * mensaje privado del alumno), un aviso suyo y una conversación privada del
 * otro alumno con otra persona, que el titular no debe ver.
 *
 * @return array{docente: Usuario, alumno: int, curso: int, componente: int, actividad: int, grupo: int}
 */
function fixtureBuzonDocente($test): array
{
    $row = DB::selectOne("
        SELECT g.id_actividad_asignada_grupo AS grupo, a.id_actividad, a.id_componente, c.id_curso,
               e.id_usuario AS alumno, d.id_usuario AS titular
        FROM agenda.actividad_asignada_grupo g
        JOIN agenda.actividad a ON a.id_actividad = g.id_actividad
        JOIN curso.componente co ON co.id_componente = a.id_componente
        JOIN curso.curso c ON c.id_curso = co.id_curso
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN agenda.integrante_grupo ig ON ig.id_actividad_asignada_grupo = g.id_actividad_asignada_grupo
        JOIN usuario.estudiante e ON e.id_estudiante = ig.id_estudiante
        JOIN curso.inscripcion_componente icm ON icm.id_estudiante = e.id_estudiante AND icm.id_componente = co.id_componente
        WHERE NOT a.es_plantilla AND NOT c.es_plantilla AND c.fecha_eliminacion IS NULL
        ORDER BY g.id_actividad_asignada_grupo
        LIMIT 1
    ");
    if (!$row) {
        $test->markTestSkipped('No hay un grupo con un alumno inscrito en el componente de un curso con titular.');
    }

    $otro = DB::table('curso.inscripcion_componente as icm')
        ->join('usuario.estudiante as e', 'e.id_estudiante', '=', 'icm.id_estudiante')
        ->where('icm.id_componente', $row->id_componente)
        ->where('e.id_usuario', '<>', $row->alumno)
        ->value('e.id_usuario');
    $tercero = DB::table('usuario.usuario')
        ->whereNotIn('id_usuario', array_filter([$row->titular, $row->alumno, $otro]))
        ->value('id_usuario');
    if (!$otro || !$tercero) {
        $test->markTestSkipped('Faltan un segundo alumno en el componente u otro usuario.');
    }

    $docente = Usuario::findOrFail($row->titular);
    $docente->fecha_cambio_passhash = now()->subDay();
    $docente->save();

    $ahora = now()->addMinute();

    Agenda::create([
        'fecha_envio'                 => $ahora->toDateTimeString(),
        'tipo_mensaje'                => TipoMensaje::MENSAJE_AL_PROFESOR,
        'mensaje'                     => 'Profe, ¿la entrega es en PDF? (test buzón docente)',
        'id_usuario_emisor'           => (int) $row->alumno,
        'id_actividad_asignada_grupo' => (int) $row->grupo,
    ]);

    $mensaje = fn (int $emisor, ?int $receptor, string $tipo, string $tema, string $texto) => DB::table('curso.mensaje')->insert([
        'id_componente'       => $row->id_componente,
        'id_usuario_emisor'   => $emisor,
        'id_usuario_receptor' => $receptor,
        'tipo_mensaje'        => $tipo,
        'tema'                => $tema,
        'mensaje'             => $texto,
        'fecha_creacion'      => $ahora,
    ]);
    $mensaje((int) $row->alumno, (int) $row->titular, 'MENSAJE_INDIVIDUAL', 'Consulta privada (test buzón docente)', 'Profe, ¿puedo hablarle de algo?');
    $mensaje((int) $row->titular, null, 'MENSAJE_PARA_TODO_EL_CURSO', 'Aviso (test buzón docente)', 'Mañana no hay clases.');
    $mensaje((int) $otro, (int) $tercero, 'MENSAJE_INDIVIDUAL', 'Tema ajeno (test buzón docente)', 'Esto no es del titular.');

    return [
        'docente'    => $docente,
        'alumno'     => (int) $row->alumno,
        'curso'      => (int) $row->id_curso,
        'componente' => (int) $row->id_componente,
        'actividad'  => (int) $row->id_actividad,
        'grupo'      => (int) $row->grupo,
    ];
}

/** Las props del buzón que ve el docente. */
function buzonDelDocente($test, Usuario $docente): array
{
    $respuesta = $test->actingAs($docente)->get('/docente/mensajes');
    $respuesta->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('docente/Mensajes')
        ->has('hilos')
        ->has('cursos'));

    return $respuesta->viewData('page')['props'];
}

test('el buzón junta la agenda de la actividad, la conversación privada y los avisos, cada uno con su origen', function () {
    $f = fixtureBuzonDocente($this);
    $hilos = collect(buzonDelDocente($this, $f['docente'])['hilos'])->keyBy('clave');

    $actividad = $hilos["g-{$f['actividad']}"] ?? null;
    expect($actividad)->not->toBeNull()
        ->and($actividad['origen'])->toBe('actividad')
        ->and($actividad['tipo'])->toBe('Mensaje al profesor')
        ->and($actividad['no_leidos'])->toBeGreaterThanOrEqual(1)
        ->and($actividad['por_responder'])->toBeGreaterThanOrEqual(1)
        ->and($actividad['href'])->toBe("/docente/cursos/{$f['curso']}/actividades/{$f['actividad']}/evaluacion?grupo={$f['grupo']}");

    $privado = $hilos["m-{$f['componente']}-{$f['alumno']}"] ?? null;
    expect($privado)->not->toBeNull()
        ->and($privado['origen'])->toBe('mensaje')
        ->and($privado['tema'])->toBe('Consulta privada (test buzón docente)')
        ->and($privado['es_mio'])->toBeFalse()
        ->and($privado['no_leidos'])->toBeGreaterThanOrEqual(1)
        ->and($privado['por_responder'])->toBe(1)
        ->and($privado['href'])->toBe("/docente/cursos/{$f['curso']}/mensajeria?componente_id={$f['componente']}&alumno_id={$f['alumno']}");

    $avisos = $hilos["a-{$f['componente']}"] ?? null;
    expect($avisos)->not->toBeNull()
        ->and($avisos['origen'])->toBe('aviso')
        ->and($avisos['es_mio'])->toBeTrue()
        ->and($avisos['href'])->toBe("/docente/cursos/{$f['curso']}/mensajeria?componente_id={$f['componente']}");

    // Lo que tiene algo sin leer va antes que lo leído.
    $orden = array_column($hilos->values()->all(), 'no_leidos');
    $primeroLeido = array_search(0, $orden, true);
    if ($primeroLeido !== false) {
        expect(array_filter(array_slice($orden, $primeroLeido)))->toBeEmpty();
    }
});

test('el buzón no muestra conversaciones privadas de otras personas', function () {
    $f = fixtureBuzonDocente($this);
    $hilos = buzonDelDocente($this, $f['docente'])['hilos'];

    expect(array_column($hilos, 'tema'))->not->toContain('Tema ajeno (test buzón docente)')
        ->and(array_column($hilos, 'texto'))->not->toContain('Esto no es del titular.');
});

test('abrir el buzón no marca nada como leído y la cifra del menú suma lo de las filas', function () {
    $f = fixtureBuzonDocente($this);
    $id = $f['docente']->id_usuario;

    $lecturas = fn () => [
        DB::table('agenda.lectura_agenda')->where('id_usuario_lector', $id)->count(),
        DB::table('curso.interaccion_mensaje')->where('id_usuario_lector', $id)->count(),
    ];
    $antes = $lecturas();

    $hilos = buzonDelDocente($this, $f['docente'])['hilos'];

    expect($lecturas())->toBe($antes);

    $this->actingAs($f['docente'])
        ->get('/docente/mensajes')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.docente_buzon_no_leidos', array_sum(array_column($hilos, 'no_leidos'))));
});

test('el buzón trae los cursos vigentes para escribir un mensaje nuevo, y «Mensajes para» lleva al buzón', function () {
    $f = fixtureBuzonDocente($this);
    $props = buzonDelDocente($this, $f['docente']);

    expect($props['periodo'])->toHaveKeys(['sem', 'ano']);
    foreach ($props['cursos'] as $c) {
        expect($c)->toHaveKeys(['id_curso', 'nombre', 'codigo', 'letra_grupo', 'no_leidos'])
            ->not->toHaveKey('total_estudiantes');
    }

    $this->actingAs($f['docente'])->get('/docente/mensajeria')->assertRedirect('/docente/mensajes');
});
