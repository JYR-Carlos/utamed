<?php

/**
 * El syllabus muestra el código de la asignatura (DM095), nunca el código
 * interno del curso (11000). Los syllabus antiguos guardaron el del curso en la
 * sección I y los aprobados no se editan: se corrige al leer.
 */

use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Services\ProgramaService;
use App\Services\SyllabusWizardPresenter;
use App\Traits\ParsesSyllabus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

const CODIGO_CURSO_VIEJO = '987654';

/** Texto de la sección I tal como lo lee el documento. */
function textoSeccionI(array $data): string
{
    $lector = new class
    {
        use ParsesSyllabus;

        public function leer(array $data): array
        {
            return $this->parseSecciones($data);
        }
    };

    return collect($lector->leer($data))->firstWhere('numeral_romano', 'I')['contenidos'][0]['texto_contenido'];
}

function seccionIConCodigo(string $codigo): array
{
    return ['I' => ['contenido' => [
        'nombre_asignatura' => 'Taller',
        'codigo' => $codigo,
        'creditos_sct' => 5,
        'horas' => ['catedra' => 0, 'taller' => 4, 'laboratorio' => 0],
        'categoria' => 'Obligatorio',
    ]]];
}

/** Curso con un estudiante inscrito y su código de asignatura. */
function cursoConInscrito(): object
{
    return DB::selectOne("
        SELECT ic.id_curso, e.id_usuario, a.cod_asignatura
        FROM curso.inscripcion_curso ic
        JOIN usuario.estudiante e ON e.id_estudiante = ic.id_estudiante
        JOIN curso.curso c ON c.id_curso = ic.id_curso
        JOIN administrativo.asignacion_plan ap ON ap.id_asignacion_plan = c.id_asignacion_plan
        JOIN administrativo.asignatura a ON a.id_asignatura = ap.id_asignatura
        WHERE ic.estado_inscripcion = 'INSCRITO' AND c.fecha_eliminacion IS NULL
        ORDER BY ic.id_curso LIMIT 1
    ") ?? test()->markTestSkipped('Sin un estudiante inscrito.');
}

function entrarComo(int $idUsuario): Usuario
{
    $usuario = Usuario::findOrFail($idUsuario);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();
    test()->actingAs($usuario);

    return $usuario;
}

test('la sección I muestra el código de la asignatura aunque guarde el del curso', function () {
    $texto = textoSeccionI([
        'metadata' => ['asignatura' => ['codigo' => 'DM095', 'horas' => ['autonomas' => 6]]],
        'secciones' => seccionIConCodigo(CODIGO_CURSO_VIEJO),
    ]);

    expect($texto)->toContain('Código: DM095')
        ->not->toContain(CODIGO_CURSO_VIEJO)
        ->toContain('Horas dedicación: 6 horas semanales');
});

test('las horas presenciales van arriba y su desglose abajo', function () {
    expect(textoSeccionI(['secciones' => seccionIConCodigo('DM069')]))
        ->toContain("Horas presenciales: 4 horas semanales
Cátedra: 0
Taller: 4
Laboratorio: 0")
        ->toContain('Horas dedicación: sin registrar en la asignatura');
});

test('las horas de dedicación salen de la sección I, como total del semestre, si la asignatura no las trae', function () {
    $seccion = seccionIConCodigo('DM095');
    $seccion['I']['contenido']['horas']['autonomas'] = 104;

    expect(textoSeccionI([
        'metadata' => ['asignatura' => ['horas' => ['autonomas' => 0]]],
        'secciones' => $seccion,
    ]))->toContain('Horas dedicación: 104 horas semestrales de trabajo autónomo');
});

test('sin metadata de la asignatura se conserva el código de la sección', function () {
    expect(textoSeccionI(['secciones' => seccionIConCodigo('DM069')]))->toContain('Código: DM069');
});

test('el alumno ve el código de la asignatura en un syllabus aprobado que guardó el del curso', function () {
    $f = cursoConInscrito();
    $curso = Curso::findOrFail($f->id_curso);
    ProgramaService::generateProgramaWithSyllabus($curso, entrarComo($f->id_usuario), [
        'tipo_syllabus' => 'COMPLETO',
        'estado' => 'APROBADO',
        'secciones' => seccionIConCodigo(CODIGO_CURSO_VIEJO),
    ]);

    $this->get("/estudiante/cursos/{$f->id_curso}/programa")
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($f) {
            $page->component('student/Courses/Syllabus')
                ->where('curso.cod_asignatura', $f->cod_asignatura)
                ->missing('curso.cod_curso');

            $texto = collect($page->toArray()['props']['secciones'])
                ->firstWhere('numeral_romano', 'I')['contenidos'][0]['texto_contenido'];
            expect($texto)->toContain("Código: {$f->cod_asignatura}")->not->toContain(CODIGO_CURSO_VIEJO);
        });
});

test('el asistente recibe el código de la asignatura y no el del curso', function () {
    $f = cursoConInscrito();
    $props = SyllabusWizardPresenter::build(Curso::findOrFail($f->id_curso), entrarComo($f->id_usuario), null);

    expect($props['curso']['cod_asignatura'])->toBe($f->cod_asignatura)
        ->and($props['curso'])->not->toHaveKey('cod_curso');
});

test('el listado del admin muestra y busca por el código de la asignatura', function () {
    $f = cursoConInscrito();
    $admin = Usuario::where('username', 'superadmin')->first() ?? $this->markTestSkipped('Sin superadmin.');
    entrarComo($admin->id_usuario);

    $this->get('/admin/syllabus?per_page=200&search=' . urlencode($f->cod_asignatura))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($f) {
            $page->component('admin/Syllabus');
            $fila = collect($page->toArray()['props']['cursos'])->firstWhere('id_curso', $f->id_curso);

            expect($fila)->not->toBeNull()
                ->and($fila['cod_asignatura'])->toBe($f->cod_asignatura)
                ->and($fila)->not->toHaveKey('cod_curso');
        });
});
