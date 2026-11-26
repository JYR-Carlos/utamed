<?php

namespace Tests\Feature\Docente;

use App\Http\Middleware\ForcePasswordChange;
use App\Models\Administrativo\AsignacionPlan;
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
use App\Services\Authorization\PermissionCache;
use App\Services\Authorization\RolesPermissionsConfigSyncService;
use App\Services\UserCoursesService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TraspasoPermisosFrontendTest extends TestCase
{
    use DatabaseTransactions;

    protected Usuario $titular;
    protected Docente $docenteTitular;
    protected Usuario $colegiado;
    protected Docente $docenteColegiado;
    protected Curso $curso;
    protected Componente $comp1;
    protected Componente $comp2;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        app(RolesPermissionsConfigSyncService::class)
            ->syncAll(['Docente Titular', 'Docente Componente', 'Docente Componente Colegiado']);

        $this->titular = Usuario::factory()->create([
            'email' => 'titular-traspaso@test.com',
            'fecha_cambio_passhash' => now(),
            'fecha_verificacion_email' => now(),
        ]);
        $this->docenteTitular = Docente::factory()->create(['id_usuario' => $this->titular->id_usuario]);

        $this->colegiado = Usuario::factory()->create([
            'email' => 'colegiado-traspaso@test.com',
            'fecha_cambio_passhash' => now(),
            'fecha_verificacion_email' => now(),
        ]);
        $this->docenteColegiado = Docente::factory()->create(['id_usuario' => $this->colegiado->id_usuario]);

        $asignacionPlan = AsignacionPlan::firstOrFail();

        $this->curso = Curso::create([
            'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
            'id_docente_titular' => $this->docenteTitular->id_docente,
            'cod_curso' => 98101,
            'nombre' => 'Curso Traspaso Permisos',
            'fecha_inicio' => now()->subMonth(),
            'fecha_fin' => now()->addMonths(4),
            'agno_real' => (int) now()->format('Y'),
            'semestre_real' => 1,
            'estado_interno' => 'ACTIVO',
            'estado_acta' => 'EN_CURSO',
            'es_plantilla' => false,
            'vers_plantilla' => 1,
        ])->fresh();

        $tipos = TipoComponente::take(2)->get();
        $tipo1 = $tipos[0];
        $tipo2 = $tipos[1] ?? $tipos[0];

        $this->comp1 = Componente::create([
            'id_curso' => $this->curso->id_curso,
            'id_tipo_componente' => $tipo1->id_tipo_componente,
            'genera_acta' => true,
            'aprobacion_obligatoria' => true,
            'porcentaje_aprobacion' => 60,
            'porcentaje_asistencia_obligatoria' => 75,
        ])->fresh();

        $this->comp2 = Componente::create([
            'id_curso' => $this->curso->id_curso,
            'id_tipo_componente' => $tipo2->id_tipo_componente,
            'genera_acta' => false,
            'aprobacion_obligatoria' => false,
            'porcentaje_aprobacion' => 60,
            'porcentaje_asistencia_obligatoria' => 0,
        ])->fresh();

        $rolTitular = Rol::where('nombre', 'Docente Titular')->firstOrFail();
        $rolColegiado = Rol::where('nombre', 'Docente Componente Colegiado')->firstOrFail();

        UsuarioRolAsignacion::create([
            'id_usuario' => $this->titular->id_usuario,
            'id_rol' => $rolTitular->id_rol,
            'id_contexto' => $this->curso->id_contexto,
            'asignado_por' => $this->titular->id_usuario,
            'creado_por' => $this->titular->id_usuario,
            'esta_activo' => true,
            'fue_eliminado' => false,
            'fecha_inicio_planificada' => now()->subDay(),
            'fecha_fin_planificada' => now()->addYear(),
        ]);

        foreach ([$this->comp1, $this->comp2] as $comp) {
            DocenteComponente::create([
                'id_docente' => $this->docenteColegiado->id_docente,
                'id_componente' => $comp->id_componente,
                'es_titular' => false,
            ]);

            UsuarioRolAsignacion::create([
                'id_usuario' => $this->colegiado->id_usuario,
                'id_rol' => $rolColegiado->id_rol,
                'id_contexto' => $comp->id_contexto,
                'asignado_por' => $this->titular->id_usuario,
                'creado_por' => $this->titular->id_usuario,
                'esta_activo' => true,
                'fue_eliminado' => false,
                'fecha_inicio_planificada' => now()->subDay(),
                'fecha_fin_planificada' => now()->addYear(),
            ]);
        }

        // Dar cursos:ver al colegiado en el curso para poder abrir el detalle del curso
        $this->crearUpe($this->colegiado->id_usuario, Permissions::CURSOS_VER, $this->curso->id_contexto, true);
        app(PermissionCache::class)->olvidarUsuario($this->colegiado->id_usuario);
    }

    private function crearUpe(int $idUsuario, Permissions $perm, int $idContexto, bool $permitido): void
    {
        $permiso = Permiso::where('slug', $perm->value)->firstOrFail();

        UsuarioPermisoEspecial::create([
            'id_usuario' => $idUsuario,
            'id_permiso' => $permiso->id_permiso,
            'id_contexto' => $idContexto,
            'esta_permitido' => $permitido,
            'puede_delegar' => false,
            'esta_activo' => true,
            'fue_borrado' => false,
            'creado_por' => $this->titular->id_usuario,
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada' => now()->addMonths(6),
        ]);
    }

    public function test_user_courses_service_retorna_slugs_efectivos_y_respeta_deny(): void
    {
        // El titular tiene cursos:ver y cursos:editar en el curso; revocamos cursos:editar con DENY
        $this->crearUpe($this->titular->id_usuario, Permissions::CURSOS_EDITAR, $this->curso->id_contexto, false);
        app(PermissionCache::class)->olvidarUsuario($this->titular->id_usuario);

        $cursos = app(UserCoursesService::class)->getDocenteCourses($this->docenteTitular);
        $cursoItem = collect($cursos)->firstWhere('id_curso', $this->curso->id_curso);

        $this->assertNotNull($cursoItem);
        $this->assertIsArray($cursoItem['permisos']);
        $this->assertContains(Permissions::CURSOS_VER->value, $cursoItem['permisos']);
        $this->assertNotContains(Permissions::CURSOS_EDITAR->value, $cursoItem['permisos']);
    }

    public function test_docente_curso_y_actividades_envian_user_permissions_by_component_y_respetan_deny(): void
    {
        // Otorgar actividades:crear y actividades:ver SOLO en comp1 (no en comp2)
        $this->crearUpe($this->colegiado->id_usuario, Permissions::ACTIVIDADES_VER, $this->comp1->id_contexto, true);
        $this->crearUpe($this->colegiado->id_usuario, Permissions::ACTIVIDADES_CREAR, $this->comp1->id_contexto, true);
        app(PermissionCache::class)->olvidarUsuario($this->colegiado->id_usuario);

        // 1. CursoDetalle recibe userPermissionsByComponent diferenciado entre comp1 y comp2
        $this->actingAs($this->colegiado)
            ->withoutMiddleware(ForcePasswordChange::class)
            ->get(route('docente.cursos.show', $this->curso))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docente/CursoDetalle')
                ->where('curso.userPermissions', fn ($perms) => in_array(Permissions::CURSOS_VER->value, $perms->toArray(), true))
                ->where("curso.userPermissionsByComponent.{$this->comp1->id_componente}", fn ($perms) => in_array(Permissions::ACTIVIDADES_CREAR->value, $perms->toArray(), true))
                ->where("curso.userPermissionsByComponent.{$this->comp2->id_componente}", fn ($perms) => !in_array(Permissions::ACTIVIDADES_CREAR->value, $perms->toArray(), true))
            );

        // 2. Actividades recibe userPermissionsByComponent y un DENY en Curso (sobre actividades:editar, válido en curso/componente) revoca el permiso del componente hijo
        $this->crearUpe($this->colegiado->id_usuario, Permissions::ACTIVIDADES_EDITAR, $this->comp1->id_contexto, true);
        $this->crearUpe($this->colegiado->id_usuario, Permissions::ACTIVIDADES_EDITAR, $this->curso->id_contexto, false);
        app(PermissionCache::class)->olvidarUsuario($this->colegiado->id_usuario);

        $this->actingAs($this->colegiado)
            ->withoutMiddleware(ForcePasswordChange::class)
            ->get(route('docente.cursos.actividades.index', $this->curso))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docente/Actividades')
                ->where("curso.userPermissionsByComponent.{$this->comp1->id_componente}", fn ($perms) => in_array(Permissions::ACTIVIDADES_VER->value, $perms->toArray(), true)
                    && in_array(Permissions::ACTIVIDADES_CREAR->value, $perms->toArray(), true)
                    && !in_array(Permissions::ACTIVIDADES_EDITAR->value, $perms->toArray(), true))
            );
    }

    public function test_handle_inertia_requests_resuelve_permisos_globales_efectivos(): void
    {
        // Otorgar un permiso especial en contexto global (id=1)
        $this->crearUpe($this->colegiado->id_usuario, Permissions::CURSOS_VER, 1, true);
        app(PermissionCache::class)->olvidarUsuario($this->colegiado->id_usuario);

        $this->actingAs($this->colegiado)
            ->withoutMiddleware(ForcePasswordChange::class)
            ->get(route('docente.cursos.show', $this->curso))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', fn ($perms) => in_array(Permissions::CURSOS_VER->value, $perms->toArray(), true))
            );
    }
}
