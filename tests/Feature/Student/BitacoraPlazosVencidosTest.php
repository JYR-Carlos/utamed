<?php

/**
 * OBS-55: la bitácora del alumno muestra los plazos que vencieron sin
 * entrega. No dejan fila en la agenda; se calculan desde las actividades.
 */

use App\Models\Curso\Curso;
use App\Models\Usuario\Estudiante;
use App\Services\Student\BitacoraCursoEstudiante;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/** Una actividad visible del curso del alumno en la que su grupo no tiene nada en la agenda. */
function actividadSinAgendaDelAlumno(): ?object
{
    return DB::selectOne("
        SELECT a.id_actividad, cmp.id_curso, est.id_estudiante
        FROM agenda.actividad a
        JOIN curso.componente cmp ON cmp.id_componente = a.id_componente
        JOIN curso.inscripcion_componente ic ON ic.id_componente = a.id_componente
        JOIN usuario.estudiante est ON est.id_estudiante = ic.id_estudiante
        WHERE a.visible
          AND NOT EXISTS (
                SELECT 1
                FROM agenda.actividad_asignada_grupo aag
                JOIN agenda.integrante_grupo ig ON ig.id_actividad_asignada_grupo = aag.id_actividad_asignada_grupo
                WHERE aag.id_actividad = a.id_actividad
                  AND ig.id_estudiante = est.id_estudiante
                  AND (ig.nota_individual IS NOT NULL
                       OR aag.nota IS NOT NULL
                       OR EXISTS (SELECT 1 FROM agenda.agenda ag WHERE ag.id_actividad_asignada_grupo = aag.id_actividad_asignada_grupo))
          )
        ORDER BY a.id_actividad
        LIMIT 1
    ");
}

test('un plazo vencido sin entrega aparece en la bitácora', function () {
    $row = actividadSinAgendaDelAlumno();
    if (!$row) {
        $this->markTestSkipped('No hay una actividad sin entregas del alumno.');
    }

    DB::table('agenda.actividad')->where('id_actividad', $row->id_actividad)
        ->update(['fecha_limite' => now()->subDays(10)->toDateString()]);

    $bitacora = app(BitacoraCursoEstudiante::class)
        ->compilar(Curso::findOrFail($row->id_curso), Estudiante::findOrFail($row->id_estudiante));

    $plazo = collect($bitacora['entradas'])->firstWhere('clave', "plazo-{$row->id_actividad}");

    expect($plazo)->not->toBeNull()
        ->and($plazo['tipo'])->toBe(BitacoraCursoEstudiante::PLAZO_VENCIDO)
        ->and($plazo['fecha'])->toStartWith(now()->subDays(10)->toDateString())
        ->and(collect($bitacora['actividades'])->pluck('id'))->toContain($row->id_actividad);
});

test('un plazo que todavía no vence no aparece', function () {
    $row = actividadSinAgendaDelAlumno();
    if (!$row) {
        $this->markTestSkipped('No hay una actividad sin entregas del alumno.');
    }

    DB::table('agenda.actividad')->where('id_actividad', $row->id_actividad)
        ->update(['fecha_limite' => now()->toDateString()]);

    $bitacora = app(BitacoraCursoEstudiante::class)
        ->compilar(Curso::findOrFail($row->id_curso), Estudiante::findOrFail($row->id_estudiante));

    expect(collect($bitacora['entradas'])->firstWhere('clave', "plazo-{$row->id_actividad}"))->toBeNull();
});
