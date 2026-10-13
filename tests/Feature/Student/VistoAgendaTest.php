<?php

/**
 * «Visto» de la agenda del estudiante (T07): leer es ABRIR la agenda, no cargar
 * la página de la actividad. Cargar la actividad sólo trae quién vio su último
 * mensaje; el registro de lectura lo hace POST …/agenda/leida cuando se abre el
 * modal.
 */

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Agenda;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/**
 * Grupo con un integrante inscrito y con rol Estudiante, más un mensaje del
 * docente y otro del propio estudiante en su agenda.
 *
 * @return array{usuario: Usuario, grupo: int, curso: int, actividad: int, deDocente: Agenda, propio: Agenda}
 */
function fixtureVistoAgenda($test): array
{
    $row = DB::selectOne("
        SELECT g.id_actividad_asignada_grupo, g.id_actividad, c.id_curso, e.id_usuario
        FROM agenda.actividad_asignada_grupo g
        JOIN agenda.actividad a ON a.id_actividad = g.id_actividad
        JOIN curso.componente co ON co.id_componente = a.id_componente
        JOIN curso.curso c ON c.id_curso = co.id_curso
        JOIN agenda.integrante_grupo ig ON ig.id_actividad_asignada_grupo = g.id_actividad_asignada_grupo
        JOIN usuario.estudiante e ON e.id_estudiante = ig.id_estudiante
        JOIN curso.inscripcion_curso ic ON ic.id_estudiante = e.id_estudiante AND ic.id_curso = c.id_curso
             AND ic.estado_inscripcion = 'INSCRITO'
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = e.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE ura.esta_activo AND NOT ura.fue_eliminado AND r.nombre = 'Estudiante'
          AND c.id_docente_titular IS NOT NULL AND a.visible = true
        ORDER BY g.id_actividad_asignada_grupo
        LIMIT 1
    ");
    if (!$row) {
        $test->markTestSkipped('No hay un grupo visible con un estudiante inscrito de rol Estudiante.');
    }

    $idDocente = DB::table('usuario.docente')
        ->join('curso.curso', 'curso.id_docente_titular', '=', 'docente.id_docente')
        ->where('curso.id_curso', $row->id_curso)
        ->value('docente.id_usuario');

    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    $mensaje = fn (int $emisor, TipoMensaje $tipo, string $texto) => Agenda::create([
        'fecha_envio' => now()->toDateTimeString(),
        'tipo_mensaje' => $tipo,
        'mensaje' => $texto,
        'id_usuario_emisor' => $emisor,
        'id_actividad_asignada_grupo' => $row->id_actividad_asignada_grupo,
    ]);

    return [
        'usuario' => $usuario,
        'grupo' => (int) $row->id_actividad_asignada_grupo,
        'curso' => (int) $row->id_curso,
        'actividad' => (int) $row->id_actividad,
        'deDocente' => $mensaje((int) $idDocente, TipoMensaje::FEEDBACK, 'feedback del docente (test)'),
        'propio' => $mensaje((int) $usuario->id_usuario, TipoMensaje::MENSAJE_AL_PROFESOR, 'consulta del alumno (test)'),
    ];
}

function lecturasDe(int $idUsuario, int $idGrupo): int
{
    return DB::table('agenda.lectura_agenda as l')
        ->join('agenda.agenda as a', 'a.id_agenda', '=', 'l.id_agenda')
        ->where('l.id_usuario_lector', $idUsuario)
        ->where('a.id_actividad_asignada_grupo', $idGrupo)
        ->count();
}

test('cargar la página de la actividad no marca la agenda como leída', function () {
    $f = fixtureVistoAgenda($this);

    $this->actingAs($f['usuario'])
        ->get("/estudiante/cursos/{$f['curso']}/actividad/{$f['actividad']}")
        ->assertOk();

    expect(lecturasDe($f['usuario']->id_usuario, $f['grupo']))->toBe(0);
});

test('abrir la agenda marca como leídos los mensajes ajenos y no los propios, y es idempotente', function () {
    $f = fixtureVistoAgenda($this);
    $ruta = "/estudiante/grupos-asignados/{$f['grupo']}/agenda/leida";

    $this->actingAs($f['usuario'])->post($ruta)->assertNoContent();
    $this->actingAs($f['usuario'])->post($ruta)->assertNoContent();

    expect(lecturasDe($f['usuario']->id_usuario, $f['grupo']))->toBe(1)
        ->and(DB::table('agenda.lectura_agenda')
            ->where('id_agenda', $f['deDocente']->id_agenda)
            ->where('id_usuario_lector', $f['usuario']->id_usuario)->exists())->toBeTrue()
        ->and(DB::table('agenda.lectura_agenda')
            ->where('id_agenda', $f['propio']->id_agenda)->exists())->toBeFalse();
});

test('un estudiante que no es del grupo no puede marcar su agenda como leída', function () {
    $f = fixtureVistoAgenda($this);
    $ajeno = Usuario::whereHas('estudiante', fn ($q) => $q->whereNotIn(
        'id_estudiante',
        DB::table('agenda.integrante_grupo')->where('id_actividad_asignada_grupo', $f['grupo'])->pluck('id_estudiante')
    ))->first() ?? $this->markTestSkipped('No hay otro estudiante para probar.');
    $ajeno->fecha_cambio_passhash = now();
    $ajeno->save();

    $this->actingAs($ajeno)->post("/estudiante/grupos-asignados/{$f['grupo']}/agenda/leida")->assertNotFound();

    expect(DB::table('agenda.lectura_agenda')->where('id_agenda', $f['deDocente']->id_agenda)->exists())->toBeFalse();
});
