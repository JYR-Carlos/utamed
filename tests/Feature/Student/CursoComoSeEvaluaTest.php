<?php

/**
 * Ficha del curso del estudiante, sección «Cómo se evalúa»: cada tarjeta de
 * componente cuenta sus actividades, así que cada actividad visible llega
 * con el tipo de su componente (`componente_tipo`) y el breadcrumb con la
 * etiqueta del curso.
 */

use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

test('cada actividad visible del curso llega con el tipo de su componente', function () {
    $row = DB::selectOne("
        SELECT ic.id_curso, e.id_usuario
        FROM curso.inscripcion_curso ic
        JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
        WHERE ic.estado_inscripcion = 'INSCRITO'
          AND EXISTS (
            SELECT 1 FROM agenda.actividad a
            JOIN curso.componente co ON co.id_componente = a.id_componente
            WHERE co.id_curso = ic.id_curso AND a.visible
          )
        ORDER BY ic.id_curso
        LIMIT 1
    ");
    if (!$row) {
        $this->markTestSkipped('No hay un estudiante inscrito en un curso con actividades visibles.');
    }

    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    $tipos = DB::table('agenda.actividad as a')
        ->join('curso.componente as co', 'co.id_componente', '=', 'a.id_componente')
        ->join('curso.tipo_componente as tc', 'tc.id_tipo_componente', '=', 'co.id_tipo_componente')
        ->where('co.id_curso', $row->id_curso)
        ->where('a.visible', true)
        ->pluck('tc.tipo')
        ->unique()
        ->values()
        ->all();

    $this->actingAs($usuario)
        ->get("/estudiante/cursos/{$row->id_curso}")
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($row, $tipos) {
            $page->component('student/Courses/Show')
                ->where('curso.etiqueta', Curso::findOrFail($row->id_curso)->etiqueta());

            $recibidos = collect($page->toArray()['props']['actividades'])
                ->pluck('componente_tipo')
                ->unique()
                ->values()
                ->all();

            expect($recibidos)->not->toContain(null);
            expect(array_diff($recibidos, $tipos))->toBe([]);
        });
});

test('el calendario del semestre llega sólo al pedirlo, con las fechas de todos los cursos del período', function () {
    $row = DB::selectOne("
        SELECT ic.id_curso, e.id_usuario
        FROM curso.inscripcion_curso ic
        JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
        WHERE ic.estado_inscripcion = 'INSCRITO'
        ORDER BY ic.id_curso
        LIMIT 1
    ");
    if (!$row) {
        $this->markTestSkipped('No hay un estudiante inscrito en un curso.');
    }

    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    $this->actingAs($usuario)
        ->get("/estudiante/cursos/{$row->id_curso}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('calendario_semestre'));

    // Una prop pedida que el servidor no define vuelve como null, y el
    // calendario la volvería a pedir sin fin: tiene que llegar una lista.
    $this->actingAs($usuario)
        ->get("/estudiante/cursos/{$row->id_curso}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('calendario_semestre', fn (Assert $reload) => $reload
                ->whereType('calendario_semestre', 'array')
                ->etc()));
});
