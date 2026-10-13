<?php

use App\Enums\DB\TipoActividad;
use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\Evaluacion;
use App\Models\Curso\Curso;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

function fixtureEstudianteEvaluacionFormativa(): ?array
{
    $row = DB::selectOne("
        SELECT c.id_curso, a.id_actividad, u.id_usuario, g.id_actividad_asignada_grupo
        FROM curso.curso c
        JOIN curso.inscripcion_curso ic ON ic.id_curso = c.id_curso AND ic.estado_inscripcion = 'INSCRITO'
        JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
        JOIN usuario.usuario u ON u.id_usuario = e.id_usuario
        JOIN curso.componente comp ON comp.id_curso = c.id_curso
        JOIN agenda.actividad a ON a.id_componente = comp.id_componente
        JOIN agenda.actividad_asignada_grupo g ON g.id_actividad = a.id_actividad
        JOIN agenda.integrante_grupo ig ON ig.id_actividad_asignada_grupo = g.id_actividad_asignada_grupo AND ig.id_estudiante = e.id_estudiante
        WHERE a.visible = true
        ORDER BY a.id_actividad
        LIMIT 1
    ");

    return $row ? (array) $row : null;
}

test('estudiante ve la evaluacion formativa (Bueno, Regular, Malo) en ultima_evaluacion sin nota numerica', function () {
    $fixture = fixtureEstudianteEvaluacionFormativa();
    if (!$fixture) {
        $this->markTestSkipped('No hay fixture de estudiante con actividad asignada.');
    }

    $usuarioEstudiante = Usuario::findOrFail($fixture['id_usuario']);
    $usuarioEstudiante->forceFill(['fecha_cambio_passhash' => now()])->save();
    $curso = Curso::findOrFail($fixture['id_curso']);
    $actividad = Actividad::findOrFail($fixture['id_actividad']);
    $actividad->tipo_actividad = TipoActividad::FORMATIVA;
    $actividad->save();

    $idGrupo = $fixture['id_actividad_asignada_grupo'];
    ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $idGrupo)->update(['nota' => null]);
    DB::table('agenda.integrante_grupo')
        ->where('id_actividad_asignada_grupo', $idGrupo)
        ->update(['nota_individual' => null]);

    // Limpiar agendas previas del grupo
    DB::table('agenda.agenda')->where('id_actividad_asignada_grupo', $idGrupo)->delete();

    // Crear docente evaluador
    $docenteUser = Usuario::factory()->create(['nombre1' => 'Profesor', 'apellido1' => 'Prueba']);
    $docenteUser->forceFill(['fecha_cambio_passhash' => now()])->save();
    Docente::firstOrCreate(['id_usuario' => $docenteUser->id_usuario]);

    $mensajeEvaluacion = 'Trabajo muy bien estructurado. Observación formativa detallada sobre el entregable.';

    // Registrar evaluación formativa
    $idAgenda = DB::table('agenda.agenda')->insertGetId([
        'mensaje' => $mensajeEvaluacion,
        'id_usuario_emisor' => $docenteUser->id_usuario,
        'id_actividad_asignada_grupo' => $idGrupo,
        'tipo_mensaje' => TipoMensaje::EVALUACIÓN->value,
        'fecha_envio' => now(),
    ], 'id_agenda');

    Evaluacion::create([
        'evaluacion_obtenida' => 'Bueno',
        'id_agenda' => $idAgenda,
        'id_usuario_evaluador' => $docenteUser->id_usuario,
        'fecha_evaluacion' => now(),
        'id_rubrica' => null,
        'puntaje_obtenido' => null,
        'resultado' => null,
    ]);

    $response = $this->actingAs($usuarioEstudiante)
        ->get("/estudiante/cursos/{$curso->id_curso}/actividad/{$actividad->id_actividad}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('student/Activities/Index')
        ->where('es_sumativa', false)
        ->where('ultima_nota', null)
        ->where('ultima_evaluacion.evaluacion_obtenida', 'Bueno')
        ->where('ultima_evaluacion.retroalimentacion', $mensajeEvaluacion)
        ->where('ultima_evaluacion.puntaje_obtenido', null)
        ->has('listado_interacciones', 1)
        ->where('listado_interacciones.0.tipo_interaccion', TipoMensaje::EVALUACIÓN->value)
        ->where('listado_interacciones.0.evaluacion_obtenida', 'Bueno')
    );
});

test('actividad formativa no evaluada entrega ultima_evaluacion y ultima_nota en null', function () {
    $fixture = fixtureEstudianteEvaluacionFormativa();
    if (!$fixture) {
        $this->markTestSkipped('No hay fixture de estudiante con actividad asignada.');
    }

    $usuarioEstudiante = Usuario::findOrFail($fixture['id_usuario']);
    $usuarioEstudiante->forceFill(['fecha_cambio_passhash' => now()])->save();
    $curso = Curso::findOrFail($fixture['id_curso']);
    $actividad = Actividad::findOrFail($fixture['id_actividad']);
    $actividad->tipo_actividad = TipoActividad::FORMATIVA;
    $actividad->save();

    $idGrupo = $fixture['id_actividad_asignada_grupo'];
    ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $idGrupo)->update(['nota' => null]);
    DB::table('agenda.integrante_grupo')
        ->where('id_actividad_asignada_grupo', $idGrupo)
        ->update(['nota_individual' => null]);

    DB::table('agenda.agenda')->where('id_actividad_asignada_grupo', $idGrupo)->delete();

    $response = $this->actingAs($usuarioEstudiante)
        ->get("/estudiante/cursos/{$curso->id_curso}/actividad/{$actividad->id_actividad}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('student/Activities/Index')
        ->where('es_sumativa', false)
        ->where('ultima_nota', null)
        ->where('ultima_evaluacion', null)
    );
});
