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
        $this->assertTrue(
            $this->colegiado->can($permisoSlug, $this->curso),
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
        $this->assertFalse(
            $this->colegiado->can($permisoSlug, $this->curso),
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
        $this->colegiado->refresh();
        $this->assertTrue(
            $this->colegiado->can(Permissions::ACTIVIDADES_EVALUAR, $this->curso),
            'Colegiado debe tener ACTIVIDADES_EVALUAR tras delegación'
        );

        // Con permiso delegado: el colegiado pasa el guard assertPuedeEditarEvaluacion
        // y no recibe 403 (llega a validación de campos)
        $userColegiado = Usuario::find($this->colegiado->id_usuario);

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
}
