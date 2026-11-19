<?php

/**
 * Calendario general del estudiante (FEAT-06, OBS-23): las fechas límite de
 * todos sus cursos del semestre vigente (el más reciente con cursos
 * inscritos), cada una con el curso al que pertenece.
 */

use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

test('el calendario general junta las fechas de los cursos del semestre vigente', function () {
    $row = DB::selectOne("
        SELECT e.id_usuario, e.id_estudiante
        FROM curso.inscripcion_curso ic
        JOIN curso.curso c ON c.id_curso = ic.id_curso
        JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
        WHERE ic.estado_inscripcion = 'INSCRITO'
          AND c.agno_real IS NOT NULL AND c.semestre_real IS NOT NULL
        ORDER BY ic.id_curso
        LIMIT 1
    ");
    if (!$row) {
        $this->markTestSkipped('No hay un estudiante inscrito en un curso con período.');
    }

    $periodo = DB::selectOne("
        SELECT c.agno_real, c.semestre_real
        FROM curso.inscripcion_curso ic
        JOIN curso.curso c ON c.id_curso = ic.id_curso
        WHERE ic.id_estudiante = ? AND ic.estado_inscripcion = 'INSCRITO'
          AND c.agno_real IS NOT NULL AND c.semestre_real IS NOT NULL
        ORDER BY c.agno_real DESC, c.semestre_real DESC
        LIMIT 1
    ", [$row->id_estudiante]);

    $cursosDelPeriodo = collect(DB::select("
        SELECT c.id_curso
        FROM curso.inscripcion_curso ic
        JOIN curso.curso c ON c.id_curso = ic.id_curso
        WHERE ic.id_estudiante = ? AND ic.estado_inscripcion = 'INSCRITO'
          AND c.agno_real = ? AND c.semestre_real = ?
    ", [$row->id_estudiante, $periodo->agno_real, $periodo->semestre_real]))->pluck('id_curso')->map(fn ($id) => (int) $id);

    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    $this->actingAs($usuario)
        ->get('/estudiante/calendario')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/Calendario')
            ->where('periodo', ['semestre' => (int) $periodo->semestre_real, 'agno' => (int) $periodo->agno_real])
            ->whereType('actividades', 'array')
            ->where('actividades', function ($actividades) use ($cursosDelPeriodo) {
                // Cada fecha trae su curso, y el curso es del semestre vigente.
                return collect($actividades)->every(fn ($a) => $cursosDelPeriodo->contains((int) $a['id_curso'])
                    && $a['fecha_limite'] !== null);
            }));
});

test('quien no es estudiante no entra al calendario general', function () {
    $row = DB::selectOne("
        SELECT u.id_usuario
        FROM usuario.usuario u
        WHERE NOT EXISTS (SELECT 1 FROM usuario.estudiante e WHERE e.id_usuario = u.id_usuario)
        ORDER BY u.id_usuario
        LIMIT 1
    ");
    if (!$row) {
        $this->markTestSkipped('No hay un usuario que no sea estudiante.');
    }

    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    $respuesta = $this->actingAs($usuario)->get('/estudiante/calendario');

    expect($respuesta->status())->not->toBe(200);
});
