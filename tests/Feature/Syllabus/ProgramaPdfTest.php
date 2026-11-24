<?php

/**
 * «Imprimir» abre el programa como PDF (docente, ayudante y administrador): lo
 * ve quien puede ver el programa, y sale con las viñetas y los campos en su lugar.
 */

use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\Authorization\PermissionCache;
use App\Services\ProgramaService;
use App\Support\Permissions;
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

test('el administrador abre el PDF desde su panel', function () {
    $f = cursoConTitular();
    programaDePrueba($f);
    $admin = Usuario::where('username', 'superadmin')->first() ?? $this->markTestSkipped('Sin superadmin.');
    comoUsuario($admin->id_usuario);

    $respuesta = $this->get("/admin/cursos/{$f->id_curso}/programa/pdf")->assertOk();

    expect($respuesta->headers->get('content-type'))->toBe('application/pdf');
});

test('el ayudante abre el PDF de su curso sólo con el permiso de ver programas', function () {
    $f = cursoConTitular();
    programaDePrueba($f);
    $idContexto = DB::table('curso.curso')->where('id_curso', $f->id_curso)->value('id_contexto');
    $idRol = DB::table('usuario.rol')->whereRaw("LOWER(nombre) = 'ayudante'")->value('id_rol');
    $idUsuario = DB::selectOne('
        SELECT u.id_usuario FROM usuario.usuario u
        WHERE NOT EXISTS (SELECT 1 FROM usuario.usuario_rol_asignacion ura WHERE ura.id_usuario = u.id_usuario)
        ORDER BY u.id_usuario LIMIT 1
    ')?->id_usuario
        ?? DB::selectOne('SELECT e.id_usuario FROM usuario.estudiante e ORDER BY e.id_usuario LIMIT 1')?->id_usuario;
    if (! $idContexto || ! $idRol || ! $idUsuario) {
        $this->markTestSkipped('Sin contexto, rol o usuario para un ayudante.');
    }
    UsuarioRolAsignacion::create([
        'id_usuario' => $idUsuario,
        'id_rol' => $idRol,
        'id_contexto' => $idContexto,
        'asignado_por' => $f->titular,
        'fecha_inicio_planificada' => now()->subMinute(),
        'fecha_fin_planificada' => now()->addYear(),
        'esta_activo' => true,
        'fue_eliminado' => false,
        'creado_por' => $f->titular,
    ]);
    comoUsuario($idUsuario);
    $url = "/ayudante/cursos/{$f->id_curso}/programa/pdf";

    // El rol por sí solo no trae el permiso: se concede sobre el curso.
    $this->get($url)->assertRedirect(route('ayudante.cursos.index'));

    DB::table('usuario.usuario_permiso_especial')->insert([
        'id_usuario' => $idUsuario,
        'id_permiso' => DB::table('usuario.permiso')->where('slug', Permissions::CURSOS_PROGRAMAS_VER_TODOS->value)->value('id_permiso'),
        'id_contexto' => $idContexto,
        'esta_permitido' => true,
        'puede_delegar' => false,
        'esta_activo' => true,
        'fue_borrado' => false,
        'fecha_inicio_planificada' => now()->subMinute(),
        'fecha_fin_planificada' => now()->addYear(),
        'creado_por' => $f->titular,
    ]);
    // El insert directo no dispara el evento que vacía la caché de permisos.
    app(PermissionCache::class)->olvidarUsuario($idUsuario);
    comoUsuario($idUsuario);

    expect($this->get($url)->assertOk()->headers->get('content-type'))->toBe('application/pdf');

    $otro = DB::table('curso.curso')->where('id_curso', '<>', $f->id_curso)->whereNull('fecha_eliminacion')->value('id_curso');
    $this->get("/ayudante/cursos/{$otro}/programa/pdf")->assertRedirect(route('ayudante.cursos.index'));
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
