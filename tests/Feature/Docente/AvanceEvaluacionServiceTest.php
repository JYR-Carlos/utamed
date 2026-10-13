<?php

/**
 * AvanceEvaluacionService: métricas de las tarjetas del tablero docente.
 *
 * - «entregaron» cuenta grupos con entrega vigente, no filas: reemplazar el
 *   archivo no suma y una entrega cancelada no cuenta.
 * - Una formativa se da por revisada con feedback dirigido al grupo; un
 *   mensaje masivo del titular (misma fila en todos los grupos) no cuenta.
 */

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Agenda;
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

function filaAvance(int $grupo, int $emisor, TipoMensaje $tipo, ?string $uuid, string $mensaje, $fecha): void
{
    Agenda::create([
        'fecha_envio' => $fecha,
        'tipo_mensaje' => $tipo,
        'mensaje' => $mensaje,
        'uuid_archivo_subido' => $uuid,
        'id_usuario_emisor' => $emisor,
        'id_actividad_asignada_grupo' => $grupo,
    ]);
}

test('cuenta grupos que entregaron, calificados, feedback individual y pendientes', function () {
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
    [$g1, $g2, $g3] = $grupos;

    // Punto de partida limpio para esta actividad.
    DB::table('agenda.actividad_asignada_grupo')->whereIn('id_actividad_asignada_grupo', $grupos)->update(['nota' => null]);
    DB::table('agenda.agenda')->whereIn('id_actividad_asignada_grupo', $grupos)->delete();

    $emisor = (int) DB::table('usuario.usuario')->value('id_usuario');
    $antes = now()->subDays(2);

    // g1 entrega dos veces (reemplazo) y tiene nota.
    filaAvance($g1, $emisor, TipoMensaje::ENTREGA_DE_ARCHIVO, archivoAvance(), 'v1', $antes);
    filaAvance($g1, $emisor, TipoMensaje::ENTREGA_DE_ARCHIVO, archivoAvance(), 'v2', $antes->copy()->addHour());
    DB::table('agenda.actividad_asignada_grupo')->where('id_actividad_asignada_grupo', $g1)->update(['nota' => 5.5]);

    // g2 entrega y recibe feedback dirigido a él.
    filaAvance($g2, $emisor, TipoMensaje::ENTREGA_DE_ARCHIVO, archivoAvance(), 'entrega', $antes);
    filaAvance($g2, $emisor, TipoMensaje::FEEDBACK, null, 'Buen avance, revisa la portada.', $antes->copy()->addDay());

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
        'grupos_con_entrega' => 2,   // g1 (una vez, pese al reemplazo) y g2
        'calificados' => 1,          // g1
        'por_calificar' => 1,        // g2 entregó y no tiene nota
        'con_feedback' => 1,         // g2; el masivo no cuenta
        'por_revisar' => 1,          // g1 entregó y no recibió feedback individual
        'promedio' => 5.5,
    ]);
});

test('sin actividades devuelve una colección vacía', function () {
    expect(app(AvanceEvaluacionService::class)->porActividad([]))->toBeEmpty();
});
