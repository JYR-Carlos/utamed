<?php

/**
 * Feature test: endpoints HTTP de CursoController para detección/sincronización
 * de componentes desde Intranet (wizard, curso individual y sincronización masiva).
 *
 * IntranetServiceSincronizacionComponentesTest ya cubre el servicio a nivel
 * unitario; este archivo cubre la capa que faltaba: rutas, autorización,
 * validación y forma de la respuesta JSON de CursoController.
 *
 * `OracleDataService` se reemplaza por un Mockery double vía el contenedor,
 * igual que en InscripcionAutomaticaTest — no requiere Oracle real.
 */

use App\DTOs\External\ComponenteCursoData;
use App\Enums\External\TipoAsignatura;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Curso;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function csihMockOracle(\Illuminate\Support\Collection $componentes): void
{
    $mock = Mockery::mock();
    $mock->shouldReceive('traer_cur_codigos')->andReturn($componentes);
    app()->instance('OracleDataService', $mock);
}

beforeEach(function () {
    $facultad = Facultad::firstOrCreate(['nombre' => 'CSIH Test Facultad'], ['id_contexto' => 1]);
    $departamento = Departamento::firstOrCreate(
        ['nombre' => 'CSIH Test Departamento'],
        ['id_facultad' => $facultad->id_facultad, 'id_contexto' => 1]
    );
    $this->carrera = Carrera::firstOrCreate(
        ['nombre' => 'CSIH Test Carrera'],
        ['id_departamento' => $departamento->id_departamento, 'id_contexto' => 1]
    );
    $this->plan = Plan::firstOrCreate(
        ['id_carrera' => $this->carrera->id_carrera, 'agno_plan' => 2026, 'version_plan' => 1],
        ['id_contexto' => 1]
    );
    $this->asignatura = Asignatura::firstOrCreate(
        ['cod_asignatura' => 'CSIH-101'],
        [
            'nombre' => 'CSIH Test Asignatura',
            'creditos_sct' => 4,
            'horas_catedra' => 3,
            'horas_taller' => 0,
            'horas_laboratorio' => 0,
            'horas_dirigidas' => 0,
            'horas_autonomas' => 0,
        ]
    );
    $this->asignacionPlan = AsignacionPlan::firstOrCreate(
        ['id_plan' => $this->plan->id_plan, 'id_asignatura' => $this->asignatura->id_asignatura],
        ['agno_planificado' => 1, 'semestre_planificado' => 1, 'id_contexto' => 1]
    );

    $this->tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $this->tipoLaboratorio = TipoComponente::firstOrCreate(['tipo' => 'Laboratorio']);

    $usuarioDocente = Usuario::firstOrCreate(
        ['rut' => '88888888-8'],
        [
            'username' => 'docente_csih_test',
            'passhash' => bcrypt('password'),
            'nombre1' => 'Docente',
            'apellido1' => 'CSIH Test',
            'esta_activo' => true,
        ]
    );
    $this->docente = Docente::firstOrCreate(['id_usuario' => $usuarioDocente->id_usuario]);

    // Usuario admin: mismo patrón que InscripcionAutomaticaTest para pasar
    // el middleware is_admin y las policies (rol SuperAdmin en el contexto global).
    $this->admin = Usuario::firstOrCreate(
        ['rut' => '77777777-7'],
        [
            'username' => 'admin_csih_test',
            'passhash' => bcrypt('password'),
            'nombre1' => 'Admin',
            'apellido1' => 'CSIH Test',
            'esta_activo' => true,
        ]
    );
    $this->admin->forceFill([
        'fecha_cambio_passhash' => now(),
        'fecha_verificacion_email' => now(),
    ])->saveQuietly();

    $rolSuperAdmin = Rol::firstOrCreate(['nombre' => 'SuperAdmin'], ['creado_por' => $this->admin->id_usuario]);
    UsuarioRolAsignacion::firstOrCreate(
        ['id_usuario' => $this->admin->id_usuario, 'id_rol' => $rolSuperAdmin->id_rol, 'id_contexto' => 1],
        [
            'asignado_por' => $this->admin->id_usuario,
            // now(), no now()->subMinute(): NOW() de Postgres queda congelado al
            // iniciar la transacción del test, y el now() de PHP sigue avanzando
            // mientras corre el resto del beforeEach — con now() a secas,
            // "fecha_inicio_planificada <= NOW()" queda del lado equivocado y
            // vw_permisos_usuario excluye la fila (visto empíricamente: ~5/6 corridas).
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada' => now()->addYears(100),
            'esta_activo' => true,
            'fue_eliminado' => false,
            'creado_por' => $this->admin->id_usuario,
        ]
    );

    // Sin fecha de cambio de clave, ForcePasswordChange responde 423 a todo.
    $this->admin->forceFill(['fecha_cambio_passhash' => now()])->save();

    $this->actingAs($this->admin);
});

afterEach(function () {
    Mockery::close();
});

function csihCrearCurso(int $codCurso, string $nombre, $asignacionPlan, $docente): Curso
{
    $contexto = Contexto::create(['contexto_display' => $nombre . ' ' . uniqid()]);

    return Curso::create([
        'cod_curso' => $codCurso,
        'nombre' => $nombre,
        'indice_grupo' => 1,
        'fecha_inicio' => now()->toDateString(),
        'fecha_fin' => now()->addMonths(4)->toDateString(),
        'agno_real' => 2026,
        'semestre_real' => 1,
        'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
        'id_contexto' => $contexto->id_contexto,
        'id_docente_titular' => $docente->id_docente,
    ]);
}

describe('GET /admin/cursos/preview-componentes (wizard, antes de crear el curso)', function () {

    test('retorna el origen INTRANET y las componentes detectadas sin crear nada en BD', function () {
        csihMockOracle(collect([
            new ComponenteCursoData(cur_codigo: 5001, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));

        $response = $this->getJson('/admin/cursos/preview-componentes?' . http_build_query([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'id_plan' => $this->plan->id_plan,
            'agno_real' => 2026,
            'semestre_real' => 1,
            'indice_grupo' => 1,
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('origen', 'INTRANET')
            ->assertJsonPath('componentes.0.tipo', 'Cátedra')
            ->assertJsonPath('id_tipo_componente_principal', $this->tipoCatedra->id_tipo_componente);

        expect(Curso::where('id_asignacion_plan', $this->asignacionPlan->id_asignacion_plan)->count())->toBe(0);
    });

    test('valida que los parámetros requeridos estén presentes', function () {
        $response = $this->getJson('/admin/cursos/preview-componentes');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_asignatura', 'id_plan', 'agno_real', 'semestre_real']);
    });
});

describe('GET /admin/cursos/{curso}/sincronizar-intranet/preview', function () {

    test('retorna el preview de un curso ya creado sin escribir nada en BD', function () {
        $curso = csihCrearCurso(950001, 'CSIH Preview Individual', $this->asignacionPlan, $this->docente);

        csihMockOracle(collect([
            new ComponenteCursoData(cur_codigo: 5002, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));

        $response = $this->getJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet/preview");

        $response->assertStatus(200)
            ->assertJsonPath('origen', 'INTRANET')
            ->assertJsonPath('componentes.0.tipo', 'Cátedra');

        expect($curso->componentes()->count())->toBe(0);
    });

    test('detecta y reporta componentes_sobrantes locales que no figuran en la oferta de Intranet', function () {
        $curso = csihCrearCurso(950002, 'CSIH Preview Sobrantes', $this->asignacionPlan, $this->docente);

        $compLab = \App\Models\Curso\Componente::create([
            'id_curso'                          => $curso->id_curso,
            'id_tipo_componente'                => $this->tipoLaboratorio->id_tipo_componente,
            'genera_acta'                       => true,
            'aprobacion_obligatoria'            => false,
            'porcentaje_aprobacion'             => 60.0,
            'porcentaje_asistencia_obligatoria' => 75.0,
        ]);

        csihMockOracle(collect([
            new ComponenteCursoData(cur_codigo: 5003, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));

        $response = $this->getJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet/preview");

        $response->assertStatus(200)
            ->assertJsonPath('origen', 'INTRANET')
            ->assertJsonPath('componentes.0.tipo', 'Cátedra')
            ->assertJsonCount(1, 'componentes_sobrantes')
            ->assertJsonPath('componentes_sobrantes.0.id_componente', $compLab->id_componente)
            ->assertJsonPath('componentes_sobrantes.0.tipo', 'Laboratorio');

        $advertencias = $response->json('advertencias');
        expect(implode(' ', $advertencias))->toContain('Laboratorio');
    });
});

describe('POST /admin/cursos/{curso}/sincronizar-intranet', function () {

    test('crea las componentes confirmadas y es idempotente en la segunda llamada', function () {
        $curso = csihCrearCurso(950002, 'CSIH Sincronizar Individual', $this->asignacionPlan, $this->docente);

        csihMockOracle(collect([
            new ComponenteCursoData(cur_codigo: 5003, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));

        $payload = ['tipos_componente_ids' => [$this->tipoCatedra->id_tipo_componente]];

        $r1 = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet", $payload);
        $r1->assertStatus(200)
            ->assertJsonPath('componentes_creadas', ['Cátedra'])
            ->assertJsonPath('componentes_existentes', []);

        expect($curso->componentes()->count())->toBe(1);

        $r2 = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet", $payload);
        $r2->assertStatus(200)
            ->assertJsonPath('componentes_creadas', [])
            ->assertJsonPath('componentes_existentes', ['Cátedra']);

        expect($curso->componentes()->count())->toBe(1);
    });

    test('valida que tipos_componente_ids sea requerido', function () {
        $curso = csihCrearCurso(950003, 'CSIH Validacion', $this->asignacionPlan, $this->docente);

        $response = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet", []);

        $response->assertStatus(422)->assertJsonValidationErrors(['tipos_componente_ids']);
    });

    test('con inscribir_automaticamente=true tambien inscribe a los alumnos que reporta Intranet', function () {
        $curso = csihCrearCurso(950004, 'CSIH Sincronizar Con Inscripcion', $this->asignacionPlan, $this->docente);

        $alumRut = rand(25000000, 25999999);
        $mock = Mockery::mock();
        $mock->shouldReceive('traer_cur_codigos')->andReturn(collect([
            new ComponenteCursoData(cur_codigo: 5004, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));
        $mock->shouldReceive('traer_ins_id')->andReturn(collect([
            new \App\DTOs\External\InscripcionData(ins_id: rand(600000, 699999), alum_rut: $alumRut),
        ]));
        $mock->shouldReceive('traer_alumno')->with($alumRut)->andReturn(new \App\DTOs\External\AlumnoIntranetData(
            alum_rut: $alumRut,
            alum_digito: '5',
            alum_nombre: 'CSIH',
            alum_apellido_pat: 'TEST',
            alum_apellido_mat: 'HTTP'
        ));
        app()->instance('OracleDataService', $mock);

        $response = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet", [
            'tipos_componente_ids' => [$this->tipoCatedra->id_tipo_componente],
            'inscribir_automaticamente' => true,
        ]);

        $response->assertStatus(200);

        $usuarioCreado = Usuario::where('username', (string) $alumRut)->first();
        expect($usuarioCreado)->not->toBeNull();

        $estudiante = \App\Models\Usuario\Estudiante::where('id_usuario', $usuarioCreado->id_usuario)->first();
        expect(\App\Models\Curso\InscripcionCurso::where('id_curso', $curso->id_curso)
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->exists())->toBeTrue();
    });

    test('reporta componentes_sobrantes locales en el resultado de sincronizacion', function () {
        $curso = csihCrearCurso(950005, 'CSIH Sync Sobrantes', $this->asignacionPlan, $this->docente);

        $compLab = \App\Models\Curso\Componente::create([
            'id_curso'                          => $curso->id_curso,
            'id_tipo_componente'                => $this->tipoLaboratorio->id_tipo_componente,
            'genera_acta'                       => true,
            'aprobacion_obligatoria'            => false,
            'porcentaje_aprobacion'             => 60.0,
            'porcentaje_asistencia_obligatoria' => 75.0,
        ]);

        csihMockOracle(collect([
            new ComponenteCursoData(cur_codigo: 5005, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));

        $response = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet", [
            'tipos_componente_ids' => [$this->tipoCatedra->id_tipo_componente],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('componentes_creadas.0', 'Cátedra')
            ->assertJsonCount(1, 'componentes_sobrantes')
            ->assertJsonPath('componentes_sobrantes.0.id_componente', $compLab->id_componente);
    });
});

describe('GET /admin/cursos/sincronizar-intranet-masivo/preview', function () {

    test('lista solo cursos sin componentes, cada uno con su preview', function () {
        $cursoA = csihCrearCurso(950005, 'CSIH Masivo A', $this->asignacionPlan, $this->docente);
        $cursoB = csihCrearCurso(950006, 'CSIH Masivo B', $this->asignacionPlan, $this->docente);

        csihMockOracle(collect([
            new ComponenteCursoData(cur_codigo: 5005, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));

        $response = $this->getJson('/admin/cursos/sincronizar-intranet-masivo/preview');

        $response->assertStatus(200);

        $idsListados = collect($response->json('cursos'))->pluck('id_curso')->all();
        expect($idsListados)->toContain($cursoA->id_curso);
        expect($idsListados)->toContain($cursoB->id_curso);
    });
});

describe('POST /admin/cursos/sincronizar-intranet-masivo', function () {

    test('sincroniza los cursos confirmados y reporta el resultado de cada uno', function () {
        $cursoA = csihCrearCurso(950007, 'CSIH Masivo Confirmado A', $this->asignacionPlan, $this->docente);
        $cursoB = csihCrearCurso(950008, 'CSIH Masivo Confirmado B', $this->asignacionPlan, $this->docente);

        csihMockOracle(collect([
            new ComponenteCursoData(cur_codigo: 5006, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));

        $response = $this->postJson('/admin/cursos/sincronizar-intranet-masivo', [
            'ids_curso' => [$cursoA->id_curso, $cursoB->id_curso],
        ]);

        $response->assertStatus(200);

        $resultados = collect($response->json('resultados'))->keyBy('id_curso');
        expect($resultados[$cursoA->id_curso]['resultado']['componentes_creadas'])->toBe(['Cátedra']);
        expect($resultados[$cursoB->id_curso]['resultado']['componentes_creadas'])->toBe(['Cátedra']);

        expect($cursoA->componentes()->count())->toBe(1);
        expect($cursoB->componentes()->count())->toBe(1);
    });

    test('rechaza la peticion si algun id_curso no existe', function () {
        $curso = csihCrearCurso(950009, 'CSIH Masivo Con Error', $this->asignacionPlan, $this->docente);
        $idInexistente = 999999999;

        $response = $this->postJson('/admin/cursos/sincronizar-intranet-masivo', [
            'ids_curso' => [$curso->id_curso, $idInexistente],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['ids_curso.1']);
        expect($curso->componentes()->count())->toBe(0);
    });
});

describe('autorizacion', function () {

    test('un usuario sin rol administrativo no puede sincronizar un curso', function () {
        $curso = csihCrearCurso(950010, 'CSIH Sin Permiso', $this->asignacionPlan, $this->docente);

        $usuarioSinRol = Usuario::firstOrCreate(
            ['rut' => '66666666-6'],
            [
                'username' => 'sin_permiso_csih_test',
                'passhash' => bcrypt('password'),
                'nombre1' => 'Sin',
                'apellido1' => 'Permiso',
                'esta_activo' => true,
            ]
        );
        $usuarioSinRol->forceFill([
            'fecha_cambio_passhash' => now(),
            'fecha_verificacion_email' => now(),
        ])->saveQuietly();
        $this->actingAs($usuarioSinRol);

        $response = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet", [
            'tipos_componente_ids' => [$this->tipoCatedra->id_tipo_componente],
        ]);

        $response->assertStatus(302);
        expect($curso->componentes()->count())->toBe(0);
    });

    test('un usuario sin rol administrativo no puede revertir una sincronizacion', function () {
        $curso = csihCrearCurso(950011, 'CSIH Revertir Sin Permiso', $this->asignacionPlan, $this->docente);

        $usuarioSinRol = Usuario::firstOrCreate(
            ['rut' => '66666666-6'],
            [
                'username' => 'sin_permiso_csih_test',
                'passhash' => bcrypt('password'),
                'nombre1' => 'Sin',
                'apellido1' => 'Permiso',
                'esta_activo' => true,
            ]
        );
        $usuarioSinRol->forceFill([
            'fecha_cambio_passhash' => now(),
            'fecha_verificacion_email' => now(),
        ])->saveQuietly();
        $this->actingAs($usuarioSinRol);

        $response = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet/revertir", [
            'id_log' => 1,
        ]);

        $response->assertStatus(302);
    });
});

describe('POST /admin/cursos/{curso}/sincronizar-intranet/revertir y trazabilidad', function () {

    test('sincronizarIntranet registra fecha_ultima_sincronizacion y crea intranet_sincronizacion_log', function () {
        $curso = csihCrearCurso(950012, 'CSIH Log Auditoria Test', $this->asignacionPlan, $this->docente);

        csihMockOracle(collect([
            new ComponenteCursoData(cur_codigo: 7701, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));

        $response = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet", [
            'tipos_componente_ids' => [$this->tipoCatedra->id_tipo_componente],
        ]);

        $response->assertStatus(200);
        $json = $response->json();
        expect($json['id_log'])->not->toBeNull();

        $curso->refresh();
        expect($curso->fecha_ultima_sincronizacion)->not->toBeNull();

        $log = \App\Models\Curso\IntranetSincronizacionLog::find($json['id_log']);
        expect($log)->not->toBeNull()
            ->and($log->id_curso)->toBe($curso->id_curso)
            ->and($log->estado)->toBe('APLICADA')
            ->and($log->esReversible())->toBeTrue();
    });

    test('preview expone fecha_ultima_sincronizacion y ultimo_log', function () {
        $curso = csihCrearCurso(950013, 'CSIH Preview Con Log', $this->asignacionPlan, $this->docente);

        csihMockOracle(collect([
            new ComponenteCursoData(cur_codigo: 7702, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));

        // Sincronizar primero
        $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet", [
            'tipos_componente_ids' => [$this->tipoCatedra->id_tipo_componente],
        ]);

        // Luego pedir preview
        $previewRes = $this->getJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet/preview");
        $previewRes->assertStatus(200);

        expect($previewRes->json('fecha_ultima_sincronizacion'))->not->toBeNull()
            ->and($previewRes->json('ultimo_log'))->not->toBeNull()
            ->and($previewRes->json('ultimo_log.es_reversible'))->toBeTrue();
    });

    test('revertir sincronizacion elimina componentes creadas y restaura fecha_ultima_sincronizacion', function () {
        $curso = csihCrearCurso(950014, 'CSIH Rollback Exitoso', $this->asignacionPlan, $this->docente);

        csihMockOracle(collect([
            new ComponenteCursoData(cur_codigo: 7703, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));

        $syncRes = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet", [
            'tipos_componente_ids' => [$this->tipoCatedra->id_tipo_componente],
        ]);
        $idLog = $syncRes->json('id_log');

        expect($curso->componentes()->count())->toBe(1);

        // Ejecutar reversión
        $revertRes = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet/revertir", [
            'id_log' => $idLog,
            'motivo' => 'Test de reversión automática',
        ]);

        $revertRes->assertStatus(200)
            ->assertJsonPath('exitosa', true);

        $curso->refresh();
        expect($curso->componentes()->count())->toBe(0)
            ->and($curso->fecha_ultima_sincronizacion)->toBeNull();

        $log = \App\Models\Curso\IntranetSincronizacionLog::find($idLog);
        expect($log->estado)->toBe('REVERTIDA')
            ->and($log->esReversible())->toBeFalse()
            ->and($log->fecha_reversion)->not->toBeNull();
    });

    test('guardarrail: bloquea reversion si una componente creada ya tiene actividades registradas', function () {
        $curso = csihCrearCurso(950015, 'CSIH Guardarrail Actividades', $this->asignacionPlan, $this->docente);

        csihMockOracle(collect([
            new ComponenteCursoData(cur_codigo: 7704, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));

        $syncRes = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet", [
            'tipos_componente_ids' => [$this->tipoCatedra->id_tipo_componente],
        ]);
        $idLog = $syncRes->json('id_log');

        $componente = $curso->componentes()->first();

        // Simular que el docente creó una actividad en esa componente
        $unidad = \App\Models\Curso\Unidad::create([
            'id_curso'   => $curso->id_curso,
            'num_unidad' => 1,
            'nombre'     => 'U1 Guardarrail',
        ]);

        \Illuminate\Support\Facades\DB::table('agenda.actividad')->insert([
            'nombre'              => 'Actividad con Nota Bloqueante',
            'id_componente'       => $componente->id_componente,
            'id_unidad'           => $unidad->id_unidad,
            'id_contexto'         => 1,
            'tipo_actividad'      => 'SUMATIVA',
            'tipo_entrega'        => 'Digital',
            'ponderacion'         => 20.00,
            'exigencia'           => 60.00,
            'visible'             => true,
            'es_grupal'           => false,
            'es_plantilla'        => false,
            'fecha_limite'        => now()->addDays(7)->toDateString(),
        ]);

        // Intentar revertir: debe ser rechazado con 422
        $revertRes = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-intranet/revertir", [
            'id_log' => $idLog,
        ]);

        $revertRes->assertStatus(422)
            ->assertJsonPath('error', fn ($err) => str_contains($err, 'actividad(es)'));

        // Verificar que la componente sigue intacta y el log sigue en APLICADA
        expect($curso->componentes()->count())->toBe(1);
        $log = \App\Models\Curso\IntranetSincronizacionLog::find($idLog);
        expect($log->estado)->toBe('APLICADA');
    });
});
