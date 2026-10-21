<?php

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
use App\Models\Usuario\Docente;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $facultad = Facultad::firstOrCreate(['nombre' => 'SCG Test Facultad'], ['id_contexto' => 1]);
    $departamento = Departamento::firstOrCreate(
        ['nombre' => 'SCG Test Departamento'],
        ['id_facultad' => $facultad->id_facultad, 'id_contexto' => 1]
    );
    $this->carrera = Carrera::firstOrCreate(
        ['nombre' => 'SCG Test Carrera'],
        ['id_departamento' => $departamento->id_departamento, 'id_contexto' => 1]
    );
    $this->plan = Plan::firstOrCreate(
        ['id_carrera' => $this->carrera->id_carrera, 'agno_plan' => 2026, 'version_plan' => 1],
        ['id_contexto' => 1]
    );
    // Asignatura con Cátedra (3h) y Taller (2h), pero Laboratorio (0h)
    $this->asignatura = Asignatura::firstOrCreate(
        ['cod_asignatura' => 'SCG-101'],
        [
            'nombre' => 'SCG Test Asignatura',
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
    $this->laboratorio = TipoComponente::firstOrCreate(['tipo' => 'Laboratorio']);

    $usuarioDocente = Usuario::firstOrCreate(
        ['rut' => '88888888-8'],
        [
            'username' => 'scg_docente',
            'passhash' => bcrypt('password'),
            'nombre1' => 'SCG',
            'apellido1' => 'Docente',
            'esta_activo' => true,
        ]
    );
    $usuarioDocente->forceFill(['fecha_cambio_passhash' => now()])->save();
    $this->docente = Docente::firstOrCreate(['id_usuario' => $usuarioDocente->id_usuario]);

    $usuarioAdmin = Usuario::firstOrCreate(
        ['rut' => '77777777-7'],
        [
            'username' => 'scg_admin',
            'passhash' => bcrypt('password'),
            'nombre1' => 'SCG',
            'apellido1' => 'Admin',
            'esta_activo' => true,
        ]
    );
    $usuarioAdmin->forceFill(['fecha_cambio_passhash' => now()])->save();
    $rolSuperAdmin = Rol::firstOrCreate(['nombre' => 'SuperAdmin'], ['creado_por' => $usuarioAdmin->id_usuario]);
    UsuarioRolAsignacion::firstOrCreate(
        ['id_usuario' => $usuarioAdmin->id_usuario, 'id_rol' => $rolSuperAdmin->id_rol, 'id_contexto' => 1],
        [
            'asignado_por' => $usuarioAdmin->id_usuario,
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada' => now()->addYears(100),
            'esta_activo' => true,
            'fue_eliminado' => false,
            'creado_por' => $usuarioAdmin->id_usuario,
        ]
    );

    $this->actingAs($usuarioAdmin);
});

describe('Guardarraíl G-02: StoreCursoRequest validación de componentes', function () {

    test('rechaza creacion de curso si incluye componente con 0 horas en el plan y sin respaldo en intranet', function () {
        // Mock de OracleDataService sin oferta de laboratorio
        $mockOracle = Mockery::mock();
        $mockOracle->shouldReceive('traer_cur_codigos')->andReturn(collect([
            new ComponenteCursoData(cur_codigo: 202610001, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
        ]));
        app()->instance('OracleDataService', $mockOracle);

        $payload = [
            'id_asignatura'                => $this->asignatura->id_asignatura,
            'id_plan'                      => $this->plan->id_plan,
            'agno_real'                    => 2026,
            'semestre_real'                => 1,
            'indice_grupo'                 => 1,
            'fecha_inicio'                 => '2026-03-01',
            'id_docente_sugerido'          => $this->docente->id_docente,
            'id_tipo_componente_principal' => $this->catedra->id_tipo_componente,
            'tipos_componente_ids'         => [
                $this->catedra->id_tipo_componente,
                $this->laboratorio->id_tipo_componente, // 0 horas en plan y no ofertado en intranet
            ],
            'docentes_por_componente'      => [
                $this->catedra->id_tipo_componente     => [$this->docente->id_docente],
                $this->laboratorio->id_tipo_componente => [$this->docente->id_docente],
            ],
        ];

        $response = $this->post('/admin/cursos', $payload);

        $response->assertSessionHasErrors('tipos_componente_ids');
        $errores = session('errors')->get('tipos_componente_ids');
        expect($errores[0])->toContain("La componente 'Laboratorio' no tiene horas asignadas en el plan de estudios ni cuenta con respaldo en la oferta de Intranet.");
    });

    test('rechaza creacion si id_tipo_componente_principal tiene 0 horas y sin respaldo en intranet', function () {
        $mockOracle = Mockery::mock();
        $mockOracle->shouldReceive('traer_cur_codigos')->andReturn(collect());
        app()->instance('OracleDataService', $mockOracle);

        $payload = [
            'id_asignatura'                => $this->asignatura->id_asignatura,
            'id_plan'                      => $this->plan->id_plan,
            'agno_real'                    => 2026,
            'semestre_real'                => 1,
            'indice_grupo'                 => 1,
            'fecha_inicio'                 => '2026-03-01',
            'id_docente_sugerido'          => $this->docente->id_docente,
            'id_tipo_componente_principal' => $this->laboratorio->id_tipo_componente,
            'tipos_componente_ids'         => [$this->laboratorio->id_tipo_componente],
            'docentes_por_componente'      => [
                $this->laboratorio->id_tipo_componente => [$this->docente->id_docente],
            ],
        ];

        $response = $this->post('/admin/cursos', $payload);

        $response->assertSessionHasErrors('id_tipo_componente_principal');
        $errores = session('errors')->get('id_tipo_componente_principal');
        expect($errores[0])->toContain("La componente 'Laboratorio' no tiene horas asignadas en el plan de estudios ni cuenta con respaldo en la oferta de Intranet.");
    });

    test('permite creacion de curso con componente de 0 horas si intranet la certifica explicitamente', function () {
        // En este escenario Oracle informa un acta oficial para Laboratorio
        $mockOracle = Mockery::mock();
        $mockOracle->shouldReceive('traer_cur_codigos')->andReturn(collect([
            new ComponenteCursoData(cur_codigo: 202610001, curso_tipo_asig: TipoAsignatura::Catedra, curso_grupo_asig: 'A'),
            new ComponenteCursoData(cur_codigo: 202610002, curso_tipo_asig: TipoAsignatura::Laboratorio, curso_grupo_asig: 'A'),
        ]));
        app()->instance('OracleDataService', $mockOracle);

        $payload = [
            'id_asignatura'                => $this->asignatura->id_asignatura,
            'id_plan'                      => $this->plan->id_plan,
            'agno_real'                    => 2026,
            'semestre_real'                => 1,
            'indice_grupo'                 => 1,
            'fecha_inicio'                 => '2026-03-01',
            'id_docente_sugerido'          => $this->docente->id_docente,
            'id_tipo_componente_principal' => $this->catedra->id_tipo_componente,
            'tipos_componente_ids'         => [
                $this->catedra->id_tipo_componente,
                $this->laboratorio->id_tipo_componente,
            ],
            'docentes_por_componente'      => [
                $this->catedra->id_tipo_componente     => [$this->docente->id_docente],
                $this->laboratorio->id_tipo_componente => [$this->docente->id_docente],
            ],
        ];

        $response = $this->post('/admin/cursos', $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $curso = Curso::where('id_asignacion_plan', $this->asignacionPlan->id_asignacion_plan)
            ->where('agno_real', 2026)
            ->first();
        expect($curso)->not->toBeNull();
        expect($curso->componentes()->count())->toBe(2);
    });

    test('permite creacion de curso con componentes que tienen horas en el plan sin consultar intranet', function () {
        // Oracle lanza excepción simulando caída de servicio, pero como el plan sí tiene horas de Cátedra y Taller debe crearse
        $mockOracle = Mockery::mock();
        $mockOracle->shouldReceive('traer_cur_codigos')->andThrow(new RuntimeException('Oracle inaccesible'));
        app()->instance('OracleDataService', $mockOracle);

        $payload = [
            'id_asignatura'                => $this->asignatura->id_asignatura,
            'id_plan'                      => $this->plan->id_plan,
            'agno_real'                    => 2026,
            'semestre_real'                => 1,
            'indice_grupo'                 => 1,
            'fecha_inicio'                 => '2026-03-01',
            'id_docente_sugerido'          => $this->docente->id_docente,
            'id_tipo_componente_principal' => $this->catedra->id_tipo_componente,
            'tipos_componente_ids'         => [
                $this->catedra->id_tipo_componente,
                $this->taller->id_tipo_componente,
            ],
            'docentes_por_componente'      => [
                $this->catedra->id_tipo_componente => [$this->docente->id_docente],
                $this->taller->id_tipo_componente  => [$this->docente->id_docente],
            ],
        ];

        $response = $this->post('/admin/cursos', $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $curso = Curso::where('id_asignacion_plan', $this->asignacionPlan->id_asignacion_plan)
            ->latest('id_curso')
            ->first();
        expect($curso)->not->toBeNull();
        expect($curso->componentes()->count())->toBe(2);
    });
});
