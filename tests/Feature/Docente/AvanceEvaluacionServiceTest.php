<?php

/**
 * AvanceEvaluacionService: métricas de las tarjetas del tablero docente.
 *
 * - «entregaron» cuenta grupos con entrega vigente, no filas: reemplazar el
 *   archivo no suma y una entrega cancelada no cuenta.
 * - Una formativa se da por revisada cuando el docente la evalúa; escribir en
 *   la agenda (a un grupo o a todos) es conversación y no cambia el estado.
 */

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\Evaluacion;
use App\Models\Operaciones\Archivo;
use App\Services\Agenda\AvanceEvaluacionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

function archivoAvance(): string
{
    $uuid = (string) Str::uuid7();
    Archivo::create([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "archivos/test/avance/{$uuid}.pdf",
        'nombre_original' => 'entrega.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 512,
        'pendiente_de_borrado' => false,
    ]);

    return $uuid;
}

function filaAvance(int $grupo, int $emisor, TipoMensaje $tipo, ?string $uuid, string $mensaje, $fecha): Agenda
{
    return Agenda::create([
        'fecha_envio' => $fecha,
        'tipo_mensaje' => $tipo,
        'mensaje' => $mensaje,
        'uuid_archivo_subido' => $uuid,
        'id_usuario_emisor' => $emisor,
        'id_actividad_asignada_grupo' => $grupo,
    ]);
}

test('cuenta grupos que entregaron, calificados, evaluados y pendientes', function () {
    $actividad = DB::selectOne('
        SELECT g.id_actividad
        FROM agenda.actividad_asignada_grupo g
        GROUP BY g.id_actividad
        HAVING COUNT(*) >= 4
        ORDER BY g.id_actividad
        LIMIT 1
    ');
    if (!$actividad) {
        $this->markTestSkipped('No hay una actividad con al menos 4 grupos.');
    }

    $grupos = DB::table('agenda.actividad_asignada_grupo')
        ->where('id_actividad', $actividad->id_actividad)
        ->orderBy('id_actividad_asignada_grupo')
        ->pluck('id_actividad_asignada_grupo')
        ->all();
    [$g1, $g2, $g3, $g4] = $grupos;

    // Punto de partida limpio para esta actividad.
    DB::table('agenda.actividad_asignada_grupo')->whereIn('id_actividad_asignada_grupo', $grupos)->update(['nota' => null]);
    DB::table('agenda.agenda')->whereIn('id_actividad_asignada_grupo', $grupos)->delete();

    $emisor = (int) DB::table('usuario.usuario')->value('id_usuario');
    $antes = now()->subDays(2);

    // g1 entrega dos veces (reemplazo) y tiene nota.
    filaAvance($g1, $emisor, TipoMensaje::ENTREGA_DE_ARCHIVO, archivoAvance(), 'v1', $antes);
    filaAvance($g1, $emisor, TipoMensaje::ENTREGA_DE_ARCHIVO, archivoAvance(), 'v2', $antes->copy()->addHour());
    DB::table('agenda.actividad_asignada_grupo')->where('id_actividad_asignada_grupo', $g1)->update(['nota' => 5.5]);

    // g2 entrega y el docente sólo le escribe: sigue esperando la evaluación.
    filaAvance($g2, $emisor, TipoMensaje::ENTREGA_DE_ARCHIVO, archivoAvance(), 'entrega', $antes);
    filaAvance($g2, $emisor, TipoMensaje::FEEDBACK, null, 'Buen avance, revisa la portada.', $antes->copy()->addDay());

    // g4 entrega y el docente lo evalúa, sin escribirle aparte.
    filaAvance($g4, $emisor, TipoMensaje::ENTREGA_DE_ARCHIVO, archivoAvance(), 'entrega', $antes);
    $evaluacion = filaAvance($g4, $emisor, TipoMensaje::EVALUACIÓN, null, 'Buen trabajo.', $antes->copy()->addDay());
    Evaluacion::create([
        'evaluacion_obtenida' => 'Bueno',
        'fecha_evaluacion' => $antes->copy()->addDay(),
        'id_usuario_evaluador' => $emisor,
        'id_agenda' => $evaluacion->id_agenda,
    ]);

    // g3 entrega y la cancela: no cuenta como entregó.
    $uuid = archivoAvance();
    filaAvance($g3, $emisor, TipoMensaje::ENTREGA_DE_ARCHIVO, $uuid, 'entrega', $antes);
    filaAvance($g3, $emisor, TipoMensaje::CANCELACIÓN_DE_ENTREGA, $uuid, 'cancelo', $antes->copy()->addHour());

    // Mensaje masivo del titular: la misma fila «Feedback» en todos los grupos.
    $masivo = $antes->copy()->addDays(1)->addHour();
    foreach ($grupos as $g) {
        filaAvance($g, $emisor, TipoMensaje::FEEDBACK, null, 'Recuerden la fecha de entrega.', $masivo);
    }

    $avance = app(AvanceEvaluacionService::class)->porActividad([$actividad->id_actividad])
        ->get($actividad->id_actividad);

    expect($avance)->toMatchArray([
        'total_grupos' => count($grupos),
        'grupos_con_entrega' => 3,   // g1 (una vez, pese al reemplazo), g2 y g4
        'calificados' => 1,          // g1
        'por_calificar' => 2,        // g2 y g4 entregaron y no tienen nota
        'revisados' => 1,            // g4; escribirle a g2 o a todos no cuenta
        'por_revisar' => 2,          // g1 y g2 entregaron y no están evaluados
        'promedio' => 5.5,
    ]);
});

test('sin actividades devuelve una colección vacía', function () {
    expect(app(AvanceEvaluacionService::class)->porActividad([]))->toBeEmpty();
});
