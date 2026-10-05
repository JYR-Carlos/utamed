<?php

/*
 * Cancelación de entrega por el estudiante (Student\AgendaController::destroyEntrega).
 *
 * Sólo se cancela la última «Entrega de archivo» vigente de un integrante del
 * grupo: nunca un feedback del docente con adjunto, ni la entrega de alguien
 * ajeno al grupo, ni una ya cancelada, ni una entrega anterior, ni fuera de
 * plazo. La cancelación marca el archivo para borrado y deja una única fila
 * «Cancelación de entrega» con el mismo uuid.
 */

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Agenda;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

/**
 * Grupo sembrado con un integrante que tiene el rol Estudiante activo y sin
 * ninguna evaluación registrada. Deja la actividad con plazo abierto.
 */
function fixtureCancelacionEntrega(): ?array
{
    $row = DB::selectOne("
        SELECT g.id_actividad_asignada_grupo, g.id_actividad, e.id_usuario
        FROM agenda.actividad_asignada_grupo g
        JOIN agenda.integrante_grupo ig ON ig.id_actividad_asignada_grupo = g.id_actividad_asignada_grupo
        JOIN usuario.estudiante e ON e.id_estudiante = ig.id_estudiante
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = e.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND lower(r.nombre) = 'estudiante'
          AND NOT EXISTS (
              SELECT 1 FROM agenda.agenda a
              JOIN agenda.evaluacion ev ON ev.id_agenda = a.id_agenda
              WHERE a.id_actividad_asignada_grupo = g.id_actividad_asignada_grupo
          )
        ORDER BY g.id_actividad_asignada_grupo, e.id_usuario
        LIMIT 1
    ");

    if (!$row) {
        return null;
    }

    $f = (array) $row;

    // Sin nota grupal ni individual: el grupo no cuenta como evaluado.
    DB::table('agenda.actividad_asignada_grupo')
        ->where('id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->update(['nota' => null, 'nro_dias_adicionales_para_bloqueo_personal' => 0]);
    DB::table('agenda.integrante_grupo')
        ->where('id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->update(['nota_individual' => null]);

    // Plazo abierto.
    DB::table('agenda.actividad')
        ->where('id_actividad', $f['id_actividad'])
        ->update(['fecha_limite' => now()->addDays(10)->toDateString(), 'nro_dias_adicionales_para_bloqueo' => 0]);

    $usuario = Usuario::findOrFail($f['id_usuario']);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();
    $f['usuario'] = $usuario;

    return $f;
}

function crearArchivoDePrueba(): string
{
    $uuid = (string) Str::uuid();

    DB::table('operaciones.archivo')->insert([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "tests/{$uuid}.pdf",
        'nombre_original' => 'entrega-prueba.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 1234,
    ]);

    return $uuid;
}

function crearFilaAgenda(int $idGrupo, int $idEmisor, TipoMensaje $tipo, ?string $uuid, $fecha = null): Agenda
{
    return Agenda::create([
        'id_actividad_asignada_grupo' => $idGrupo,
        'id_usuario_emisor' => $idEmisor,
        'fecha_envio' => $fecha ?? now()->addMinute(),
        'tipo_mensaje' => $tipo,
        'mensaje' => 'Fila de prueba',
        'uuid_archivo_subido' => $uuid,
    ]);
}

function urlCancelar(int $idGrupo, int $idAgenda): string
{
    return "/estudiante/grupos-asignados/{$idGrupo}/entregas/{$idAgenda}";
}

function filasCancelacion(int $idGrupo, string $uuid): int
{
    return Agenda::where('id_actividad_asignada_grupo', $idGrupo)
        ->where('tipo_mensaje', TipoMensaje::CANCELACIÓN_DE_ENTREGA->value)
        ->where('uuid_archivo_subido', $uuid)
        ->count();
}

function archivoPendienteDeBorrado(string $uuid): bool
{
    return (bool) DB::table('operaciones.archivo')->where('uuid_archivo', $uuid)->value('pendiente_de_borrado');
}

/** Un usuario que no integra el grupo (p. ej. el docente o un alumno de otro grupo). */
function usuarioAjenoAlGrupo(int $idGrupo): ?int
{
    $row = DB::selectOne("
        SELECT u.id_usuario FROM usuario.usuario u
        WHERE NOT EXISTS (
            SELECT 1 FROM usuario.estudiante e
            JOIN agenda.integrante_grupo ig ON ig.id_estudiante = e.id_estudiante
            WHERE e.id_usuario = u.id_usuario AND ig.id_actividad_asignada_grupo = ?
        )
        ORDER BY u.id_usuario LIMIT 1
    ", [$idGrupo]);

    return $row?->id_usuario;
}

test('no se puede cancelar un feedback del docente con archivo y su archivo no queda marcado para borrado', function () {
    $f = fixtureCancelacionEntrega();
    if (!$f) {
        $this->markTestSkipped('No hay grupo sembrado con integrante estudiante y sin evaluaciones.');
    }
    $idGrupo = $f['id_actividad_asignada_grupo'];
    $docente = usuarioAjenoAlGrupo($idGrupo);
    if (!$docente) {
        $this->markTestSkipped('No hay usuario ajeno al grupo para emitir el feedback.');
    }

    $uuid = crearArchivoDePrueba();
    $feedback = crearFilaAgenda($idGrupo, $docente, TipoMensaje::FEEDBACK, $uuid);

    $this->actingAs($f['usuario'])
        ->delete(urlCancelar($idGrupo, $feedback->id_agenda))
        ->assertForbidden();

    expect(archivoPendienteDeBorrado($uuid))->toBeFalse()
        ->and(filasCancelacion($idGrupo, $uuid))->toBe(0);
});

test('no se puede cancelar una entrega emitida por alguien que no integra el grupo', function () {
    $f = fixtureCancelacionEntrega();
    if (!$f) {
        $this->markTestSkipped('No hay grupo sembrado con integrante estudiante y sin evaluaciones.');
    }
    $idGrupo = $f['id_actividad_asignada_grupo'];
    $ajeno = usuarioAjenoAlGrupo($idGrupo);
    if (!$ajeno) {
        $this->markTestSkipped('No hay usuario ajeno al grupo.');
    }

    $uuid = crearArchivoDePrueba();
    $entrega = crearFilaAgenda($idGrupo, $ajeno, TipoMensaje::ENTREGA_DE_ARCHIVO, $uuid);

    $this->actingAs($f['usuario'])
        ->delete(urlCancelar($idGrupo, $entrega->id_agenda))
        ->assertForbidden();

    expect(archivoPendienteDeBorrado($uuid))->toBeFalse()
        ->and(filasCancelacion($idGrupo, $uuid))->toBe(0);
});

test('cancelar la última entrega marca el archivo para borrado y registra una sola cancelación', function () {
    $f = fixtureCancelacionEntrega();
    if (!$f) {
        $this->markTestSkipped('No hay grupo sembrado con integrante estudiante y sin evaluaciones.');
    }
    $idGrupo = $f['id_actividad_asignada_grupo'];

    $uuid = crearArchivoDePrueba();
    $entrega = crearFilaAgenda($idGrupo, $f['id_usuario'], TipoMensaje::ENTREGA_DE_ARCHIVO, $uuid);

    $this->actingAs($f['usuario'])
        ->delete(urlCancelar($idGrupo, $entrega->id_agenda))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(archivoPendienteDeBorrado($uuid))->toBeTrue()
        ->and(filasCancelacion($idGrupo, $uuid))->toBe(1)
        ->and($entrega->fresh()->fueCancelada())->toBeTrue();
});

test('cancelar dos veces la misma entrega falla y deja una sola fila de cancelación', function () {
    $f = fixtureCancelacionEntrega();
    if (!$f) {
        $this->markTestSkipped('No hay grupo sembrado con integrante estudiante y sin evaluaciones.');
    }
    $idGrupo = $f['id_actividad_asignada_grupo'];

    $uuid = crearArchivoDePrueba();
    $entrega = crearFilaAgenda($idGrupo, $f['id_usuario'], TipoMensaje::ENTREGA_DE_ARCHIVO, $uuid);

    $this->actingAs($f['usuario'])
        ->delete(urlCancelar($idGrupo, $entrega->id_agenda))
        ->assertSessionHasNoErrors();

    $this->actingAs($f['usuario'])
        ->delete(urlCancelar($idGrupo, $entrega->id_agenda))
        ->assertSessionHasErrors('error_general');

    expect(filasCancelacion($idGrupo, $uuid))->toBe(1);
});

test('no se puede cancelar una entrega que ya fue reemplazada por otra más reciente', function () {
    $f = fixtureCancelacionEntrega();
    if (!$f) {
        $this->markTestSkipped('No hay grupo sembrado con integrante estudiante y sin evaluaciones.');
    }
    $idGrupo = $f['id_actividad_asignada_grupo'];

    $uuidA = crearArchivoDePrueba();
    $uuidB = crearFilaAgenda($idGrupo, $f['id_usuario'], TipoMensaje::ENTREGA_DE_ARCHIVO, crearArchivoDePrueba(), now()->addMinutes(2))->uuid_archivo_subido;
    $entregaA = crearFilaAgenda($idGrupo, $f['id_usuario'], TipoMensaje::ENTREGA_DE_ARCHIVO, $uuidA, now()->addMinute());

    $this->actingAs($f['usuario'])
        ->delete(urlCancelar($idGrupo, $entregaA->id_agenda))
        ->assertSessionHasErrors('error_general');

    expect(archivoPendienteDeBorrado($uuidA))->toBeFalse()
        ->and(filasCancelacion($idGrupo, $uuidA))->toBe(0)
        ->and(archivoPendienteDeBorrado($uuidB))->toBeFalse();
});

test('no se puede cancelar una entrega con la fecha límite vencida', function () {
    $f = fixtureCancelacionEntrega();
    if (!$f) {
        $this->markTestSkipped('No hay grupo sembrado con integrante estudiante y sin evaluaciones.');
    }
    $idGrupo = $f['id_actividad_asignada_grupo'];

    DB::table('agenda.actividad')
        ->where('id_actividad', $f['id_actividad'])
        ->update(['fecha_limite' => now()->subDays(10)->toDateString()]);

    $uuid = crearArchivoDePrueba();
    $entrega = crearFilaAgenda($idGrupo, $f['id_usuario'], TipoMensaje::ENTREGA_DE_ARCHIVO, $uuid);

    $this->actingAs($f['usuario'])
        ->delete(urlCancelar($idGrupo, $entrega->id_agenda))
        ->assertSessionHasErrors('error_general');

    expect(archivoPendienteDeBorrado($uuid))->toBeFalse()
        ->and(filasCancelacion($idGrupo, $uuid))->toBe(0);
});
