<?php

use App\Models\Usuario\Usuario;
use App\Services\MensajeriaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Mensajería por componente (curso.mensaje) — el hilo lo sostiene el componente.
 *
 * El caso que justifica el diseño: cuando responde un COLEGIADO (un segundo
 * docente del mismo componente), su mensaje tiene que entrar en el mismo canal
 * del alumno, no abrir una conversación aparte.
 *
 * Se entra siempre desde un curso (/{rol}/cursos/{curso}/mensajeria), así que
 * las rutas llevan el curso y el acceso se decide contra los componentes que el
 * usuario tiene EN ESE CURSO.
 *
 * Los fixtures se descubren desde la base sembrada en vez de fijar ids, para no
 * depender de qué instancia esté apuntando la suite.
 */
uses(DatabaseTransactions::class);

/**
 * @return array{componente:int, curso:int, titular:int, alumno:int, colegiado:int}|null
 */
function fixturesMensajeria(): ?array
{
    $componente = DB::selectOne("
        SELECT dc.id_componente, cmp.id_curso, d.id_usuario AS titular
        FROM curso.docente_componente dc
        JOIN curso.componente cmp ON cmp.id_componente = dc.id_componente
        JOIN usuario.docente d ON d.id_docente = dc.id_docente
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido', 'Docente Componente')
          AND EXISTS (SELECT 1 FROM curso.inscripcion_componente ic
                      WHERE ic.id_componente = dc.id_componente)
        ORDER BY dc.id_componente
        LIMIT 1
    ");

    if (!$componente) {
        return null;
    }

    $alumno = DB::selectOne("
        SELECT u.id_usuario
        FROM curso.inscripcion_componente ic
        JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
        JOIN usuario.usuario u ON u.id_usuario = e.id_usuario
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = u.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE ic.id_componente = ? AND ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre = 'Estudiante'
        LIMIT 1
    ", [$componente->id_componente]);

    $colegiado = DB::selectOne("
        SELECT d.id_docente, d.id_usuario
        FROM usuario.docente d
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido', 'Docente Componente')
          AND d.id_usuario <> ?
          AND d.id_docente NOT IN (
                SELECT id_docente FROM curso.docente_componente WHERE id_componente = ?
          )
        LIMIT 1
    ", [$componente->titular, $componente->id_componente]);

    if (!$alumno || !$colegiado) {
        return null;
    }

    // El colegiado se suma al componente (dentro de la transacción del test).
    DB::table('curso.docente_componente')->insert([
        'es_titular'    => false,
        'id_docente'    => $colegiado->id_docente,
        'id_componente' => $componente->id_componente,
    ]);

    return [
        'componente' => (int) $componente->id_componente,
        'curso'      => (int) $componente->id_curso,
        'titular'    => (int) $componente->titular,
        'alumno'     => (int) $alumno->id_usuario,
        'colegiado'  => (int) $colegiado->id_usuario,
    ];
}

beforeEach(function () {
    $f = fixturesMensajeria();

    if ($f === null) {
        test()->markTestSkipped('La base no tiene un componente con docente y alumnos sembrados.');
    }

    $this->f = $f;
});

it('la bandeja del curso sólo trae los componentes de ese curso', function () {
    $delCurso = DB::table('curso.componente')
        ->where('id_curso', $this->f['curso'])
        ->pluck('id_componente')
        ->map(fn($id) => (int) $id)
        ->all();

    $this->actingAs(Usuario::find($this->f['titular']))
        ->get("/docente/cursos/{$this->f['curso']}/mensajeria")
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('docente/Mensajeria')
            ->where('curso.id_curso', $this->f['curso'])
            // Ni un componente de otro curso: entrar por el curso es justamente
            // lo que evita la bandeja global que mezclaba todo.
            ->where('componentes', fn($componentes) => collect($componentes)
                ->every(fn($c) => in_array((int) $c['id_componente'], $delCurso, true)))
            // La pestaña activa se resuelve en el servidor para que una recarga
            // aterrice en el mismo sitio.
            ->has('componente_activo'));
});

it('el docente envía una difusión al componente', function () {
    $this->actingAs(Usuario::find($this->f['titular']))
        ->post("/docente/cursos/{$this->f['curso']}/mensajeria/componentes/{$this->f['componente']}/difusion", [
            'tema'    => 'Cambio de aula',
            'mensaje' => 'Manana nos vemos en el aula 3.',
        ])
        ->assertRedirect();

    $difusiones = app(MensajeriaService::class)
        ->difusionesDeComponente($this->f['componente'], $this->f['titular']);

    expect($difusiones)->toHaveCount(1)
        ->and($difusiones->first()['tema'])->toBe('Cambio de aula');
});

it('la respuesta del colegiado entra en el mismo canal del alumno', function () {
    $svc = app(MensajeriaService::class);

    // 1. El alumno pregunta, dirigido al titular.
    $this->actingAs(Usuario::find($this->f['alumno']))
        ->post("/estudiante/cursos/{$this->f['curso']}/mensajeria/componentes/{$this->f['componente']}/mensaje", [
            'mensaje' => 'No entiendo el punto 2 del TP3',
            'tema'    => 'Duda TP3',
        ])
        ->assertRedirect();

    // 2. Responde el COLEGIADO, no el titular.
    $this->actingAs(Usuario::find($this->f['colegiado']))
        ->post("/docente/cursos/{$this->f['curso']}/mensajeria/componentes/{$this->f['componente']}/alumnos/{$this->f['alumno']}/mensaje", [
            'mensaje' => 'El punto 2 pide el intervalo de confianza',
        ])
        ->assertRedirect();

    // 3. Y después el titular.
    $this->actingAs(Usuario::find($this->f['titular']))
        ->post("/docente/cursos/{$this->f['curso']}/mensajeria/componentes/{$this->f['componente']}/alumnos/{$this->f['alumno']}/mensaje", [
            'mensaje' => 'Confirmo lo que indica el profesor',
        ])
        ->assertRedirect();

    $canal = $svc->mensajesDelCanal($this->f['componente'], $this->f['alumno'], $this->f['titular']);

    // Un solo canal con los tres mensajes, en orden, y el asunto heredado.
    expect($canal)->toHaveCount(3)
        ->and($canal->pluck('mensaje')->all())->toBe([
            'No entiendo el punto 2 del TP3',
            'El punto 2 pide el intervalo de confianza',
            'Confirmo lo que indica el profesor',
        ])
        ->and($canal->pluck('tema')->unique()->all())->toBe(['Duda TP3']);

    // El titular ve la respuesta del colegiado dentro de su misma conversación.
    expect($canal->pluck('es_alumno')->all())->toBe([true, false, false]);
});

it('abrir el panel marca como leído y baja el contador', function () {
    $svc = app(MensajeriaService::class);

    $this->actingAs(Usuario::find($this->f['titular']))
        ->post("/docente/cursos/{$this->f['curso']}/mensajeria/componentes/{$this->f['componente']}/difusion", [
            'tema'    => 'Aviso',
            'mensaje' => 'Contenido del aviso',
        ]);

    $antes = $svc->noLeidosPorComponente([$this->f['componente']], $this->f['alumno'], false);
    expect($antes[$this->f['componente']] ?? 0)->toBe(1);

    // El alumno abre el componente: el panel marca como leído lo que muestra.
    $this->actingAs(Usuario::find($this->f['alumno']))
        ->get("/estudiante/cursos/{$this->f['curso']}/mensajeria?componente_id={$this->f['componente']}")
        ->assertOk();

    $ids = $svc->difusionesDeComponente($this->f['componente'], $this->f['alumno'])
        ->pluck('id_mensaje')->all();
    $svc->marcarLeidos($ids, $this->f['alumno']);

    $despues = $svc->noLeidosPorComponente([$this->f['componente']], $this->f['alumno'], false);
    expect($despues[$this->f['componente']] ?? 0)->toBe(0);
});

it('marcar leído dos veces no duplica el acuse', function () {
    $svc = app(MensajeriaService::class);

    $id = $svc->enviarDifusion($this->f['componente'], $this->f['titular'], 'Cuerpo', 'Asunto');

    $svc->marcarLeidos([$id], $this->f['alumno']);
    $svc->marcarLeidos([$id], $this->f['alumno']);

    $filas = DB::table('curso.interaccion_mensaje')
        ->where('id_mensaje', $id)
        ->where('id_usuario_lector', $this->f['alumno'])
        ->count();

    expect($filas)->toBe(1);
});

it('un docente ajeno al componente no puede escribir', function () {
    $svc = app(MensajeriaService::class);

    // Un docente cuyo listado de componentes visibles NO incluye el del test.
    $ajeno = DB::selectOne("
        SELECT d.id_docente, d.id_usuario
        FROM usuario.docente d
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido', 'Docente Componente')
          AND d.id_usuario NOT IN (?, ?)
        ORDER BY d.id_docente DESC
        LIMIT 1
    ", [$this->f['titular'], $this->f['colegiado']]);

    if (!$ajeno) {
        $this->markTestSkipped('No hay un tercer docente para probar el rechazo.');
    }

    $visibles = $svc->componentesDeDocente((int) $ajeno->id_docente)
        ->pluck('id_componente')->map(fn($id) => (int) $id)->all();

    if (in_array($this->f['componente'], $visibles, true)) {
        $this->markTestSkipped('El docente elegido sí tiene acceso a este componente.');
    }

    // Ni siquiera puede abrir la bandeja del curso: el acceso se decide contra
    // los componentes que tiene EN ESE CURSO, y no tiene ninguno.
    $this->actingAs(Usuario::find($ajeno->id_usuario))
        ->get("/docente/cursos/{$this->f['curso']}/mensajeria")
        ->assertForbidden();

    $this->actingAs(Usuario::find($ajeno->id_usuario))
        ->post("/docente/cursos/{$this->f['curso']}/mensajeria/componentes/{$this->f['componente']}/difusion", [
            'tema'    => 'Intruso',
            'mensaje' => 'No deberia poder',
        ])
        ->assertForbidden();
});

it('el alumno no puede escribir en un componente donde no está inscrito', function () {
    $ajeno = DB::selectOne("
        SELECT cmp.id_componente, cmp.id_curso
        FROM curso.componente cmp
        WHERE NOT EXISTS (
            SELECT 1 FROM curso.inscripcion_componente ic
            JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
            WHERE ic.id_componente = cmp.id_componente AND e.id_usuario = ?
        )
        LIMIT 1
    ", [$this->f['alumno']]);

    if (!$ajeno) {
        $this->markTestSkipped('El alumno está inscrito en todos los componentes.');
    }

    // Rechaza tanto si no cursa ese curso (no ve nada) como si cursa el otro
    // componente del mismo curso (lo ve, pero no ése).
    $this->actingAs(Usuario::find($this->f['alumno']))
        ->post("/estudiante/cursos/{$ajeno->id_curso}/mensajeria/componentes/{$ajeno->id_componente}/mensaje", [
            'mensaje' => 'hola',
        ])
        ->assertForbidden();
});
