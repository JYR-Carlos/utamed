<?php

/**
 * Ficha del estudiante (revisión «Mi UTAmed»): la pestaña Estudiantes trae el
 * RUT y el correo de cada alumno, y la pestaña «Evaluación» de la ficha pide
 * GET …/componentes/{c}/estudiantes/{e}/evaluacion, con una fila por actividad
 * publicada de su componente (nota o calificación, estado y grupo).
 */

use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/** Estudiante en un grupo de una actividad visible de un componente que dicta un docente con rol activo. */
function fixtureFichaEvaluacion(): ?array
{
    $row = DB::selectOne("
        SELECT co.id_curso, d.id_usuario, co.id_componente, ig.id_estudiante
        FROM curso.docente_componente dc
        JOIN usuario.docente d ON d.id_docente = dc.id_docente
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        JOIN curso.componente co ON co.id_componente = dc.id_componente
        JOIN curso.inscripcion_componente ic ON ic.id_componente = co.id_componente
        JOIN agenda.actividad a ON a.id_componente = co.id_componente AND a.visible
        JOIN agenda.actividad_asignada_grupo aag ON aag.id_actividad = a.id_actividad
        JOIN agenda.integrante_grupo ig ON ig.id_actividad_asignada_grupo = aag.id_actividad_asignada_grupo
             AND ig.id_estudiante = ic.id_estudiante
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido', 'Docente')
        ORDER BY co.id_componente, ig.id_estudiante
        LIMIT 1
    ");

    return $row ? (array) $row : null;
}

function docenteFichaEvaluacion(int $idUsuario): Usuario
{
    $usuario = Usuario::findOrFail($idUsuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    return $usuario;
}

test('la evaluación del estudiante llega por actividad con tipo, nota o calificación, estado y grupo', function () {
    $f = fixtureFichaEvaluacion() ?? $this->markTestSkipped('Sin estudiante en grupos de actividades visibles.');

    $respuesta = $this->actingAs(docenteFichaEvaluacion($f['id_usuario']))
        ->getJson("/docente/cursos/{$f['id_curso']}/componentes/{$f['id_componente']}/estudiantes/{$f['id_estudiante']}/evaluacion")
        ->assertOk()
        ->assertJsonStructure([['id_actividad', 'nombre', 'es_sumativa', 'grupo', 'nota', 'calificacion', 'estado']]);

    foreach ($respuesta->json() as $fila) {
        expect($fila['estado'])->toBeIn(['no', 'pend', 'ok']);
        if ($fila['es_sumativa']) {
            expect($fila['calificacion'])->toBeNull();
        }
    }
});

test('un estudiante que no está en el componente devuelve 404', function () {
    $f = fixtureFichaEvaluacion() ?? $this->markTestSkipped('Sin estudiante en grupos de actividades visibles.');
    $ajeno = DB::table('usuario.estudiante')
        ->whereNotIn('id_estudiante', DB::table('curso.inscripcion_componente')->where('id_componente', $f['id_componente'])->select('id_estudiante'))
        ->value('id_estudiante') ?? $this->markTestSkipped('Todos los estudiantes están en el componente.');

    $this->actingAs(docenteFichaEvaluacion($f['id_usuario']))
        ->getJson("/docente/cursos/{$f['id_curso']}/componentes/{$f['id_componente']}/estudiantes/{$ajeno}/evaluacion")
        ->assertNotFound();
});

test('la pestaña Estudiantes trae el RUT, el correo y el id de usuario de cada alumno', function () {
    $f = fixtureFichaEvaluacion() ?? $this->markTestSkipped('Sin estudiante en grupos de actividades visibles.');

    $this->actingAs(docenteFichaEvaluacion($f['id_usuario']))
        ->get("/docente/cursos/{$f['id_curso']}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('docente/CursoDetalle')
            ->has('mis_estudiantes.0.estudiante.rut')
            ->has('mis_estudiantes.0.estudiante.email')
            ->has('mis_estudiantes.0.estudiante.id_usuario')
            ->missing('actividades')
        );
});
