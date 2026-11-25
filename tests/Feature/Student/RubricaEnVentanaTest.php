<?php

/**
 * El ícono de rúbrica al lado del nombre de la actividad: se enciende sólo en
 * las sumativas que tienen rúbrica, y la ventana la lee de su propia ruta.
 */

use App\Enums\DB\EstadoRubrica;
use App\Enums\DB\TipoActividad;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Curso;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Services\Student\ResumenActividadesEstudiante;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/** Un alumno inscrito en el componente de una actividad visible, sin rúbrica previa ni evaluaciones. */
function fixtureRubricaEnVentana(): ?array
{
    $row = DB::selectOne("
        SELECT c.id_curso, a.id_actividad, e.id_estudiante, e.id_usuario
        FROM curso.curso c
        JOIN curso.inscripcion_curso ic ON ic.id_curso = c.id_curso AND ic.estado_inscripcion = 'INSCRITO'
        JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
        JOIN curso.componente comp ON comp.id_curso = c.id_curso
        JOIN curso.inscripcion_componente icc ON icc.id_componente = comp.id_componente AND icc.id_estudiante = e.id_estudiante
        JOIN agenda.actividad a ON a.id_componente = comp.id_componente
        WHERE a.visible = true
          AND NOT EXISTS (SELECT 1 FROM agenda.rubrica r WHERE r.id_actividad = a.id_actividad)
          AND NOT EXISTS (
              SELECT 1 FROM agenda.agenda ag
              JOIN agenda.actividad_asignada_grupo g USING (id_actividad_asignada_grupo)
              WHERE g.id_actividad = a.id_actividad AND ag.id_agenda IN (SELECT id_agenda FROM agenda.evaluacion)
          )
        ORDER BY a.id_actividad
        LIMIT 1
    ");

    if (!$row) {
        return null;
    }

    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->forceFill(['fecha_cambio_passhash' => now()])->save();

    return [
        'usuario'    => $usuario,
        'estudiante' => Estudiante::findOrFail($row->id_estudiante),
        'curso'      => Curso::findOrFail($row->id_curso),
        'actividad'  => Actividad::findOrFail($row->id_actividad),
    ];
}

function ponerRubrica(Actividad $actividad, TipoActividad $tipo): void
{
    Actividad::whereKey($actividad->id_actividad)->update(['tipo_actividad' => $tipo->value]);
    Rubrica::create([
        'id_actividad'   => $actividad->id_actividad,
        'rubrica'        => ['niveles' => [], 'columnas' => [], 'detalles_evaluacion' => ['marca' => 'de prueba']],
        'estado_rubrica' => EstadoRubrica::POSTULADA->value,
    ]);
}

function rutaRubrica(array $f): string
{
    return "/estudiante/cursos/{$f['curso']->id_curso}/actividades/{$f['actividad']->id_actividad}/rubrica";
}

test('una sumativa con rúbrica enciende el ícono y la ventana la lee', function () {
    $f = fixtureRubricaEnVentana();
    if (!$f) {
        $this->markTestSkipped('No hay alumno con una actividad sin rúbrica.');
    }
    ponerRubrica($f['actividad'], TipoActividad::SUMATIVA);

    $fila = app(ResumenActividadesEstudiante::class)
        ->delCurso($f['estudiante'], $f['curso'])
        ->firstWhere('id_actividad', $f['actividad']->id_actividad);
    expect($fila['con_rubrica'])->toBeTrue();

    $this->actingAs($f['usuario'])
        ->getJson(rutaRubrica($f))
        ->assertOk()
        ->assertJsonPath('rubrica.detalles_evaluacion.marca', 'de prueba')
        // Sin evaluar, se lee sin marcas ni nota.
        ->assertJsonPath('resultado', null)
        ->assertJsonPath('nota', null);
});

test('sin rúbrica, o en una formativa, no hay ícono ni ventana', function () {
    $f = fixtureRubricaEnVentana();
    if (!$f) {
        $this->markTestSkipped('No hay alumno con una actividad sin rúbrica.');
    }
    $fila = fn () => app(ResumenActividadesEstudiante::class)
        ->delCurso($f['estudiante'], $f['curso'])
        ->firstWhere('id_actividad', $f['actividad']->id_actividad);

    Actividad::whereKey($f['actividad']->id_actividad)->update(['tipo_actividad' => TipoActividad::SUMATIVA->value]);
    expect($fila()['con_rubrica'])->toBeFalse();
    $this->actingAs($f['usuario'])->getJson(rutaRubrica($f))->assertNotFound();

    ponerRubrica($f['actividad'], TipoActividad::FORMATIVA);
    expect($fila()['con_rubrica'])->toBeFalse();
    $this->actingAs($f['usuario'])->getJson(rutaRubrica($f))->assertNotFound();
});

test('quien no está inscrito en el curso no lee la rúbrica, ni se lee la de una actividad oculta', function () {
    $f = fixtureRubricaEnVentana();
    if (!$f) {
        $this->markTestSkipped('No hay alumno con una actividad sin rúbrica.');
    }
    ponerRubrica($f['actividad'], TipoActividad::SUMATIVA);

    Actividad::whereKey($f['actividad']->id_actividad)->update(['visible' => false]);
    $this->actingAs($f['usuario'])->getJson(rutaRubrica($f))->assertForbidden();
    Actividad::whereKey($f['actividad']->id_actividad)->update(['visible' => true]);

    DB::table('curso.inscripcion_curso')
        ->where('id_curso', $f['curso']->id_curso)
        ->where('id_estudiante', $f['estudiante']->id_estudiante)
        ->update(['estado_inscripcion' => 'RETIRADO']);
    $this->actingAs($f['usuario'])->getJson(rutaRubrica($f))->assertForbidden();
});
