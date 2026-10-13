<?php

/**
 * Pantalla de evaluación del docente (docente/Activities/Index): el enunciado
 * se muestra junto a la rúbrica en «Sobre esta actividad», así que la página
 * recibe el archivo y las reglas que EnunciadoModal valida en el cliente. El
 * curso llega rotulado con Curso::etiqueta() para el breadcrumb.
 */

use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/** Actividad de un curso cuyo docente titular tiene su rol activo. */
function fixtureEvaluacionEnunciado(): ?array
{
    $row = DB::selectOne("
        SELECT c.id_curso, d.id_usuario, a.id_actividad
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        JOIN agenda.actividad a ON a.id_componente IN (SELECT id_componente FROM curso.componente WHERE id_curso = c.id_curso)
        WHERE ura.esta_activo AND NOT ura.fue_eliminado
          AND r.nombre IN ('Docente Titular', 'Docente Titular Restringido')
        ORDER BY a.id_actividad
        LIMIT 1
    ");

    return $row ? (array) $row : null;
}

function docenteEvaluacionEnunciado(array $f): Usuario
{
    $usuario = Usuario::findOrFail($f['id_usuario']);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    return $usuario;
}

test('la evaluación recibe el enunciado adjunto y las reglas para reemplazarlo', function () {
    $f = fixtureEvaluacionEnunciado();
    if (!$f) {
        $this->markTestSkipped('No se encontró un curso con titular activo y actividades.');
    }

    $uuid = (string) Str::uuid7();
    Archivo::create([
        'uuid_archivo' => $uuid,
        'ruta_fisica' => "archivos/test/enunciados/{$uuid}.pdf",
        'nombre_original' => 'enunciado_tarea.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'peso_bytes' => 2048,
        'pendiente_de_borrado' => false,
    ]);
    Actividad::whereKey($f['id_actividad'])->update(['uuid_archivo_enunciado' => $uuid]);

    $etiqueta = Curso::findOrFail($f['id_curso'])->etiqueta();

    $this->actingAs(docenteEvaluacionEnunciado($f))
        ->get("/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/evaluacion")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('docente/Activities/Index')
            ->where('curso.etiqueta', $etiqueta)
            ->where('actividad.archivo_enunciado.nombre_original', 'enunciado_tarea.pdf')
            ->where('actividad.archivo_enunciado.peso_bytes', 2048)
            ->has('reglas_enunciado.extensiones_pdf')
            ->has('reglas_enunciado.max_mb_pdf')
        );
});

test('sin enunciado la evaluación recibe archivo_enunciado en null', function () {
    $f = fixtureEvaluacionEnunciado();
    if (!$f) {
        $this->markTestSkipped('No se encontró un curso con titular activo y actividades.');
    }

    Actividad::whereKey($f['id_actividad'])->update(['uuid_archivo_enunciado' => null]);

    $this->actingAs(docenteEvaluacionEnunciado($f))
        ->get("/docente/cursos/{$f['id_curso']}/actividades/{$f['id_actividad']}/evaluacion")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('actividad.archivo_enunciado', null)
            ->has('reglas_enunciado')
        );
});
