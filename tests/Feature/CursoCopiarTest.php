<?php

/**
 * Feature test: CursoService::copiar() y el endpoint admin POST /admin/cursos/{curso}/copiar.
 *
 * Cubre dos correcciones:
 *  - El trigger tr_curso_pre_insert reemplaza curso.id_contexto por un contexto
 *    que él mismo crea: el rol «Docente Titular» debe quedar en ese contexto
 *    real (el del curso refrescado desde BD), no en el creado antes del INSERT.
 *  - agenda.actividad.id_unidad es NOT NULL y curso.unidad pertenece al curso:
 *    copiar() debe copiar las unidades y reasignar cada actividad a su copia.
 */

use App\Models\Curso\Curso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\CursoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/**
 * Curso sembrado con docente titular (usuario activo), al menos una unidad y
 * al menos una actividad en alguno de sus componentes.
 */
function fixtureCursoParaCopiar(): ?array
{
    $row = DB::selectOne("
        SELECT c.id_curso, d.id_usuario
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN usuario.usuario u ON u.id_usuario = d.id_usuario
        WHERE u.esta_activo
          AND EXISTS (SELECT 1 FROM curso.unidad un WHERE un.id_curso = c.id_curso)
          AND EXISTS (
              SELECT 1 FROM agenda.actividad a
              JOIN curso.componente co ON co.id_componente = a.id_componente
              WHERE co.id_curso = c.id_curso
          )
        ORDER BY c.id_curso
        LIMIT 1
    ");

    return $row ? (array) $row : null;
}

function codCursoLibreParaCopia(): int
{
    return (int) DB::selectOne('SELECT COALESCE(MAX(cod_curso), 0) + 1000 AS cod FROM curso.curso')->cod;
}

/**
 * Usuario con rol SuperAdmin en el contexto global (mismo patrón que
 * CursoSincronizarIntranetHttpTest; fecha_inicio_planificada en el pasado
 * porque NOW() de Postgres queda congelado al iniciar la transacción).
 */
function adminParaCopiarCurso(): Usuario
{
    $admin = Usuario::firstOrCreate(
        ['rut' => '77777777-7'],
        [
            'username'    => 'admin_copiar_curso_test',
            'passhash'    => bcrypt('password'),
            'nombre1'     => 'Admin',
            'apellido1'   => 'Copiar Curso Test',
            'esta_activo' => true,
        ]
    );
    // Con fecha_cambio_passhash en NULL el middleware redirige a cambiar la clave.
    $admin->fecha_cambio_passhash = now();
    $admin->save();

    $rolSuperAdmin = Rol::firstOrCreate(['nombre' => 'SuperAdmin'], ['creado_por' => $admin->id_usuario]);
    UsuarioRolAsignacion::firstOrCreate(
        ['id_usuario' => $admin->id_usuario, 'id_rol' => $rolSuperAdmin->id_rol, 'id_contexto' => 1],
        [
            'asignado_por'             => $admin->id_usuario,
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada'    => now()->addYears(100),
            'esta_activo'              => true,
            'fue_eliminado'            => false,
            'creado_por'               => $admin->id_usuario,
        ]
    );

    return $admin;
}

function cantidadActividadesDeCurso(int $idCurso): int
{
    return (int) DB::selectOne('
        SELECT COUNT(*) AS n
        FROM agenda.actividad a
        JOIN curso.componente co ON co.id_componente = a.id_componente
        WHERE co.id_curso = ?
    ', [$idCurso])->n;
}

beforeEach(function () {
    $this->fixture = fixtureCursoParaCopiar();
    if (!$this->fixture) {
        $this->markTestSkipped('No se encontró un curso sembrado con titular, unidades y actividades.');
    }
    $this->padre = Curso::findOrFail($this->fixture['id_curso']);
    $this->admin = adminParaCopiarCurso();
    Auth::login($this->admin);
});

function copiarCursoDeFixture(Curso $padre): Curso
{
    return app(CursoService::class)->copiar($padre, [
        'cod_curso'    => codCursoLibreParaCopia(),
        'fecha_inicio' => now()->toDateString(),
    ]);
}

test('copiar un curso con titular, unidades y actividades no lanza excepción', function () {
    $nuevo = copiarCursoDeFixture($this->padre);

    expect($nuevo->id_curso)->not->toBe($this->padre->id_curso)
        ->and((int) $nuevo->id_curso_padre)->toBe((int) $this->padre->id_curso);
});

test('el curso copiado tiene las mismas unidades que el padre, propias del curso nuevo', function () {
    $nuevo = copiarCursoDeFixture($this->padre);

    $unidadesPadre = DB::table('curso.unidad')
        ->where('id_curso', $this->padre->id_curso)
        ->orderBy('num_unidad')->orderBy('nombre')
        ->get(['num_unidad', 'nombre'])
        ->map(fn ($u) => [(int) $u->num_unidad, $u->nombre])
        ->all();

    $unidadesNuevo = DB::table('curso.unidad')
        ->where('id_curso', $nuevo->id_curso)
        ->orderBy('num_unidad')->orderBy('nombre')
        ->get(['num_unidad', 'nombre'])
        ->map(fn ($u) => [(int) $u->num_unidad, $u->nombre])
        ->all();

    expect($unidadesNuevo)->not->toBeEmpty()
        ->and($unidadesNuevo)->toBe($unidadesPadre);
});

test('todas las actividades copiadas apuntan a unidades del curso nuevo', function () {
    $nuevo = copiarCursoDeFixture($this->padre);

    $actividades = DB::select('
        SELECT a.id_actividad, a.id_unidad, un.id_curso AS id_curso_unidad
        FROM agenda.actividad a
        JOIN curso.componente co ON co.id_componente = a.id_componente
        LEFT JOIN curso.unidad un ON un.id_unidad = a.id_unidad
        WHERE co.id_curso = ?
    ', [$nuevo->id_curso]);

    expect(count($actividades))->toBe(cantidadActividadesDeCurso($this->padre->id_curso));

    foreach ($actividades as $act) {
        expect($act->id_unidad)->not->toBeNull()
            ->and((int) $act->id_curso_unidad)->toBe((int) $nuevo->id_curso);
    }
});

test('el titular recibe el rol Docente Titular en el contexto real del curso nuevo', function () {
    $nuevo = copiarCursoDeFixture($this->padre);

    // El trigger reemplaza id_contexto: el valor válido es el de la BD.
    $idContextoReal = (int) DB::table('curso.curso')
        ->where('id_curso', $nuevo->id_curso)
        ->value('id_contexto');

    expect((int) $nuevo->fresh()->id_contexto)->toBe($idContextoReal);

    $existe = DB::table('usuario.usuario_rol_asignacion as ura')
        ->join('usuario.rol as r', 'r.id_rol', '=', 'ura.id_rol')
        ->where('r.nombre', 'Docente Titular')
        ->where('ura.id_usuario', $this->fixture['id_usuario'])
        ->where('ura.id_contexto', $idContextoReal)
        ->where('ura.esta_activo', true)
        ->where('ura.fue_eliminado', false)
        ->exists();

    expect($existe)->toBeTrue();
});

test('el endpoint admin de copia responde sin errores y crea el curso', function () {
    $codCurso = codCursoLibreParaCopia();

    $response = $this->actingAs($this->admin)
        ->post("/admin/cursos/{$this->padre->id_curso}/copiar", [
            'cod_curso'    => $codCurso,
            'fecha_inicio' => now()->toDateString(),
        ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.cursos.index'));

    $nuevo = Curso::where('cod_curso', $codCurso)->first();
    expect($nuevo)->not->toBeNull()
        ->and((int) $nuevo->id_curso_padre)->toBe((int) $this->padre->id_curso)
        ->and(cantidadActividadesDeCurso($nuevo->id_curso))
        ->toBe(cantidadActividadesDeCurso($this->padre->id_curso));
});
