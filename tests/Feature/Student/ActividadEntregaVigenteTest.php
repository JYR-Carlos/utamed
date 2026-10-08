<?php

/*
 * Vista de actividad del estudiante (ActivityController::show): qué entrega
 * cuenta como vigente.
 *
 * - `ultima_entrega` es la última «Entrega de archivo» del grupo, salvo que esa
 *   misma haya sido cancelada (fila «Cancelación de entrega» del grupo con el
 *   mismo `uuid_archivo_subido`); en ese caso es null. Una cancelación de una
 *   entrega anterior ya no oculta la vigente (commit 6ea34ac).
 * - `entradas` excluye las entregas canceladas.
 * - Sin fecha límite, la prop `fecha_limite` llega como '' (commit 9bbf1ca: la
 *   tarjeta muestra «Sin fecha límite» en vez de «NaN/NaN»).
 */

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Agenda;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

// Sólo interesan las props de Inertia; no depender del build de Vite.
beforeEach(fn () => $this->withoutVite());

/**
 * Estudiante (rol activo) inscrito en el curso, integrante de un grupo de una
 * actividad visible de ese curso, cuyo hilo de agenda está vacío.
 */
function entregaVigente_fixtureEntregaVigente(): ?array
{
    $row = DB::selectOne("
        SELECT e.id_usuario, comp.id_curso, a.id_actividad, g.id_actividad_asignada_grupo
        FROM agenda.integrante_grupo ig
        JOIN agenda.actividad_asignada_grupo g ON g.id_actividad_asignada_grupo = ig.id_actividad_asignada_grupo
        JOIN agenda.actividad a ON a.id_actividad = g.id_actividad
        JOIN curso.componente comp ON comp.id_componente = a.id_componente
        JOIN usuario.estudiante e ON e.id_estudiante = ig.id_estudiante
        JOIN curso.inscripcion_curso ic ON ic.id_estudiante = e.id_estudiante
             AND ic.id_curso = comp.id_curso AND ic.estado_inscripcion = 'INSCRITO'
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = e.id_usuario
             AND ura.esta_activo AND NOT ura.fue_eliminado
        JOIN usuario.rol r ON r.id_rol = ura.id_rol AND r.nombre = 'Estudiante'
        WHERE a.visible
          AND NOT EXISTS (
              SELECT 1 FROM agenda.agenda ag
              WHERE ag.id_actividad_asignada_grupo = g.id_actividad_asignada_grupo
          )
        ORDER BY g.id_actividad_asignada_grupo
        LIMIT 1
    ");

    if (!$row) {
        return null;
    }

    $f = (array) $row;

    $usuario = Usuario::findOrFail($f['id_usuario']);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();
    $f['usuario'] = $usuario;

    return $f;
}

/** Crea un archivo en operaciones.archivo y devuelve su uuid. */
function entregaVigente_archivoDePrueba(string $nombre): string
{
    $uuid = (string) Str::uuid();

    DB::table('operaciones.archivo')->insert([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "tests/entregas/{$uuid}.pdf",
        'nombre_original' => $nombre,
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 1024,
    ]);

    return $uuid;
}

/**
 * Fila de agenda del grupo. `$haceMinutos` ordena el hilo: a menor valor,
 * más reciente.
 */
function entregaVigente_filaAgenda(array $f, TipoMensaje $tipo, ?string $uuidArchivo, int $haceMinutos): Agenda
{
    return Agenda::create([
        'fecha_envio' => now()->subMinutes($haceMinutos),
        'tipo_mensaje' => $tipo,
        'mensaje' => $tipo->value,
        'uuid_archivo_subido' => $uuidArchivo,
        'id_usuario_emisor' => $f['usuario']->id_usuario,
        'id_actividad_asignada_grupo' => $f['id_actividad_asignada_grupo'],
    ]);
}

function entregaVigente_verActividad($test, array $f)
{
    return $test->actingAs($f['usuario'])
        ->get("/estudiante/cursos/{$f['id_curso']}/actividad/{$f['id_actividad']}")
        ->assertOk();
}

/** Ids de las entradas (entregas vigentes) que recibe la vista. */
function entregaVigente_idsEntradas($response): array
{
    $ids = [];
    $response->assertInertia(function (Assert $page) use (&$ids) {
        $page->component('student/Activities/Index');
        $ids = collect($page->toArray()['props']['entradas'])->pluck('id')->map(fn ($id) => (int) $id)->all();
    });

    return $ids;
}

test('con dos entregas, la vigente es la más reciente', function () {
    $f = entregaVigente_fixtureEntregaVigente();
    if (!$f) {
        $this->markTestSkipped('No hay estudiante inscrito con grupo de actividad visible y hilo vacío.');
    }

    $a = entregaVigente_filaAgenda($f, TipoMensaje::ENTREGA_DE_ARCHIVO, entregaVigente_archivoDePrueba('a.pdf'), 30);
    $b = entregaVigente_filaAgenda($f, TipoMensaje::ENTREGA_DE_ARCHIVO, entregaVigente_archivoDePrueba('b.pdf'), 20);

    $response = entregaVigente_verActividad($this, $f);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('student/Activities/Index')
        ->where('ultima_entrega.id_interaccion', $b->id_agenda)
    );
    expect(entregaVigente_idsEntradas($response))->toBe([$a->id_agenda, $b->id_agenda]);
});

test('si se cancela la última entrega, no hay entrega vigente y la cancelada sale de entradas', function () {
    $f = entregaVigente_fixtureEntregaVigente();
    if (!$f) {
        $this->markTestSkipped('No hay estudiante inscrito con grupo de actividad visible y hilo vacío.');
    }

    $a = entregaVigente_filaAgenda($f, TipoMensaje::ENTREGA_DE_ARCHIVO, entregaVigente_archivoDePrueba('a.pdf'), 30);
    $uuidB = entregaVigente_archivoDePrueba('b.pdf');
    entregaVigente_filaAgenda($f, TipoMensaje::ENTREGA_DE_ARCHIVO, $uuidB, 20);
    entregaVigente_filaAgenda($f, TipoMensaje::CANCELACIÓN_DE_ENTREGA, $uuidB, 10);

    $response = entregaVigente_verActividad($this, $f);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('student/Activities/Index')
        ->where('ultima_entrega', null)
    );
    expect(entregaVigente_idsEntradas($response))->toBe([$a->id_agenda]);
});

test('una entrega nueva después de cancelar pasa a ser la vigente', function () {
    $f = entregaVigente_fixtureEntregaVigente();
    if (!$f) {
        $this->markTestSkipped('No hay estudiante inscrito con grupo de actividad visible y hilo vacío.');
    }

    $a = entregaVigente_filaAgenda($f, TipoMensaje::ENTREGA_DE_ARCHIVO, entregaVigente_archivoDePrueba('a.pdf'), 40);
    $uuidB = entregaVigente_archivoDePrueba('b.pdf');
    entregaVigente_filaAgenda($f, TipoMensaje::ENTREGA_DE_ARCHIVO, $uuidB, 30);
    entregaVigente_filaAgenda($f, TipoMensaje::CANCELACIÓN_DE_ENTREGA, $uuidB, 20);
    $c = entregaVigente_filaAgenda($f, TipoMensaje::ENTREGA_DE_ARCHIVO, entregaVigente_archivoDePrueba('c.pdf'), 10);

    $response = entregaVigente_verActividad($this, $f);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('student/Activities/Index')
        ->where('ultima_entrega.id_interaccion', $c->id_agenda)
    );
    expect(entregaVigente_idsEntradas($response))->toBe([$a->id_agenda, $c->id_agenda]);
});

test('la cancelación de una entrega antigua no oculta la entrega vigente posterior', function () {
    $f = entregaVigente_fixtureEntregaVigente();
    if (!$f) {
        $this->markTestSkipped('No hay estudiante inscrito con grupo de actividad visible y hilo vacío.');
    }

    // Hilo heredado de antes del arreglo: la cancelación de A quedó registrada
    // después de que B ya estaba entregada. Antes, cualquier cancelación más
    // reciente que la última entrega dejaba al grupo «sin entrega».
    $uuidA = entregaVigente_archivoDePrueba('a.pdf');
    entregaVigente_filaAgenda($f, TipoMensaje::ENTREGA_DE_ARCHIVO, $uuidA, 30);
    $b = entregaVigente_filaAgenda($f, TipoMensaje::ENTREGA_DE_ARCHIVO, entregaVigente_archivoDePrueba('b.pdf'), 20);
    entregaVigente_filaAgenda($f, TipoMensaje::CANCELACIÓN_DE_ENTREGA, $uuidA, 10);

    $response = entregaVigente_verActividad($this, $f);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('student/Activities/Index')
        ->where('ultima_entrega.id_interaccion', $b->id_agenda)
    );
    expect(entregaVigente_idsEntradas($response))->toBe([$b->id_agenda]);
});

test('una actividad sin fecha límite envía fecha_limite vacía', function () {
    $f = entregaVigente_fixtureEntregaVigente();
    if (!$f) {
        $this->markTestSkipped('No hay estudiante inscrito con grupo de actividad visible y hilo vacío.');
    }

    DB::table('agenda.actividad')
        ->where('id_actividad', $f['id_actividad'])
        ->update(['fecha_limite' => null]);

    entregaVigente_verActividad($this, $f)->assertInertia(fn (Assert $page) => $page
        ->component('student/Activities/Index')
        ->where('fecha_limite', '')
    );
});
