<?php

/**
 * Holgura a todo el curso: el titular extiende el plazo de una actividad para
 * todos con un botón, sin pasar por el formulario de edición. Escribe la
 * holgura general de la actividad; la personal de cada grupo se conserva y se
 * suma.
 */

use App\Enums\DB\EstadoActividadAsignada;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/**
 * Actividad visible, vencida hace 3 días y sin holgura, con al menos 2 grupos.
 *
 * @return array{titular: Usuario, actividad: Actividad, id_curso: int, url: string}
 */
function escenarioHolguraCurso(): array
{
    $row = DB::selectOne("
        SELECT c.id_curso, d.id_usuario, a.id_actividad
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        JOIN curso.componente co ON co.id_curso = c.id_curso
        JOIN agenda.actividad a ON a.id_componente = co.id_componente
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido')
          AND c.es_plantilla = false
          AND (SELECT count(*) FROM agenda.actividad_asignada_grupo g
               WHERE g.id_actividad = a.id_actividad) >= 2
        ORDER BY c.id_curso, a.id_actividad
        LIMIT 1
    ") ?? test()->markTestSkipped('Sin curso con titular y una actividad con grupos.');

    $titular = Usuario::findOrFail($row->id_usuario);
    $titular->fecha_cambio_passhash = now();
    $titular->save();

    $actividad = Actividad::findOrFail($row->id_actividad);
    $actividad->update([
        'visible' => true,
        'fecha_limite' => now()->subDays(3)->toDateString(),
        'nro_dias_adicionales_para_bloqueo' => 0,
    ]);
    ActividadAsignadaGrupo::where('id_actividad', $actividad->id_actividad)->update([
        'nro_dias_adicionales_para_bloqueo_personal' => 0,
        'estado_actividad_asignada' => EstadoActividadAsignada::CERRADA->value,
    ]);

    return [
        'titular' => $titular,
        'actividad' => $actividad,
        'id_curso' => (int) $row->id_curso,
        'url' => "/docente/cursos/{$row->id_curso}/actividades/{$row->id_actividad}/holgura",
    ];
}

function estadosGruposHolgura(Actividad $a): array
{
    return ActividadAsignadaGrupo::where('id_actividad', $a->id_actividad)->get()
        ->map(fn ($g) => $g->estado_actividad_asignada->value)->unique()->values()->all();
}

test('el titular da holgura a todo el curso: reabre a todos y conserva la personal', function () {
    $e = escenarioHolguraCurso();
    $grupo = ActividadAsignadaGrupo::where('id_actividad', $e['actividad']->id_actividad)
        ->orderBy('id_actividad_asignada_grupo')->firstOrFail();
    $grupo->update(['nro_dias_adicionales_para_bloqueo_personal' => 2]);
    $antes = $e['actividad']->fresh()->getAttributes();

    $this->actingAs($e['titular'])
        ->patch($e['url'], ['nro_dias_adicionales_para_bloqueo' => 5])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $despues = $e['actividad']->fresh()->getAttributes();
    expect((int) $despues['nro_dias_adicionales_para_bloqueo'])->toBe(5)
        ->and($despues['fecha_limite'])->toBe($antes['fecha_limite'])
        ->and($despues['nombre'])->toBe($antes['nombre'])
        ->and((int) $grupo->fresh()->nro_dias_adicionales_para_bloqueo_personal)->toBe(2)
        ->and(estadosGruposHolgura($e['actividad']))->toBe(['ACTIVA']);
});

test('quitar la holgura del curso vuelve a cerrar la actividad, salvo a quien tiene holgura personal', function () {
    $e = escenarioHolguraCurso();
    $e['actividad']->update(['nro_dias_adicionales_para_bloqueo' => 5]);
    $grupo = ActividadAsignadaGrupo::where('id_actividad', $e['actividad']->id_actividad)
        ->orderBy('id_actividad_asignada_grupo')->firstOrFail();
    $grupo->update(['nro_dias_adicionales_para_bloqueo_personal' => 6]);

    $this->actingAs($e['titular'])
        ->patch($e['url'], ['nro_dias_adicionales_para_bloqueo' => 0])
        ->assertSessionHas('success');

    expect($grupo->fresh()->estado_actividad_asignada->value)->toBe('ACTIVA')
        ->and(ActividadAsignadaGrupo::where('id_actividad', $e['actividad']->id_actividad)
            ->where('id_actividad_asignada_grupo', '!=', $grupo->id_actividad_asignada_grupo)
            ->get()->map(fn ($g) => $g->estado_actividad_asignada->value)->unique()->all())->toBe(['CERRADA']);
});

test('rechaza días inválidos y no cambia nada', function (mixed $dias) {
    $e = escenarioHolguraCurso();

    $this->actingAs($e['titular'])
        ->patch($e['url'], ['nro_dias_adicionales_para_bloqueo' => $dias])
        ->assertSessionHasErrors('nro_dias_adicionales_para_bloqueo');

    expect((int) $e['actividad']->fresh()->nro_dias_adicionales_para_bloqueo)->toBe(0);
})->with(['negativo' => -1, 'fuera de smallint' => 40000, 'texto' => 'tres', 'vacío' => null]);

test('un docente que no es titular del curso no puede dar holgura a todos', function () {
    $e = escenarioHolguraCurso();
    $id = DB::selectOne("
        SELECT d.id_usuario
        FROM usuario.docente d
        WHERE d.id_docente <> (SELECT id_docente_titular FROM curso.curso WHERE id_curso = ?)
          AND NOT EXISTS (
              SELECT 1 FROM usuario.usuario_rol_asignacion ura
              JOIN usuario.rol r ON r.id_rol = ura.id_rol
              WHERE ura.id_usuario = d.id_usuario AND ura.esta_activo AND NOT ura.fue_eliminado
                AND r.nombre IN ('Administrador', 'SuperAdmin', 'Super Admin'))
        ORDER BY d.id_docente LIMIT 1
    ", [$e['id_curso']])?->id_usuario ?? $this->markTestSkipped('Sin otro docente.');
    $otro = Usuario::findOrFail($id);
    $otro->fecha_cambio_passhash = now();
    $otro->save();

    $this->actingAs($otro)->patch($e['url'], ['nro_dias_adicionales_para_bloqueo' => 5])
        ->assertForbidden();

    expect((int) $e['actividad']->fresh()->nro_dias_adicionales_para_bloqueo)->toBe(0);
});

test('404 si la actividad no es del curso', function () {
    $e = escenarioHolguraCurso();
    $ajena = DB::selectOne('
        SELECT a.id_actividad FROM agenda.actividad a
        JOIN curso.componente co ON co.id_componente = a.id_componente
        WHERE co.id_curso <> ? ORDER BY a.id_actividad LIMIT 1
    ', [$e['id_curso']])?->id_actividad ?? $this->markTestSkipped('Sin actividades de otro curso.');

    $this->actingAs($e['titular'])
        ->patch("/docente/cursos/{$e['id_curso']}/actividades/{$ajena}/holgura", ['nro_dias_adicionales_para_bloqueo' => 5])
        ->assertNotFound();
});
