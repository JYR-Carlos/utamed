<?php

/*
 * Cancelación de entrega vs evaluación (commit 36eeec2).
 *
 * Las evaluaciones no quedan amarradas a la entrega: en
 * DocenteActivityController::storeEvaluacion `id_agenda_entrega` es opcional
 * (la fila «Evaluación» sólo repite el uuid de la entrega si se vinculó) y en
 * una actividad formativa la nota del grupo queda en NULL. El bloqueo
 * «ya evaluada» vive en ActividadAsignadaGrupo::yaFueEvaluado() y tiene que
 * reconocer las cuatro combinaciones sumativa/formativa × vinculada/sin vincular.
 * Antes de la corrección, una formativa evaluada sin vincular se podía cancelar.
 */

use App\Enums\DB\EstadoRubrica;
use App\Enums\DB\TipoActividad;
use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\Rubrica;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

/**
 * Un grupo sembrado sin mensajes en la agenda, con un integrante cuyo usuario
 * tiene el rol Estudiante activo, más otro usuario que hará de evaluador.
 */
function fixtureGrupoEstudianteSinAgenda(): ?array
{
    $row = DB::selectOne("
        SELECT g.id_actividad_asignada_grupo, g.id_actividad, ig.id_asignado_actividad,
               e.id_usuario AS id_usuario_estudiante,
               (SELECT u.id_usuario FROM usuario.usuario u
                 WHERE u.id_usuario <> e.id_usuario ORDER BY u.id_usuario LIMIT 1) AS id_usuario_evaluador
        FROM agenda.actividad_asignada_grupo g
        JOIN agenda.integrante_grupo ig ON ig.id_actividad_asignada_grupo = g.id_actividad_asignada_grupo
        JOIN usuario.estudiante e ON e.id_estudiante = ig.id_estudiante
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = e.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre = 'Estudiante'
          AND NOT EXISTS (
              SELECT 1 FROM agenda.agenda ag
              WHERE ag.id_actividad_asignada_grupo = g.id_actividad_asignada_grupo
          )
        ORDER BY g.id_actividad_asignada_grupo
        LIMIT 1
    ");

    return $row ? (array) $row : null;
}

/**
 * Deja el grupo listo para cancelar: actividad del tipo pedido con plazo
 * vigente, sin notas, y una entrega de archivo del estudiante como la última.
 *
 * @return array{f: array, usuario: Usuario, grupo: ActividadAsignadaGrupo, entrega: Agenda}
 */
function prepararEntregaVigente($test, TipoActividad $tipo): array
{
    $f = fixtureGrupoEstudianteSinAgenda();
    if (!$f || !$f['id_usuario_evaluador']) {
        $test->markTestSkipped('No hay un grupo sembrado sin agenda con un integrante de rol Estudiante.');
    }

    $usuario = Usuario::findOrFail($f['id_usuario_estudiante']);
    if (!$usuario->hasRole('Estudiante')) {
        $test->markTestSkipped('El integrante del grupo no tiene el rol Estudiante vigente.');
    }
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    DB::table('agenda.actividad')->where('id_actividad', $f['id_actividad'])->update([
        'tipo_actividad' => $tipo->value,
        'fecha_limite' => now()->addDays(7)->toDateString(),
        'nro_dias_adicionales_para_bloqueo' => 0,
    ]);
    DB::table('agenda.actividad_asignada_grupo')
        ->where('id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->update(['nota' => null, 'nro_dias_adicionales_para_bloqueo_personal' => 0]);
    DB::table('agenda.integrante_grupo')
        ->where('id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->update(['nota_individual' => null, 'diferencia_decimas' => null]);

    $uuid = (string) Str::uuid();
    DB::table('operaciones.archivo')->insert([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "tests/entregas/{$uuid}.pdf",
        'nombre_original' => 'entrega-prueba.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 1024,
        'pendiente_de_borrado' => false,
    ]);

    $entrega = Agenda::create([
        'id_actividad_asignada_grupo' => $f['id_actividad_asignada_grupo'],
        'id_usuario_emisor' => $usuario->id_usuario,
        'fecha_envio' => now()->subMinutes(10),
        'tipo_mensaje' => TipoMensaje::ENTREGA_DE_ARCHIVO,
        'mensaje' => 'Entrega de prueba',
        'uuid_archivo_subido' => $uuid,
    ]);

    return [
        'f' => $f,
        'usuario' => $usuario,
        'grupo' => ActividadAsignadaGrupo::findOrFail($f['id_actividad_asignada_grupo']),
        'entrega' => $entrega,
    ];
}

/**
 * Registra una evaluación del docente como lo hace storeEvaluacion: fila
 * «Evaluación» en agenda.agenda (con el uuid de la entrega sólo si se vinculó)
 * + fila en agenda.evaluacion. Si se da nota, la copia al grupo y a cada
 * integrante (nota_individual = nota grupal, sin décimas).
 */
function registrarEvaluacion(array $e, bool $vinculada, ?float $nota): void
{
    $rubrica = Rubrica::create([
        'id_actividad' => $e['f']['id_actividad'],
        'rubrica' => ['columnas' => [], 'niveles' => [], 'detalles_evaluacion' => ['puntaje_total' => 20]],
        'estado_rubrica' => EstadoRubrica::CERRADA,
    ]);

    $filaEvaluacion = Agenda::create([
        'id_actividad_asignada_grupo' => $e['grupo']->id_actividad_asignada_grupo,
        'id_usuario_emisor' => $e['f']['id_usuario_evaluador'],
        'fecha_envio' => now()->subMinutes(5),
        'tipo_mensaje' => TipoMensaje::EVALUACIÓN,
        'mensaje' => 'Retroalimentación de prueba',
        'uuid_archivo_subido' => $vinculada ? $e['entrega']->uuid_archivo_subido : null,
    ]);

    DB::table('agenda.evaluacion')->insert([
        'puntaje_obtenido' => 18,
        'resultado' => json_encode(['niv1' => 'esc2']),
        'evaluacion_obtenida' => $nota === null ? 'Aprobado' : null,
        'id_rubrica' => $rubrica->id_rubrica,
        'id_usuario_evaluador' => $e['f']['id_usuario_evaluador'],
        'id_agenda' => $filaEvaluacion->id_agenda,
    ]);

    if ($nota !== null) {
        DB::table('agenda.actividad_asignada_grupo')
            ->where('id_actividad_asignada_grupo', $e['grupo']->id_actividad_asignada_grupo)
            ->update(['nota' => $nota]);
        DB::table('agenda.integrante_grupo')
            ->where('id_actividad_asignada_grupo', $e['grupo']->id_actividad_asignada_grupo)
            ->update(['nota_individual' => $nota, 'diferencia_decimas' => 0]);
    }

    $e['grupo']->refresh();
}

function urlEntregas(ActividadAsignadaGrupo $grupo, ?Agenda $entrega = null): string
{
    $base = "/estudiante/grupos-asignados/{$grupo->id_actividad_asignada_grupo}/entregas";

    return $entrega ? "{$base}/{$entrega->id_agenda}" : $base;
}

function cancelacionesDelGrupo(ActividadAsignadaGrupo $grupo): int
{
    return Agenda::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
        ->where('tipo_mensaje', TipoMensaje::CANCELACIÓN_DE_ENTREGA->value)
        ->count();
}

function archivoMarcadoParaBorrar(Agenda $entrega): bool
{
    return (bool) DB::table('operaciones.archivo')
        ->where('uuid_archivo', $entrega->uuid_archivo_subido)
        ->value('pendiente_de_borrado');
}

// Las cuatro combinaciones: [tipo de actividad, evaluación vinculada a la entrega, nota]
dataset('evaluaciones', [
    'sumativa vinculada' => [TipoActividad::SUMATIVA, true, 6.0],
    'sumativa sin vincular' => [TipoActividad::SUMATIVA, false, 6.0],
    'formativa vinculada' => [TipoActividad::FORMATIVA, true, null],
    'formativa sin vincular' => [TipoActividad::FORMATIVA, false, null],
]);

test('sin evaluación el grupo no figura como evaluado y la última entrega se puede cancelar', function () {
    $e = prepararEntregaVigente($this, TipoActividad::FORMATIVA);

    expect($e['grupo']->yaFueEvaluado())->toBeFalse();

    $this->actingAs($e['usuario'])
        ->from('/estudiante/dashboard')
        ->delete(urlEntregas($e['grupo'], $e['entrega']))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/estudiante/dashboard');

    expect(cancelacionesDelGrupo($e['grupo']))->toBe(1)
        ->and(archivoMarcadoParaBorrar($e['entrega']))->toBeTrue();
});

test('yaFueEvaluado reconoce la evaluación', function (TipoActividad $tipo, bool $vinculada, ?float $nota) {
    $e = prepararEntregaVigente($this, $tipo);
    registrarEvaluacion($e, $vinculada, $nota);

    expect($e['grupo']->yaFueEvaluado())->toBeTrue();
})->with('evaluaciones');

test('no se puede cancelar la última entrega de un grupo ya evaluado', function (TipoActividad $tipo, bool $vinculada, ?float $nota) {
    $e = prepararEntregaVigente($this, $tipo);
    registrarEvaluacion($e, $vinculada, $nota);

    $this->actingAs($e['usuario'])
        ->from('/estudiante/dashboard')
        ->delete(urlEntregas($e['grupo'], $e['entrega']))
        ->assertRedirect('/estudiante/dashboard')
        ->assertSessionHasErrors([
            'error_general' => 'La entrega ya ha sido evaluada por el docente. No es posible eliminarla.',
        ]);

    expect(cancelacionesDelGrupo($e['grupo']))->toBe(0)
        ->and(archivoMarcadoParaBorrar($e['entrega']))->toBeFalse();
})->with('evaluaciones');

test('no se puede subir una nueva entrega tras una evaluación formativa sin vincular', function () {
    $e = prepararEntregaVigente($this, TipoActividad::FORMATIVA);
    registrarEvaluacion($e, vinculada: false, nota: null);

    $entregasAntes = Agenda::where('id_actividad_asignada_grupo', $e['grupo']->id_actividad_asignada_grupo)
        ->where('tipo_mensaje', TipoMensaje::ENTREGA_DE_ARCHIVO->value)
        ->count();

    $this->actingAs($e['usuario'])
        ->from('/estudiante/dashboard')
        ->post(urlEntregas($e['grupo']), [
            'archivo' => UploadedFile::fake()->create('nueva-entrega.pdf', 10, 'application/pdf'),
            'mensaje' => 'Reemplazo tras la evaluación',
        ])
        ->assertRedirect('/estudiante/dashboard')
        ->assertSessionHasErrors([
            'error_general' => 'La entrega ya ha sido evaluada por el docente. No es posible subir ni reemplazar archivos.',
        ]);

    expect(
        Agenda::where('id_actividad_asignada_grupo', $e['grupo']->id_actividad_asignada_grupo)
            ->where('tipo_mensaje', TipoMensaje::ENTREGA_DE_ARCHIVO->value)
            ->count()
    )->toBe($entregasAntes);
});
