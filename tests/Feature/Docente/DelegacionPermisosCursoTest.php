<?php

namespace Tests\Feature\Docente;

use App\Http\Middleware\ForcePasswordChange;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoComponente;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\Authorization\RolesPermissionsConfigSyncService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DelegacionPermisosCursoTest extends TestCase
{
    use DatabaseTransactions;

    protected Usuario $titular;
    protected Docente $docenteTitular;

    protected Usuario $colegiado;
    protected Docente $docenteColegiado;

    protected Usuario $externo;
    protected Docente $docenteExterno;

    protected Curso $curso;
    protected Componente $componente;

    protected Rol $rolTitular;
    protected Rol $rolComponente;
    protected Rol $rolColegiado;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // Asegurar matriz de roles/permisos/delegabilidad alineada con scripts/*_config.php
        app(RolesPermissionsConfigSyncService::class)
            ->syncAll(['Docente Titular', 'Docente Componente', 'Docente Componente Colegiado']);

        $this->titular = Usuario::factory()->create([
            'email' => 'titular@test.com',
            'fecha_cambio_passhash' => now(),
            'fecha_verificacion_email' => now(),
        ]);
        $this->docenteTitular = Docente::factory()->create(['id_usuario' => $this->titular->id_usuario]);

        $this->colegiado = Usuario::factory()->create([
            'email' => 'colegiado@test.com',
            'fecha_cambio_passhash' => now(),
            'fecha_verificacion_email' => now(),
        ]);
        $this->docenteColegiado = Docente::factory()->create(['id_usuario' => $this->colegiado->id_usuario]);

        $this->externo = Usuario::factory()->create([
            'email' => 'externo@test.com',
            'fecha_cambio_passhash' => now(),
            'fecha_verificacion_email' => now(),
        ]);
        $this->docenteExterno = Docente::factory()->create(['id_usuario' => $this->externo->id_usuario]);

        $this->rolTitular    = Rol::where('nombre', 'Docente Titular')->firstOrFail();
        $this->rolComponente = Rol::where('nombre', 'Docente Componente')->firstOrFail();
        $this->rolColegiado  = Rol::where('nombre', 'Docente Componente Colegiado')->firstOrFail();

        // Externo tiene rol docente en contexto global (pasa IsDocente), pero no pertenece a este curso
        $this->asignarRol($this->externo->id_usuario, $this->rolColegiado->id_rol, 1);

        $asignacionPlan = AsignacionPlan::first() ?? AsignacionPlan::firstOrCreate([
            'id_plan' => 1,
            'id_asignatura' => 1,
            'agno_planificado' => 1,
            'semestre_planificado' => 1,
            'id_contexto' => 1,
        ]);

        // Crear Curso con titular (trigger BD asigna id_contexto)
        $this->curso = Curso::create([
            'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
            'id_docente_titular' => $this->docenteTitular->id_docente,
            'cod_curso' => random_int(800000, 899999),
            'nombre' => 'Curso de Prueba Delegación',
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addMonths(6)->toDateString(),
            'semestre_real' => 1,
            'agno_real' => 2026,
            'indice_grupo' => 1,
            'estado_interno' => 'ABIERTO',
            'es_plantilla' => false,
        ]);
        $this->curso->refresh();

        // Titular del curso -> Docente Titular en el contexto del curso
        $this->asignarRol($this->titular->id_usuario, $this->rolTitular->id_rol, $this->curso->id_contexto);

        $tipo = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);

        // Crear Componente (Cátedra) dictado por Titular y acompañado por Colegiado
        $this->componente = Componente::create([
            'id_curso' => $this->curso->id_curso,
            'id_tipo_componente' => $tipo->id_tipo_componente,
            'total_alumnos' => 30,
            'genera_acta' => true,
            'porcentaje_aprobacion' => 100,
            'aprobacion_obligatoria' => true,
            'porcentaje_asistencia_obligatoria' => 75,
        ]);
        $this->componente->refresh();

        DocenteComponente::create([
            'id_componente' => $this->componente->id_componente,
            'id_docente' => $this->docenteTitular->id_docente,
            'es_titular' => true,
        ]);
        $this->asignarRol($this->titular->id_usuario, $this->rolComponente->id_rol, $this->componente->id_contexto);

        DocenteComponente::create([
            'id_componente' => $this->componente->id_componente,
            'id_docente' => $this->docenteColegiado->id_docente,
            'es_titular' => false,
        ]);
        $this->asignarRol($this->colegiado->id_usuario, $this->rolColegiado->id_rol, $this->componente->id_contexto);
    }

    private function asignarRol(int $idUsuario, int $idRol, int $idContexto): void
    {
        UsuarioRolAsignacion::firstOrCreate(
            ['id_usuario' => $idUsuario, 'id_rol' => $idRol, 'id_contexto' => $idContexto],
            [
                'asignado_por' => $idUsuario,
                'fecha_inicio_planificada' => now()->subMinute(),
                'fecha_fin_planificada' => now()->addYears(5),
                'esta_activo' => true,
                'fue_eliminado' => false,
                'creado_por' => $idUsuario,
            ]
        );
    }

    public function test_usuario_externo_no_puede_acceder_ni_delegar_permisos(): void
    {
        $responseIndex = $this->actingAs($this->externo)
            ->withoutMiddleware(ForcePasswordChange::class)
            ->get(route('docente.cursos.delegacion-permisos.index', $this->curso));

        $responseIndex->assertStatus(403);

        $responseToggle = $this->actingAs($this->externo)
            ->withoutMiddleware(ForcePasswordChange::class)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slug' => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1->value,
                'otorgar' => true,
            ]);

        $responseToggle->assertStatus(403);
    }

    public function test_docente_no_titular_no_puede_delegar_permisos(): void
    {
        $response = $this->actingAs($this->colegiado)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slug' => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar' => true,
                'id_contexto' => $this->componente->id_contexto,
            ]);

        $response->assertStatus(403);
    }

    public function test_docente_titular_no_puede_auto_delegarse_permisos(): void
    {
        $response = $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->titular->id_usuario,
                'slug' => Permissions::CURSOS_EDITAR->value,
                'otorgar' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_docente_titular_no_puede_delegar_a_usuario_externo_al_curso(): void
    {
        $response = $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->externo->id_usuario,
                'slug' => Permissions::CURSOS_EDITAR->value,
                'otorgar' => true,
            ]);

        $response->assertStatus(403);
    }

    public function test_docente_titular_puede_otorgar_y_revocar_permiso_usando_enum(): void
    {
        $permisoSlug = Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1;
        $permisoDb = Permiso::where('slug', $permisoSlug->value)->firstOrFail();

        $this->assertFalse(
            $this->colegiado->can($permisoSlug, $this->curso),
            'Precondición: el colegiado no debería tener permisos de edición de syllabus módulo 1 por defecto'
        );

        // 1. Otorgar
        $responseOtorgar = $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slug' => $permisoSlug->value,
                'otorgar' => true,
            ]);

        $responseOtorgar->assertRedirect();
        $responseOtorgar->assertSessionHas('success');

        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario' => $this->colegiado->id_usuario,
            'id_permiso' => $permisoDb->id_permiso,
            'id_contexto' => $this->curso->id_contexto,
            'esta_activo' => true,
            'fue_borrado' => false,
            'esta_permitido' => true,
        ]);

        $colegiadoOtorgado = Usuario::find($this->colegiado->id_usuario);
        $this->assertTrue($colegiadoOtorgado->can($permisoSlug, $this->curso));

        // 2. Revocar
        $responseRevocar = $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slug' => $permisoSlug->value,
                'otorgar' => false,
            ]);

        $responseRevocar->assertRedirect();
        $responseRevocar->assertSessionHas('success');

        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario' => $this->colegiado->id_usuario,
            'id_permiso' => $permisoDb->id_permiso,
            'id_contexto' => $this->curso->id_contexto,
            'esta_activo' => false,
            'fue_borrado' => true,
        ]);

        $colegiadoRevocado = Usuario::find($this->colegiado->id_usuario);
        $this->assertFalse($colegiadoRevocado->can($permisoSlug, $this->curso));
    }

    public function test_docente_delegado_con_actividades_evaluar_puede_evaluar_actividad_fuera_de_su_componente(): void
    {
        // Crear segundo componente donde el titular del curso también es titular de componente
        $tipo = TipoComponente::firstOrCreate(['tipo' => 'Laboratorio']);
        $otroComponente = Componente::create([
            'id_curso' => $this->curso->id_curso,
            'id_tipo_componente' => $tipo->id_tipo_componente,
            'total_alumnos' => 20,
            'genera_acta' => true,
            'porcentaje_aprobacion' => 100,
            'aprobacion_obligatoria' => true,
            'porcentaje_asistencia_obligatoria' => 75,
        ]);
        $otroComponente->refresh();

        DocenteComponente::create([
            'id_componente' => $otroComponente->id_componente,
            'id_docente' => $this->docenteTitular->id_docente,
            'es_titular' => true,
        ]);
        $this->asignarRol($this->titular->id_usuario, $this->rolComponente->id_rol, $otroComponente->id_contexto);

        $unidad = Unidad::create([
            'id_curso' => $this->curso->id_curso,
            'nombre' => 'Unidad 1 Test',
            'num_unidad' => 1,
            'es_plantilla' => false,
        ]);

        $actividad = Actividad::create([
            'id_componente' => $otroComponente->id_componente,
            'id_unidad' => $unidad->id_unidad,
            'nombre' => 'Control 1 Laboratorio',
            'descripcion' => 'Evaluación de laboratorio',
            'ponderacion' => 20,
            'tipo_actividad' => 'SUMATIVA',
            'tipo_entrega' => 'SIN_ENTREGA',
            'es_sumativa' => true,
            'es_grupal' => true,
            'trae_archivo' => false,
            'nro_dias_adicionales_para_bloqueo' => 0,
        ]);

        // Sin permiso delegado: colegiado recibe 403
        $responseSinPermiso = $this->actingAs($this->colegiado)
            ->post(route('docente.cursos.actividades.grupos.create', [$this->curso, $actividad]), [
                'nombre_grupo' => 'Grupo 1',
                'estudiantes' => [999],
            ]);

        $responseSinPermiso->assertStatus(403);

        // Titular del componente delega ACTIVIDADES_EVALUAR en contexto de otroComponente
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slug' => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar' => true,
                'id_contexto' => $otroComponente->id_contexto,
            ])
            ->assertSessionHas('success');

        $userColegiado = Usuario::find($this->colegiado->id_usuario);
        $this->assertTrue($userColegiado->can(Permissions::ACTIVIDADES_EVALUAR, $otroComponente));

        $responseConPermiso = $this->actingAs($userColegiado)
            ->post(route('docente.cursos.actividades.grupos.create', [$this->curso, $actividad]), [
                'nombre_grupo' => 'Grupo 1',
                'estudiantes' => [999],
            ]);

        $this->assertNotEquals(403, $responseConPermiso->status());
    }

    public function test_docente_titular_puede_otorgar_y_revocar_permisos_en_lote_batch(): void
    {
        // Lote a nivel Curso
        $slugsCurso = [
            Permissions::CURSOS_UNIDADES_CREAR->value,
            Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_6->value,
        ];

        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.batch', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slugs' => $slugsCurso,
                'otorgar' => true,
                'id_contexto' => $this->curso->id_contexto,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($slugsCurso as $slug) {
            $this->assertTrue($this->colegiado->can(Permissions::from($slug), $this->curso));
        }

        // Lote a nivel Componente
        $slugsComponente = [
            Permissions::ACTIVIDADES_EVALUAR->value,
            Permissions::ACTIVIDADES_DAR_FEEDBACK->value,
        ];

        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.batch', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slugs' => $slugsComponente,
                'otorgar' => true,
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($slugsComponente as $slug) {
            $this->assertTrue($this->colegiado->can(Permissions::from($slug), $this->componente));
        }

        // Revocar lote componente
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.batch', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slugs' => $slugsComponente,
                'otorgar' => false,
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($slugsComponente as $slug) {
            $this->assertFalse($this->colegiado->can(Permissions::from($slug), $this->componente));
        }
    }

    public function test_colegiado_requiere_delegacion_para_evaluar_propio_componente_y_no_accede_a_ajeno(): void
    {
        $tipoOtro = TipoComponente::firstOrCreate(['tipo' => 'Laboratorio']);
        $componenteAjeno = Componente::create([
            'id_curso' => $this->curso->id_curso,
            'id_tipo_componente' => $tipoOtro->id_tipo_componente,
            'total_alumnos' => 30,
            'genera_acta' => true,
            'porcentaje_aprobacion' => 100,
            'aprobacion_obligatoria' => true,
            'porcentaje_asistencia_obligatoria' => 75,
        ]);

        $unidad = Unidad::firstOrCreate([
            'id_curso' => $this->curso->id_curso,
            'num_unidad' => 1,
        ], [
            'nombre' => 'Unidad 1',
            'es_plantilla' => false,
        ]);

        $actividadPropia = Actividad::create([
            'id_componente' => $this->componente->id_componente,
            'id_unidad' => $unidad->id_unidad,
            'nombre' => 'Actividad Propia Colegiado',
            'ponderacion' => 10,
            'tipo_actividad' => 'SUMATIVA',
            'tipo_entrega' => 'SIN_ENTREGA',
            'es_sumativa' => true,
            'es_grupal' => false,
            'trae_archivo' => false,
            'nro_dias_adicionales_para_bloqueo' => 0,
        ]);

        $actividadAjena = Actividad::create([
            'id_componente' => $componenteAjeno->id_componente,
            'id_unidad' => $unidad->id_unidad,
            'nombre' => 'Actividad Ajena Sin Delegar',
            'ponderacion' => 10,
            'tipo_actividad' => 'SUMATIVA',
            'tipo_entrega' => 'SIN_ENTREGA',
            'es_sumativa' => true,
            'es_grupal' => false,
            'trae_archivo' => false,
            'nro_dias_adicionales_para_bloqueo' => 0,
        ]);

        // 1. Colegiado en piso mínimo NO puede evaluar siquiera su propio componente sin delegación
        $this->actingAs($this->colegiado)
            ->get(route('docente.cursos.actividades.evaluacion', [$this->curso, $actividadPropia]))
            ->assertStatus(403);

        // 2. Titular del componente delega ACTIVIDADES_EVALUAR dentro del componente propio
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slug' => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar' => true,
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertSessionHas('success');

        $userColegiado = Usuario::find($this->colegiado->id_usuario);

        // 3. Tras delegación, evalúa actividad propia y edita rúbrica, pero sigue recibiendo 403 en componente ajeno
        $this->actingAs($userColegiado)
            ->get(route('docente.cursos.actividades.evaluacion', [$this->curso, $actividadPropia]))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
                $page->component('docente/Actividades')
                    ->where('puede_editar_rubrica', true)
            );

        $this->actingAs($userColegiado)
            ->get(route('docente.cursos.actividades.evaluacion', [$this->curso, $actividadAjena]))
            ->assertStatus(403);
    }

    public function test_docente_titular_puede_delegar_permisos_en_contexto_de_componente_especifico(): void
    {
        $permisoDb = Permiso::where('slug', Permissions::ACTIVIDADES_EVALUAR->value)->firstOrFail();
        $idContextoComponente = $this->componente->id_contexto;

        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => true,
                'id_contexto' => $idContextoComponente,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'     => $this->colegiado->id_usuario,
            'id_permiso'     => $permisoDb->id_permiso,
            'id_contexto'    => $idContextoComponente,
            'esta_activo'    => true,
            'fue_borrado'    => false,
            'esta_permitido' => true,
        ]);

        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => false,
                'id_contexto' => $idContextoComponente,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoDb->id_permiso,
            'id_contexto' => $idContextoComponente,
            'esta_activo' => false,
            'fue_borrado' => true,
        ]);
    }

    public function test_index_carga_todos_los_contextos_sin_query_parameter(): void
    {
        $response = $this->actingAs($this->titular)
            ->get(route('docente.cursos.delegacion-permisos.index', $this->curso));

        $response->assertOk();
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
            $page->component('docente/DelegacionPermisosCurso')
                ->where('id_contexto_curso', $this->curso->id_contexto)
                ->where('puedeDelegarCurso', true)
                ->has('componentes', 1)
                ->has('delegablesPorContexto')
                ->has('dependencias')
                ->has('miembros.0.permisos_por_contexto.' . $this->curso->id_contexto)
                ->has('miembros.0.permisos_por_contexto.' . $this->componente->id_contexto)
        );
    }

    public function test_superseding_permiso_en_curso_se_hereda_y_bloquea_en_componente(): void
    {
        // Dar al titular actividades:ver delegable en el Curso (válido en Curso y Componente)
        $permActVer = Permiso::where('slug', Permissions::ACTIVIDADES_VER->value)->firstOrFail();
        \App\Models\Usuario\UsuarioPermisoEspecial::create([
            'id_usuario'               => $this->titular->id_usuario,
            'id_permiso'               => $permActVer->id_permiso,
            'id_contexto'              => $this->curso->id_contexto,
            'esta_permitido'           => true,
            'puede_delegar'            => true,
            'esta_activo'              => true,
            'fue_borrado'              => false,
            'creado_por'               => $this->titular->id_usuario,
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada'    => now()->addMonths(6),
        ]);
        app(\App\Services\Authorization\PermissionCache::class)->olvidarUsuario($this->titular->id_usuario);

        // 1. Titular otorga ACTIVIDADES_VER en Curso general
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_VER->value,
                'otorgar'     => true,
                'id_contexto' => $this->curso->id_contexto,
            ])
            ->assertSessionHas('success');

        // 2. En componente aparece como 'heredado'
        $response = $this->actingAs($this->titular)
            ->get(route('docente.cursos.delegacion-permisos.index', $this->curso));

        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
            $page->component('docente/DelegacionPermisosCurso')
                ->where(
                    'miembros.0.permisos_por_contexto.' . $this->componente->id_contexto . '.' . Permissions::ACTIVIDADES_VER->value . '.origen',
                    'heredado'
                )
                ->where(
                    'miembros.0.permisos_por_contexto.' . $this->componente->id_contexto . '.' . Permissions::ACTIVIDADES_VER->value . '.activo',
                    true
                )
        );

        // 3. Revocar desde componente queda bloqueado por superseding del Curso
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_VER->value,
                'otorgar'     => false,
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertSessionHasErrors(['slug']);
    }

    public function test_grafo_de_dependencias_resuelve_prerrequisitos_y_cascada(): void
    {
        $permisoEvaluar = Permiso::where('slug', Permissions::ACTIVIDADES_EVALUAR->value)->firstOrFail();
        $permisoVer     = Permiso::where('slug', Permissions::ACTIVIDADES_VER->value)->firstOrFail();

        // 1. Otorgar evaluar en componente otorga automáticamente ver (upstream)
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => true,
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoEvaluar->id_permiso,
            'id_contexto' => $this->componente->id_contexto,
            'esta_activo' => true,
        ]);
        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoVer->id_permiso,
            'id_contexto' => $this->componente->id_contexto,
            'esta_activo' => true,
        ]);

        // 2. Revocar ver revoca evaluar en cascada (downstream)
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_VER->value,
                'otorgar'     => false,
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoVer->id_permiso,
            'id_contexto' => $this->componente->id_contexto,
            'esta_activo' => false,
            'fue_borrado' => true,
        ]);
        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoEvaluar->id_permiso,
            'id_contexto' => $this->componente->id_contexto,
            'esta_activo' => false,
            'fue_borrado' => true,
        ]);
    }

    public function test_permisos_curso_no_vienen_heredados_por_defecto_desde_roles_globales(): void
    {
        $response = $this->actingAs($this->titular)
            ->get(route('docente.cursos.delegacion-permisos.index', $this->curso));

        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
            $page->component('docente/DelegacionPermisosCurso')
                ->where(
                    'miembros.0.permisos_por_contexto.' . $this->curso->id_contexto . '.' . Permissions::ACTIVIDADES_VER->value . '.origen',
                    'inactivo'
                )
                ->where(
                    'miembros.0.permisos_por_contexto.' . $this->curso->id_contexto . '.' . Permissions::CURSOS_VER->value . '.origen',
                    'inactivo'
                )
                ->where(
                    'miembros.0.permisos_por_contexto.' . $this->curso->id_contexto . '.' . Permissions::ACTIVIDADES_GRUPOS_VER->value . '.origen',
                    'inactivo'
                )
        );
    }

    public function test_otorgar_en_componente_con_prerrequisito_activo_en_curso_mantiene_prerrequisito_heredado(): void
    {
        $permisoEvaluar = Permiso::where('slug', Permissions::ACTIVIDADES_EVALUAR->value)->firstOrFail();
        $permisoActVer  = Permiso::where('slug', Permissions::ACTIVIDADES_VER->value)->firstOrFail();

        // Dar al titular actividades:ver delegable en Curso
        \App\Models\Usuario\UsuarioPermisoEspecial::create([
            'id_usuario'               => $this->titular->id_usuario,
            'id_permiso'               => $permisoActVer->id_permiso,
            'id_contexto'              => $this->curso->id_contexto,
            'esta_permitido'           => true,
            'puede_delegar'            => true,
            'esta_activo'              => true,
            'fue_borrado'              => false,
            'creado_por'               => $this->titular->id_usuario,
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada'    => now()->addMonths(6),
        ]);
        app(\App\Services\Authorization\PermissionCache::class)->olvidarUsuario($this->titular->id_usuario);

        // 1. En Curso general, se otorga ACTIVIDADES_VER al colegiado
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_VER->value,
                'otorgar'     => true,
                'id_contexto' => $this->curso->id_contexto,
            ])
            ->assertSessionHas('success');

        // 2. En Componente, se otorga ACTIVIDADES_EVALUAR (requiere ACTIVIDADES_VER)
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => true,
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoEvaluar->id_permiso,
            'id_contexto' => $this->componente->id_contexto,
            'esta_activo' => true,
        ]);
        $this->assertDatabaseMissing('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoActVer->id_permiso,
            'id_contexto' => $this->componente->id_contexto,
            'esta_activo' => true,
        ]);

        $response = $this->actingAs($this->titular)
            ->get(route('docente.cursos.delegacion-permisos.index', $this->curso));

        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
            $page->component('docente/DelegacionPermisosCurso')
                ->where(
                    'miembros.0.permisos_por_contexto.' . $this->componente->id_contexto . '.' . Permissions::ACTIVIDADES_VER->value . '.origen',
                    'heredado'
                )
                ->where(
                    'miembros.0.permisos_por_contexto.' . $this->componente->id_contexto . '.' . Permissions::ACTIVIDADES_EVALUAR->value . '.origen',
                    'directo'
                )
        );
    }

    public function test_caso_lab_cedido_dt_pasa_a_colegiado_y_titular_de_componente_delega_acotado(): void
    {
        $cecilia = Usuario::factory()->create([
            'email' => 'cecilia_lab@test.com',
            'fecha_cambio_passhash' => now(),
            'fecha_verificacion_email' => now(),
        ]);
        $docCecilia = Docente::factory()->create(['id_usuario' => $cecilia->id_usuario]);

        $tipoLab = TipoComponente::firstOrCreate(['tipo' => 'Laboratorio']);
        $lab = Componente::create([
            'id_curso' => $this->curso->id_curso,
            'id_tipo_componente' => $tipoLab->id_tipo_componente,
            'total_alumnos' => 25,
            'genera_acta' => true,
            'porcentaje_aprobacion' => 100,
            'aprobacion_obligatoria' => true,
            'porcentaje_asistencia_obligatoria' => 75,
        ]);
        $lab->refresh();

        // Cecilia es titular del Laboratorio; el DT (titular curso) quedó como Colegiado en Laboratorio
        DocenteComponente::create(['id_componente' => $lab->id_componente, 'id_docente' => $docCecilia->id_docente, 'es_titular' => true]);
        DocenteComponente::create(['id_componente' => $lab->id_componente, 'id_docente' => $this->docenteTitular->id_docente, 'es_titular' => false]);
        DocenteComponente::create(['id_componente' => $lab->id_componente, 'id_docente' => $this->docenteColegiado->id_docente, 'es_titular' => false]);

        $this->asignarRol($cecilia->id_usuario, $this->rolComponente->id_rol, $lab->id_contexto);
        $this->asignarRol($this->titular->id_usuario, $this->rolColegiado->id_rol, $lab->id_contexto);
        $this->asignarRol($this->colegiado->id_usuario, $this->rolColegiado->id_rol, $lab->id_contexto);

        $unidad = Unidad::create([
            'id_curso' => $this->curso->id_curso,
            'nombre' => 'Unidad Lab',
            'num_unidad' => 1,
            'es_plantilla' => false,
        ]);

        $actLab = Actividad::create([
            'id_componente' => $lab->id_componente,
            'id_unidad' => $unidad->id_unidad,
            'nombre' => 'Actividad Lab Ajena al DT',
            'ponderacion' => 20,
            'tipo_actividad' => 'SUMATIVA',
            'tipo_entrega' => 'SIN_ENTREGA',
            'es_sumativa' => true,
            'es_grupal' => false,
            'trae_archivo' => false,
            'nro_dias_adicionales_para_bloqueo' => 0,
        ]);

        // 1. El DT del curso NO puede evaluar, ni tomar asistencia, ni delegar dentro del Lab cedido a Cecilia
        $this->actingAs($this->titular)
            ->get(route('docente.cursos.actividades.evaluacion', [$this->curso, $actLab]))
            ->assertStatus(403);

        $this->actingAs($this->titular)
            ->getJson(route('docente.cursos.componentes.asistencia.index', [$this->curso, $lab]))
            ->assertStatus(403);

        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => true,
                'id_contexto' => $lab->id_contexto,
            ])
            ->assertStatus(403);

        // 2. Cecilia (Docente Componente no-DT) entra a delegacion-permisos solo con su Lab,
        //    ve tanto a Colegiado como al propio DT (en calidad de colegiado en el Lab) y delega allí
        $this->actingAs($cecilia)
            ->get(route('docente.cursos.delegacion-permisos.index', $this->curso))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
                $page->component('docente/DelegacionPermisosCurso')
                    ->where('puedeDelegarCurso', false)
                    ->has('componentes', 1)
                    ->where('componentes.0.id_contexto', $lab->id_contexto)
            );

        $this->actingAs($cecilia)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->titular->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => true,
                'id_contexto' => $lab->id_contexto,
            ])
            ->assertSessionHas('success');

        // 3. Cecilia recibe 403 si intenta delegar en Curso o en Cátedra (otro componente)
        $this->actingAs($cecilia)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::CURSOS_EDITAR->value,
                'otorgar'     => true,
                'id_contexto' => $this->curso->id_contexto,
            ])
            ->assertStatus(403);

        $this->actingAs($cecilia)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => true,
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertStatus(403);
    }

    public function test_colegiado_piso_minimo_y_regla_no_delegable(): void
    {
        $unidad = Unidad::create([
            'id_curso' => $this->curso->id_curso,
            'nombre' => 'Unidad Piso Mínimo',
            'num_unidad' => 1,
            'es_plantilla' => false,
        ]);

        $estUser = Usuario::factory()->create();
        $estudiante = Estudiante::factory()->create(['id_usuario' => $estUser->id_usuario]);
        $inscripcion = InscripcionCurso::create([
            'id_curso' => $this->curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'cod_inscripcion_intranet' => random_int(100000, 999999),
            'estado_inscripcion' => 'INSCRITO',
            'fecha_inscripcion' => now(),
            'es_manual' => true,
        ]);

        // Colegiado sin delegación es rechazado por políticas y controladores
        $this->assertFalse($this->colegiado->can('create', [Unidad::class, $this->curso]));
        $this->assertFalse($this->colegiado->can('update', $unidad));
        $this->assertFalse($this->colegiado->can('delete', $unidad));
        $this->assertFalse($this->colegiado->can('view', $inscripcion));

        $this->actingAs($this->colegiado)
            ->getJson(route('docente.cursos.componentes.asistencia.index', [$this->curso, $this->componente]))
            ->assertStatus(403);

        // Permisos marcados no delegables (puede_delegar = false) no pueden delegarse ni por el DT/DC
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::CURSOS_ELIMINAR->value,
                'otorgar'     => true,
                'id_contexto' => $this->curso->id_contexto,
            ])
            ->assertStatus(403);

        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::COMPONENTES_ASISTENCIA_ELIMINAR->value,
                'otorgar'     => true,
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertStatus(403);
    }

    public function test_tri_estado_revocar_permiso_de_rol_y_restaurar_a_neutro(): void
    {
        // Convertir al colegiado en Docente Componente (tiene actividades:editar por rol base)
        $this->asignarRol($this->colegiado->id_usuario, $this->rolComponente->id_rol, $this->componente->id_contexto);
        $validator = app(\App\Services\Authorization\PermissionValidator::class);

        $this->assertTrue(
            $validator->validate($this->colegiado->fresh(), Permissions::ACTIVIDADES_EDITAR, $this->componente)
        );

        // Titular aplica DENY explícito ('revocado') en el componente
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EDITAR->value,
                'estado'      => 'revocado',
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse(
            $validator->validate($this->colegiado->fresh(), Permissions::ACTIVIDADES_EDITAR, $this->componente)
        );

        // El mapa de la matriz muestra estado='revocado' y activoPorRol=true
        $permisosDb = Permiso::whereIn('slug', [Permissions::ACTIVIDADES_EDITAR->value])->get()->keyBy('slug');
        $mapa = app(\App\Services\Docente\PermisosCursoService::class)->mapaPermisosPorContextosCurso(
            $this->colegiado->id_usuario,
            $this->curso,
            collect([$this->componente]),
            $permisosDb
        );
        $celda = $mapa[$this->componente->id_contexto][Permissions::ACTIVIDADES_EDITAR->value];
        $this->assertSame('revocado', $celda['estado']);
        $this->assertTrue($celda['activoPorRol']);

        // Al devolver a 'neutro', se elimina el DENY y vuelve a aplicar el rol base
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EDITAR->value,
                'estado'      => 'neutro',
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            $validator->validate($this->colegiado->fresh(), Permissions::ACTIVIDADES_EDITAR, $this->componente)
        );
    }

    public function test_revocacion_en_curso_bloquea_componente_hijo(): void
    {
        // Dar al titular actividades:ver delegable a nivel de Curso (válido en Curso y Componente)
        $permActVer = Permiso::where('slug', Permissions::ACTIVIDADES_VER->value)->firstOrFail();
        \App\Models\Usuario\UsuarioPermisoEspecial::create([
            'id_usuario'               => $this->titular->id_usuario,
            'id_permiso'               => $permActVer->id_permiso,
            'id_contexto'              => $this->curso->id_contexto,
            'esta_permitido'           => true,
            'puede_delegar'            => true,
            'esta_activo'              => true,
            'fue_borrado'              => false,
            'creado_por'               => $this->titular->id_usuario,
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada'    => now()->addMonths(6),
        ]);
        app(\App\Services\Authorization\PermissionCache::class)->olvidarUsuario($this->titular->id_usuario);

        // Titular revoca actividades:ver a nivel de Curso
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_VER->value,
                'estado'      => 'revocado',
                'id_contexto' => $this->curso->id_contexto,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $permisosDb = Permiso::whereIn('slug', [Permissions::ACTIVIDADES_VER->value])->get()->keyBy('slug');
        $mapa = app(\App\Services\Docente\PermisosCursoService::class)->mapaPermisosPorContextosCurso(
            $this->colegiado->id_usuario,
            $this->curso,
            collect([$this->componente]),
            $permisosDb
        );

        $celdaHijo = $mapa[$this->componente->id_contexto][Permissions::ACTIVIDADES_VER->value];
        $this->assertSame('revocado', $celdaHijo['estado']);
        $this->assertSame('revocado', $celdaHijo['bloqueadoPor']);

        // Intentar otorgar en el componente hijo debe fallar con error de validación
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_VER->value,
                'estado'      => 'asignado',
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('slug');
    }

    public function test_colision_padre_hijo_requiere_confirmacion_y_neutraliza_hijos(): void
    {
        // Dar al titular actividades:ver delegable a nivel de Curso
        $permActVer = Permiso::where('slug', Permissions::ACTIVIDADES_VER->value)->firstOrFail();
        \App\Models\Usuario\UsuarioPermisoEspecial::create([
            'id_usuario'               => $this->titular->id_usuario,
            'id_permiso'               => $permActVer->id_permiso,
            'id_contexto'              => $this->curso->id_contexto,
            'esta_permitido'           => true,
            'puede_delegar'            => true,
            'esta_activo'              => true,
            'fue_borrado'              => false,
            'creado_por'               => $this->titular->id_usuario,
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada'    => now()->addMonths(6),
        ]);
        app(\App\Services\Authorization\PermissionCache::class)->olvidarUsuario($this->titular->id_usuario);

        // 1. Asignar actividades:ver directamente en el componente hijo
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_VER->value,
                'estado'      => 'asignado',
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // 2. Intentar revocar en el Curso SIN confirmar_cascada_hijos -> debe advertir y no mutar
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_VER->value,
                'estado'      => 'revocado',
                'id_contexto' => $this->curso->id_contexto,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('confirmar_cascada_hijos')
            ->assertSessionHas('colisiones_hijos');

        // El permiso del hijo sigue asignado
        $service = app(\App\Services\Docente\PermisosCursoService::class);
        $this->assertTrue(
            $service->tienePermisoActivoEnContexto($this->colegiado->id_usuario, $this->componente->id_contexto, Permissions::ACTIVIDADES_VER->value)
        );

        // 3. Reenviar CON confirmar_cascada_hijos=true -> neutraliza hijo y revoca en padre
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'              => $this->colegiado->id_usuario,
                'slug'                    => Permissions::ACTIVIDADES_VER->value,
                'estado'                  => 'revocado',
                'id_contexto'             => $this->curso->id_contexto,
                'confirmar_cascada_hijos' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse(
            $service->tienePermisoActivoEnContexto($this->colegiado->id_usuario, $this->componente->id_contexto, Permissions::ACTIVIDADES_VER->value)
        );
        $estadosCurso = $service->estadosUpeEnContexto($this->colegiado->id_usuario, $this->curso->id_contexto, [Permissions::ACTIVIDADES_VER->value]);
        $this->assertSame('revocado', $estadosCurso[Permissions::ACTIVIDADES_VER->value] ?? null);
    }
}
