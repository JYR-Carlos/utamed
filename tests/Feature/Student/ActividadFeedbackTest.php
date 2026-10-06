<?php

use App\Enums\DB\EstadoRubrica;
use App\Enums\DB\TipoActividad;
use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\Evaluacion;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Curso;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

function fixtureEstudianteActividad(): ?array
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

    if (!$row) {
        return null;
    }

    return (array) $row;
}

test('mensaje de feedback del docente no marca ultima_evaluacion ni es_retroalimentacion en evaluacion', function () {
    $fixture = fixtureEstudianteActividad();
    if (!$fixture) {
        $this->markTestSkipped('No hay fixture de estudiante con actividad asignada.');
    }

    $usuarioEstudiante = Usuario::findOrFail($fixture['id_usuario']);
    $usuarioEstudiante->forceFill(['fecha_cambio_passhash' => now()])->save();
    $curso = Curso::findOrFail($fixture['id_curso']);
    $actividad = Actividad::findOrFail($fixture['id_actividad']);
    $idGrupo = $fixture['id_actividad_asignada_grupo'];

    // Asegurarse de que no haya nota asignada
    ActividadAsignadaGrupo::where('id_actividad_asignada_grupo', $idGrupo)->update(['nota' => null]);
    DB::table('agenda.integrante_grupo')
        ->where('id_actividad_asignada_grupo', $idGrupo)
        ->update(['nota_individual' => null]);

    // Limpiar agendas previas de este grupo dentro de la transacción de prueba
    DB::table('agenda.agenda')->where('id_actividad_asignada_grupo', $idGrupo)->delete();

    // Crear un docente y un mensaje de FEEDBACK
    $docenteUser = Usuario::factory()->create();
    $docenteUser->forceFill(['fecha_cambio_passhash' => now()])->save();
    Docente::firstOrCreate(['id_usuario' => $docenteUser->id_usuario]);

    DB::table('agenda.agenda')->insert([
        'mensaje' => 'Hola, buen avance en el borrador.',
        'id_usuario_emisor' => $docenteUser->id_usuario,
        'id_actividad_asignada_grupo' => $idGrupo,
        'tipo_mensaje' => TipoMensaje::FEEDBACK->value,
        'fecha_envio' => now(),
    ]);

    $response = $this->actingAs($usuarioEstudiante)
        ->get("/estudiante/cursos/{$curso->id_curso}/actividad/{$actividad->id_actividad}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('student/Activities/Index')
        ->where('ultima_evaluacion', null)
        ->where('ultima_nota', null)
        ->has('listado_interacciones', 1)
        ->where('listado_interacciones.0.tipo_interaccion', TipoMensaje::FEEDBACK->value)
        ->where('listado_interacciones.0.es_retroalimentacion', true)
    );
});

test('mensaje de evaluacion marca ultima_evaluacion y tiene emisor y fecha_emision', function () {
    $fixture = fixtureEstudianteActividad();
    if (!$fixture) {
        $this->markTestSkipped('No hay fixture de estudiante con actividad asignada.');
    }

    $usuarioEstudiante = Usuario::findOrFail($fixture['id_usuario']);
    $usuarioEstudiante->forceFill(['fecha_cambio_passhash' => now()])->save();
    $curso = Curso::findOrFail($fixture['id_curso']);
    $actividad = Actividad::findOrFail($fixture['id_actividad']);
    $idGrupo = $fixture['id_actividad_asignada_grupo'];

    // Asegurar rúbrica
    $rubrica = Rubrica::where('id_actividad', $actividad->id_actividad)->first();
    if (!$rubrica) {
        $rubrica = Rubrica::create([
            'id_actividad' => $actividad->id_actividad,
            'rubrica' => ['columnas' => [], 'niveles' => []],
            'estado_rubrica' => EstadoRubrica::POSTULADA->value,
        ]);
    }

    // Limpiar agendas previas de este grupo dentro de la transacción de prueba
    DB::table('agenda.agenda')->where('id_actividad_asignada_grupo', $idGrupo)->delete();

    $docenteUser = Usuario::factory()->create([
        'nombre1' => 'Profesor',
        'apellido1' => 'Prueba',
    ]);
    $docenteUser->forceFill(['fecha_cambio_passhash' => now()])->save();
    Docente::firstOrCreate(['id_usuario' => $docenteUser->id_usuario]);

    $idAgenda = DB::table('agenda.agenda')->insertGetId([
        'mensaje' => 'Excelente trabajo, evaluación final.',
        'id_usuario_emisor' => $docenteUser->id_usuario,
        'id_actividad_asignada_grupo' => $idGrupo,
        'tipo_mensaje' => TipoMensaje::EVALUACIÓN->value,
        'fecha_envio' => now(),
    ], 'id_agenda');

    Evaluacion::create([
        'puntaje_obtenido' => 100,
        'resultado' => [],
        'evaluacion_obtenida' => '7.0',
        'fecha_evaluacion' => now(),
        'id_rubrica' => $rubrica->id_rubrica,
        'id_usuario_evaluador' => $docenteUser->id_usuario,
        'id_agenda' => $idAgenda,
    ]);

    $response = $this->actingAs($usuarioEstudiante)
        ->get("/estudiante/cursos/{$curso->id_curso}/actividad/{$actividad->id_actividad}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('student/Activities/Index')
        ->whereNot('ultima_evaluacion', null)
        ->where('ultima_evaluacion.emisor', fn (string $val) => str_contains($val, 'PROFESOR PRUEBA'))
        ->has('ultima_evaluacion.fecha_emision')
        ->has('listado_interacciones', 1)
        ->where('listado_interacciones.0.tipo_interaccion', TipoMensaje::EVALUACIÓN->value)
        ->where('listado_interacciones.0.es_retroalimentacion', false)
    );
});
