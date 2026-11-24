<?php

/**
 * «Imprimir» del docente abre el programa como PDF: lo ve quien puede ver el
 * programa, y sale con las viñetas, los campos y las horas en su lugar.
 */

use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Usuario;
use App\Services\ProgramaService;
use App\Syllabus\SyllabusBloques;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/** Un curso con titular, y un docente sin relación con el curso. */
function cursoConTitular(): object
{
    $fila = DB::selectOne('
        SELECT c.id_curso, d.id_usuario AS titular
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        WHERE c.fecha_eliminacion IS NULL AND NOT c.es_plantilla
        ORDER BY c.id_curso LIMIT 1
    ');

    return $fila ?? test()->markTestSkipped('Sin un curso con titular.');
}

function comoUsuario(int $id): Usuario
{
    $usuario = Usuario::findOrFail($id);
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();
    test()->actingAs($usuario);

    return $usuario;
}

function programaDePrueba(object $f): Programa
{
    Programa::where('id_curso', $f->id_curso)->update(['es_actual' => false]);

    return ProgramaService::generateProgramaWithSyllabus(
        Curso::findOrFail($f->id_curso),
        comoUsuario($f->titular),
        [
            'tipo_syllabus' => 'COMPLETO',
            'estado' => 'APROBADO',
            'secciones' => ['I' => ['contenido' => [
                'nombre_asignatura' => 'Taller <script>alert(1)</script>',
                'codigo' => 'DM095',
                'creditos_sct' => 5,
                'horas' => ['catedra' => 0, 'taller' => 4, 'laboratorio' => 0],
                'categoria' => 'Obligatorio',
            ]]],
        ],
    );
}

test('el titular abre el programa como PDF en el navegador', function () {
    $f = cursoConTitular();
    programaDePrueba($f);

    $respuesta = $this->get("/docente/cursos/{$f->id_curso}/programa/pdf")->assertOk();

    expect($respuesta->headers->get('content-type'))->toBe('application/pdf')
        ->and($respuesta->headers->get('content-disposition'))->toStartWith('inline')->toContain('Programa_')
        ->and($respuesta->getContent())->toStartWith('%PDF');
});

test('un docente ajeno al curso no ve el PDF', function () {
    $f = cursoConTitular();
    programaDePrueba($f);
    $ajeno = DB::selectOne('
        SELECT d.id_usuario FROM usuario.docente d
        JOIN usuario.usuario_rol_asignacion ura ON ura.id_usuario = d.id_usuario
        JOIN usuario.rol r ON r.id_rol = ura.id_rol
        WHERE d.id_usuario <> ?
          AND ura.esta_activo AND NOT ura.fue_eliminado AND LOWER(r.nombre) LIKE \'docente%\'
          AND NOT EXISTS (
              SELECT 1 FROM curso.docente_componente dc
              JOIN curso.componente co ON co.id_componente = dc.id_componente
              WHERE dc.id_docente = d.id_docente AND co.id_curso = ?)
        LIMIT 1
    ', [$f->titular, $f->id_curso]);
    if (! $ajeno) {
        $this->markTestSkipped('Sin un docente ajeno al curso.');
    }
    comoUsuario($ajeno->id_usuario);

    $this->get("/docente/cursos/{$f->id_curso}/programa/pdf")->assertForbidden();
});

test('sin programa vigente responde 404 y sin sesión manda a ingresar', function () {
    $f = cursoConTitular();
    $this->get("/docente/cursos/{$f->id_curso}/programa/pdf")->assertRedirect();

    Programa::where('id_curso', $f->id_curso)->update(['es_actual' => false]);
    comoUsuario($f->titular);
    $this->get("/docente/cursos/{$f->id_curso}/programa/pdf")->assertNotFound();
});

test('los campos seguidos quedan juntos y las viñetas a mano son viñetas', function () {
    $bloques = SyllabusBloques::de(implode("\n", [
        'Código: DM095',
        'Taller: 4',
        '',
        'Unidad 1: Prototipo',
        '- Clase demostrativa: el docente muestra ejemplos.',
        '- Ejercicio guiado con un rótulo bastante más largo que el resto: se practica.',
        '1. Primer punto: texto corrido',
        'Resultados:',
        '  • Explorar el entorno',
        '    de trabajo',
        '  «Cita del ítem»',
    ]), 'VII');

    expect(array_column($bloques, 'tipo'))->toBe(['campos', 'subtitulo', 'lista', 'parrafo', 'subtitulo', 'lista'])
        ->and($bloques[0]['filas'])->toHaveCount(2)
        ->and($bloques[2]['items'])->toHaveCount(2)
        ->and($bloques[5]['items'][0])->toBe([
            'texto' => 'Explorar el entorno de trabajo',
            'anidado' => true,
            'cita' => '«Cita del ítem»',
        ]);
});

test('las secciones de prosa no se parten por los dos puntos', function () {
    expect(SyllabusBloques::de("Propósito: formar.\n\nSegundo párrafo.", 'II'))->toBe([
        ['tipo' => 'parrafo', 'texto' => 'Propósito: formar.'],
        ['tipo' => 'parrafo', 'texto' => 'Segundo párrafo.'],
    ])->and(SyllabusBloques::de('  ', 'I'))->toBe([]);
});
