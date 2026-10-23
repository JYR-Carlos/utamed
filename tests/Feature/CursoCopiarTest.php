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

use App\Enums\DB\EstadoRubrica;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Curso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\CursoService;
use App\Services\ProgramaService;
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
    $admin->fecha_verificacion_email = now();
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

test('el endpoint admin de copia responde sin errores y crea el curso con la letra en el nombre', function () {
    $response = $this->actingAs($this->admin)
        ->post("/admin/cursos/{$this->padre->id_curso}/copiar", [
            'fecha_inicio'  => now()->toDateString(),
            'agno_real'     => $this->padre->agno_real ?? 2026,
            'semestre_real' => $this->padre->semestre_real ?? 1,
        ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.cursos.index'));

    $nuevo = Curso::where('id_curso_padre', $this->padre->id_curso)->first();

    expect($nuevo)->not->toBeNull()
        ->and((int) $nuevo->id_curso_padre)->toBe((int) $this->padre->id_curso)
        ->and($nuevo->nombre)->toMatch('/Grupo [A-Z]+$/')
        ->and(cantidadActividadesDeCurso($nuevo->id_curso))
        ->toBe(cantidadActividadesDeCurso($this->padre->id_curso));
});

test('al copiar un curso en el mismo periodo calcula la siguiente letra consecutiva', function () {
    $agnoReal = $this->padre->agno_real ?? 2026;
    $semestreReal = $this->padre->semestre_real ?? 1;
    $indiceEsperado = app(CursoService::class)->nextIndiceGrupo($this->padre->id_asignacion_plan, $agnoReal, $semestreReal);
    $letraEsperada = \App\Support\LetraGrupo::fromIndice($indiceEsperado);

    $response = $this->actingAs($this->admin)
        ->post("/admin/cursos/{$this->padre->id_curso}/copiar", [
            'fecha_inicio'  => now()->toDateString(),
            'agno_real'     => $agnoReal,
            'semestre_real' => $semestreReal,
        ]);

    $response->assertSessionHasNoErrors();
    $nuevo = Curso::where('id_curso_padre', $this->padre->id_curso)->latest('id_curso')->first();

    expect($nuevo->indice_grupo)->toBe($indiceEsperado)
        ->and($nuevo->nombre)->toContain("Grupo {$letraEsperada}");
});

test('preview-copia retorna la proxima_letra y nuevo_nombre calculados', function () {
    $response = $this->actingAs($this->admin)
        ->getJson("/admin/cursos/{$this->padre->id_curso}/preview-copia");

    $response->assertOk()
        ->assertJsonStructure([
            'curso' => [
                'id_curso',
                'nombre',
                'proxima_letra',
                'nuevo_nombre',
                'asignatura_nombre',
            ],
            'componentes',
        ]);
});

test('al copiar un curso con nombre personalizado respeta el nombre enviado', function () {
    $nombreCustom = 'Curso Especial de Verano 2026';

    $response = $this->actingAs($this->admin)
        ->post("/admin/cursos/{$this->padre->id_curso}/copiar", [
            'nombre'        => $nombreCustom,
            'fecha_inicio'  => now()->toDateString(),
            'agno_real'     => $this->padre->agno_real ?? 2026,
            'semestre_real' => $this->padre->semestre_real ?? 1,
        ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.cursos.index'));

    $nuevo = Curso::where('id_curso_padre', $this->padre->id_curso)->first();

    expect($nuevo)->not->toBeNull()
        ->and($nuevo->nombre)->toBe($nombreCustom);
});

test('al copiar un curso omite componentes con 0 horas en el plan si intranet no las oferta para el grupo destino', function () {
    $tipoLab = \App\Models\Curso\TipoComponente::firstOrCreate(['tipo' => 'Laboratorio']);
    $asignatura = $this->padre->asignacionPlan->asignatura;
    $asignatura->forceFill(['horas_laboratorio' => 0])->save();

    \App\Models\Curso\Componente::create([
        'id_curso'                          => $this->padre->id_curso,
        'id_tipo_componente'                => $tipoLab->id_tipo_componente,
        'genera_acta'                       => false,
        'aprobacion_obligatoria'            => false,
        'porcentaje_aprobacion'             => 60.00,
        'porcentaje_asistencia_obligatoria' => 75.00,
    ]);

    $mockOracle = Mockery::mock();
    $mockOracle->shouldReceive('traer_cur_codigos')->andReturn(collect([
        new \App\DTOs\External\ComponenteCursoData(cur_codigo: 999901, curso_tipo_asig: \App\Enums\External\TipoAsignatura::Catedra, curso_grupo_asig: 'B'),
    ]));
    app()->instance('OracleDataService', $mockOracle);

    $nuevo = app(CursoService::class)->copiar($this->padre->fresh(), [
        'fecha_inicio' => now()->toDateString(),
    ]);

    $tiposNuevo = $nuevo->componentes()->pluck('id_tipo_componente')->toArray();
    expect($tiposNuevo)->not->toContain($tipoLab->id_tipo_componente);
});

test('al copiar un curso conserva componentes con 0 horas si intranet las oferta explicitamente para el grupo destino', function () {
    $tipoLab = \App\Models\Curso\TipoComponente::firstOrCreate(['tipo' => 'Laboratorio']);
    $asignatura = $this->padre->asignacionPlan->asignatura;
    $asignatura->forceFill(['horas_laboratorio' => 0])->save();

    \App\Models\Curso\Componente::create([
        'id_curso'                          => $this->padre->id_curso,
        'id_tipo_componente'                => $tipoLab->id_tipo_componente,
        'genera_acta'                       => false,
        'aprobacion_obligatoria'            => false,
        'porcentaje_aprobacion'             => 60.00,
        'porcentaje_asistencia_obligatoria' => 75.00,
    ]);

    $mockOracle = Mockery::mock();
    $mockOracle->shouldReceive('traer_cur_codigos')->andReturn(collect([
        new \App\DTOs\External\ComponenteCursoData(cur_codigo: 999901, curso_tipo_asig: \App\Enums\External\TipoAsignatura::Laboratorio, curso_grupo_asig: 'B'),
    ]));
    app()->instance('OracleDataService', $mockOracle);

    $nuevo = app(CursoService::class)->copiar($this->padre->fresh(), [
        'fecha_inicio' => now()->toDateString(),
    ]);

    $tiposNuevo = $nuevo->componentes()->pluck('id_tipo_componente')->toArray();
    expect($tiposNuevo)->toContain($tipoLab->id_tipo_componente);
});

test('al copiar un curso monocomponente conserva exclusivamente su unica componente valida sin agregar otras', function () {
    // Curso monocomponente (solo Cátedra)
    $tipoCatedra = \App\Models\Curso\TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $asignatura = $this->padre->asignacionPlan->asignatura;
    $asignatura->forceFill([
        'horas_catedra'     => 4,
        'horas_taller'      => 0,
        'horas_laboratorio' => 0,
    ])->save();

    $codCursoMono = codCursoLibreParaCopia();
    $padreMono = Curso::create([
        'cod_curso'                    => $codCursoMono,
        'nombre'                       => 'Curso Mono Grupo A',
        'indice_grupo'                 => 1,
        'agno_real'                    => 2026,
        'semestre_real'                => 1,
        'id_asignacion_plan'           => $this->padre->id_asignacion_plan,
        'id_docente_titular'           => $this->padre->id_docente_titular,
        'id_tipo_componente_principal' => $tipoCatedra->id_tipo_componente,
        'fecha_inicio'                 => now()->toDateString(),
        'fecha_fin'                    => now()->addMonths(5)->toDateString(),
    ]);

    \App\Models\Curso\Componente::create([
        'id_curso'                          => $padreMono->id_curso,
        'id_tipo_componente'                => $tipoCatedra->id_tipo_componente,
        'genera_acta'                       => true,
        'aprobacion_obligatoria'            => false,
        'porcentaje_aprobacion'             => 60.00,
        'porcentaje_asistencia_obligatoria' => 75.00,
    ]);

    $mockOracle = Mockery::mock();
    $mockOracle->shouldReceive('traer_cur_codigos')->andReturn(collect([
        new \App\DTOs\External\ComponenteCursoData(cur_codigo: 999905, curso_tipo_asig: \App\Enums\External\TipoAsignatura::Catedra, curso_grupo_asig: 'B'),
    ]));
    app()->instance('OracleDataService', $mockOracle);

    $nuevo = app(CursoService::class)->copiar($padreMono->fresh(), [
        'fecha_inicio' => now()->toDateString(),
    ]);

    expect($nuevo->componentes()->count())->toBe(1)
        ->and($nuevo->componentes()->first()->id_tipo_componente)->toBe($tipoCatedra->id_tipo_componente);
});

test('al copiar un curso clona el syllabus vigente y las rubricas analiticas con estado POSTULADA', function () {
    // Asegurar que el curso padre tenga programa
    if (!$this->padre->programas()->where('es_actual', true)->exists()) {
        ProgramaService::generateProgramaWithSyllabus($this->padre, $this->admin);
    }

    $programaPadre = $this->padre->programas()->where('es_actual', true)->first();
    $programaPadre->update([
        'estado' => 'APROBADO',
        'revisado_por' => $this->admin->id_usuario,
    ]);

    // Crear una actividad específica con rúbrica analítica en el curso padre
    $comp = $this->padre->componentes()->first();
    $unidad = $this->padre->unidades()->first();
    $actividad = Actividad::create([
        'nombre'         => 'Actividad Exclusiva con Rubrica Analitica',
        'fecha_limite'   => now()->addDays(3),
        'visible'        => true,
        'ponderacion'    => 25,
        'exigencia'      => 60,
        'tipo_actividad' => 'SUMATIVA',
        'tipo_entrega'   => 'ARCHIVO',
        'es_grupal'      => false,
        'es_plantilla'   => false,
        'id_componente'  => $comp->id_componente,
        'id_unidad'      => $unidad->id_unidad,
    ]);

    $rubricaPadre = Rubrica::create([
        'id_actividad'   => $actividad->id_actividad,
        'estado_rubrica' => EstadoRubrica::POSTULADA,
        'rubrica'        => [
            'criterios' => [
                ['titulo' => 'Comprensión clínica', 'ponderacion' => 50],
                ['titulo' => 'Razonamiento diagnóstico', 'ponderacion' => 50],
            ],
        ],
    ]);

    $nuevo = copiarCursoDeFixture($this->padre);

    // 1. Validar que se clonó la rúbrica analítica asociada
    $nuevaActividad = Actividad::whereHas('componente', fn($q) => $q->where('id_curso', $nuevo->id_curso))
        ->where('nombre', 'Actividad Exclusiva con Rubrica Analitica')
        ->first();

    expect($nuevaActividad)->not->toBeNull()
        ->and($nuevaActividad->fecha_limite)->toBeNull();

    $nuevaRubrica = Rubrica::where('id_actividad', $nuevaActividad->id_actividad)->first();
    expect($nuevaRubrica)->not->toBeNull()
        ->and($nuevaRubrica->id_rubrica)->not->toBe($rubricaPadre->id_rubrica)
        ->and($nuevaRubrica->estado_rubrica)->toBe(EstadoRubrica::POSTULADA)
        ->and($nuevaRubrica->rubrica['criterios'][0]['titulo'])->toBe('Comprensión clínica')
        ->and($nuevaRubrica->rubrica['criterios'][1]['ponderacion'])->toBe(50);

    // 2. Validar que el syllabus vigente fue clonado como BORRADOR
    $nuevoPrograma = $nuevo->programas()->where('es_actual', true)->first();
    expect($nuevoPrograma)->not->toBeNull()
        ->and($nuevoPrograma->id_curso)->toBe($nuevo->id_curso)
        ->and($nuevoPrograma->estado)->toBe('BORRADOR')
        ->and($nuevoPrograma->revisado_por)->toBeNull()
        ->and($nuevoPrograma->fecha_entrega)->toBeNull();
});

test('endpoint buscarFuente retorna cursos filtrados por cod_asignatura o nombre', function () {
    $ap = $this->padre->asignacionPlan;
    if (!$ap?->asignatura) {
        $this->padre->load('asignacionPlan.asignatura');
        $ap = $this->padre->asignacionPlan;
    }

    $codAsig = $ap->asignatura->cod_asignatura;

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.cursos.buscar-fuente', ['q' => substr($codAsig, 0, 3)]));

    $response->assertOk()
        ->assertJsonFragment([
            'id_curso'       => $this->padre->id_curso,
            'cod_asignatura' => $codAsig,
        ]);
});
