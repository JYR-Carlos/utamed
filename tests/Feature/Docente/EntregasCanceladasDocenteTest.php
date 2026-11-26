<?php

/**
 * Lado docente: las entregas que el estudiante canceló dejan de contar.
 *
 * Una entrega queda cancelada cuando existe, en el mismo grupo, una fila
 * «Cancelación de entrega» con el mismo `uuid_archivo_subido`. Ver
 * Agenda::soloEntregasVigentes() y su uso en DocenteActivityController
 * (listado de entregas, contador `total_entregas` y storeEvaluacion).
 */

use App\Enums\DB\EstadoRubrica;
use App\Enums\DB\TipoActividad;
use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\Rubrica;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

/**
 * Curso con docente titular activo, una actividad suya y un grupo asignado.
 */
function fixtureEntregasCanceladasDocente(): ?array
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

/**
 * Docente titular del fixture, listo para autenticarse.
 */
function docenteEntregasCanceladas(array $f): Usuario
{
    $usuario = Usuario::findOrFail($f['id_usuario']);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    return $usuario;
}

/**
 * Emisor de las filas del estudiante: un integrante del grupo si lo hay; si
 * no, el propio docente (el filtro no mira al emisor).
 */
function emisorEntregasCanceladas(array $f): int
{
    $idUsuario = DB::table('agenda.integrante_grupo as ig')
        ->join('usuario.estudiante as e', 'e.id_estudiante', '=', 'ig.id_estudiante')
        ->where('ig.id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->value('e.id_usuario');

    return (int) ($idUsuario ?? $f['id_usuario']);
}

function archivoEntregaCancelada(string $nombre): string
{
    $uuid = (string) Str::uuid7();
    Archivo::create([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "archivos/test/entregas/{$uuid}.pdf",
        'nombre_original' => $nombre,
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 1024,
        'pendiente_de_borrado' => false,
    ]);

    return $uuid;
}

function filaAgendaEntregas(int $grupo, int $emisor, TipoMensaje $tipo, ?string $uuid, string $fecha): Agenda
{
    return Agenda::create([
        'fecha_envio' => $fecha,
        'tipo_mensaje' => $tipo,
        'mensaje' => $tipo->value . ' (test)',
        'uuid_archivo_subido' => $uuid,
        'id_usuario_emisor' => $emisor,
        'id_actividad_asignada_grupo' => $grupo,
    ]);
}

/**
 * Hilo del grupo con tres entregas:
 *  - anterior: entregada y reemplazada por otra posterior, pero NO cancelada.
 *  - cancelada: entregada y luego cancelada (fila «Cancelación de entrega» con su uuid).
 *  - vigente: la última, sin cancelar.
 *
 * @return array{anterior: Agenda, cancelada: Agenda, cancelacion: Agenda, vigente: Agenda}
 */
function sembrarHiloEntregasCanceladas(array $f, ?int $grupo = null): array
{
    $grupo ??= (int) $f['id_actividad_asignada_grupo'];
    $emisor = emisorEntregasCanceladas($f);
    $base = now()->subDays(3);

    $uuidAnterior = archivoEntregaCancelada('entrega_anterior.pdf');
    $uuidCancelada = archivoEntregaCancelada('entrega_cancelada.pdf');
    $uuidVigente = archivoEntregaCancelada('entrega_vigente.pdf');

    return [
        'anterior' => filaAgendaEntregas($grupo, $emisor, TipoMensaje::ENTREGA_DE_ARCHIVO, $uuidAnterior, $base->copy()->toDateTimeString()),
        'cancelada' => filaAgendaEntregas($grupo, $emisor, TipoMensaje::ENTREGA_DE_ARCHIVO, $uuidCancelada, $base->copy()->addHour()->toDateTimeString()),
        'cancelacion' => filaAgendaEntregas($grupo, $emisor, TipoMensaje::CANCELACIÓN_DE_ENTREGA, $uuidCancelada, $base->copy()->addHours(2)->toDateTimeString()),
        'vigente' => filaAgendaEntregas($grupo, $emisor, TipoMensaje::ENTREGA_DE_ARCHIVO, $uuidVigente, $base->copy()->addHours(3)->toDateTimeString()),
    ];
}

/**
 * Rúbrica de la actividad que storeEvaluacion acepta: la existente o una nueva.
 */
function rubricaEntregasCanceladas(int $idActividad): Rubrica
{
    $rubrica = Rubrica::where('id_actividad', $idActividad)->first();
    if ($rubrica) {
        return $rubrica;
    }

    return Rubrica::create([
        'id_actividad' => $idActividad,
        'rubrica' => [
            'columnas' => [['id' => 'col1', 'nombre' => 'Logrado', 'puntos' => 10]],
            'niveles' => [[
                'id' => 'niv1',
                'nombre' => 'Criterio',
                'descripcion' => 'Criterio de prueba',
                'ponderacion' => 100,
                'nro_escalas' => 1,
                'puntaje_total' => 10,
                'puntaje_minimo' => 0,
                'escalas' => [['id' => 'esc1', 'puntos' => 10, 'criterio' => 'Logrado']],
            ]],
            'detalles_evaluacion' => ['puntaje_total' => 10, 'escala_evaluacion' => []],
        ],
        'estado_rubrica' => EstadoRubrica::POSTULADA,
    ]);
}

function payloadEvaluacionEntregas(Actividad $actividad, int $idRubrica, int $idAgendaEntrega): array
{
    $esSumativa = $actividad->tipo_actividad === TipoActividad::SUMATIVA;

    return [
        'id_agenda_entrega' => $idAgendaEntrega,
        'id_rubrica' => $idRubrica,
        'mensaje' => 'Evaluación de prueba',
        'puntaje_obtenido' => 10,
        'resultado' => ['niv1' => 'esc1'],
        'nota' => $esSumativa ? 6.0 : null,
        'evaluacion_obtenida' => $esSumativa ? null : 'Aprobado',
    ];
}

// ---------------------------------------------------------------------------
// Listado de entregas (JSON)
// ---------------------------------------------------------------------------

test('entregas por grupo no lista la entrega cancelada y sí la anterior reemplazada y la vigente', function () {
    $f = fixtureEntregasCanceladasDocente();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = docenteEntregasCanceladas($f);
    $hilo = sembrarHiloEntregasCanceladas($f);

    $response = $this->actingAs($usuario)
        ->getJson("/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/grupos/{$f['id_actividad_asignada_grupo']}/entregas");

    $response->assertOk();
    $ids = collect($response->json())->pluck('id_agenda')->all();

    expect($ids)
        ->toContain($hilo['anterior']->id_agenda)
        ->toContain($hilo['vigente']->id_agenda)
        ->not->toContain($hilo['cancelada']->id_agenda)
        ->not->toContain($hilo['cancelacion']->id_agenda);

    // Sólo filas «Entrega de archivo».
    expect(collect($response->json())->pluck('tipo_registro')->unique()->values()->all())
        ->toBe([TipoMensaje::ENTREGA_DE_ARCHIVO->value]);
});

test('entregas por actividad no lista la entrega cancelada y sí las demás', function () {
    $f = fixtureEntregasCanceladasDocente();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = docenteEntregasCanceladas($f);
    $hilo = sembrarHiloEntregasCanceladas($f);

    $response = $this->actingAs($usuario)
        ->getJson("/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/entregas");

    $response->assertOk();
    $ids = collect($response->json())->pluck('id_agenda')->all();

    expect($ids)
        ->toContain($hilo['anterior']->id_agenda)
        ->toContain($hilo['vigente']->id_agenda)
        ->not->toContain($hilo['cancelada']->id_agenda)
        ->not->toContain($hilo['cancelacion']->id_agenda);
});

// ---------------------------------------------------------------------------
// Contador total_entregas del listado de actividades
// ---------------------------------------------------------------------------

test('total_entregas del listado de actividades no cuenta las entregas canceladas', function () {
    $f = fixtureEntregasCanceladasDocente();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = docenteEntregasCanceladas($f);
    // La vista Blade de Inertia no necesita el manifiesto de Vite para el test.
    $this->withoutVite();

    $totalEntregas = function () use ($usuario, $f): ?int {
        $response = $this->actingAs($usuario)->get("/docente/cursos/{$f['id_curso']}/actividades");
        $response->assertOk();

        $actividad = collect($response->viewData('page')['props']['actividades'])
            ->firstWhere('id_actividad', $f['id_actividad']);

        return $actividad['total_entregas'] ?? null;
    };

    $antes = $totalEntregas();
    expect($antes)->not->toBeNull();

    sembrarHiloEntregasCanceladas($f);

    // Tres entregas sembradas, una de ellas cancelada: suman dos.
    expect($totalEntregas())->toBe($antes + 2);
});

// ---------------------------------------------------------------------------
// storeEvaluacion
// ---------------------------------------------------------------------------

test('storeEvaluacion rechaza evaluar una entrega cancelada', function () {
    $f = fixtureEntregasCanceladasDocente();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = docenteEntregasCanceladas($f);
    $hilo = sembrarHiloEntregasCanceladas($f);
    $actividad = Actividad::findOrFail($f['id_actividad']);
    $rubrica = rubricaEntregasCanceladas($actividad->id_actividad);

    $response = $this->actingAs($usuario)
        ->post(
            "/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/grupos/{$f['id_actividad_asignada_grupo']}/evaluacion",
            payloadEvaluacionEntregas($actividad, $rubrica->id_rubrica, $hilo['cancelada']->id_agenda)
        );

    $response->assertSessionHasErrors(['id_agenda_entrega']);

    // No se creó la fila «Evaluación» con el archivo retirado.
    expect(Agenda::where('id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->where('tipo_mensaje', TipoMensaje::EVALUACIÓN->value)
        ->where('uuid_archivo_subido', $hilo['cancelada']->uuid_archivo_subido)
        ->exists())->toBeFalse();
});

test('storeEvaluacion acepta evaluar una entrega vigente', function () {
    $f = fixtureEntregasCanceladasDocente();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $usuario = docenteEntregasCanceladas($f);
    $hilo = sembrarHiloEntregasCanceladas($f);
    $actividad = Actividad::findOrFail($f['id_actividad']);
    $rubrica = rubricaEntregasCanceladas($actividad->id_actividad);

    $response = $this->actingAs($usuario)
        ->post(
            "/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/grupos/{$f['id_actividad_asignada_grupo']}/evaluacion",
            payloadEvaluacionEntregas($actividad, $rubrica->id_rubrica, $hilo['vigente']->id_agenda)
        );

    $response->assertSessionDoesntHaveErrors(['id_agenda_entrega']);

    expect(Agenda::where('id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->where('tipo_mensaje', TipoMensaje::EVALUACIÓN->value)
        ->where('uuid_archivo_subido', $hilo['vigente']->uuid_archivo_subido)
        ->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Agenda::soloEntregasVigentes y scope entregasVigentes
// ---------------------------------------------------------------------------

test('soloEntregasVigentes funciona con la tabla aliada', function () {
    $f = fixtureEntregasCanceladasDocente();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $hilo = sembrarHiloEntregasCanceladas($f);

    $ids = DB::table('agenda.agenda as a')
        ->where('a.id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->where(fn ($q) => Agenda::soloEntregasVigentes($q, 'a'))
        ->pluck('a.id_agenda')
        ->all();

    expect($ids)
        ->toContain($hilo['anterior']->id_agenda)
        ->toContain($hilo['vigente']->id_agenda)
        ->not->toContain($hilo['cancelada']->id_agenda)
        ->not->toContain($hilo['cancelacion']->id_agenda);
});

test('soloEntregasVigentes funciona con la tabla calificada por esquema', function () {
    $f = fixtureEntregasCanceladasDocente();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $hilo = sembrarHiloEntregasCanceladas($f);

    $ids = DB::table('agenda.agenda')
        ->where('id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->where(fn ($q) => Agenda::soloEntregasVigentes($q, 'agenda.agenda'))
        ->pluck('id_agenda')
        ->all();

    expect($ids)
        ->toContain($hilo['anterior']->id_agenda)
        ->toContain($hilo['vigente']->id_agenda)
        ->not->toContain($hilo['cancelada']->id_agenda)
        ->not->toContain($hilo['cancelacion']->id_agenda);
});

test('el scope entregasVigentes excluye la entrega cancelada', function () {
    $f = fixtureEntregasCanceladasDocente();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $hilo = sembrarHiloEntregasCanceladas($f);

    $ids = Agenda::where('id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->entregasVigentes()
        ->pluck('id_agenda')
        ->all();

    expect($ids)
        ->toContain($hilo['anterior']->id_agenda)
        ->toContain($hilo['vigente']->id_agenda)
        ->not->toContain($hilo['cancelada']->id_agenda)
        ->not->toContain($hilo['cancelacion']->id_agenda);
});

test('una cancelación con el mismo archivo en otro grupo no cancela la entrega', function () {
    $f = fixtureEntregasCanceladasDocente();
    if (!$f) {
        $this->markTestSkipped('No se encontró fixture de curso/actividad con grupo.');
    }

    $otroGrupo = DB::table('agenda.actividad_asignada_grupo')
        ->where('id_actividad_asignada_grupo', '!=', $f['id_actividad_asignada_grupo'])
        ->value('id_actividad_asignada_grupo');
    if (!$otroGrupo) {
        $this->markTestSkipped('Se requiere un segundo grupo en la base de datos.');
    }

    $emisor = emisorEntregasCanceladas($f);
    $uuid = archivoEntregaCancelada('entrega_cruzada.pdf');
    $entrega = filaAgendaEntregas((int) $f['id_actividad_asignada_grupo'], $emisor, TipoMensaje::ENTREGA_DE_ARCHIVO, $uuid, now()->subHours(2)->toDateTimeString());
    filaAgendaEntregas((int) $otroGrupo, $emisor, TipoMensaje::CANCELACIÓN_DE_ENTREGA, $uuid, now()->subHour()->toDateTimeString());

    $ids = Agenda::where('id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->entregasVigentes()
        ->pluck('id_agenda')
        ->all();

    expect($ids)->toContain($entrega->id_agenda);
});
