<?php

use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\MensajeriaService;
use App\Services\Student\MensajeriaCursoEstudiante;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Mensajería por componente (curso.mensaje).
 *
 * Desde OBS-49 (20-11-2026) cada conversación es privada entre el alumno y una
 * persona del equipo: un COLEGIADO (segundo docente del componente) o un
 * ayudante tiene la suya, y el titular no la ve.
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

    Usuario::whereIn('id_usuario', [$f['titular'], $f['alumno'], $f['colegiado']])
        ->update(['fecha_cambio_passhash' => now()]);
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

/**
 * Suma un ayudante al curso del componente (rol «Ayudante» sobre el contexto
 * del curso, el mismo criterio de MensajeriaService::componentesDeAyudante) y
 * devuelve su id_usuario. Es alguien que no cursa ni dicta el componente.
 */
function ayudanteDelCurso(array $f): ?int
{
    $usuario = DB::selectOne("
        SELECT u.id_usuario
        FROM usuario.usuario u
        WHERE u.id_usuario NOT IN (?, ?, ?)
          AND NOT EXISTS (SELECT 1 FROM curso.inscripcion_componente ic
                          JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
                          WHERE ic.id_componente = ? AND e.id_usuario = u.id_usuario)
          AND NOT EXISTS (SELECT 1 FROM curso.docente_componente dc
                          JOIN usuario.docente d ON d.id_docente = dc.id_docente
                          WHERE dc.id_componente = ? AND d.id_usuario = u.id_usuario)
        ORDER BY u.id_usuario
        LIMIT 1
    ", [$f['titular'], $f['alumno'], $f['colegiado'], $f['componente'], $f['componente']]);

    $rol = DB::table('usuario.rol')->whereRaw("LOWER(nombre) = 'ayudante'")->value('id_rol');

    if (!$usuario || !$rol) {
        return null;
    }

    UsuarioRolAsignacion::create([
        'id_usuario'               => $usuario->id_usuario,
        'id_rol'                   => $rol,
        'id_contexto'              => DB::table('curso.curso')->where('id_curso', $f['curso'])->value('id_contexto'),
        'asignado_por'             => $f['titular'],
        'fecha_inicio_planificada' => now()->subMinute(),
        'fecha_fin_planificada'    => now()->addYear(),
        'esta_activo'              => true,
        'fue_eliminado'            => false,
        'creado_por'               => $f['titular'],
    ]);

    return (int) $usuario->id_usuario;
}

it('cada docente tiene su propia conversación con el alumno', function () {
    $svc = app(MensajeriaService::class);
    $comp = $this->f['componente'];
    $noLeidos = fn(int $usuario) => $svc->noLeidosPorComponente([$comp], $usuario)[$comp] ?? 0;

    $antesTitular = $noLeidos($this->f['titular']);
    $antesColegiado = $noLeidos($this->f['colegiado']);

    // 1. El alumno le pregunta al titular.
    $this->actingAs(Usuario::find($this->f['alumno']))
        ->post("/estudiante/cursos/{$this->f['curso']}/mensajeria/componentes/{$comp}/mensaje", [
            'mensaje'             => 'No entiendo el punto 2 del TP3',
            'tema'                => 'Duda TP3',
            'id_usuario_receptor' => $this->f['titular'],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // 2. El COLEGIADO le escribe al alumno por su cuenta.
    $this->actingAs(Usuario::find($this->f['colegiado']))
        ->post("/docente/cursos/{$this->f['curso']}/mensajeria/componentes/{$comp}/alumnos/{$this->f['alumno']}/mensaje", [
            'mensaje' => 'El punto 2 pide el intervalo de confianza',
        ])
        ->assertRedirect();

    // 3. El titular responde.
    $this->actingAs(Usuario::find($this->f['titular']))
        ->post("/docente/cursos/{$this->f['curso']}/mensajeria/componentes/{$comp}/alumnos/{$this->f['alumno']}/mensaje", [
            'mensaje' => 'Te respondo yo',
        ])
        ->assertRedirect();

    // Dos conversaciones separadas; la del titular hereda el asunto.
    $conTitular = $svc->mensajesDeConversacion($comp, $this->f['alumno'], $this->f['titular'], $this->f['titular']);
    $conColegiado = $svc->mensajesDeConversacion($comp, $this->f['alumno'], $this->f['colegiado'], $this->f['colegiado']);

    expect($conTitular->pluck('mensaje')->all())->toBe(['No entiendo el punto 2 del TP3', 'Te respondo yo'])
        ->and($conTitular->pluck('tema')->unique()->all())->toBe(['Duda TP3'])
        ->and($conTitular->pluck('es_alumno')->all())->toBe([true, false])
        ->and($conColegiado->pluck('mensaje')->all())->toBe(['El punto 2 pide el intervalo de confianza']);

    // La pregunta al titular no le llega al colegiado, ni en el contador ni en su lista.
    expect($noLeidos($this->f['titular']) - $antesTitular)->toBe(1)
        ->and($noLeidos($this->f['colegiado']) - $antesColegiado)->toBe(0);

    $filaDe = fn(int $actor) => $svc->canalesDeComponente($comp, $actor)->firstWhere('id_alumno', $this->f['alumno']);

    expect($filaDe($this->f['colegiado']))->toMatchArray(['ultimo_mensaje' => 'El punto 2 pide el intervalo de confianza', 'no_leidos' => 0])
        ->and($filaDe($this->f['titular']))->toMatchArray(['ultimo_mensaje' => 'Te respondo yo', 'no_leidos' => 1]);

    // Y la bandeja del colegiado abre sólo su conversación.
    $this->actingAs(Usuario::find($this->f['colegiado']))
        ->get("/docente/cursos/{$this->f['curso']}/mensajeria?componente_id={$comp}&alumno_id={$this->f['alumno']}")
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->where('panel.canal.mensajes', fn($mensajes) => collect($mensajes)->pluck('mensaje')->all()
                === ['El punto 2 pide el intervalo de confianza']));
});

it('el ayudante tiene su propia conversación y el alumno lo ve en el equipo', function () {
    $ayudante = ayudanteDelCurso($this->f);
    if ($ayudante === null) {
        $this->markTestSkipped('No hay a quién asignar como ayudante.');
    }

    $svc = app(MensajeriaService::class);
    $comp = $this->f['componente'];
    $curso = Curso::find($this->f['curso']);

    // El alumno ve a cada persona del equipo, con su papel.
    $bandeja = app(MensajeriaCursoEstudiante::class)->bandeja($curso, $this->f['alumno'], $comp);
    $roles = collect($bandeja['panel']['conversaciones'])->pluck('rol', 'id_usuario');

    expect($roles[$this->f['titular']] ?? null)->toBe('Titular')
        ->and($roles[$this->f['colegiado']] ?? null)->toBe('Docente')
        ->and($roles[$ayudante] ?? null)->toBe('Ayudante');

    $antesTitular = $svc->noLeidosPorComponente([$comp], $this->f['titular'])[$comp] ?? 0;

    $this->actingAs(Usuario::find($this->f['alumno']))
        ->post("/estudiante/cursos/{$this->f['curso']}/mensajeria/componentes/{$comp}/mensaje", [
            'mensaje'             => 'Ayudante, ¿revisas mi código?',
            'tema'                => 'Laboratorio 4',
            'id_usuario_receptor' => $ayudante,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // Le llega al ayudante y a nadie más del equipo.
    expect($svc->noLeidosPorComponente([$comp], $ayudante)[$comp] ?? 0)->toBeGreaterThanOrEqual(1)
        ->and($svc->canalesDeComponente($comp, $ayudante)->firstWhere('id_alumno', $this->f['alumno'])['ultimo_mensaje'])
            ->toBe('Ayudante, ¿revisas mi código?')
        ->and($svc->noLeidosPorComponente([$comp], $this->f['titular'])[$comp] ?? 0)->toBe($antesTitular)
        ->and($svc->mensajesDeConversacion($comp, $this->f['alumno'], $this->f['titular'], $this->f['titular']))->toBeEmpty();

    // El ayudante responde; sin pedir una, el alumno aterriza en esa conversación.
    $svc->enviarIndividual($comp, $ayudante, $this->f['alumno'], 'Sí, mañana');

    $otra = app(MensajeriaCursoEstudiante::class)->bandeja($curso, $this->f['alumno'], $comp);
    $abierta = $otra['panel']['conversacion'];

    expect($abierta['id_usuario'])->toBe($ayudante)
        ->and(collect($abierta['mensajes'])->pluck('mensaje')->all())->toBe(['Ayudante, ¿revisas mi código?', 'Sí, mañana'])
        ->and(collect($abierta['mensajes'])->last()['nuevo'])->toBeTrue()
        // Hereda el asunto de la conversación con el ayudante, no el de otra.
        ->and(collect($abierta['mensajes'])->last()['tema'])->toBe('Laboratorio 4');

    // Pedir otra conversación la abre a ella.
    $conTitular = app(MensajeriaCursoEstudiante::class)->bandeja($curso, $this->f['alumno'], $comp, $this->f['titular']);
    expect($conTitular['panel']['conversacion']['id_usuario'])->toBe($this->f['titular'])
        ->and($conTitular['panel']['conversacion']['mensajes'])->toBeEmpty();
});

it('el alumno sólo puede escribirle a alguien del equipo', function () {
    // Otro alumno del componente: no es del equipo.
    $otro = DB::selectOne("
        SELECT e.id_usuario
        FROM curso.inscripcion_componente ic
        JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
        WHERE ic.id_componente = ? AND e.id_usuario <> ?
        LIMIT 1
    ", [$this->f['componente'], $this->f['alumno']]);

    if (!$otro) {
        $this->markTestSkipped('El componente tiene un solo alumno.');
    }

    $antes = DB::table('curso.mensaje')->where('id_usuario_emisor', $this->f['alumno'])->count();

    $this->actingAs(Usuario::find($this->f['alumno']))
        ->post("/estudiante/cursos/{$this->f['curso']}/mensajeria/componentes/{$this->f['componente']}/mensaje", [
            'mensaje'             => 'hola',
            'tema'                => 'Prueba',
            'id_usuario_receptor' => (int) $otro->id_usuario,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('mensaje');

    expect(DB::table('curso.mensaje')->where('id_usuario_emisor', $this->f['alumno'])->count())->toBe($antes);
});

it('abrir el panel marca como leído y baja el contador', function () {
    $svc = app(MensajeriaService::class);

    $this->actingAs(Usuario::find($this->f['titular']))
        ->post("/docente/cursos/{$this->f['curso']}/mensajeria/componentes/{$this->f['componente']}/difusion", [
            'tema'    => 'Aviso',
            'mensaje' => 'Contenido del aviso',
        ]);

    $antes = $svc->noLeidosPorComponente([$this->f['componente']], $this->f['alumno']);
    expect($antes[$this->f['componente']] ?? 0)->toBe(1);

    // La URL antigua lleva a la pestaña Mensajería de la ficha, con el componente.
    $this->actingAs(Usuario::find($this->f['alumno']))
        ->get("/estudiante/cursos/{$this->f['curso']}/mensajeria?componente_id={$this->f['componente']}")
        ->assertRedirect("/estudiante/cursos/{$this->f['curso']}?seccion=mensajeria&componente_id={$this->f['componente']}");

    // El alumno abre el componente: el panel marca como leído lo que muestra.
    $bandeja = app(MensajeriaCursoEstudiante::class)
        ->bandeja(Curso::find($this->f['curso']), $this->f['alumno'], $this->f['componente']);

    expect($bandeja['componente_activo'])->toBe($this->f['componente'])
        ->and($bandeja['panel']['difusiones']->pluck('tema'))->toContain('Aviso')
        // Llegó sin abrir: en esta visita se destaca como «Nuevo».
        ->and($bandeja['panel']['difusiones']->firstWhere('tema', 'Aviso')['nuevo'])->toBeTrue();

    $despues = $svc->noLeidosPorComponente([$this->f['componente']], $this->f['alumno']);
    expect($despues[$this->f['componente']] ?? 0)->toBe(0);

    // La próxima vez ya no es nuevo.
    $otraVez = app(MensajeriaCursoEstudiante::class)
        ->bandeja(Curso::find($this->f['curso']), $this->f['alumno'], $this->f['componente']);
    expect($otraVez['panel']['difusiones']->firstWhere('tema', 'Aviso')['nuevo'])->toBeFalse();
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
    Usuario::where('id_usuario', $ajeno->id_usuario)->update(['fecha_cambio_passhash' => now()]);

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
            'mensaje'             => 'hola',
            'id_usuario_receptor' => $this->f['titular'],
        ])
        ->assertForbidden();
});
