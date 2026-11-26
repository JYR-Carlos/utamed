<?php

/**
 * Tests de contexto correcto para asignación y revocación de roles fuera del
 * AssignmentWizardController:
 *
 * - CursoService (create, copiar, update/cambio de titular)
 * - ComponenteController (store, update, addDocente, removeDocente, setTitular, destroy)
 * - CursoController::assignDocente (revoca titular previo)
 * - CourseTeamController::store (rechaza roles incompatibles con contexto curso)
 * - Migración 24_reparar_contextos_roles_docente_componente
 */

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\Authorization\GlobalContextService;
use App\Services\CursoService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(DatabaseTransactions::class);

function arcCrearUsuarioDocente(string $rut, string $username): array
{
    $usuario = Usuario::firstOrCreate(
        ['rut' => $rut],
        [
            'username'    => $username,
            'passhash'    => bcrypt('password'),
            'nombre1'     => 'ARC',
            'apellido1'   => $username,
            'esta_activo' => true,
        ]
    );
    $usuario->forceFill(['fecha_cambio_passhash' => now()])->save();

    $docente = Docente::firstOrCreate(
        ['id_usuario' => $usuario->id_usuario],
        ['grado' => 'Magíster', 'titulo' => 'Ingeniero']
    );

    return [$usuario, $docente];
}

function arcTieneRolActivo(int $idUsuario, int $idContexto, string $rolNombre): bool
{
    $idRol = Rol::where('nombre', $rolNombre)->value('id_rol');
    if (!$idRol) {
        return false;
    }

    return UsuarioRolAsignacion::where('id_usuario', $idUsuario)
        ->where('id_contexto', $idContexto)
        ->where('id_rol', $idRol)
        ->where('esta_activo', true)
        ->where('fue_eliminado', false)
        ->exists();
}

beforeEach(function () {
    $facultad = Facultad::firstOrCreate(['nombre' => 'ARC Test Facultad'], ['id_contexto' => 1]);
    $departamento = Departamento::firstOrCreate(
        ['nombre' => 'ARC Test Departamento'],
        ['id_facultad' => $facultad->id_facultad, 'id_contexto' => 1]
    );
    $this->carrera = Carrera::firstOrCreate(
        ['nombre' => 'ARC Test Carrera'],
        ['id_departamento' => $departamento->id_departamento, 'id_contexto' => 1]
    );
    $this->plan = Plan::firstOrCreate(
        ['id_carrera' => $this->carrera->id_carrera, 'agno_plan' => 2026, 'version_plan' => 1],
        ['id_contexto' => 1]
    );
    $this->asignatura = Asignatura::firstOrCreate(
        ['cod_asignatura' => 'ARC-101'],
        [
            'nombre'            => 'ARC Asignatura Test',
            'creditos_sct'      => 4,
            'horas_catedra'     => 2,
            'horas_taller'      => 2,
            'horas_laboratorio' => 0,
            'horas_dirigidas'   => 0,
            'horas_autonomas'   => 0,
        ]
    );
    $this->asignacionPlan = AsignacionPlan::firstOrCreate(
        ['id_plan' => $this->plan->id_plan, 'id_asignatura' => $this->asignatura->id_asignatura],
        ['agno_planificado' => 1, 'semestre_planificado' => 1, 'id_contexto' => 1]
    );

    $this->catedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $this->taller  = TipoComponente::firstOrCreate(['tipo' => 'Taller']);

    [$this->adminUser] = arcCrearUsuarioDocente('77100001-1', 'arc_admin');
    $globalContextId = app(GlobalContextService::class)->getContextId();
    $permisoGlobal = Permiso::firstOrCreate(
        ['slug' => '*'],
        ['nombre' => 'Global Wildcard', 'descripcion' => 'Test']
    );
    UsuarioPermisoEspecial::create([
        'id_usuario'               => $this->adminUser->id_usuario,
        'id_permiso'               => $permisoGlobal->id_permiso,
        'id_contexto'              => $globalContextId,
        'esta_permitido'           => true,
        'puede_delegar'            => true,
        'fecha_inicio_planificada' => now()->subMinute(),
        'fecha_fin_planificada'    => now()->addDays(30),
        'creado_por'               => $this->adminUser->id_usuario,
        'esta_activo'              => true,
        'fue_borrado'              => false,
    ]);

    Rol::firstOrCreate(['nombre' => 'Docente Titular'], ['creado_por' => $this->adminUser->id_usuario]);
    Rol::firstOrCreate(['nombre' => 'Docente Componente'], ['creado_por' => $this->adminUser->id_usuario]);
    Rol::firstOrCreate(['nombre' => 'Docente Componente Colegiado'], ['creado_por' => $this->adminUser->id_usuario]);
    Rol::firstOrCreate(['nombre' => 'Ayudante'], ['creado_por' => $this->adminUser->id_usuario]);

    [$this->userDt, $this->docDt] = arcCrearUsuarioDocente('77100002-2', 'arc_dt');
    [$this->userA, $this->docA]   = arcCrearUsuarioDocente('77100003-3', 'arc_doc_a');
    [$this->userB, $this->docB]   = arcCrearUsuarioDocente('77100004-4', 'arc_doc_b');
    [$this->userC, $this->docC]   = arcCrearUsuarioDocente('77100005-5', 'arc_doc_c');

    $this->actingAs($this->adminUser);
});

test('CursoService::create y ::copiar asignan roles de componente en id_contexto del componente sin colisionar entre componentes', function () {
    /** @var CursoService $service */
    $service = app(CursoService::class);

    // Docente A es titular en Taller y colegiado en Cátedra; Docente DT es DT del curso.
    $curso = $service->create([
        'id_asignatura'                => $this->asignatura->id_asignatura,
        'id_plan'                      => $this->plan->id_plan,
        'cod_curso'                    => random_int(700000, 749999),
        'nombre'                       => 'ARC Curso Multi-Componente',
        'fecha_inicio'                 => now()->toDateString(),
        'agno_real'                    => 2026,
        'semestre_real'                => 1,
        'id_docente_sugerido'          => $this->docDt->id_docente,
        'id_tipo_componente_principal' => $this->catedra->id_tipo_componente,
        'tipos_componente_ids'         => [$this->catedra->id_tipo_componente, $this->taller->id_tipo_componente],
        'docentes_por_componente'      => [
            $this->catedra->id_tipo_componente => [$this->docB->id_docente, $this->docA->id_docente],
            $this->taller->id_tipo_componente  => [$this->docA->id_docente],
        ],
    ]);

    $compCatedra = Componente::where('id_curso', $curso->id_curso)
        ->where('id_tipo_componente', $this->catedra->id_tipo_componente)
        ->firstOrFail();
    $compTaller = Componente::where('id_curso', $curso->id_curso)
        ->where('id_tipo_componente', $this->taller->id_tipo_componente)
        ->firstOrFail();

    // DT solo tiene Docente Titular en contexto curso
    expect(arcTieneRolActivo($this->userDt->id_usuario, $curso->id_contexto, 'Docente Titular'))->toBeTrue();

    // Docente B tiene 'Docente Componente' exclusivamente en Cátedra (no en curso ni en Taller)
    expect(arcTieneRolActivo($this->userB->id_usuario, $compCatedra->id_contexto, 'Docente Componente'))->toBeTrue();
    expect(arcTieneRolActivo($this->userB->id_usuario, $curso->id_contexto, 'Docente Componente'))->toBeFalse();
    expect(arcTieneRolActivo($this->userB->id_usuario, $compTaller->id_contexto, 'Docente Componente'))->toBeFalse();

    // Docente A tiene 'Docente Componente Colegiado' en Cátedra y 'Docente Componente' en Taller simultáneamente
    expect(arcTieneRolActivo($this->userA->id_usuario, $compCatedra->id_contexto, 'Docente Componente Colegiado'))->toBeTrue();
    expect(arcTieneRolActivo($this->userA->id_usuario, $compTaller->id_contexto, 'Docente Componente'))->toBeTrue();
    expect(arcTieneRolActivo($this->userA->id_usuario, $curso->id_contexto, 'Docente Componente'))->toBeFalse();
    expect(arcTieneRolActivo($this->userA->id_usuario, $curso->id_contexto, 'Docente Componente Colegiado'))->toBeFalse();

    // Copiar curso debe replicar los roles sobre los nuevos componentes
    $copia = $service->copiar($curso, [
        'fecha_inicio' => now()->addMonths(6)->toDateString(),
        'cod_curso'    => random_int(750000, 799999),
    ], $this->adminUser);

    $copiaCatedra = Componente::where('id_curso', $copia->id_curso)
        ->where('id_tipo_componente', $this->catedra->id_tipo_componente)
        ->firstOrFail();
    $copiaTaller = Componente::where('id_curso', $copia->id_curso)
        ->where('id_tipo_componente', $this->taller->id_tipo_componente)
        ->firstOrFail();

    expect(arcTieneRolActivo($this->userDt->id_usuario, $copia->id_contexto, 'Docente Titular'))->toBeTrue();
    expect(arcTieneRolActivo($this->userB->id_usuario, $copiaCatedra->id_contexto, 'Docente Componente'))->toBeTrue();
    expect(arcTieneRolActivo($this->userA->id_usuario, $copiaCatedra->id_contexto, 'Docente Componente Colegiado'))->toBeTrue();
    expect(arcTieneRolActivo($this->userA->id_usuario, $copiaTaller->id_contexto, 'Docente Componente'))->toBeTrue();
});

test('CursoService::update y CursoController::assignDocente revocan Docente Titular anterior en contexto curso', function () {
    /** @var CursoService $service */
    $service = app(CursoService::class);

    $curso = $service->create([
        'id_asignatura'                => $this->asignatura->id_asignatura,
        'id_plan'                      => $this->plan->id_plan,
        'cod_curso'                    => random_int(700000, 749999),
        'nombre'                       => 'ARC Curso Cambio DT',
        'fecha_inicio'                 => now()->toDateString(),
        'agno_real'                    => 2026,
        'semestre_real'                => 1,
        'id_docente_sugerido'          => $this->docDt->id_docente,
        'id_tipo_componente_principal' => $this->catedra->id_tipo_componente,
        'docentes_por_componente'      => [
            $this->catedra->id_tipo_componente => [$this->docDt->id_docente],
        ],
    ]);

    expect(arcTieneRolActivo($this->userDt->id_usuario, $curso->id_contexto, 'Docente Titular'))->toBeTrue();

    // 1. Reemplazo vía CursoService::update()
    $service->update($curso->fresh(), [
        'id_asignatura'       => $this->asignatura->id_asignatura,
        'id_plan'             => $this->plan->id_plan,
        'cod_curso'           => $curso->cod_curso,
        'nombre'              => $curso->nombre,
        'id_docente_sugerido' => $this->docA->id_docente,
    ]);

    expect(arcTieneRolActivo($this->userDt->id_usuario, $curso->id_contexto, 'Docente Titular'))->toBeFalse();
    expect(arcTieneRolActivo($this->userA->id_usuario, $curso->id_contexto, 'Docente Titular'))->toBeTrue();

    // 2. Reemplazo vía CursoController::assignDocente()
    $this->post("/admin/cursos/{$curso->id_curso}/docente", [
        'id_docente' => $this->docB->id_docente,
    ])->assertRedirect()->assertSessionHas('success');

    expect(arcTieneRolActivo($this->userA->id_usuario, $curso->id_contexto, 'Docente Titular'))->toBeFalse();
    expect(arcTieneRolActivo($this->userB->id_usuario, $curso->id_contexto, 'Docente Titular'))->toBeTrue();
});

test('ComponenteController asigna, promueve, degrada y revoca roles en el contexto exclusivo del componente', function () {
    /** @var CursoService $service */
    $service = app(CursoService::class);

    $curso = $service->create([
        'id_asignatura'                => $this->asignatura->id_asignatura,
        'id_plan'                      => $this->plan->id_plan,
        'cod_curso'                    => random_int(700000, 749999),
        'nombre'                       => 'ARC Curso Componentes HTTP',
        'fecha_inicio'                 => now()->toDateString(),
        'agno_real'                    => 2026,
        'semestre_real'                => 1,
        'id_docente_sugerido'          => $this->docDt->id_docente,
        'id_tipo_componente_principal' => $this->catedra->id_tipo_componente,
        'docentes_por_componente'      => [
            $this->catedra->id_tipo_componente => [$this->docA->id_docente],
        ],
    ]);

    $catedra = Componente::where('id_curso', $curso->id_curso)
        ->where('id_tipo_componente', $this->catedra->id_tipo_componente)
        ->firstOrFail();

    // store() crea Taller con Docente A como titular -> obtiene id_contexto tras refresh()
    $this->postJson("/admin/cursos/{$curso->id_curso}/componentes", [
        'id_tipo_componente' => $this->taller->id_tipo_componente,
        'id_docente'         => $this->docA->id_docente,
    ])->assertOk();

    $taller = Componente::where('id_curso', $curso->id_curso)
        ->where('id_tipo_componente', $this->taller->id_tipo_componente)
        ->firstOrFail();

    expect(arcTieneRolActivo($this->userA->id_usuario, $taller->id_contexto, 'Docente Componente'))->toBeTrue();
    expect(arcTieneRolActivo($this->userA->id_usuario, $curso->id_contexto, 'Docente Componente'))->toBeFalse();

    // addDocente() agrega Docente B en Taller -> recibe Colegiado en contexto Taller
    $addResp = $this->postJson("/admin/cursos/{$curso->id_curso}/componentes/{$taller->id_componente}/docentes", [
        'id_docente' => $this->docB->id_docente,
    ])->assertOk();

    $idDcB = $addResp->json('docente_componente.id_docente_componente');
    expect(arcTieneRolActivo($this->userB->id_usuario, $taller->id_contexto, 'Docente Componente Colegiado'))->toBeTrue();
    expect(arcTieneRolActivo($this->userB->id_usuario, $curso->id_contexto, 'Docente Componente Colegiado'))->toBeFalse();

    // setTitular() promueve a Docente B en Taller y degrada a Docente A solo en Taller (conserva titular en Cátedra!)
    $this->putJson("/admin/cursos/{$curso->id_curso}/componentes/{$taller->id_componente}/titular", [
        'id_docente_componente' => $idDcB,
    ])->assertOk();

    expect(arcTieneRolActivo($this->userB->id_usuario, $taller->id_contexto, 'Docente Componente'))->toBeTrue();
    expect(arcTieneRolActivo($this->userA->id_usuario, $taller->id_contexto, 'Docente Componente Colegiado'))->toBeTrue();
    expect(arcTieneRolActivo($this->userA->id_usuario, $taller->id_contexto, 'Docente Componente'))->toBeFalse();
    expect(arcTieneRolActivo($this->userA->id_usuario, $catedra->id_contexto, 'Docente Componente'))->toBeTrue();

    // removeDocente() revoca a Docente A del Taller inmediatamente aunque siga dictando Cátedra
    $dcAEnTaller = DocenteComponente::where('id_componente', $taller->id_componente)
        ->where('id_docente', $this->docA->id_docente)
        ->firstOrFail();
    $this->deleteJson("/admin/cursos/{$curso->id_curso}/componentes/{$taller->id_componente}/docentes/{$dcAEnTaller->id_docente_componente}")
        ->assertOk();

    expect(arcTieneRolActivo($this->userA->id_usuario, $taller->id_contexto, 'Docente Componente Colegiado'))->toBeFalse();
    expect(arcTieneRolActivo($this->userA->id_usuario, $catedra->id_contexto, 'Docente Componente'))->toBeTrue();

    // update() en Taller reemplaza Docente B por Docente C y revoca a Docente B
    $this->putJson("/admin/cursos/{$curso->id_curso}/componentes/{$taller->id_componente}", [
        'id_tipo_componente' => $this->taller->id_tipo_componente,
        'id_docente'         => $this->docC->id_docente,
    ])->assertOk();

    expect(arcTieneRolActivo($this->userB->id_usuario, $taller->id_contexto, 'Docente Componente'))->toBeFalse();
    expect(arcTieneRolActivo($this->userC->id_usuario, $taller->id_contexto, 'Docente Componente'))->toBeTrue();

    // destroy() del componente revoca a Docente C en contexto del Taller
    $this->deleteJson("/admin/cursos/{$curso->id_curso}/componentes/{$taller->id_componente}")
        ->assertOk();

    expect(arcTieneRolActivo($this->userC->id_usuario, $taller->id_contexto, 'Docente Componente'))->toBeFalse();
});

test('CourseTeamController::store rechaza roles de contexto global o exclusivo de componente en un curso', function () {
    /** @var CursoService $service */
    $service = app(CursoService::class);

    $curso = $service->create([
        'id_asignatura'                => $this->asignatura->id_asignatura,
        'id_plan'                      => $this->plan->id_plan,
        'cod_curso'                    => random_int(700000, 749999),
        'nombre'                       => 'ARC Curso Team Context Guard',
        'fecha_inicio'                 => now()->toDateString(),
        'agno_real'                    => 2026,
        'semestre_real'                => 1,
        'id_docente_sugerido'          => $this->docDt->id_docente,
        'id_tipo_componente_principal' => $this->catedra->id_tipo_componente,
    ]);

    // Rol global-only (ej: AsignaturasManager) debe rechazarse en contexto curso
    $permAsigVer = Permiso::firstOrCreate(
        ['slug' => Permissions::ASIGNATURAS_VER->value],
        ['nombre' => 'Ver Asignaturas', 'descripcion' => 'Test']
    );
    $rolGlobal = Rol::firstOrCreate(['nombre' => 'ARC Global Only Test'], ['creado_por' => $this->adminUser->id_usuario]);
    $rolGlobal->permisos()->syncWithoutDetaching([$permAsigVer->id_permiso => ['puede_delegar_permiso' => false]]);

    $this->post("/admin/cursos/{$curso->id_curso}/team", [
        'id_usuario' => $this->userA->id_usuario,
        'role_name'  => 'ARC Global Only Test',
    ])->assertSessionHas('error');
    expect(arcTieneRolActivo($this->userA->id_usuario, $curso->id_contexto, 'ARC Global Only Test'))->toBeFalse();

    // Rol exclusivo de componente debe rechazarse en contexto curso
    $this->post("/admin/cursos/{$curso->id_curso}/team", [
        'id_usuario' => $this->userA->id_usuario,
        'role_name'  => 'Docente Componente Colegiado',
    ])->assertSessionHas('error');
    expect(arcTieneRolActivo($this->userA->id_usuario, $curso->id_contexto, 'Docente Componente Colegiado'))->toBeFalse();

    // Rol compatible con curso (Ayudante) debe aceptarse
    $permCursosVer = Permiso::firstOrCreate(
        ['slug' => Permissions::CURSOS_VER->value],
        ['nombre' => 'Ver Cursos', 'descripcion' => 'Test']
    );
    $rolAyudante = Rol::where('nombre', 'Ayudante')->firstOrFail();
    $rolAyudante->permisos()->syncWithoutDetaching([$permCursosVer->id_permiso => ['puede_delegar_permiso' => false]]);

    $this->post("/admin/cursos/{$curso->id_curso}/team", [
        'id_usuario' => $this->userA->id_usuario,
        'role_name'  => 'Ayudante',
    ])->assertSessionHas('success');
    expect(arcTieneRolActivo($this->userA->id_usuario, $curso->id_contexto, 'Ayudante'))->toBeTrue();
});

test('Migración 24 mueve roles Docente Componente mal ubicados en global o curso al contexto real del componente', function () {
    /** @var CursoService $service */
    $service = app(CursoService::class);

    $curso = $service->create([
        'id_asignatura'                => $this->asignatura->id_asignatura,
        'id_plan'                      => $this->plan->id_plan,
        'cod_curso'                    => random_int(700000, 749999),
        'nombre'                       => 'ARC Curso Migracion 24',
        'fecha_inicio'                 => now()->toDateString(),
        'agno_real'                    => 2026,
        'semestre_real'                => 1,
        'id_docente_sugerido'          => $this->docDt->id_docente,
        'id_tipo_componente_principal' => $this->catedra->id_tipo_componente,
    ]);

    $componente = Componente::where('id_curso', $curso->id_curso)->firstOrFail();

    // Simular caso histórico (como id_ura = 614): Docente B en docente_componente (colegiado),
    // pero con URA en contexto global (id_contexto = 1) y Docente C titular en contexto curso.
    DocenteComponente::create([
        'id_componente' => $componente->id_componente,
        'id_docente'    => $this->docC->id_docente,
        'es_titular'    => true,
    ]);
    DocenteComponente::create([
        'id_componente' => $componente->id_componente,
        'id_docente'    => $this->docB->id_docente,
        'es_titular'    => false,
    ]);

    $rolCompTit  = Rol::where('nombre', 'Docente Componente')->firstOrFail();
    $rolCompCol  = Rol::where('nombre', 'Docente Componente Colegiado')->firstOrFail();
    $globalCtxId = app(GlobalContextService::class)->getContextId();

    $uraGlobal = UsuarioRolAsignacion::create([
        'id_usuario'               => $this->userB->id_usuario,
        'id_rol'                   => $rolCompCol->id_rol,
        'id_contexto'              => $globalCtxId,
        'asignado_por'             => $this->adminUser->id_usuario,
        'creado_por'               => $this->adminUser->id_usuario,
        'fecha_inicio_planificada' => now()->subMinute(),
        'fecha_fin_planificada'    => now()->addDays(30),
        'esta_activo'              => true,
        'fue_eliminado'            => false,
    ]);

    $uraCurso = UsuarioRolAsignacion::create([
        'id_usuario'               => $this->userC->id_usuario,
        'id_rol'                   => $rolCompTit->id_rol,
        'id_contexto'              => $curso->id_contexto,
        'asignado_por'             => $this->adminUser->id_usuario,
        'creado_por'               => $this->adminUser->id_usuario,
        'fecha_inicio_planificada' => now()->subMinute(),
        'fecha_fin_planificada'    => now()->addDays(30),
        'esta_activo'              => true,
        'fue_eliminado'            => false,
    ]);

    $migration = require base_path('database/migrations/24_reparar_contextos_roles_docente_componente.php');
    $migration->up();

    expect($uraGlobal->fresh()->esta_activo)->toBeFalse();
    expect($uraCurso->fresh()->esta_activo)->toBeFalse();

    expect(arcTieneRolActivo($this->userB->id_usuario, $componente->id_contexto, 'Docente Componente Colegiado'))->toBeTrue();
    expect(arcTieneRolActivo($this->userC->id_usuario, $componente->id_contexto, 'Docente Componente'))->toBeTrue();
});

test('DT recibe roles de componente ortogonales y se degrada a Colegiado solo en el componente donde cede titularidad', function () {
    /** @var CursoService $service */
    $service = app(CursoService::class);

    $curso = $service->create([
        'id_asignatura'                => $this->asignatura->id_asignatura,
        'id_plan'                      => $this->plan->id_plan,
        'cod_curso'                    => random_int(700000, 749999),
        'nombre'                       => 'ARC Curso DT Ortogonal',
        'fecha_inicio'                 => now()->toDateString(),
        'agno_real'                    => 2026,
        'semestre_real'                => 1,
        'id_docente_sugerido'          => $this->docDt->id_docente,
        'id_tipo_componente_principal' => $this->catedra->id_tipo_componente,
        'tipos_componente_ids'         => [$this->catedra->id_tipo_componente, $this->taller->id_tipo_componente],
        'docentes_por_componente'      => [
            $this->catedra->id_tipo_componente => [$this->docDt->id_docente],
            $this->taller->id_tipo_componente  => [$this->docDt->id_docente, $this->docA->id_docente],
        ],
    ]);

    $catedra = Componente::where('id_curso', $curso->id_curso)
        ->where('id_tipo_componente', $this->catedra->id_tipo_componente)
        ->firstOrFail();
    $taller = Componente::where('id_curso', $curso->id_curso)
        ->where('id_tipo_componente', $this->taller->id_tipo_componente)
        ->firstOrFail();

    // Inicialmente DT tiene Docente Titular en curso y Docente Componente en Cátedra y Taller
    expect(arcTieneRolActivo($this->userDt->id_usuario, $curso->id_contexto, 'Docente Titular'))->toBeTrue();
    expect(arcTieneRolActivo($this->userDt->id_usuario, $catedra->id_contexto, 'Docente Componente'))->toBeTrue();
    expect(arcTieneRolActivo($this->userDt->id_usuario, $taller->id_contexto, 'Docente Componente'))->toBeTrue();
    expect(arcTieneRolActivo($this->userA->id_usuario, $taller->id_contexto, 'Docente Componente Colegiado'))->toBeTrue();

    // Se cede la titularidad del Taller al Docente A (caso Amalia Rosa -> Cecilia)
    $dcAEnTaller = DocenteComponente::where('id_componente', $taller->id_componente)
        ->where('id_docente', $this->docA->id_docente)
        ->firstOrFail();

    $this->putJson("/admin/cursos/{$curso->id_curso}/componentes/{$taller->id_componente}/titular", [
        'id_docente_componente' => $dcAEnTaller->id_docente_componente,
    ])->assertOk();

    // DT conserva Docente Titular en curso y Docente Componente en Cátedra, pero en Taller pasa a Colegiado
    expect(arcTieneRolActivo($this->userDt->id_usuario, $curso->id_contexto, 'Docente Titular'))->toBeTrue();
    expect(arcTieneRolActivo($this->userDt->id_usuario, $catedra->id_contexto, 'Docente Componente'))->toBeTrue();
    expect(arcTieneRolActivo($this->userDt->id_usuario, $taller->id_contexto, 'Docente Componente'))->toBeFalse();
    expect(arcTieneRolActivo($this->userDt->id_usuario, $taller->id_contexto, 'Docente Componente Colegiado'))->toBeTrue();
    expect(arcTieneRolActivo($this->userA->id_usuario, $taller->id_contexto, 'Docente Componente'))->toBeTrue();
});

test('Migración 25 unifica DTR en Docente Titular, configura delegabilidad, deja Colegiado en piso mínimo y hace backfill al DT', function () {
    /** @var CursoService $service */
    $service = app(CursoService::class);

    $curso = $service->create([
        'id_asignatura'                => $this->asignatura->id_asignatura,
        'id_plan'                      => $this->plan->id_plan,
        'cod_curso'                    => random_int(700000, 749999),
        'nombre'                       => 'ARC Curso Migracion 25',
        'fecha_inicio'                 => now()->toDateString(),
        'agno_real'                    => 2026,
        'semestre_real'                => 1,
        'id_docente_sugerido'          => $this->docDt->id_docente,
        'id_tipo_componente_principal' => $this->catedra->id_tipo_componente,
        'tipos_componente_ids'         => [$this->catedra->id_tipo_componente, $this->taller->id_tipo_componente],
    ]);

    $catedra = Componente::where('id_curso', $curso->id_curso)
        ->where('id_tipo_componente', $this->catedra->id_tipo_componente)
        ->firstOrFail();
    $taller = Componente::where('id_curso', $curso->id_curso)
        ->where('id_tipo_componente', $this->taller->id_tipo_componente)
        ->firstOrFail();

    // Simular docente_componente histórico sin URA en componente (incluyendo DT como titular en Cátedra y colegiado en Taller)
    DocenteComponente::create(['id_componente' => $catedra->id_componente, 'id_docente' => $this->docDt->id_docente, 'es_titular' => true]);
    DocenteComponente::create(['id_componente' => $taller->id_componente, 'id_docente' => $this->docA->id_docente, 'es_titular' => true]);
    DocenteComponente::create(['id_componente' => $taller->id_componente, 'id_docente' => $this->docDt->id_docente, 'es_titular' => false]);
    DocenteComponente::create(['id_componente' => $taller->id_componente, 'id_docente' => $this->docB->id_docente, 'es_titular' => false]);

    // Simular rol legado 'Docente Titular Restringido' con una URA activa sobre otro docente
    $dtrRol = Rol::firstOrCreate(
        ['nombre' => 'Docente Titular Restringido'],
        ['es_administrativo' => false, 'creado_por' => $this->adminUser->id_usuario]
    );
    $uraDtr = UsuarioRolAsignacion::create([
        'id_usuario'               => $this->userC->id_usuario,
        'id_rol'                   => $dtrRol->id_rol,
        'id_contexto'              => $curso->id_contexto,
        'asignado_por'             => $this->adminUser->id_usuario,
        'creado_por'               => $this->adminUser->id_usuario,
        'fecha_inicio_planificada' => now()->subMinute(),
        'fecha_fin_planificada'    => now()->addDays(30),
        'esta_activo'              => true,
        'fue_eliminado'            => false,
    ]);

    $migration = require base_path('database/migrations/25_unificar_dt_y_backfill_roles_componente.php');
    $migration->up();

    // 1. DTR eliminado y su URA migrada a Docente Titular
    expect(Rol::where('nombre', 'Docente Titular Restringido')->exists())->toBeFalse();
    $dtRolId = (int) Rol::where('nombre', 'Docente Titular')->value('id_rol');
    expect((int) $uraDtr->fresh()->id_rol)->toBe($dtRolId);

    // 2. Permisos y estado de delegación de Docente Titular
    $dtArp = DB::table('usuario.asignacion_rol_permiso as arp')
        ->join('usuario.permiso as p', 'p.id_permiso', '=', 'arp.id_permiso')
        ->where('arp.id_rol', $dtRolId)
        ->pluck('arp.puede_delegar_permiso', 'p.slug');

    expect($dtArp->get('componentes/inscripciones:ver'))->toBeTrue();
    expect($dtArp->get('cursos/programas:agregar'))->toBeTrue();
    expect($dtArp->get('cursos:eliminar'))->toBeFalse();
    expect($dtArp->has('actividades:evaluar'))->toBeFalse();

    // 3. Permisos y delegabilidad de Docente Componente (incluye actividades:crear/eliminar y asistencia)
    $dcRolId = (int) Rol::where('nombre', 'Docente Componente')->value('id_rol');
    $dcArp = DB::table('usuario.asignacion_rol_permiso as arp')
        ->join('usuario.permiso as p', 'p.id_permiso', '=', 'arp.id_permiso')
        ->where('arp.id_rol', $dcRolId)
        ->pluck('arp.puede_delegar_permiso', 'p.slug');

    expect($dcArp->get('actividades:crear'))->toBeTrue();
    expect($dcArp->get('actividades:crear_plantilla'))->toBeTrue();
    expect($dcArp->get('actividades:eliminar'))->toBeTrue();
    expect($dcArp->get('componentes/asistencia:registrar'))->toBeTrue();
    expect($dcArp->get('componentes/asistencia:eliminar'))->toBeFalse();

    // 4. Colegiado solo conserva cursos:ver y componentes:ver (no delegables)
    $colRolId = (int) Rol::where('nombre', 'Docente Componente Colegiado')->value('id_rol');
    $colArp = DB::table('usuario.asignacion_rol_permiso as arp')
        ->join('usuario.permiso as p', 'p.id_permiso', '=', 'arp.id_permiso')
        ->where('arp.id_rol', $colRolId)
        ->pluck('arp.puede_delegar_permiso', 'p.slug');

    expect($colArp->keys()->sort()->values()->all())->toBe(['componentes:ver', 'cursos:ver']);
    expect($colArp->every(fn ($delegable) => $delegable === false))->toBeTrue();

    // 5. Backfill aplicó roles de componente también al DT según docente_componente
    expect(arcTieneRolActivo($this->userDt->id_usuario, $catedra->id_contexto, 'Docente Componente'))->toBeTrue();
    expect(arcTieneRolActivo($this->userDt->id_usuario, $taller->id_contexto, 'Docente Componente Colegiado'))->toBeTrue();
    expect(arcTieneRolActivo($this->userA->id_usuario, $taller->id_contexto, 'Docente Componente'))->toBeTrue();
    expect(arcTieneRolActivo($this->userB->id_usuario, $taller->id_contexto, 'Docente Componente Colegiado'))->toBeTrue();

    $audit = app(\App\Services\Authorization\RolesPermissionsConfigSyncService::class)->auditDatabaseDrift();
    expect($audit['in_sync'])->toBeTrue();
});

test('RolesPermissionsConfigSyncService valida scripts/roles_config.php y rechaza roles malformados', function () {
    $syncService = app(\App\Services\Authorization\RolesPermissionsConfigSyncService::class);

    $configSlugs = $syncService->extractPermissionSlugsFromConfig();
    $enumSlugs = array_map(fn (Permissions $p) => $p->value, Permissions::cases());
    sort($enumSlugs);
    expect($configSlugs)->toBe($enumSlugs);

    $validatedRoles = $syncService->loadAndValidateRolesFromConfig();
    expect($validatedRoles)->toHaveKeys(['Docente Titular', 'Docente Componente', 'Docente Componente Colegiado']);

    // 1. Archivo temporal con un rol malformado por jerarquías incompatibles sin contexto no-global en común
    $tmpPath = storage_path('framework/testing_malformed_roles_' . uniqid() . '.php');
    file_put_contents($tmpPath, <<<'PHP'
<?php
use App\Support\Permissions;

return [
    'Rol Malformado Test' => [
        'es_administrativo' => false,
        'permisos' => [
            [Permissions::CURSOS_CREAR, false],
            [Permissions::FACULTADES_EDITAR, false],
        ],
    ],
];
PHP);

    try {
        expect(fn () => $syncService->loadAndValidateRolesFromConfig($tmpPath))
            ->toThrow(\RuntimeException::class, 'jerarquías incompatibles');
    } finally {
        @unlink($tmpPath);
    }

    // 2. Archivo temporal con formato de tupla inválido (falta booleano puede_delegar_permiso)
    $tmpTuplePath = storage_path('framework/testing_malformed_tuple_' . uniqid() . '.php');
    file_put_contents($tmpTuplePath, <<<'PHP'
<?php
use App\Support\Permissions;

return [
    'Rol Tupla Invalida Test' => [
        'es_administrativo' => false,
        'permisos' => [
            Permissions::CURSOS_VER,
        ],
    ],
];
PHP);

    try {
        expect(fn () => $syncService->loadAndValidateRolesFromConfig($tmpTuplePath))
            ->toThrow(\RuntimeException::class, 'Permiso inválido');
    } finally {
        @unlink($tmpTuplePath);
    }
});
