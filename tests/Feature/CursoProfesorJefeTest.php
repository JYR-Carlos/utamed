<?php

namespace Tests\Feature;

use App\Models\Usuario\Usuario;
use App\Models\Usuario\Docente;
use App\Models\Curso\Curso;
use App\Models\Curso\Componente;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\TipoComponente;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Usuario\Contexto;
use App\Services\CursoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CursoProfesorJefeTest extends TestCase
{
    use DatabaseTransactions;

    protected Usuario $profesor1;
    protected Usuario $profesor2;
    protected Docente $docente1;
    protected Docente $docente2;
    protected Curso $curso;
    protected Componente $componente;
    protected CursoService $cursoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cursoService = app(CursoService::class);

        // Create two teaching users
        $this->profesor1 = Usuario::factory()->create(['email' => 'profesor1@test.com']);
        $this->profesor2 = Usuario::factory()->create(['email' => 'profesor2@test.com']);

        // Create docente profiles
        $this->docente1 = Docente::factory()->create(['id_usuario' => $this->profesor1->id_usuario]);
        $this->docente2 = Docente::factory()->create(['id_usuario' => $this->profesor2->id_usuario]);

        // Create a course with profesor1 as titular
        $asignacionPlan = AsignacionPlan::first();
        if (!$asignacionPlan) {
            $asignacionPlan = AsignacionPlan::firstOrCreate([
                'id_plan' => 1,
                'id_asignatura' => 1,
                'agno_planificado' => 1,
                'semestre_planificado' => 1,
                'id_contexto' => 1,
            ]);
        }
        $contexto = Contexto::firstOrCreate(['contexto_display' => 'Curso Profesor Jefe ' . uniqid()]);
        $tipoComponente = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);

        $this->curso = Curso::create([
            'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
            'id_contexto' => $contexto->id_contexto,
            'id_docente_titular' => $this->docente1->id_docente,
            'cod_curso' => random_int(800000, 899999),
            'nombre' => 'Curso Test Profesor Jefe',
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addMonths(6)->toDateString(),
            'semestre_real' => 1,
            'agno_real' => 2026,
            'indice_grupo' => 1,
            'estado_interno' => 'ABIERTO',
        ]);
        $this->curso->refresh();

        // Create a component with profesor1 as titular
        $contextoComponente = Contexto::firstOrCreate(['contexto_display' => 'Componente Test ' . uniqid()]);
        $this->componente = Componente::create([
            'genera_acta' => true,
            'porcentaje_aprobacion' => 60,
            'aprobacion_obligatoria' => true,
            'porcentaje_asistencia_obligatoria' => 75,
            'id_curso' => $this->curso->id_curso,
            'id_tipo_componente' => $tipoComponente->id_tipo_componente,
            'id_contexto' => $contextoComponente->id_contexto,
        ]);

        DocenteComponente::create([
            'id_componente' => $this->componente->id_componente,
            'id_docente' => $this->docente1->id_docente,
            'es_titular' => true,
        ]);
    }

    /**
     * Test that current profesor jefe can manage course team
     */
    public function test_current_profesor_jefe_can_manage_team(): void
    {
        $this->actingAs($this->profesor1);

        // Profesor1 should be able to manage team
        $this->assertTrue(auth()->user()->can('manageTeam', $this->curso));
    }

    /**
     * Test that profesor jefe loses access after being replaced
     */
    public function test_profesor_jefe_loses_access_after_replacement(): void
    {
        // Update course to assign profesor2 as new titular
        $this->cursoService->update($this->curso, [
            'id_asignatura' => $this->curso->asignacionPlan->id_asignatura,
            'id_plan' => $this->curso->asignacionPlan->id_plan,
            'cod_curso' => $this->curso->cod_curso,
            'nombre' => $this->curso->nombre,
            'id_docente_sugerido' => $this->docente2->id_docente,
        ]);

        $this->curso->refresh();

        // Verify professor jefe changed
        $this->assertEquals($this->docente2->id_docente, $this->curso->id_docente_titular);

        // Profesor1 should NO longer be able to manage team
        $this->actingAs($this->profesor1);
        $this->assertFalse(auth()->user()->can('manageTeam', $this->curso));

        // Profesor2 SHOULD be able to manage team
        $this->actingAs($this->profesor2);
        $this->assertTrue(auth()->user()->can('manageTeam', $this->curso));
    }

    /**
     * Test that old professor is removed from component titular roles
     */
    public function test_old_profesor_removed_from_component_titular_roles(): void
    {
        // Verify profesor1 is currently titular of component
        $docenteComponenteAntes = DocenteComponente::where('id_componente', $this->componente->id_componente)
            ->where('id_docente', $this->docente1->id_docente)
            ->where('es_titular', true)
            ->first();
        $this->assertNotNull($docenteComponenteAntes);

        // Update course profesor jefe
        $this->cursoService->update($this->curso, [
            'id_asignatura' => $this->curso->asignacionPlan->id_asignatura,
            'id_plan' => $this->curso->asignacionPlan->id_plan,
            'cod_curso' => $this->curso->cod_curso,
            'nombre' => $this->curso->nombre,
            'id_docente_sugerido' => $this->docente2->id_docente,
        ]);

        // Verify profesor1 is removed from component
        $docenteComponenteDespues = DocenteComponente::where('id_componente', $this->componente->id_componente)
            ->where('id_docente', $this->docente1->id_docente)
            ->first();
        $this->assertNull($docenteComponenteDespues);

        // Verify profesor2 is now titular of component
        $docenteComponenteNuevo = DocenteComponente::where('id_componente', $this->componente->id_componente)
            ->where('id_docente', $this->docente2->id_docente)
            ->where('es_titular', true)
            ->first();
        $this->assertNotNull($docenteComponenteNuevo);
    }

    /**
     * Test that new professor is added to all components as titular
     */
    public function test_new_profesor_assigned_to_all_components(): void
    {
        // Create additional component with distinct type (Taller)
        $tipoComponente2 = TipoComponente::firstOrCreate(['tipo' => 'Taller']);
        $contextoComponente2 = Contexto::firstOrCreate(['contexto_display' => 'Componente Test 2 ' . uniqid()]);
        $componente2 = Componente::create([
            'genera_acta' => true,
            'porcentaje_aprobacion' => 60,
            'aprobacion_obligatoria' => true,
            'porcentaje_asistencia_obligatoria' => 75,
            'id_curso' => $this->curso->id_curso,
            'id_tipo_componente' => $tipoComponente2->id_tipo_componente,
            'id_contexto' => $contextoComponente2->id_contexto,
        ]);

        DocenteComponente::create([
            'id_componente' => $componente2->id_componente,
            'id_docente' => $this->docente1->id_docente,
            'es_titular' => true,
        ]);

        // Update course profesor jefe
        $this->cursoService->update($this->curso, [
            'id_asignatura' => $this->curso->asignacionPlan->id_asignatura,
            'id_plan' => $this->curso->asignacionPlan->id_plan,
            'cod_curso' => $this->curso->cod_curso,
            'nombre' => $this->curso->nombre,
            'id_docente_sugerido' => $this->docente2->id_docente,
        ]);

        // Verify profesor2 is titular of BOTH components
        $totalComponentes = Componente::where('id_curso', $this->curso->id_curso)->count();
        $componentesConProfesor2 = DocenteComponente::where('id_docente', $this->docente2->id_docente)
            ->where('es_titular', true)
            ->whereIn('id_componente', [$this->componente->id_componente, $componente2->id_componente])
            ->count();

        $this->assertEquals($totalComponentes, $componentesConProfesor2);
    }

    /**
     * Test that if new professor already exists in component non-titular, becomes titular
     */
    public function test_new_profesor_marked_titular_if_already_in_component(): void
    {
        // Add profesor2 to component but as non-titular
        DocenteComponente::create([
            'id_componente' => $this->componente->id_componente,
            'id_docente' => $this->docente2->id_docente,
            'es_titular' => false,
        ]);

        // Update course profesor jefe
        $this->cursoService->update($this->curso, [
            'id_asignatura' => $this->curso->asignacionPlan->id_asignatura,
            'id_plan' => $this->curso->asignacionPlan->id_plan,
            'cod_curso' => $this->curso->cod_curso,
            'nombre' => $this->curso->nombre,
            'id_docente_sugerido' => $this->docente2->id_docente,
        ]);

        // Verify profesor2 is now titular
        $docenteComponente = DocenteComponente::where('id_componente', $this->componente->id_componente)
            ->where('id_docente', $this->docente2->id_docente)
            ->first();
        $this->assertTrue($docenteComponente->es_titular);
    }

    /**
     * Test that admin cannot lose access to courses
     */
    public function test_admin_always_has_access_to_manage_team(): void
    {
        // Create admin user
        $admin = Usuario::factory()->create(['email' => 'admin@test.com']);
        $adminRole = \App\Models\Usuario\Rol::firstOrCreate(['nombre' => 'Administrador'], ['creado_por' => $admin->id_usuario]);
        \App\Models\Usuario\UsuarioRolAsignacion::firstOrCreate(
            ['id_usuario' => $admin->id_usuario, 'id_rol' => $adminRole->id_rol, 'id_contexto' => 1],
            [
                'asignado_por' => $admin->id_usuario,
                'fecha_inicio_planificada' => now()->subMinute(),
                'fecha_fin_planificada' => now()->addYears(100),
                'esta_activo' => true,
                'fue_eliminado' => false,
                'creado_por' => $admin->id_usuario,
            ]
        );

        $this->actingAs($admin);

        // Admin should be able to manage team even after profesor changes
        $this->assertTrue(auth()->user()->can('manageTeam', $this->curso));

        // Update profesor jefe
        $this->cursoService->update($this->curso, [
            'id_asignatura' => $this->curso->asignacionPlan->id_asignatura,
            'id_plan' => $this->curso->asignacionPlan->id_plan,
            'cod_curso' => $this->curso->cod_curso,
            'nombre' => $this->curso->nombre,
            'id_docente_sugerido' => $this->docente2->id_docente,
        ]);

        // Admin should STILL be able to manage team
        $this->assertTrue(auth()->user()->can('manageTeam', $this->curso));
    }
}
