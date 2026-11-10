<?php

namespace Tests\Feature\Docente;

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\TipoComponente;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Models\Usuario\UsuarioRolAsignacion;
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

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // 1. Crear usuarios y docentes con password y verificación para evitar redirects
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

        $rolTitular = Rol::firstOrCreate(['nombre' => 'Docente Titular']);
        $rolComponente = Rol::firstOrCreate(['nombre' => 'Docente Componente Colegiado']);

        // Titular con rol 'Docente Titular'
        UsuarioRolAsignacion::firstOrCreate(
            ['id_usuario' => $this->titular->id_usuario, 'id_rol' => $rolTitular->id_rol, 'id_contexto' => 1],
            [
                'asignado_por' => $this->titular->id_usuario,
                'fecha_inicio_planificada' => now()->subMinute(),
                'fecha_fin_planificada' => now()->addYears(5),
                'esta_activo' => true,
                'fue_eliminado' => false,
                'creado_por' => $this->titular->id_usuario,
            ]
        );

        // Colegiado y Externo con rol 'Docente Componente'
        foreach ([$this->colegiado, $this->externo] as $usr) {
            UsuarioRolAsignacion::firstOrCreate(
                ['id_usuario' => $usr->id_usuario, 'id_rol' => $rolComponente->id_rol, 'id_contexto' => 1],
                [
                    'asignado_por' => $usr->id_usuario,
                    'fecha_inicio_planificada' => now()->subMinute(),
                    'fecha_fin_planificada' => now()->addYears(5),
                    'esta_activo' => true,
                    'fue_eliminado' => false,
                    'creado_por' => $usr->id_usuario,
                ]
            );
        }

        // 2. Obtener o crear AsignacionPlan
        $asignacionPlan = AsignacionPlan::first() ?? AsignacionPlan::firstOrCreate([
            'id_plan' => 1,
            'id_asignatura' => 1,
            'agno_planificado' => 1,
            'semestre_planificado' => 1,
            'id_contexto' => 1,
        ]);

        // 3. Crear Curso con titular (dejar que el trigger de BD cree y asigne id_contexto con su jerarquía real)
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

        $tipo = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);

        // 4. Crear Componente y vincular al colegiado
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
            'id_docente' => $this->docenteColegiado->id_docente,
            'es_titular' => false,
        ]);
    }

    /**
     * Test 1: Seguridad y Guardias de Acceso (Matriz y Toggle)
     */
    public function test_usuario_externo_no_puede_acceder_ni_delegar_permisos(): void
    {
        // Usuario ajeno al curso intenta acceder al panel de delegación
        $responseIndex = $this->actingAs($this->externo)
            ->get(route('docente.cursos.delegacion-permisos.index', $this->curso));

        $responseIndex->assertStatus(403);

        // Usuario ajeno intenta hacer toggle
        $responseToggle = $this->actingAs($this->externo)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slug' => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar' => true,
            ]);

        $responseToggle->assertStatus(403);
    }

    public function test_docente_no_titular_no_puede_delegar_permisos(): void
    {
        // El colegiado (que sí está en el curso pero no es titular) intenta delegar
        $response = $this->actingAs($this->colegiado)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slug' => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar' => true,
            ]);

        $response->assertStatus(403);
    }

    public function test_docente_titular_no_puede_auto_delegarse_permisos(): void
    {
        // Anti-auto-delegación
        $response = $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->titular->id_usuario,
                'slug' => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_docente_titular_no_puede_delegar_a_usuario_externo_al_curso(): void
    {
        // IDOR guard: target fuera del equipo
        $response = $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->externo->id_usuario,
                'slug' => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar' => true,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test 2: Ciclo de Vida de Delegación (Otorgar y Revocar) usando enum Permissions
     */
    public function test_docente_titular_puede_otorgar_y_revocar_permiso_usando_enum(): void
    {
        $permisoSlug = Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1;

        // Asegurar que el permiso exista en BD
        $permisoDb = Permiso::firstOrCreate(
            ['slug' => $permisoSlug->value],
            ['nombre' => 'Modificar Módulo 1', 'modulo' => 'Programas']
        );

        // Pre-condición: el colegiado NO tiene este permiso en el curso
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

        // Verificar en BD
        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario' => $this->colegiado->id_usuario,
            'id_permiso' => $permisoDb->id_permiso,
            'id_contexto' => $this->curso->id_contexto,
            'esta_activo' => true,
            'fue_borrado' => false,
            'esta_permitido' => true,
        ]);

        // Verificar con motor de validación can() usando enum
        $colegiadoOtorgado = Usuario::find($this->colegiado->id_usuario);
        $this->assertTrue(
            $colegiadoOtorgado->can($permisoSlug, $this->curso),
            'El usuario colegiado debería tener el permiso otorgado evaluado con can(Permissions::...)'
        );

        // 2. Revocar
        $responseRevocar = $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slug' => $permisoSlug->value,
                'otorgar' => false,
            ]);

        $responseRevocar->assertRedirect();
        $responseRevocar->assertSessionHas('success');

        // Verificar en BD que quedó inactivo / borrado
        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario' => $this->colegiado->id_usuario,
            'id_permiso' => $permisoDb->id_permiso,
            'id_contexto' => $this->curso->id_contexto,
            'esta_activo' => false,
            'fue_borrado' => true,
        ]);

        // Verificar que can() usando enum ahora retorne false
        $colegiadoRevocado = Usuario::find($this->colegiado->id_usuario);
        $this->assertFalse(
            $colegiadoRevocado->can($permisoSlug, $this->curso),
            'El usuario colegiado ya no debería tener el permiso tras la revocación'
        );
    }

    /**
     * Test 3: Validación de Efectividad en Evaluación de Actividades
     *
     * Un docente no-titular que no dicta el componente de una actividad recibe 403 al evaluar.
     * Al delegarle el titular el permiso ACTIVIDADES_EVALUAR, queda autorizado a evaluar.
     */
    public function test_docente_delegado_con_actividades_evaluar_puede_evaluar_actividad_fuera_de_su_componente(): void
    {
        // 1. Crear un segundo componente donde el colegiado NO está asignado
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

        // 2. Crear Unidad y una actividad en ese otro componente
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

        // Sin permiso delegado: el colegiado intenta crear grupo en la actividad y recibe 403
        $responseSinPermiso = $this->actingAs($this->colegiado)
            ->post(route('docente.cursos.actividades.grupos.create', [$this->curso, $actividad]), [
                'nombre_grupo' => 'Grupo 1',
                'estudiantes' => [999],
            ]);

        $responseSinPermiso->assertStatus(403);

        // 3. El Titular le delega ACTIVIDADES_EVALUAR
        Permiso::firstOrCreate(
            ['slug' => Permissions::ACTIVIDADES_EVALUAR->value],
            ['nombre' => 'Evaluar Actividades', 'modulo' => 'Actividades']
        );

        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slug' => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar' => true,
            ])
            ->assertSessionHas('success');

        // Refrescar usuario autenticado
        $userColegiado = Usuario::find($this->colegiado->id_usuario);
        $this->assertTrue(
            $userColegiado->can(Permissions::ACTIVIDADES_EVALUAR, $this->curso),
            'Colegiado debe tener ACTIVIDADES_EVALUAR tras delegación'
        );

        // Con permiso delegado: el colegiado pasa el guard assertPuedeEditarEvaluacion
        // y no recibe 403 (llega a validación de campos)
        $responseConPermiso = $this->actingAs($userColegiado)
            ->post(route('docente.cursos.actividades.grupos.create', [$this->curso, $actividad]), [
                'nombre_grupo' => 'Grupo 1',
                'estudiantes' => [999],
            ]);

        $this->assertNotEquals(403, $responseConPermiso->status());

        $this->assertNotEquals(403, $responseConPermiso->status());
    }

    /**
     * Test 4: Delegación y Revocación en Lote (Batch)
     */
    public function test_docente_titular_puede_otorgar_y_revocar_permisos_en_lote_batch(): void
    {
        $slugs = [
            Permissions::ACTIVIDADES_EVALUAR->value,
            Permissions::ACTIVIDADES_DAR_FEEDBACK->value,
            Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_6->value,
        ];

        foreach ($slugs as $slug) {
            Permiso::firstOrCreate(['slug' => $slug], ['nombre' => $slug, 'modulo' => 'Test']);
        }

        // 1. Otorgar en lote
        $responseOtorgar = $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.batch', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slugs' => $slugs,
                'otorgar' => true,
            ]);

        $responseOtorgar->assertRedirect();
        $responseOtorgar->assertSessionHas('success');

        foreach ($slugs as $slug) {
            $this->assertTrue(
                $this->colegiado->can(Permissions::from($slug), $this->curso),
                "Colegiado debe tener {$slug} otorgado tras batch"
            );
        }

        // 2. Revocar en lote
        $responseRevocar = $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.batch', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slugs' => $slugs,
                'otorgar' => false,
            ]);

        $responseRevocar->assertRedirect();
        $responseRevocar->assertSessionHas('success');

        foreach ($slugs as $slug) {
            $this->assertFalse(
                $this->colegiado->can(Permissions::from($slug), $this->curso),
                "Colegiado NO debe tener {$slug} tras revocación batch"
            );
        }
    }

    /**
     * Test 5: Autorización de showEvaluacion y permiso de rúbricas por componente y delegación
     */
    public function test_docente_no_puede_ver_evaluacion_de_componente_ajeno_pero_si_propio_con_edicion_de_rubrica(): void
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

        // 1. Actividad propia: el colegiado accede y puede editar la rúbrica
        $responsePropia = $this->actingAs($this->colegiado)
            ->get(route('docente.cursos.actividades.evaluacion', [$this->curso, $actividadPropia]));

        $responsePropia->assertOk();
        $responsePropia->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
            $page->component('docente/Activities/Index')
                ->where('puede_editar_rubrica', true)
        );

        // 2. Actividad ajena: el colegiado recibe 403 Forbidden
        $responseAjena = $this->actingAs($this->colegiado)
            ->get(route('docente.cursos.actividades.evaluacion', [$this->curso, $actividadAjena]));

        $responseAjena->assertStatus(403);

        // 3. Titular delega ACTIVIDADES_EVALUAR al colegiado
        Permiso::firstOrCreate(
            ['slug' => Permissions::ACTIVIDADES_EVALUAR->value],
            ['nombre' => 'Evaluar Actividades', 'modulo' => 'Actividades']
        );

        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario' => $this->colegiado->id_usuario,
                'slug' => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar' => true,
            ])
            ->assertSessionHas('success');

        $userColegiado = Usuario::find($this->colegiado->id_usuario);

        // 4. Con permiso delegado: accede a la actividad ajena y puede editar rúbrica
        $responseAjenaConPermiso = $this->actingAs($userColegiado)
            ->get(route('docente.cursos.actividades.evaluacion', [$this->curso, $actividadAjena]));

        $responseAjenaConPermiso->assertOk();
        $responseAjenaConPermiso->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
            $page->component('docente/Activities/Index')
                ->where('puede_editar_rubrica', true)
        );
    }

    /**
     * Test 9: Delegación dirigida a un contexto específico de Componente
     */
    public function test_docente_titular_puede_delegar_permisos_en_contexto_de_componente_especifico(): void
    {
        $permisoDb = Permiso::firstOrCreate(
            ['slug' => Permissions::ACTIVIDADES_EVALUAR->value],
            ['nombre' => 'Evaluar Actividades', 'modulo' => 'Actividades']
        );

        $idContextoComponente = $this->componente->id_contexto;
        $this->assertNotNull($idContextoComponente, 'El componente debe tener id_contexto');

        // Otorgar permiso en el contexto del componente
        $response = $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => true,
                'id_contexto' => $idContextoComponente,
            ]);

        $response->assertSessionHas('success');

        // Verificar existencia en BD con id_contexto del componente
        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'     => $this->colegiado->id_usuario,
            'id_permiso'     => $permisoDb->id_permiso,
            'id_contexto'    => $idContextoComponente,
            'esta_activo'    => true,
            'fue_borrado'    => false,
            'esta_permitido' => true,
        ]);

        // Revocar en el mismo contexto
        $responseRevocar = $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => false,
                'id_contexto' => $idContextoComponente,
            ]);

        $responseRevocar->assertSessionHas('success');

        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'     => $this->colegiado->id_usuario,
            'id_permiso'     => $permisoDb->id_permiso,
            'id_contexto'    => $idContextoComponente,
            'esta_activo'    => false,
            'fue_borrado'    => true,
        ]);
    }

    /**
     * Test 10: Index carga matriz completa para todos los contextos del curso sin query parameters
     */
    public function test_index_carga_todos_los_contextos_sin_query_parameter(): void
    {
        $response = $this->actingAs($this->titular)
            ->get(route('docente.cursos.delegacion-permisos.index', $this->curso));

        $response->assertOk();
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
            $page->component('docente/DelegacionPermisosCurso')
                ->where('id_contexto_curso', $this->curso->id_contexto)
                ->has('componentes', 1)
                ->has('dependencias')
                ->has('miembros.0.permisos_por_contexto.' . $this->curso->id_contexto)
                ->has('miembros.0.permisos_por_contexto.' . $this->componente->id_contexto)
        );
    }

    /**
     * Test 11: Superseding de contextos - permiso otorgado en Curso prevalece como 'heredado' en Componente
     */
    public function test_superseding_permiso_en_curso_se_hereda_y_bloquea_en_componente(): void
    {
        Permiso::firstOrCreate(
            ['slug' => Permissions::ACTIVIDADES_EVALUAR->value],
            ['nombre' => 'Evaluar Actividades', 'modulo' => 'Actividades']
        );
        Permiso::firstOrCreate(
            ['slug' => Permissions::ACTIVIDADES_VER->value],
            ['nombre' => 'Ver Actividades', 'modulo' => 'Actividades']
        );

        // 1. Titular otorga permiso en Curso general
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => true,
                'id_contexto' => $this->curso->id_contexto,
            ])
            ->assertSessionHas('success');

        // 2. Verificar que el index reporta el permiso en el componente como 'heredado'
        $response = $this->actingAs($this->titular)
            ->get(route('docente.cursos.delegacion-permisos.index', $this->curso));

        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
            $page->component('docente/DelegacionPermisosCurso')
                ->where(
                    'miembros.0.permisos_por_contexto.' . $this->componente->id_contexto . '.' . Permissions::ACTIVIDADES_EVALUAR->value . '.origen',
                    'heredado'
                )
                ->where(
                    'miembros.0.permisos_por_contexto.' . $this->componente->id_contexto . '.' . Permissions::ACTIVIDADES_EVALUAR->value . '.activo',
                    true
                )
        );

        // 3. Intento de revocar en el componente debe ser bloqueado por superseding
        $responseBloqueo = $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => false,
                'id_contexto' => $this->componente->id_contexto,
            ]);

        $responseBloqueo->assertSessionHasErrors(['slug']);
    }

    /**
     * Test 12: Grafo de dependencias - otorgar evaluar otorga ver; revocar ver revoca evaluar en cascada
     */
    public function test_grafo_de_dependencias_resuelve_prerrequisitos_y_cascada(): void
    {
        $permisoEvaluar = Permiso::firstOrCreate(
            ['slug' => Permissions::ACTIVIDADES_EVALUAR->value],
            ['nombre' => 'Evaluar Actividades', 'modulo' => 'Actividades']
        );
        $permisoVer = Permiso::firstOrCreate(
            ['slug' => Permissions::ACTIVIDADES_VER->value],
            ['nombre' => 'Ver Actividades', 'modulo' => 'Actividades']
        );

        // 1. Otorgar evaluar debe otorgar automáticamente ver (upstream)
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => true,
                'id_contexto' => $this->curso->id_contexto,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoEvaluar->id_permiso,
            'id_contexto' => $this->curso->id_contexto,
            'esta_activo' => true,
        ]);
        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoVer->id_permiso,
            'id_contexto' => $this->curso->id_contexto,
            'esta_activo' => true,
        ]);

        // 2. Revocar ver debe revocar en cascada evaluar (downstream)
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_VER->value,
                'otorgar'     => false,
                'id_contexto' => $this->curso->id_contexto,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoVer->id_permiso,
            'id_contexto' => $this->curso->id_contexto,
            'esta_activo' => false,
            'fue_borrado' => true,
        ]);
        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoEvaluar->id_permiso,
            'id_contexto' => $this->curso->id_contexto,
            'esta_activo' => false,
            'fue_borrado' => true,
        ]);
    }

    /**
     * Test 13: En Curso general los permisos delegables no vienen heredados por defecto desde roles globales
     */
    public function test_permisos_curso_no_vienen_heredados_por_defecto_desde_roles_globales(): void
    {
        Permiso::firstOrCreate(
            ['slug' => Permissions::ACTIVIDADES_VER->value],
            ['nombre' => 'Ver Actividades', 'modulo' => 'Actividades']
        );
        Permiso::firstOrCreate(
            ['slug' => Permissions::COMPONENTES_VER->value],
            ['nombre' => 'Ver Componentes', 'modulo' => 'Cursos']
        );
        Permiso::firstOrCreate(
            ['slug' => Permissions::ACTIVIDADES_GRUPOS_VER->value],
            ['nombre' => 'Ver Grupos de Actividad', 'modulo' => 'Actividades']
        );

        $response = $this->actingAs($this->titular)
            ->get(route('docente.cursos.delegacion-permisos.index', $this->curso));

        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
            $page->component('docente/DelegacionPermisosCurso')
                ->where(
                    'miembros.0.permisos_por_contexto.' . $this->curso->id_contexto . '.' . Permissions::ACTIVIDADES_VER->value . '.origen',
                    'inactivo'
                )
                ->where(
                    'miembros.0.permisos_por_contexto.' . $this->curso->id_contexto . '.' . Permissions::COMPONENTES_VER->value . '.origen',
                    'inactivo'
                )
                ->where(
                    'miembros.0.permisos_por_contexto.' . $this->curso->id_contexto . '.' . Permissions::ACTIVIDADES_GRUPOS_VER->value . '.origen',
                    'inactivo'
                )
        );
    }

    /**
     * Test 14: Otorgar en componente con prerrequisito ya activo en Curso mantiene prerrequisito como heredado
     */
    public function test_otorgar_en_componente_con_prerrequisito_activo_en_curso_mantiene_prerrequisito_heredado(): void
    {
        $permisoEvaluar = Permiso::firstOrCreate(
            ['slug' => Permissions::ACTIVIDADES_EVALUAR->value],
            ['nombre' => 'Evaluar Actividades', 'modulo' => 'Actividades']
        );
        $permisoVer = Permiso::firstOrCreate(
            ['slug' => Permissions::ACTIVIDADES_VER->value],
            ['nombre' => 'Ver Actividades', 'modulo' => 'Actividades']
        );

        // 1. En Curso general, se otorga ACTIVIDADES_VER al colegiado
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_VER->value,
                'otorgar'     => true,
                'id_contexto' => $this->curso->id_contexto,
            ])
            ->assertSessionHas('success');

        // 2. En Componente, se otorga ACTIVIDADES_EVALUAR (que requiere ACTIVIDADES_VER)
        $this->actingAs($this->titular)
            ->post(route('docente.cursos.delegacion-permisos.toggle', $this->curso), [
                'id_usuario'  => $this->colegiado->id_usuario,
                'slug'        => Permissions::ACTIVIDADES_EVALUAR->value,
                'otorgar'     => true,
                'id_contexto' => $this->componente->id_contexto,
            ])
            ->assertSessionHas('success');

        // 3. Verificar que en la BD solo se creó la UPE de evaluar para el componente, NO de ver (que ya viene de curso)
        $this->assertDatabaseHas('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoEvaluar->id_permiso,
            'id_contexto' => $this->componente->id_contexto,
            'esta_activo' => true,
        ]);
        $this->assertDatabaseMissing('usuario.usuario_permiso_especial', [
            'id_usuario'  => $this->colegiado->id_usuario,
            'id_permiso'  => $permisoVer->id_permiso,
            'id_contexto' => $this->componente->id_contexto,
            'esta_activo' => true,
        ]);

        // 4. Verificar en Inertia que en el componente ACTIVIDADES_VER se mantiene como 'heredado'
        // y ACTIVIDADES_EVALUAR es 'directo'
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
}
