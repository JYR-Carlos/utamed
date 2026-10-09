<?php

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\Agenda;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Services\Student\ResumenActividadesEstudiante;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

function resumen_fixtureEstudianteConActividad(): ?array
{
    $row = DB::selectOne("
        SELECT e.id_estudiante, e.id_usuario, comp.id_curso, comp.id_componente, a.id_actividad, g.id_actividad_asignada_grupo
        FROM agenda.integrante_grupo ig
        JOIN agenda.actividad_asignada_grupo g ON g.id_actividad_asignada_grupo = ig.id_actividad_asignada_grupo
        JOIN agenda.actividad a ON a.id_actividad = g.id_actividad
        JOIN curso.componente comp ON comp.id_componente = a.id_componente
        JOIN usuario.estudiante e ON e.id_estudiante = ig.id_estudiante
        JOIN curso.inscripcion_curso ic ON ic.id_estudiante = e.id_estudiante
             AND ic.id_curso = comp.id_curso AND ic.estado_inscripcion = 'INSCRITO'
        WHERE a.visible
        ORDER BY g.id_actividad_asignada_grupo
        LIMIT 1
    ");

    if (!$row) {
        return null;
    }

    $f = (array) $row;
    $f['estudiante'] = Estudiante::findOrFail($f['id_estudiante']);
    $f['usuario'] = Usuario::findOrFail($f['id_usuario']);

    // Limpiar mensajes previos en agenda para esta actividad/grupo en el test
    Agenda::where('id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])->delete();

    return $f;
}

test('proximasAVencer incluye una actividad vigente dentro de la ventana de 7 días sin entrega', function () {
    $f = resumen_fixtureEstudianteConActividad();
    expect($f)->not->toBeNull();

    $actividad = Actividad::findOrFail($f['id_actividad']);
    $actividad->visible = true;
    $actividad->fecha_limite = Carbon::now()->addDays(3);
    $actividad->nro_dias_adicionales_para_bloqueo = 0;
    $actividad->tipo_entrega = 'Con entrega';
    $actividad->save();

    $resumen = new ResumenActividadesEstudiante();
    $pendientes = $resumen->proximasAVencer($f['estudiante']);

    $ids = collect($pendientes)->pluck('id_actividad')->all();
    expect($ids)->toContain($actividad->id_actividad);
});

test('proximasAVencer incluye actividades vencidas no entregadas recientes con flag es_vencida', function () {
    $f = resumen_fixtureEstudianteConActividad();
    expect($f)->not->toBeNull();

    $actividad = Actividad::findOrFail($f['id_actividad']);
    $actividad->visible = true;
    $actividad->fecha_limite = Carbon::now()->subDays(4);
    $actividad->nro_dias_adicionales_para_bloqueo = 0;
    $actividad->tipo_entrega = 'Con entrega';
    $actividad->save();

    $resumen = new ResumenActividadesEstudiante();
    $pendientes = $resumen->proximasAVencer($f['estudiante']);

    $item = collect($pendientes)->firstWhere('id_actividad', $actividad->id_actividad);
    expect($item)->not->toBeNull();
    expect($item['es_vencida'])->toBeTrue();
});

test('proximasAVencer excluye actividades vencidas antiguas de mas de 14 dias', function () {
    $f = resumen_fixtureEstudianteConActividad();
    expect($f)->not->toBeNull();

    $actividad = Actividad::findOrFail($f['id_actividad']);
    $actividad->visible = true;
    $actividad->fecha_limite = Carbon::now()->subDays(20);
    $actividad->nro_dias_adicionales_para_bloqueo = 0;
    $actividad->save();

    $resumen = new ResumenActividadesEstudiante();
    $pendientes = $resumen->proximasAVencer($f['estudiante']);

    $ids = collect($pendientes)->pluck('id_actividad')->all();
    expect($ids)->not->toContain($actividad->id_actividad);
});

test('proximasAVencer excluye actividades vencidas que ya fueron entregadas', function () {
    $f = resumen_fixtureEstudianteConActividad();
    expect($f)->not->toBeNull();

    $actividad = Actividad::findOrFail($f['id_actividad']);
    $actividad->visible = true;
    $actividad->fecha_limite = Carbon::now()->subDays(3);
    $actividad->nro_dias_adicionales_para_bloqueo = 0;
    $actividad->tipo_entrega = 'Con entrega';
    $actividad->save();

    // Crear entrega vigente para la actividad vencida
    $uuid = (string) Str::uuid();
    DB::table('operaciones.archivo')->insert([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "tests/entregas/{$uuid}.pdf",
        'nombre_original' => 'trabajo_vencido.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 1024,
    ]);

    Agenda::create([
        'fecha_envio' => now(),
        'tipo_mensaje' => TipoMensaje::ENTREGA_DE_ARCHIVO,
        'mensaje' => 'Entrega',
        'uuid_archivo_subido' => $uuid,
        'id_usuario_emisor' => $f['usuario']->id_usuario,
        'id_actividad_asignada_grupo' => $f['id_actividad_asignada_grupo'],
    ]);

    $resumen = new ResumenActividadesEstudiante();
    $pendientes = $resumen->proximasAVencer($f['estudiante']);

    $ids = collect($pendientes)->pluck('id_actividad')->all();
    expect($ids)->not->toContain($actividad->id_actividad);
});

test('proximasAVencer excluye actividades donde el estudiante ya tiene una entrega vigente', function () {
    $f = resumen_fixtureEstudianteConActividad();
    expect($f)->not->toBeNull();

    $actividad = Actividad::findOrFail($f['id_actividad']);
    $actividad->visible = true;
    $actividad->fecha_limite = Carbon::now()->addDays(3);
    $actividad->nro_dias_adicionales_para_bloqueo = 0;
    $actividad->tipo_entrega = 'Con entrega';
    $actividad->save();

    // Crear entrega vigente en agenda
    $uuid = (string) Str::uuid();
    DB::table('operaciones.archivo')->insert([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "tests/entregas/{$uuid}.pdf",
        'nombre_original' => 'trabajo.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 1024,
    ]);

    Agenda::create([
        'fecha_envio' => now(),
        'tipo_mensaje' => TipoMensaje::ENTREGA_DE_ARCHIVO,
        'mensaje' => 'Entrega',
        'uuid_archivo_subido' => $uuid,
        'id_usuario_emisor' => $f['usuario']->id_usuario,
        'id_actividad_asignada_grupo' => $f['id_actividad_asignada_grupo'],
    ]);

    $resumen = new ResumenActividadesEstudiante();
    $pendientes = $resumen->proximasAVencer($f['estudiante']);

    $ids = collect($pendientes)->pluck('id_actividad')->all();
    expect($ids)->not->toContain($actividad->id_actividad);
});

test('proximasAVencer excluye actividades que ya fueron evaluadas', function () {
    $f = resumen_fixtureEstudianteConActividad();
    expect($f)->not->toBeNull();

    $actividad = Actividad::findOrFail($f['id_actividad']);
    $actividad->visible = true;
    $actividad->fecha_limite = Carbon::now()->addDays(3);
    $actividad->save();

    // Marcar nota en el grupo
    DB::table('agenda.actividad_asignada_grupo')
        ->where('id_actividad_asignada_grupo', $f['id_actividad_asignada_grupo'])
        ->update(['nota' => 6.5]);

    $resumen = new ResumenActividadesEstudiante();
    $pendientes = $resumen->proximasAVencer($f['estudiante']);

    $ids = collect($pendientes)->pluck('id_actividad')->all();
    expect($ids)->not->toContain($actividad->id_actividad);
});
