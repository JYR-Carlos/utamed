<?php

/**
 * Integration Test: IntranetService — detección híbrida de componentes
 * (Intranet → fallback Plan de Estudios) y sincronización idempotente.
 *
 * Usa BD de testing real (sin RefreshDatabase), igual que
 * CursoPolicyIntegrationTest. `OracleDataService` se remplaza por un
 * Mockery double vía el contenedor, así que no requiere Oracle real.
 */

use App\DTOs\External\ComponenteCursoData;
use App\Enums\External\TipoAsignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Usuario;
use App\Services\IntranetService;
use Illuminate\Support\Facades\DB;

function iscMockOracle(\Illuminate\Support\Collection $componentes): void
{
    $mock = Mockery::mock();
    $mock->shouldReceive('traer_cur_codigos')->andReturn($componentes);
    app()->bind('OracleDataService', fn() => $mock);
}

beforeEach(function () {
    // ---- Limpieza de datos de tests anteriores ----
    DB::table('componente')
        ->whereIn('id_curso', fn($q) => $q->select('id_curso')->from('curso')->where('nombre', 'ISC Test Curso'))
        ->delete();
    DB::table('curso')->where('nombre', 'ISC Test Curso')->delete();
    DB::table('asignacion_plan')
        ->whereIn('id_asignatura', fn($q) => $q->select('id_asignatura')->from('asignatura')->where('cod_asignatura', 'ISC-101'))
        ->delete();
    DB::table('asignatura')->where('cod_asignatura', 'ISC-101')->delete();
    DB::table('plan')->whereIn('id_carrera', fn($q) => $q->select('id_carrera')->from('carrera')->where('nombre', 'ISC Test Carrera'))->delete();
    DB::table('carrera')->where('nombre', 'ISC Test Carrera')->delete();
    DB::table('departamento')->where('nombre', 'ISC Test Departamento')->delete();
    DB::table('facultad')->where('nombre', 'ISC Test Facultad')->delete();

    // ---- Catálogo de tipos de componente (reutiliza si ya existen) ----
    $this->tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $this->tipoTaller = TipoComponente::firstOrCreate(['tipo' => 'Taller']);
    $this->tipoLaboratorio = TipoComponente::firstOrCreate(['tipo' => 'Laboratorio']);

    // ---- Jerarquía administrativa mínima ----
    $facultadId = DB::table('facultad')->insertGetId(
        ['nombre' => 'ISC Test Facultad', 'fecha_creacion' => now(), 'fecha_modificacion' => now()],
        'id_facultad'
    );
    $departamentoId = DB::table('departamento')->insertGetId([
        'nombre' => 'ISC Test Departamento',
        'id_facultad' => $facultadId,
        'fecha_creacion' => now(),
        'fecha_modificacion' => now(),
    ], 'id_departamento');

    $this->carrera = Carrera::create([
        'nombre' => 'ISC Test Carrera',
        'jornada' => 'Diurna',
        'sede' => 'Central',
        'modalidad' => 'Presencial',
        'id_departamento' => $departamentoId,
    ]);
    $this->carrera->refresh();

    $this->plan = Plan::create([
        'id_carrera' => $this->carrera->id_carrera,
        'agno_plan' => 2026,
        'version_plan' => 1,
    ]);

    $this->idAsignatura = DB::table('asignatura')->insertGetId([
        'cod_asignatura' => 'ISC-101',
        'nombre' => 'ISC Test Asignatura',
        'creditos_sct' => 4,
        'horas_catedra' => 3,
        'horas_taller' => 2,
        'horas_laboratorio' => 0,
        'horas_dirigidas' => 0,
        'horas_autonomas' => 0,
        'fecha_creacion' => now(),
        'fecha_modificacion' => now(),
    ], 'id_asignatura');

    $this->idAsignacionPlan = DB::table('asignacion_plan')->insertGetId([
        'id_asignatura' => $this->idAsignatura,
        'id_plan' => $this->plan->id_plan,
        'agno_planificado' => 1,
        'semestre_planificado' => 1,
        'fecha_creacion' => now(),
    ], 'id_asignacion_plan');

    $usuarioDocente = Usuario::firstOrCreate(
        ['rut' => '99999999-9'],
        [
            'username' => 'docente_isc_test',
            'passhash' => bcrypt('password'),
            'nombre1' => 'Docente',
            'apellido1' => 'ISC Test',
            'esta_activo' => true,
        ]
    );
    $this->idDocente = Docente::firstOrCreate(['id_usuario' => $usuarioDocente->id_usuario])->id_docente;
});

afterEach(function () {
    Mockery::close();
});

test('resuelve componentes desde parámetros sueltos, sin necesitar un curso ya guardado', function () {
    iscMockOracle(collect([
        new ComponenteCursoData(cur_codigo: 1001, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
    ]));

    // Deliberadamente NO se crea ningún Curso: el punto es que esta función
    // funcione sólo con los datos sueltos que ya existen antes de guardar.
    $resultado = app(IntranetService::class)->resolverComponentesDesdeParametros(
        idAsignatura: $this->idAsignatura,
        idPlan: $this->plan->id_plan,
        agno: 2026,
        semestre: 1,
        letraGrupo: 'A'
    );

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->cur_codigo)->toBe(1001);
});

test('previsualizar usa Intranet cuando responde, y avisa si el Plan de Estudios sugiere algo distinto', function () {
    // La asignatura tiene horas de Cátedra Y Taller, pero Intranet sólo
    // informa Cátedra: es la discrepancia que debe generar advertencia,
    // usando igualmente lo que dice Intranet (fuente de verdad).
    iscMockOracle(collect([
        new ComponenteCursoData(cur_codigo: 1001, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
    ]));

    $resultado = app(IntranetService::class)->previsualizarComponentes(
        idAsignatura: $this->idAsignatura,
        idPlan: $this->plan->id_plan,
        agno: 2026,
        semestre: 1,
        letraGrupo: 'A'
    );

    expect($resultado->origen)->toBe('INTRANET');
    expect($resultado->componentes)->toHaveCount(1);
    expect($resultado->componentes[0]->tipo)->toBe('Cátedra');
    expect($resultado->id_tipo_componente_principal)->toBe($this->tipoCatedra->id_tipo_componente);

    $huboAdvertenciaDeDiscrepancia = collect($resultado->advertencias)
        ->contains(fn($a) => str_contains($a, 'Plan de Estudios sugiere'));
    expect($huboAdvertenciaDeDiscrepancia)->toBeTrue();
});

test('previsualizar cae al Plan de Estudios cuando Intranet no tiene oferta para el periodo', function () {
    iscMockOracle(collect()); // Intranet vacío

    $resultado = app(IntranetService::class)->previsualizarComponentes(
        idAsignatura: $this->idAsignatura,
        idPlan: $this->plan->id_plan,
        agno: 2026,
        semestre: 1,
        letraGrupo: 'A'
    );

    expect($resultado->origen)->toBe('PLAN');
    // horas_catedra=3, horas_taller=2, horas_laboratorio=0 → Cátedra + Taller
    $tipos = collect($resultado->componentes)->pluck('tipo')->all();
    expect($tipos)->toContain('Cátedra');
    expect($tipos)->toContain('Taller');
    expect($tipos)->not->toContain('Laboratorio');
    expect($resultado->id_tipo_componente_principal)->toBe($this->tipoCatedra->id_tipo_componente);
});

test('sincronizarComponentes es idempotente: no duplica una componente que ya existe', function () {
    $curso = Curso::create([
        'cod_curso' => 919191919,
        'nombre' => 'ISC Test Curso',
        'fecha_inicio' => now(),
        'fecha_fin' => now()->addMonths(4),
        'agno_real' => 2026,
        'semestre_real' => 1,
        'id_asignacion_plan' => $this->idAsignacionPlan,
        'id_docente_titular' => $this->idDocente,
    ]);
    $curso->refresh();

    $servicio = app(IntranetService::class);

    $r1 = $servicio->sincronizarComponentes($curso, [$this->tipoCatedra->id_tipo_componente]);
    expect($r1->componentes_creadas)->toHaveCount(1);
    expect($r1->componentes_existentes)->toHaveCount(0);

    $curso->refresh();
    $r2 = $servicio->sincronizarComponentes($curso, [$this->tipoCatedra->id_tipo_componente]);
    expect($r2->componentes_creadas)->toHaveCount(0);
    expect($r2->componentes_existentes)->toHaveCount(1);

    expect(Componente::where('id_curso', $curso->id_curso)->count())->toBe(1);
});

test('inscribirAutomaticamente reporta advertencia (no la descarta en silencio) cuando falta el equivalente UTAMED', function () {
    $curso = Curso::create([
        'cod_curso' => 929292929,
        'nombre' => 'ISC Test Curso',
        'fecha_inicio' => now(),
        'fecha_fin' => now()->addMonths(4),
        'agno_real' => 2026,
        'semestre_real' => 1,
        'id_asignacion_plan' => $this->idAsignacionPlan,
        'id_docente_titular' => $this->idDocente,
    ]);
    $curso->refresh();

    // El curso en UTAMED sólo tiene Taller — Intranet va a reportar Cátedra,
    // que no tiene equivalente: debe quedar como advertencia, no perderse.
    Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $this->tipoTaller->id_tipo_componente,
        'id_contexto' => $this->carrera->id_contexto,
        'genera_acta' => true,
        'aprobacion_obligatoria' => false,
        'porcentaje_aprobacion' => 60,
        'porcentaje_asistencia_obligatoria' => 75,
    ]);

    iscMockOracle(collect([
        new ComponenteCursoData(cur_codigo: 1001, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
    ]));

    $resultado = app(IntranetService::class)->inscribirAutomaticamente($curso);

    expect($resultado->advertencias)->toHaveCount(1);
    expect($resultado->advertencias[0])->toContain('no tiene equivalente configurado en UTAMED');
});

test('inscribirAutomaticamente es tolerante si Oracle falla con un Error fatal (ej. OCI_DEFAULT no definido)', function () {
    $curso = Curso::create([
        'cod_curso' => 939393939,
        'nombre' => 'ISC Test Curso',
        'fecha_inicio' => now(),
        'fecha_fin' => now()->addMonths(4),
        'agno_real' => 2026,
        'semestre_real' => 1,
        'id_asignacion_plan' => $this->idAsignacionPlan,
        'id_docente_titular' => $this->idDocente,
    ]);
    $curso->refresh();

    $mock = Mockery::mock();
    $mock->shouldReceive('traer_cur_codigos')->andThrow(new \Error('Undefined constant "Yajra\Pdo\OCI_DEFAULT"'));
    app()->bind('OracleDataService', fn() => $mock);

    $resultado = app(IntranetService::class)->inscribirAutomaticamente($curso);

    expect($resultado->inscritos_exitosamente)->toBe(0);
    expect($resultado->advertencias)->toHaveCount(1);
    expect($resultado->advertencias[0])->toContain('No fue posible consultar la Intranet para inscribir alumnos');
});

test('sincronizarComponentes con inscribirAlumnos crea componentes exitosamente aun cuando Oracle falle con Error fatal', function () {
    $curso = Curso::create([
        'cod_curso' => 949494949,
        'nombre' => 'ISC Test Curso',
        'fecha_inicio' => now(),
        'fecha_fin' => now()->addMonths(4),
        'agno_real' => 2026,
        'semestre_real' => 1,
        'id_asignacion_plan' => $this->idAsignacionPlan,
        'id_docente_titular' => $this->idDocente,
    ]);
    $curso->refresh();

    $mock = Mockery::mock();
    $mock->shouldReceive('traer_cur_codigos')->andThrow(new \Error('Undefined constant "Yajra\Pdo\OCI_DEFAULT"'));
    app()->bind('OracleDataService', fn() => $mock);

    $servicio = app(IntranetService::class);
    $resultado = $servicio->sincronizarComponentes($curso, [$this->tipoCatedra->id_tipo_componente], true);

    expect($resultado->componentes_creadas)->toHaveCount(1);
    expect($resultado->advertencias)->not->toBeEmpty();
    expect(Componente::where('id_curso', $curso->id_curso)->count())->toBe(1);
});
