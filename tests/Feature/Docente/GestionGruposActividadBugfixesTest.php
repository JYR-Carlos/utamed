<?php

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\InscripcionCurso;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

/**
 * Fixture de curso con docente titular y al menos 2 estudiantes inscritos.
 *
 * @return array{id_curso: int, id_usuario: int, id_componente: int, id_unidad: int}|null
 */
function fixtureGruposBugfixes(): ?array
{
    $row = DB::selectOne("
        SELECT c.id_curso, d.id_usuario, a.id_componente, a.id_unidad
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        JOIN curso.componente co ON co.id_curso = c.id_curso
        JOIN agenda.actividad a ON a.id_componente = co.id_componente
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido')
          AND c.es_plantilla = false
          AND a.id_unidad IS NOT NULL
          AND (SELECT count(*) FROM curso.inscripcion_curso i
               WHERE i.id_curso = c.id_curso AND i.estado_inscripcion = 'INSCRITO') >= 2
        ORDER BY c.id_curso, a.id_actividad
        LIMIT 1
    ");

    return $row ? (array) $row : null;
}

function docenteGruposBugfixes(array $f): Usuario
{
    $usuario = Usuario::findOrFail($f['id_usuario']);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    return $usuario;
}

function crearActividadGrupalTest(Usuario $docente, array $f, int $maxIntegrantes = 3): Actividad
{
    test()->actingAs($docente)
        ->post("/docente/cursos/{$f['id_curso']}/actividades", [
            'nombre' => 'Actividad Bugfix Grupos',
            'fecha_limite' => now()->addDays(5)->toDateString(),
            'tipo_actividad' => 'SUMATIVA',
            'tipo_entrega' => 'online',
            'es_grupal' => true,
            'max_integrantes' => $maxIntegrantes,
            'visible' => true,
            'ponderacion' => 0,
            'exigencia' => 60,
            'id_componente' => $f['id_componente'],
            'id_unidad' => $f['id_unidad'],
        ])
        ->assertSessionHasNoErrors();

    return Actividad::where('id_componente', $f['id_componente'])
        ->where('nombre', 'Actividad Bugfix Grupos')
        ->latest('id_actividad')
        ->firstOrFail();
}

test('eliminar un grupo no evaluado con mensajes y entregas borra el grupo y marca archivos pendiente de borrado', function () {
    $f = fixtureGruposBugfixes() ?? $this->markTestSkipped('Sin curso adecuado con titular e inscritos.');
    $docente = docenteGruposBugfixes($f);
    $actividad = crearActividadGrupalTest($docente, $f);

    $inscrito = InscripcionCurso::where('id_curso', $f['id_curso'])
        ->where('estado_inscripcion', 'INSCRITO')
        ->firstOrFail();

    $grupo = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad->id_actividad,
        'nombre_grupo' => 'Grupo A Eliminar',
        'estado_actividad_asignada' => 'PLANIFICADA',
    ]);

    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_estudiante' => $inscrito->id_estudiante,
    ]);

    $idEmisor = (int) DB::table('usuario.estudiante')
        ->where('id_estudiante', $inscrito->id_estudiante)
        ->value('id_usuario');

    $uuid = (string) Str::uuid7();
    Archivo::create([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "archivos/test/{$uuid}.pdf",
        'nombre_original' => 'archivo.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 100,
        'pendiente_de_borrado' => false,
    ]);

    $agenda = Agenda::create([
        'fecha_envio' => now()->toDateTimeString(),
        'tipo_mensaje' => TipoMensaje::ENTREGA_DE_ARCHIVO,
        'mensaje' => 'Entrega de prueba',
        'uuid_archivo_subido' => $uuid,
        'id_usuario_emisor' => $idEmisor,
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
    ]);

    $this->actingAs($docente)
        ->delete("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}/grupos-delete/{$grupo->id_actividad_asignada_grupo}")
        ->assertSessionHas('success', 'Grupo eliminado correctamente.');

    expect(ActividadAsignadaGrupo::find($grupo->id_actividad_asignada_grupo))->toBeNull()
        ->and(IntegranteGrupo::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)->exists())->toBeFalse()
        ->and(Agenda::find($agenda->id_agenda))->toBeNull()
        ->and(Archivo::where('uuid_archivo', $uuid)->value('pendiente_de_borrado'))->toBeTrue();
});

test('eliminar un grupo evaluado es rechazado y no modifica el grupo', function () {
    $f = fixtureGruposBugfixes() ?? $this->markTestSkipped('Sin curso adecuado con titular e inscritos.');
    $docente = docenteGruposBugfixes($f);
    $actividad = crearActividadGrupalTest($docente, $f);

    $inscrito = InscripcionCurso::where('id_curso', $f['id_curso'])
        ->where('estado_inscripcion', 'INSCRITO')
        ->firstOrFail();

    $grupo = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad->id_actividad,
        'nombre_grupo' => 'Grupo Evaluado',
        'estado_actividad_asignada' => 'CERRADA',
        'nota' => 6.5,
    ]);

    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_estudiante' => $inscrito->id_estudiante,
        'nota_individual' => 6.5,
    ]);

    $this->actingAs($docente)
        ->delete("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}/grupos-delete/{$grupo->id_actividad_asignada_grupo}")
        ->assertSessionHasErrors(['error' => 'No se puede eliminar un grupo que ya tiene evaluación.']);

    expect(ActividadAsignadaGrupo::find($grupo->id_actividad_asignada_grupo))->not->toBeNull();
});

test('crear grupo con estudiantes duplicados en el payload es rechazado', function () {
    $f = fixtureGruposBugfixes() ?? $this->markTestSkipped('Sin curso adecuado con titular e inscritos.');
    $docente = docenteGruposBugfixes($f);
    $actividad = crearActividadGrupalTest($docente, $f);

    $idEstudiante = InscripcionCurso::where('id_curso', $f['id_curso'])
        ->where('estado_inscripcion', 'INSCRITO')
        ->value('id_estudiante');

    $this->actingAs($docente)
        ->post("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}/grupos-create", [
            'nombre_grupo' => 'Grupo Duplicado',
            'estudiantes' => [$idEstudiante, $idEstudiante],
        ])
        ->assertSessionHasErrors(['error' => 'No se pueden incluir estudiantes duplicados en el grupo.']);
});

test('crear grupo con un estudiante que ya pertenece a otro grupo de la misma actividad es rechazado', function () {
    $f = fixtureGruposBugfixes() ?? $this->markTestSkipped('Sin curso adecuado con titular e inscritos.');
    $docente = docenteGruposBugfixes($f);
    $actividad = crearActividadGrupalTest($docente, $f);

    $estudiantes = InscripcionCurso::where('id_curso', $f['id_curso'])
        ->where('estado_inscripcion', 'INSCRITO')
        ->limit(2)
        ->pluck('id_estudiante')
        ->all();

    // Crear primer grupo con $estudiantes[0]
    $this->actingAs($docente)
        ->post("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}/grupos-create", [
            'nombre_grupo' => 'Grupo Uno',
            'estudiantes' => [$estudiantes[0]],
        ])
        ->assertSessionHasNoErrors();

    // Intentar crear segundo grupo que incluya de nuevo a $estudiantes[0]
    $this->actingAs($docente)
        ->post("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}/grupos-create", [
            'nombre_grupo' => 'Grupo Dos',
            'estudiantes' => [$estudiantes[0], $estudiantes[1]],
        ])
        ->assertSessionHasErrors(['error' => 'Uno o más estudiantes ya pertenecen a otro grupo de esta actividad.']);
});

test('agregar estudiante a un grupo cuando ya pertenece a otro grupo de la misma actividad es rechazado', function () {
    $f = fixtureGruposBugfixes() ?? $this->markTestSkipped('Sin curso adecuado con titular e inscritos.');
    $docente = docenteGruposBugfixes($f);
    $actividad = crearActividadGrupalTest($docente, $f, maxIntegrantes: 3);

    $estudiantes = InscripcionCurso::where('id_curso', $f['id_curso'])
        ->where('estado_inscripcion', 'INSCRITO')
        ->limit(2)
        ->pluck('id_estudiante')
        ->all();

    // Grupo 1 con estudiante 0
    $this->actingAs($docente)
        ->post("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}/grupos-create", [
            'nombre_grupo' => 'Grupo A',
            'estudiantes' => [$estudiantes[0]],
        ])
        ->assertSessionHasNoErrors();

    // Grupo 2 con estudiante 1
    $this->actingAs($docente)
        ->post("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}/grupos-create", [
            'nombre_grupo' => 'Grupo B',
            'estudiantes' => [$estudiantes[1]],
        ])
        ->assertSessionHasNoErrors();

    $grupoB = ActividadAsignadaGrupo::where('id_actividad', $actividad->id_actividad)
        ->where('nombre_grupo', 'Grupo B')
        ->firstOrFail();

    // Intentar agregar estudiante 0 al Grupo B
    $this->actingAs($docente)
        ->post("/docente/cursos/{$f['id_curso']}/actividades/{$actividad->id_actividad}/grupos/{$grupoB->id_actividad_asignada_grupo}/estudiante", [
            'id_estudiante' => $estudiantes[0],
        ])
        ->assertSessionHasErrors(['error' => 'El estudiante ya pertenece a un grupo en esta actividad.']);
});
