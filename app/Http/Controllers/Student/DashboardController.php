<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Services\Student\DashboardEstudiante;
use App\Services\Student\ResumenActividadesEstudiante;
use App\Services\Sso\SgeqSsoService;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
/**
 * Controlador para el dashboard del estudiante.
 */
class DashboardController extends Controller
{
    /**
     * Muestra el dashboard del estudiante con su lista de cursos inscritos.
     *
     * @return \Illuminate\Http\RedirectResponse|\Inertia\Response
     */
    public function index(DashboardEstudiante $dashboard, SgeqSsoService $sgeq, ResumenActividadesEstudiante $resumen)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        // Verificar que el usuario es estudiante
        if (!$user->estudiante) {
            return redirect('/dashboard')->with('error', 'No tienes acceso a esta sección');
        }

        $estudiante = $user->estudiante;
        
        $cursosData = $dashboard->cursos($estudiante)
            ->map(fn (array $curso) => [...$curso, 'profesor' => $curso['profesor'] ?? '(sin docente asignado)']);

        // Check if user is also Ayudante
        $isAyudante = $user->rolesAsignados()
            ->wherePivot('esta_activo', true)
            ->wherePivot('fue_eliminado', false)
            ->whereIn('rol.nombre', ['Ayudante'])
            ->exists();

        //vista del semestre por defecto (entre 1 y 2)
        ['semestre' => $semestreActual, 'agno' => $agnoActual] = DashboardEstudiante::periodoActual();

        return Inertia::render('student/Dashboard', [
            'mensajeria' => $dashboard->mensajesNoLeidosPorCurso((int) $user->id_usuario),
            'cursos' => $cursosData,
            'proximasAVencer' => $resumen->proximasAVencer($estudiante, $semestreActual, $agnoActual),
            'notasRecientes' => $resumen->notasYRetroalimentaciones($estudiante),
            'stats' => [
                'total_cursos' => $cursosData->count(),
                'nombre_completo' => trim("{$user->nombre1} {$user->apellido1}"),
            ],
            'isAyudante' => $isAyudante,
            // El acceso a SGEQ depende de la carrera, así que el botón no se
            // muestra a quien igual rebotaría al hacer clic.
            'puedeAbrirSgeq' => $sgeq->resolverRol($user) !== null,
            'semestreActual' => $semestreActual,
            'periodoActual' => [
                'semestre' => $semestreActual,
                'agno' => $agnoActual,
            ],

        ]);
    }
}
