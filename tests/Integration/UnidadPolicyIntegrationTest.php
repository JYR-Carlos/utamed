<?php

/**
 * Integration Test: UnidadPolicy
 *
 * Verifica que los custom fallbacks de UnidadPolicy funcionan:
 *   - Docente titular del componente → puede crear/editar/eliminar unidades
 *   - Docente colegiado asignado     → puede crear/editar/eliminar unidades
 *   - Docente sin asignación         → no puede
 *   - SuperAdmin                     → siempre puede (via before())
 *
 * Usa BD de testing real. No usa RefreshDatabase.
 */

use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\TipoContexto;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Policies\UnidadPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class);

// ─── Setup / Teardown ────────────────────────────────────────────────────────

beforeEach(function () {

    $this->prefix = 'up_' . substr(uniqid(), -6);

    // ── Contexto global ─────────────────────────────────────────────────────
    $tipoSystem = TipoContexto::firstOrCreate(
        ['categoria' => 'global'],
        ['nombre'    => 'Global']
    );
    $contextoGlobal = Contexto::firstOrCreate(
        ['contexto_display' => 'Contexto Global | Solo Permisos Administrativos'],
        ['id_tipo_contexto'  => $tipoSystem->id_tipo_contexto]
    );
    $this->contextoGlobalId = $contextoGlobal->id_contexto;

    // ── Usuario admin de sistema (FK creado_por) ─────────────────────────────
    $adminSistema = Usuario::firstOrCreate(
        ['username' => 'admin_sistema'],
        [
            'rut'         => '00000000-0',
            'nombre1'     => 'Admin',
            'apellido1'   => 'Sistema',
            'email'       => 'admin@sistema.local',
            'passhash'    => Hash::make('admin123'),
            'esta_activo' => true,
        ]
    );
    $this->adminSistemaId = $adminSistema->id_usuario;

    // ── Limpiar datos de tests anteriores ────────────────────────────────────
    $usernames = [
        "{$this->prefix}_titular",
        "{$this->prefix}_colegiado",
        "{$this->prefix}_externo",
        "{$this->prefix}_superadmin",
    ];
    $userIds = Usuario::withTrashed()->whereIn('username', $usernames)->pluck('id_usuario');
    if ($userIds->isNotEmpty()) {
        UsuarioPermisoEspecial::whereIn('id_usuario', $userIds)->delete();
        DB::table('usuario.usuario_rol_asignacion')->whereIn('id_usuario', $userIds)->delete();
        DB::table('usuario.docente')->whereIn('id_usuario', $userIds)->delete();
        Usuario::withTrashed()->whereIn('id_usuario', $userIds)->forceDelete();
    }

    DB::table('curso.unidad')->where('nombre', 'like', "{$this->prefix}%")->delete();
    DB::table('curso.docente_componente')
        ->whereIn('id_componente', fn($q) => $q->select('id_componente')
            ->from('curso.componente')
            ->whereIn('id_curso', fn($q2) => $q2->select('id_curso')
                ->from('curso.curso')
                ->where('nombre', 'like', "{$this->prefix}%")))
        ->delete();
    DB::table('curso.componente')
        ->whereIn('id_curso', fn($q) => $q->select('id_curso')
            ->from('curso.curso')
            ->where('nombre', 'like', "{$this->prefix}%"))
        ->delete();
    Curso::withTrashed()->where('nombre', 'like', "{$this->prefix}%")->forceDelete();
    DB::table('asignacion_plan')
        ->whereIn('id_asignatura', fn($q) => $q->select('id_asignatura')
            ->from('asignatura')
            ->where('cod_asignatura', 'like', "{$this->prefix}%"))
        ->delete();
    DB::table('asignatura')->where('cod_asignatura', 'like', "{$this->prefix}%")->delete();
    DB::table('plan')
        ->whereIn('id_carrera', fn($q) => $q->select('id_carrera')
            ->from('carrera')
            ->where('nombre', 'like', "{$this->prefix}%"))
        ->delete();
    DB::table('carrera')->where('nombre', 'like', "{$this->prefix}%")->delete();
    DB::table('departamento')->where('nombre', 'like', "{$this->prefix}%")->delete();
    DB::table('facultad')->where('nombre', 'like', "{$this->prefix}%")->delete();

    // ── Crear docentes ────────────────────────────────────────────────────────
    $crearDocente = function (string $username, string $rut, string $email): array {
        $user = Usuario::create([
            'username'    => $username,
            'rut'         => $rut,
            'nombre1'     => 'Test',
            'apellido1'   => 'Docente',
            'email'       => $email,
            'passhash'    => Hash::make('pass'),
            'esta_activo' => true,
        ]);
        $idDocente = DB::table('usuario.docente')->insertGetId([
            'id_usuario' => $user->id_usuario,
            'grado'      => 'Licenciado',
            'titulo'     => 'Título Test',
            'cargo'      => 'Profesor',
        ], 'id_docente');
        $user->refresh();
        return ['user' => $user, 'id_docente' => $idDocente];
    };

    $titular   = $crearDocente("{$this->prefix}_titular",   '81111111-1', "{$this->prefix}_titular@t.local");
    $colegiado = $crearDocente("{$this->prefix}_colegiado", '82222222-2', "{$this->prefix}_colegiado@t.local");
    $externo   = $crearDocente("{$this->prefix}_externo",   '83333333-3', "{$this->prefix}_externo@t.local");

    $this->userTitular        = $titular['user'];
    $this->userColegiado      = $colegiado['user'];
    $this->userExterno        = $externo['user'];
    $this->idDocenteTitular   = $titular['id_docente'];
    $this->idDocenteColegiado = $colegiado['id_docente'];

    // ── Crear SuperAdmin ──────────────────────────────────────────────────────
    $this->userSuperAdmin = Usuario::create([
        'username'    => "{$this->prefix}_superadmin",
        'rut'         => '84444444-4',
        'nombre1'     => 'Super',
        'apellido1'   => 'Admin',
        'email'       => "{$this->prefix}_superadmin@t.local",
        'passhash'    => Hash::make('pass'),
        'esta_activo' => true,
    ]);
    $permisoWildcard = Permiso::firstOrCreate(
        ['slug'  => '*'],
        ['nombre' => 'Wildcard global', 'descripcion' => 'Acceso total']
    );
    UsuarioPermisoEspecial::create([
        'id_usuario'               => $this->userSuperAdmin->id_usuario,
        'id_permiso'               => $permisoWildcard->id_permiso,
        'id_contexto'              => $this->contextoGlobalId,
        'esta_permitido'           => true,
        'puede_delegar'            => true,
        'esta_activo'              => true,
        'fue_borrado'              => false,
        'fecha_inicio_planificada' => now(),
        'fecha_fin_planificada'    => now()->addYears(100),
        'creado_por'               => $this->adminSistemaId,
    ]);

    // ── Estructura administrativa para Curso (requiere id_asignacion_plan) ────
    $facultadId = DB::table('facultad')->insertGetId([
        'nombre'             => "{$this->prefix} Facultad",
        'fecha_creacion'     => now(),
        'fecha_modificacion' => now(),
    ], 'id_facultad');

    $departamentoId = DB::table('departamento')->insertGetId([
        'nombre'             => "{$this->prefix} Depto",
        'id_facultad'        => $facultadId,
        'fecha_creacion'     => now(),
        'fecha_modificacion' => now(),
    ], 'id_departamento');

    $carrera = Carrera::create([
        'nombre'          => "{$this->prefix} Carrera",
        'jornada'         => 'Diurna',
        'sede'            => 'Central',
        'modalidad'       => 'Presencial',
        'id_departamento' => $departamentoId,
    ]);
    $carrera->refresh();

    $plan = Plan::create([
        'id_carrera'     => $carrera->id_carrera,
        'agno'           => now()->year,
        'version_plan'   => 1,
    ]);

    $asignaturaId = DB::table('asignatura')->insertGetId([
        'cod_asignatura'      => substr($this->prefix, 0, 10) . '-A',
        'nombre'              => "{$this->prefix} Asignatura",
        'creditos_sct'        => 4,
        'horas_catedra'       => 3,
        'horas_taller'        => 0,
        'horas_laboratorio'   => 0,
        'horas_dirigidas'     => 0,
        'horas_autonomas'     => 0,
        'fecha_creacion'      => now(),
        'fecha_modificacion'  => now(),
    ], 'id_asignatura');

    $asignacionPlanId = DB::table('asignacion_plan')->insertGetId([
        'id_asignatura'        => $asignaturaId,
        'id_plan'              => $plan->id_plan,
        'agno_planificado'     => 1,
        'semestre_planificado' => 1,
        'fecha_creacion'       => now(),
    ], 'id_asignacion_plan');

    // ── Crear Curso ───────────────────────────────────────────────────────────
    $this->curso = Curso::create([
        'nombre'             => "{$this->prefix} Curso",
        'fecha_inicio'       => now(),
        'fecha_fin'          => now()->addMonths(4),
        'agno_real'          => now()->year,
        'semestre_real'      => 1,
        'estado_interno'     => 'ABIERTO',
        'es_plantilla'       => true,
        'id_asignacion_plan' => $asignacionPlanId,
        'id_docente_titular' => $this->idDocenteTitular,
    ]);
    $this->curso->refresh();

    // ── Crear tipo_componente y componente ────────────────────────────────────
    $idTipoComp = DB::table('curso.tipo_componente')->insertGetId(
        ['tipo' => "{$this->prefix}_tipo"],
        'id_tipo_componente'
    );

    $this->idComponente = DB::table('curso.componente')->insertGetId([
        'id_curso'           => $this->curso->id_curso,
        'id_tipo_componente' => $idTipoComp,
        'id_contexto'        => $this->curso->id_contexto,
    ], 'id_componente');

    // ── Asignar titular y colegiado al componente ─────────────────────────────
    DB::table('curso.docente_componente')->insert([
        'id_docente'    => $this->idDocenteTitular,
        'id_componente' => $this->idComponente,
        'es_titular'    => true,
    ]);
    DB::table('curso.docente_componente')->insert([
        'id_docente'    => $this->idDocenteColegiado,
        'id_componente' => $this->idComponente,
        'es_titular'    => false,
    ]);

    // ── Crear Unidad ──────────────────────────────────────────────────────────
    $this->idUnidad = DB::table('curso.unidad')->insertGetId([
        'nombre'       => "{$this->prefix} Unidad",
        'num_unidad'   => 1,
        'id_curso'     => $this->curso->id_curso,
        'es_plantilla' => true,
    ], 'id_unidad');

    $this->unidad = Unidad::find($this->idUnidad);
});

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('UnidadPolicy — create (customCreate fallback)', function () {

    test('docente titular del componente PUEDE crear unidades', function () {
        $this->actingAs($this->userTitular);
        expect(Auth::user()->can('create', [Unidad::class, $this->curso]))->toBeTrue();
    });

    test('docente colegiado asignado PUEDE crear unidades', function () {
        $this->actingAs($this->userColegiado);
        expect(Auth::user()->can('create', [Unidad::class, $this->curso]))->toBeTrue();
    });

    test('docente externo sin asignación NO puede crear unidades', function () {
        $this->actingAs($this->userExterno);
        expect(Auth::user()->can('create', [Unidad::class, $this->curso]))->toBeFalse();
    });

    test('superadmin PUEDE crear unidades (via before())', function () {
        $this->actingAs($this->userSuperAdmin);
        expect(Auth::user()->can('create', [Unidad::class, $this->curso]))->toBeTrue();
    });
});

describe('UnidadPolicy — update (customUpdate fallback)', function () {

    test('docente titular PUEDE editar la unidad', function () {
        $this->actingAs($this->userTitular);
        expect(Auth::user()->can('update', $this->unidad))->toBeTrue();
    });

    test('docente colegiado PUEDE editar la unidad', function () {
        $this->actingAs($this->userColegiado);
        expect(Auth::user()->can('update', $this->unidad))->toBeTrue();
    });

    test('docente externo NO puede editar la unidad', function () {
        $this->actingAs($this->userExterno);
        expect(Auth::user()->can('update', $this->unidad))->toBeFalse();
    });

    test('superadmin PUEDE editar la unidad (via before())', function () {
        $this->actingAs($this->userSuperAdmin);
        expect(Auth::user()->can('update', $this->unidad))->toBeTrue();
    });
});

describe('UnidadPolicy — delete (customDelete fallback)', function () {

    test('docente titular PUEDE eliminar la unidad', function () {
        $this->actingAs($this->userTitular);
        expect(Auth::user()->can('delete', $this->unidad))->toBeTrue();
    });

    test('docente colegiado PUEDE eliminar la unidad', function () {
        $this->actingAs($this->userColegiado);
        expect(Auth::user()->can('delete', $this->unidad))->toBeTrue();
    });

    test('docente externo NO puede eliminar la unidad', function () {
        $this->actingAs($this->userExterno);
        expect(Auth::user()->can('delete', $this->unidad))->toBeFalse();
    });

    test('superadmin PUEDE eliminar la unidad (via before())', function () {
        $this->actingAs($this->userSuperAdmin);
        expect(Auth::user()->can('delete', $this->unidad))->toBeTrue();
    });
});

describe('UnidadPolicy — edge cases (acceso directo a la policy)', function () {

    test('customCreate retorna false si $parent no es Curso', function () {
        $method = new ReflectionMethod(UnidadPolicy::class, 'customCreate');
        $method->setAccessible(true);
        $policy = new UnidadPolicy();
        expect($method->invoke($policy, $this->userTitular, null))->toBeFalse();
        expect($method->invoke($policy, $this->userTitular, 'string-invalido'))->toBeFalse();
    });

    test('customUpdate retorna false si la unidad no tiene curso cargado', function () {
        $unidadSinCurso = new Unidad();
        $method = new ReflectionMethod(UnidadPolicy::class, 'customUpdate');
        $method->setAccessible(true);
        $policy = new UnidadPolicy();
        expect($method->invoke($policy, $this->userTitular, $unidadSinCurso))->toBeFalse();
    });

    test('docente externo sin perfil docente NO puede acceder', function () {
        $userSinDocente = Usuario::create([
            'username'    => "{$this->prefix}_sin_docente",
            'rut'         => '85555555-5',
            'nombre1'     => 'Sin',
            'apellido1'   => 'Perfil',
            'email'       => "{$this->prefix}_sin_docente@t.local",
            'passhash'    => Hash::make('pass'),
            'esta_activo' => true,
        ]);
        $this->actingAs($userSinDocente);
        expect(Auth::user()->can('create', [Unidad::class, $this->curso]))->toBeFalse();
        expect(Auth::user()->can('update', $this->unidad))->toBeFalse();
        $userSinDocente->forceDelete();
    });
});
