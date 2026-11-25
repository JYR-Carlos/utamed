<?php

/**
 * Agenda de un grupo como JSON (GET …/actividades/{a}/grupos/{g}/agenda): la
 * usa el Inicio del docente para abrir la agenda del estudiante en un modal
 * sin salir de la pantalla. Devuelve lo mismo que la actividad desplegada.
 */

use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/** Grupo de una actividad de un curso cuyo titular tiene su rol activo. */
function fixtureAgendaGrupoJson(): ?array
{
    $row = DB::selectOne("
        SELECT c.id_curso, d.id_usuario, a.id_actividad, aag.id_actividad_asignada_grupo AS id_grupo
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        JOIN curso.componente co ON co.id_curso = c.id_curso
        JOIN agenda.actividad a ON a.id_componente = co.id_componente
        JOIN agenda.actividad_asignada_grupo aag ON aag.id_actividad = a.id_actividad
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido')
        ORDER BY a.id_actividad, aag.id_actividad_asignada_grupo
        LIMIT 1
    ");

    return $row ? (array) $row : null;
}

function docenteAgendaGrupoJson(array $f): Usuario
{
    $usuario = Usuario::findOrFail($f['id_usuario']);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    return $usuario;
}

test('la agenda de un grupo llega como JSON con la actividad, el grupo, la rúbrica y la conversación', function () {
    $f = fixtureAgendaGrupoJson() ?? $this->markTestSkipped('Sin curso con titular activo y grupos.');

    $this->actingAs(docenteAgendaGrupoJson($f))
        ->getJson("/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/grupos/{$f['id_grupo']}/agenda")
        ->assertOk()
        ->assertJsonPath('actividad.id_actividad', $f['id_actividad'])
        ->assertJsonPath('grupo.grupo', $f['id_grupo'])
        ->assertJsonStructure([
            'curso' => ['id_curso', 'nombre'],
            'actividad' => ['nombre', 'es_sumativa', 'trae_archivo', 'es_grupal', 'es_titular'],
            'grupo' => ['etiqueta', 'nota', 'evaluado', 'calificacion', 'entrega', 'integrantes' => ['*' => ['nombre_pila', 'nombre_completo']]],
            'rubrica',
            'rubrica_id',
            'interacciones',
        ]);
});

test('la agenda trae el primer nombre del estudiante y, en las formativas, la última calificación', function () {
    $f = fixtureAgendaGrupoJson() ?? $this->markTestSkipped('Sin curso con titular activo y grupos.');
    $ids = DB::table('agenda.integrante_grupo as ig')
        ->join('usuario.estudiante as e', 'e.id_estudiante', '=', 'ig.id_estudiante')
        ->where('ig.id_actividad_asignada_grupo', $f['id_grupo'])
        ->pluck('e.id_usuario');
    if ($ids->isEmpty()) {
        $this->markTestSkipped('El grupo no tiene integrantes.');
    }
    DB::table('usuario.usuario')->whereIn('id_usuario', $ids)->update(['nombre1' => 'Carolina Constanza']);

    foreach (['Malo' => now()->subDays(2), 'Bueno' => now()->subDay()] as $resultado => $fecha) {
        $idAgenda = DB::table('agenda.agenda')->insertGetId([
            'id_actividad_asignada_grupo' => $f['id_grupo'],
            'id_usuario_emisor' => $f['id_usuario'],
            'tipo_mensaje' => 'Evaluación',
            'mensaje' => 'Retroalimentación',
            'fecha_envio' => $fecha,
        ], 'id_agenda');
        DB::table('agenda.evaluacion')->insert([
            'id_agenda' => $idAgenda,
            'evaluacion_obtenida' => $resultado,
            'fecha_evaluacion' => $fecha,
            'id_usuario_evaluador' => $f['id_usuario'],
        ]);
    }

    $url = "/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/grupos/{$f['id_grupo']}/agenda";
    $this->actingAs(docenteAgendaGrupoJson($f));

    foreach (['FORMATIVA' => 'Bueno', 'SUMATIVA' => null] as $tipo => $calificacion) {
        DB::table('agenda.actividad')->where('id_actividad', $f['id_actividad'])->update(['tipo_actividad' => $tipo]);

        expect($this->getJson($url)->assertOk()->json('grupo'))
            ->calificacion->toBe($calificacion)
            ->integrantes->{0}->nombre_pila->toBe('Carolina');
    }
});

test('un grupo de otra actividad devuelve 404', function () {
    $f = fixtureAgendaGrupoJson() ?? $this->markTestSkipped('Sin curso con titular activo y grupos.');
    $ajeno = ActividadAsignadaGrupo::where('id_actividad', '!=', $f['id_actividad'])->value('id_actividad_asignada_grupo')
        ?? $this->markTestSkipped('No hay grupos de otra actividad.');

    $this->actingAs(docenteAgendaGrupoJson($f))
        ->getJson("/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/grupos/{$ajeno}/agenda")
        ->assertNotFound();
});
