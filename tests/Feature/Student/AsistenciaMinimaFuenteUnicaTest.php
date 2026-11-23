<?php

/**
 * OBS-45: la asistencia mínima tiene una sola fuente, el componente del curso
 * (`porcentaje_asistencia_obligatoria`). La leen el resumen de notas y el
 * programa del alumno, aunque la tabla del syllabus diga otro número.
 */

use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Usuario\Estudiante;
use App\Services\Student\RendimientoCursoEstudiante;
use App\Services\Student\StudentSyllabusPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/** Un alumno inscrito en un componente, con el componente y su tipo. */
function componenteConAlumno(): ?object
{
    return DB::selectOne("
        SELECT co.id_componente, co.id_curso, ic.id_estudiante, tc.tipo
        FROM curso.inscripcion_componente ic
        JOIN curso.componente co ON co.id_componente = ic.id_componente
        JOIN curso.tipo_componente tc ON tc.id_tipo_componente = co.id_tipo_componente
        ORDER BY co.id_componente
        LIMIT 1
    ");
}

test('el resumen de notas usa la asistencia mínima de cada componente', function () {
    $row = componenteConAlumno();
    if (!$row) {
        $this->markTestSkipped('No hay alumnos inscritos en componentes.');
    }
    // El resumen cuenta las clases del componente (curso.clase).
    if (DB::selectOne("SELECT to_regclass('curso.clase') AS t")->t === null) {
        $this->markTestSkipped('La BD de pruebas no tiene curso.clase: está atrasada (composer db:soft-reset-testing).');
    }

    Componente::whereKey($row->id_componente)->update(['porcentaje_asistencia_obligatoria' => 65]);

    $resultado = app(RendimientoCursoEstudiante::class)
        ->calcular(Curso::findOrFail($row->id_curso), Estudiante::findOrFail($row->id_estudiante));

    $fila = collect($resultado['componentes'])->firstWhere('id_componente', $row->id_componente);

    expect($fila)->not->toBeNull()
        ->and($fila['asistencia_minima'])->toBe(65.0)
        ->and($resultado)->not->toHaveKey('asistencia_minima');
});

test('sin valor en el componente, el mínimo es 70 %', function () {
    expect(Componente::ASISTENCIA_MINIMA_POR_DEFECTO)->toBe(70.0);
});

test('el programa del alumno muestra la asistencia del componente, no la que quedó escrita en el syllabus', function () {
    $row = componenteConAlumno();
    if (!$row) {
        $this->markTestSkipped('No hay alumnos inscritos en componentes.');
    }

    Componente::whereKey($row->id_componente)->update(['porcentaje_asistencia_obligatoria' => 70]);

    $secciones = [
        ['numeral_romano' => 'VI', 'nombre_seccion' => 'Unidades', 'contenidos' => []],
        ['numeral_romano' => 'IX', 'nombre_seccion' => 'Aspectos administrativos', 'componentes' => [
            ['componente' => mb_strtolower($row->tipo), 'porcentaje' => 60, 'asistencia_obligatoria' => 75],
            ['componente' => 'Componente que el curso no tiene', 'porcentaje' => 40, 'asistencia_obligatoria' => 75],
        ]],
    ];

    $resultado = StudentSyllabusPresenter::conAsistenciaDelCurso(Curso::findOrFail($row->id_curso), $secciones);

    expect($resultado[0])->toBe($secciones[0])
        ->and($resultado[1]['componentes'][0]['asistencia_obligatoria'])->toBe(70.0)
        ->and($resultado[1]['componentes'][0]['porcentaje'])->toBe(60)
        // Sin componente de ese tipo en el curso, queda lo del syllabus.
        ->and($resultado[1]['componentes'][1]['asistencia_obligatoria'])->toBe(75);
});
