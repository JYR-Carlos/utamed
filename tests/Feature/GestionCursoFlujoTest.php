<?php

/**
 * Flujo de creación y gestión de cursos (arreglos del 2026-10-14):
 *
 *  - malla: quitar una asignatura de un plan y volver a asignarla reactiva la
 *    misma fila (antes chocaba con uq_plan_asignatura por el soft delete);
 *  - wizard: el código interno del curso se asigna solo;
 *  - colegiado = dos o más docentes en un mismo componente, no un docente
 *    distinto por componente;
 *  - oferta de la Intranet para autocompletar el wizard;
 *  - inscripción automática con un INS_ID que el alumno ya usó en otro curso.
 *
 * `OracleDataService` se reemplaza por un Mockery double, como en
 * CursoSincronizarIntranetHttpTest.
 */

use App\DTOs\External\ComponenteCursoData;
use App\DTOs\External\InscripcionData;
use App\Enums\External\TipoAsignatura;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\IntranetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

function gcfUsuario(string $rut, string $username): Usuario
{
    $usuario = Usuario::firstOrCreate(
        ['rut' => $rut],
        [
            'username'    => $username,
            'passhash'    => bcrypt('password'),
            'nombre1'     => 'GCF',
            'apellido1'   => $username,
            'esta_activo' => true,
        ]
    );
    // Sin fecha de cambio de clave, ForcePasswordChange responde 423 a todo.
    $usuario->forceFill(['fecha_cambio_passhash' => now()])->save();

    return $usuario;
}

beforeEach(function () {
    $facultad = Facultad::firstOrCreate(['nombre' => 'GCF Test Facultad'], ['id_contexto' => 1]);
    $departamento = Departamento::firstOrCreate(
        ['nombre' => 'GCF Test Departamento'],
        ['id_facultad' => $facultad->id_facultad, 'id_contexto' => 1]
    );
    $this->carrera = Carrera::firstOrCreate(
        ['nombre' => 'GCF Test Carrera'],
        ['id_departamento' => $departamento->id_departamento, 'id_contexto' => 1]
    );
    $this->plan = Plan::firstOrCreate(
        ['id_carrera' => $this->carrera->id_carrera, 'agno_plan' => 2026, 'version_plan' => 1],
        ['id_contexto' => 1]
    );
    $this->asignatura = Asignatura::firstOrCreate(
        ['cod_asignatura' => 'GCF-101'],
        [
            'nombre' => 'GCF Test Asignatura',
            'creditos_sct' => 4,
            'horas_catedra' => 3,
            'horas_taller' => 2,
            'horas_laboratorio' => 0,
            'horas_dirigidas' => 0,
            'horas_autonomas' => 0,
        ]
    );
    $this->asignacionPlan = AsignacionPlan::firstOrCreate(
        ['id_plan' => $this->plan->id_plan, 'id_asignatura' => $this->asignatura->id_asignatura],
        ['agno_planificado' => 1, 'semestre_planificado' => 1, 'id_contexto' => 1]
    );

    $this->catedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $this->taller = TipoComponente::firstOrCreate(['tipo' => 'Taller']);

    $this->docenteA = Docente::firstOrCreate(['id_usuario' => gcfUsuario('55555555-5', 'gcf_docente_a')->id_usuario]);
    $this->docenteB = Docente::firstOrCreate(['id_usuario' => gcfUsuario('44444444-4', 'gcf_docente_b')->id_usuario]);

    $this->admin = gcfUsuario('33333333-3', 'gcf_admin');
    $rolSuperAdmin = Rol::firstOrCreate(['nombre' => 'SuperAdmin'], ['creado_por' => $this->admin->id_usuario]);
    UsuarioRolAsignacion::firstOrCreate(
        ['id_usuario' => $this->admin->id_usuario, 'id_rol' => $rolSuperAdmin->id_rol, 'id_contexto' => 1],
        [
            'asignado_por' => $this->admin->id_usuario,
            // now()->subMinute(): NOW() de Postgres queda congelado al iniciar la transacción.
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada' => now()->addYears(100),
            'esta_activo' => true,
            'fue_eliminado' => false,
            'creado_por' => $this->admin->id_usuario,
        ]
    );

    $this->actingAs($this->admin);
});

/** Payload mínimo que manda el wizard (sin cod_curso: lo asigna el sistema). */
function gcfPayloadCurso($test, array $docentesPorComponente, array $tipos): array
{
    return [
        'id_asignatura'                => $test->asignatura->id_asignatura,
        'id_plan'                      => $test->plan->id_plan,
        'agno_real'                    => 2031,
        'semestre_real'                => 1,
        'indice_grupo'                 => 1,
        'fecha_inicio'                 => '2031-03-09',
        'id_docente_sugerido'          => $test->docenteA->id_docente,
        'id_tipo_componente_principal' => $tipos[0],
        'tipos_componente_ids'         => $tipos,
        'docentes_por_componente'      => $docentesPorComponente,
    ];
}

function gcfUltimoCurso($test): Curso
{
    return Curso::where('id_asignacion_plan', $test->asignacionPlan->id_asignacion_plan)
        ->where('agno_real', 2031)
        ->latest('id_curso')
        ->firstOrFail();
}

describe('malla: quitar y volver a asignar una asignatura', function () {

    test('reactiva la misma asignación en vez de chocar con uq_plan_asignatura', function () {
        $otra = Asignatura::create([
            'cod_asignatura' => 'GCF-' . random_int(200, 999),
            'nombre' => 'GCF Asignatura reasignable',
            'creditos_sct' => 3,
            'horas_catedra' => 2,
            'horas_taller' => 0,
            'horas_laboratorio' => 0,
            'horas_dirigidas' => 0,
            'horas_autonomas' => 0,
        ]);
        $url = "/admin/planes/{$this->plan->id_plan}/asignaturas";

        $this->post($url, ['id_asignatura' => $otra->id_asignatura, 'agno_planificado' => 1, 'semestre_planificado' => 1])
            ->assertSessionHas('success');
        $original = AsignacionPlan::where('id_plan', $this->plan->id_plan)->where('id_asignatura', $otra->id_asignatura)->firstOrFail();

        $this->delete("{$url}/{$otra->id_asignatura}")->assertSessionHas('success');
        expect(AsignacionPlan::where('id_asignacion_plan', $original->id_asignacion_plan)->exists())->toBeFalse();

        $this->post($url, ['id_asignatura' => $otra->id_asignatura, 'agno_planificado' => 5, 'semestre_planificado' => 2])
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        $filas = AsignacionPlan::withTrashed()->where('id_plan', $this->plan->id_plan)->where('id_asignatura', $otra->id_asignatura)->get();
        expect($filas)->toHaveCount(1);
        expect($filas->first()->id_asignacion_plan)->toBe($original->id_asignacion_plan);
        expect($filas->first()->trashed())->toBeFalse();
        expect((int) $filas->first()->agno_planificado)->toBe(5);
        expect((int) $filas->first()->semestre_planificado)->toBe(2);
    });
});

describe('wizard: crear curso', function () {

    test('sin cod_curso asigna el siguiente código interno', function () {
        $maximo = (int) DB::table('curso.curso')->max('cod_curso');

        $this->post('/admin/cursos', gcfPayloadCurso($this, [
            $this->catedra->id_tipo_componente => [$this->docenteA->id_docente],
        ], [$this->catedra->id_tipo_componente]))->assertSessionHasNoErrors();

        expect((int) gcfUltimoCurso($this)->cod_curso)->toBe($maximo + 1);
    });

    test('dos docentes en un mismo componente lo hacen colegiado y el segundo recibe su rol', function () {
        $this->post('/admin/cursos', gcfPayloadCurso($this, [
            $this->catedra->id_tipo_componente => [$this->docenteA->id_docente, $this->docenteB->id_docente],
        ], [$this->catedra->id_tipo_componente]))->assertSessionHasNoErrors();

        $curso = gcfUltimoCurso($this);
        expect($curso->es_colegiado)->toBeTrue();

        $componente = Componente::where('id_curso', $curso->id_curso)->firstOrFail();
        $asignados = $componente->docenteComponentes()->orderBy('id_docente_componente')->get();
        expect($asignados->pluck('id_docente')->all())->toBe([$this->docenteA->id_docente, $this->docenteB->id_docente]);
        expect($asignados->pluck('es_titular')->all())->toBe([true, false]);

        $rolColegiado = Rol::where('nombre', 'Docente Componente Colegiado')->first();
        if ($rolColegiado) {
            expect(UsuarioRolAsignacion::where('id_usuario', $this->docenteB->id_usuario)
                ->where('id_contexto', $curso->id_contexto)
                ->where('id_rol', $rolColegiado->id_rol)
                ->where('esta_activo', true)
                ->exists())->toBeTrue();
        }
    });

    test('un docente distinto en cada componente NO es colegiado', function () {
        $this->post('/admin/cursos', gcfPayloadCurso($this, [
            $this->catedra->id_tipo_componente => [$this->docenteA->id_docente],
            $this->taller->id_tipo_componente  => [$this->docenteB->id_docente],
        ], [$this->catedra->id_tipo_componente, $this->taller->id_tipo_componente]))->assertSessionHasNoErrors();

        expect(gcfUltimoCurso($this)->es_colegiado)->toBeFalse();
    });

    test('sumar un segundo docente a un componente desde la gestión del curso lo vuelve colegiado', function () {
        $this->post('/admin/cursos', gcfPayloadCurso($this, [
            $this->catedra->id_tipo_componente => [$this->docenteA->id_docente],
        ], [$this->catedra->id_tipo_componente]))->assertSessionHasNoErrors();
        $curso = gcfUltimoCurso($this);
        $componente = Componente::where('id_curso', $curso->id_curso)->firstOrFail();
        expect($curso->es_colegiado)->toBeFalse();

        $this->postJson("/admin/cursos/{$curso->id_curso}/componentes/{$componente->id_componente}/docentes", [
            'id_docente' => $this->docenteB->id_docente,
        ])->assertOk();

        expect($curso->fresh()->es_colegiado)->toBeTrue();
    });
});

describe('GET /admin/cursos/oferta-intranet', function () {

    test('agrupa por grupo de la Intranet con sus componentes y CUR_CODIGO', function () {
        $mock = Mockery::mock();
        $mock->shouldReceive('traer_cur_codigos')->andReturn(collect([
            new ComponenteCursoData(cur_codigo: 203110000002, curso_tipo_asig: TipoAsignatura::Taller, curso_grupo_asig: 'A'),
            new ComponenteCursoData(cur_codigo: 203110000001, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
            new ComponenteCursoData(cur_codigo: 203110000003, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'B'),
        ]));
        app()->instance('OracleDataService', $mock);

        $this->getJson('/admin/cursos/oferta-intranet?' . http_build_query([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'id_plan'       => $this->plan->id_plan,
            'agno_real'     => 2031,
            'semestre_real' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('disponible', true)
            ->assertJsonPath('grupos.0.letra', 'A')
            ->assertJsonPath('grupos.0.indice_grupo', 1)
            // Cátedra antes que Taller, aunque la Intranet los devuelva al revés.
            ->assertJsonPath('grupos.0.componentes.0.cur_codigo', 203110000001)
            ->assertJsonPath('grupos.0.componentes.1.cur_codigo', 203110000002)
            ->assertJsonPath('grupos.1.letra', 'B')
            ->assertJsonPath('grupos.1.indice_grupo', 2);
    });

    test('si la Intranet no responde lo dice en vez de reportar «sin oferta»', function () {
        $mock = Mockery::mock();
        $mock->shouldReceive('traer_cur_codigos')->andThrow(new RuntimeException('sin driver oci8'));
        app()->instance('OracleDataService', $mock);

        $this->getJson('/admin/cursos/oferta-intranet?' . http_build_query([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'id_plan'       => $this->plan->id_plan,
            'agno_real'     => 2031,
            'semestre_real' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('disponible', false)
            ->assertJsonCount(0, 'grupos');
    });
});

describe('inscripción automática con un INS_ID ya usado', function () {

    test('inscribe al alumno aunque su INS_ID ya esté en otro curso, y lo cuenta como inscrito', function () {
        // El INS_ID es el folio del alumno en el periodo y se repite en todas sus
        // asignaturas; cod_inscripcion_uta es UNIQUE.
        $insId = 7300001;
        $alumRut = 23456789;

        $usuarioAlumno = Usuario::create([
            'rut' => "{$alumRut}-6",
            'username' => (string) $alumRut,
            'passhash' => bcrypt('x'),
            'nombre1' => 'GCF',
            'apellido1' => 'Alumno',
            'esta_activo' => true,
        ]);
        $estudiante = Estudiante::create(['id_usuario' => $usuarioAlumno->id_usuario, 'id_carrera' => $this->carrera->id_carrera]);

        $crearCurso = function (int $indice) {
            $curso = Curso::create([
                'cod_curso' => (int) DB::table('curso.curso')->max('cod_curso') + 1,
                'nombre' => "GCF curso {$indice}",
                'indice_grupo' => $indice,
                'fecha_inicio' => '2031-03-09',
                'fecha_fin' => '2031-07-09',
                'agno_real' => 2031,
                'semestre_real' => 1,
                'id_asignacion_plan' => $this->asignacionPlan->id_asignacion_plan,
                'id_contexto' => Contexto::create(['contexto_display' => 'GCF ' . uniqid()])->id_contexto,
                'id_docente_titular' => $this->docenteA->id_docente,
            ]);
            $curso->refresh();
            Componente::create([
                'id_curso' => $curso->id_curso,
                'id_tipo_componente' => $this->catedra->id_tipo_componente,
                'id_contexto' => Contexto::create(['contexto_display' => 'GCF comp ' . uniqid()])->id_contexto,
                'genera_acta' => true,
                'aprobacion_obligatoria' => false,
                'porcentaje_aprobacion' => 60,
                'porcentaje_asistencia_obligatoria' => 75,
            ]);

            return $curso;
        };

        $otroCurso = $crearCurso(1);
        InscripcionCurso::create([
            'id_curso' => $otroCurso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'cod_inscripcion_uta' => (string) $insId,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $curso = $crearCurso(2);

        $mock = Mockery::mock();
        $mock->shouldReceive('traer_cur_codigos')->andReturn(collect([
            new ComponenteCursoData(cur_codigo: 203110000009, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'B'),
        ]));
        $mock->shouldReceive('traer_ins_id')->andReturn(collect([
            new InscripcionData(ins_id: $insId, alum_rut: $alumRut),
        ]));
        app()->instance('OracleDataService', $mock);

        $resultado = app(IntranetService::class)->inscribirAutomaticamente($curso->fresh());

        expect($resultado->errores)->toBe([]);
        expect($resultado->inscritos_exitosamente)->toBe(1);
        expect($resultado->ya_inscritos)->toBe(0);

        $inscripcion = InscripcionCurso::where('id_curso', $curso->id_curso)->where('id_estudiante', $estudiante->id_estudiante)->first();
        expect($inscripcion)->not->toBeNull();
        expect($inscripcion->cod_inscripcion_uta)->toBeNull();

        $componente = Componente::where('id_curso', $curso->id_curso)->firstOrFail();
        $enComponente = InscripcionComponente::where('id_componente', $componente->id_componente)
            ->where('id_estudiante', $estudiante->id_estudiante)->first();
        expect((int) $enComponente->cod_inscripcion_curso_uta)->toBe($insId);
    });
});

describe('contextos: los crea la BD, no la aplicación', function () {

    /** Contextos sin tipo ni padre: la marca de los que creaba la aplicación. */
    function gcfContextosSinTipo(): int
    {
        return Contexto::whereNull('id_tipo_contexto')->whereNull('id_contexto_padre')->count();
    }

    function gcfAssertContextosDelCurso(Curso $curso): void
    {
        $ctxCurso = Contexto::findOrFail($curso->id_contexto);
        expect($ctxCurso->id_tipo_contexto)->not->toBeNull();
        expect($ctxCurso->id_contexto_padre)->not->toBeNull();

        foreach (Componente::where('id_curso', $curso->id_curso)->get() as $componente) {
            $ctxComponente = Contexto::findOrFail($componente->id_contexto);
            expect($ctxComponente->id_tipo_contexto)->not->toBeNull();
            expect($ctxComponente->id_contexto_padre)->toBe($curso->id_contexto);
        }
    }

    test('crear un curso con dos componentes no deja contextos huérfanos', function () {
        $antes = gcfContextosSinTipo();

        $this->post('/admin/cursos', gcfPayloadCurso($this, [
            $this->catedra->id_tipo_componente => [$this->docenteA->id_docente],
            $this->taller->id_tipo_componente  => [$this->docenteB->id_docente],
        ], [$this->catedra->id_tipo_componente, $this->taller->id_tipo_componente]))->assertSessionHasNoErrors();

        expect(gcfContextosSinTipo())->toBe($antes);
        gcfAssertContextosDelCurso(gcfUltimoCurso($this));
    });

    test('copiar un curso no deja contextos huérfanos', function () {
        $this->post('/admin/cursos', gcfPayloadCurso($this, [
            $this->catedra->id_tipo_componente => [$this->docenteA->id_docente],
        ], [$this->catedra->id_tipo_componente]))->assertSessionHasNoErrors();
        $original = gcfUltimoCurso($this);
        $antes = gcfContextosSinTipo();

        $copia = app(\App\Services\CursoService::class)->copiar($original, ['fecha_inicio' => '2031-08-03']);

        expect(gcfContextosSinTipo())->toBe($antes);
        expect($copia->id_contexto)->not->toBe($original->id_contexto);
        gcfAssertContextosDelCurso($copia);
    });

    test('la migración 17 borra los huérfanos sin uso y conserva los que tienen historial', function () {
        $sinUso = Contexto::create(['contexto_display' => 'Curso: GCF-huerfano-sin-uso']);
        $conHistorial = Contexto::create(['contexto_display' => 'Curso: GCF-huerfano-con-historial']);
        UsuarioRolAsignacion::create([
            'id_usuario' => $this->docenteA->id_usuario,
            'id_rol' => Rol::where('nombre', 'SuperAdmin')->value('id_rol'),
            'id_contexto' => $conHistorial->id_contexto,
            'asignado_por' => $this->admin->id_usuario,
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada' => now()->addYear(),
            'esta_activo' => false,
            'fue_eliminado' => true,
            'creado_por' => $this->admin->id_usuario,
        ]);
        $contextoReal = Contexto::findOrFail($this->carrera->fresh()->id_contexto);

        (require database_path('migrations/17_eliminar_contextos_huerfanos.php'))->up();

        expect(Contexto::find($sinUso->id_contexto))->toBeNull();
        expect(Contexto::find($conHistorial->id_contexto))->not->toBeNull();
        expect(Contexto::find($contextoReal->id_contexto))->not->toBeNull();
        expect(Contexto::find(1))->not->toBeNull();
    });
});

describe('contextos de facultad y carrera', function () {

    test('crear una facultad y una carrera desde el admin no deja contextos huérfanos', function () {
        $antes = gcfContextosSinTipo();

        $this->post('/admin/facultades', ['nombre' => 'GCF Facultad Contexto'])->assertSessionHasNoErrors();
        $facultad = Facultad::where('nombre', 'GCF Facultad Contexto')->firstOrFail();

        $departamento = Departamento::create(['nombre' => 'GCF Depto Contexto', 'id_facultad' => $facultad->id_facultad])->refresh();

        $this->post('/admin/carreras', [
            'nombre'          => 'GCF Carrera Contexto',
            'id_departamento' => $departamento->id_departamento,
            'id_facultad'     => $facultad->id_facultad,
        ])->assertSessionHasNoErrors();
        $carrera = Carrera::where('nombre', 'GCF Carrera Contexto')->firstOrFail();

        expect(gcfContextosSinTipo())->toBe($antes);
        expect(Contexto::findOrFail($facultad->id_contexto)->id_tipo_contexto)->not->toBeNull();
        expect(Contexto::findOrFail($carrera->id_contexto)->id_contexto_padre)->toBe($departamento->id_contexto);
    });

    test('editar una facultad la modifica en vez de crear otra', function () {
        $this->post('/admin/facultades', ['nombre' => 'GCF Facultad Original'])->assertSessionHasNoErrors();
        $facultad = Facultad::where('nombre', 'GCF Facultad Original')->firstOrFail();
        $total = Facultad::count();

        $this->put("/admin/facultades/{$facultad->id_facultad}", ['nombre' => 'GCF Facultad Renombrada'])
            ->assertSessionHas('success');

        expect(Facultad::count())->toBe($total);
        expect($facultad->fresh()->nombre)->toBe('GCF Facultad Renombrada');
    });
});

describe('gestionar curso: eliminar componente', function () {

    /** Curso con Cátedra (dos docentes) y Taller, y un alumno inscrito en ambos. */
    function gcfCursoConAlumno($test): array
    {
        $test->post('/admin/cursos', gcfPayloadCurso($test, [
            $test->catedra->id_tipo_componente => [$test->docenteA->id_docente, $test->docenteB->id_docente],
            $test->taller->id_tipo_componente  => [$test->docenteA->id_docente],
        ], [$test->catedra->id_tipo_componente, $test->taller->id_tipo_componente]))->assertSessionHasNoErrors();
        $curso = gcfUltimoCurso($test);

        $usuario = Usuario::create([
            'rut' => '24681357-4', 'username' => 'gcfalu2', 'passhash' => bcrypt('x'),
            'nombre1' => 'GCF', 'apellido1' => 'Alumna', 'esta_activo' => true,
        ]);
        $estudiante = Estudiante::create(['id_usuario' => $usuario->id_usuario, 'id_carrera' => $test->carrera->id_carrera]);
        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $catedra = Componente::where('id_curso', $curso->id_curso)
            ->where('id_tipo_componente', $test->catedra->id_tipo_componente)->firstOrFail();

        return [$curso, $catedra];
    }

    test('borra un componente con docentes y alumnos inscritos, y recalcula el colegiado', function () {
        [$curso, $catedra] = gcfCursoConAlumno($this);
        expect($curso->es_colegiado)->toBeTrue();
        expect($catedra->inscripcionComponentes()->count())->toBe(1);

        $this->deleteJson("/admin/cursos/{$curso->id_curso}/componentes/{$catedra->id_componente}")
            ->assertOk();

        expect(Componente::find($catedra->id_componente))->toBeNull();
        expect(DB::table('curso.docente_componente')->where('id_componente', $catedra->id_componente)->exists())->toBeFalse();
        expect($curso->fresh()->es_colegiado)->toBeFalse();
    });

    test('no borra un componente con actividades y explica por qué', function () {
        [$curso, $catedra] = gcfCursoConAlumno($this);
        $unidad = \App\Models\Curso\Unidad::create(['id_curso' => $curso->id_curso, 'num_unidad' => 1, 'nombre' => 'U1']);
        DB::table('agenda.actividad')->insert([
            'id_componente' => $catedra->id_componente, 'id_unidad' => $unidad->id_unidad,
            'nombre' => 'GCF actividad', 'ponderacion' => 20, 'tipo_actividad' => 'SUMATIVA',
            'tipo_entrega' => 'DIGITAL', 'visible' => true, 'es_grupal' => false, 'max_integrantes' => 1,
        ]);

        $this->deleteJson("/admin/cursos/{$curso->id_curso}/componentes/{$catedra->id_componente}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'No se puede eliminar el componente: tiene 1 actividad.');

        expect(Componente::find($catedra->id_componente))->not->toBeNull();
        expect($catedra->docenteComponentes()->count())->toBe(2);
    });
});
