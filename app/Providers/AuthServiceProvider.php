<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

// Models
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Curso\Seccion;
use App\Models\Curso\Unidad;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\InscripcionSeccion;
use App\Models\Curso\Asistencia;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Rol;
use App\Models\Usuario\AsignacionRolPermiso;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Usuario\UsuarioPermisoEspecial;

// Policies
use App\Policies\FacultadPolicy;
use App\Policies\DepartamentoPolicy;
use App\Policies\CarreraPolicy;
use App\Policies\PlanPolicy;
use App\Policies\AsignaturaPolicy;
use App\Policies\AsignacionPlanPolicy;
use App\Policies\CursoPolicy;
use App\Policies\ProgramaPolicy;
use App\Policies\SeccionPolicy;
use App\Policies\UnidadPolicy;
use App\Policies\InscripcionCursoPolicy;
use App\Policies\InscripcionSeccionPolicy;
use App\Policies\AsistenciaPolicy;
use App\Policies\UsuarioPolicy;
use App\Policies\EstudiantePolicy;
use App\Policies\DocentePolicy;
use App\Policies\RolPolicy;
use App\Policies\AsignacionRolPermisoPolicy;
use App\Policies\UsuarioRolAsignacionPolicy;
use App\Policies\UsuarioPermisoEspecialPolicy;

/**
 * Proveedor de servicios de autenticación y autorización.
 * AUTOGENERADO - Se sobrescribe al regenerar.
 *
 * Para políticas manuales adicionales, agregar a $policies manualmente
 * y excluir ese modelo de $policyConfigs en generate_models.php.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Mapeo de modelos a políticas de autorización.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Facultad::class => FacultadPolicy::class,
        Departamento::class => DepartamentoPolicy::class,
        Carrera::class => CarreraPolicy::class,
        Plan::class => PlanPolicy::class,
        Asignatura::class => AsignaturaPolicy::class,
        AsignacionPlan::class => AsignacionPlanPolicy::class,
        Curso::class => CursoPolicy::class,
        Programa::class => ProgramaPolicy::class,
        Seccion::class => SeccionPolicy::class,
        Unidad::class => UnidadPolicy::class,
        InscripcionCurso::class => InscripcionCursoPolicy::class,
        InscripcionSeccion::class => InscripcionSeccionPolicy::class,
        Asistencia::class => AsistenciaPolicy::class,
        Usuario::class => UsuarioPolicy::class,
        Estudiante::class => EstudiantePolicy::class,
        Docente::class => DocentePolicy::class,
        Rol::class => RolPolicy::class,
        AsignacionRolPermiso::class => AsignacionRolPermisoPolicy::class,
        UsuarioRolAsignacion::class => UsuarioRolAsignacionPolicy::class,
        UsuarioPermisoEspecial::class => UsuarioPermisoEspecialPolicy::class,
    ];

    /**
     * Registra servicios de autenticación/autorización.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
