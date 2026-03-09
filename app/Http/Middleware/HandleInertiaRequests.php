<?php

namespace App\Http\Middleware;

use App\Services\UserCoursesService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Middleware Inertia.js para compartir datos por defecto en cada response.
 * 
 * Configura el middleware de Inertia que:
 * - Define vista raíz (app.html)
 * - Gestiona versionado de assets para cache-busting
 * - Comparte datos globales (usuario, roles, permisos, quote inspirador) con toda solicitud
 * 
 * Datos compartidos automáticamente en cada página:
 * - user: Usuario autenticado con relaciones cargadas
 * - roles: Array de nombres de roles asignados al usuario
 * - docente: Perfil docente si existe
 * - estudiante: Perfil estudiante si existe
 * - auth.user, auth.roles: Duplicados para compatibilidad frontend
 * - flash: Mensajes flash de sesión
 * - message, author: Quote inspirador generado aleatoriamente
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private UserCoursesService $userCourses) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $roles = [];
        $user = $request->user();
        $docente = null;
        $estudiante = null;
        $allAyudantePerms = null;

        if ($user) {
            $user->load([
                'rolesAsignados' => fn($q) => $q->where('esta_activo', true)
                    ->where('fue_eliminado', false),
                'docente',
                'estudiante',
            ]);

            $roles = $user->rolesAsignados
                ->pluck('nombre')
                ->values()
                ->toArray();

            $docente = $user->docente;
            $estudiante = $user->estudiante;

            // Pre-cargar permisos agrupados por contexto si es ayudante (evita N+1)
            if (in_array('ayudante', array_map('strtolower', $roles))) {
                $allAyudantePerms = $user->getAllPermissionsGroupedByContext();
            }
        }

        return [
            ...parent::share($request),
            'name'  => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth'  => [
                'user'          => $user,
                'roles'         => $roles,
                'is_super_admin' => $user?->isSuperAdmin() ?? false,
                'docente'       => $docente,
                'estudiante'    => $estudiante,
                'docente_courses'    => $docente   ? $this->userCourses->getDocenteCourses($docente)                         : [],
                'estudiante_courses' => $estudiante ? $this->userCourses->getEstudianteCourses($estudiante)                  : [],
                'ayudante_courses'   => in_array('ayudante', array_map('strtolower', $roles))
                                        ? $this->userCourses->getAyudanteCourses($user, $allAyudantePerms)
                                        : [],
            ],
            'sidebarOpen' => !$request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error'   => fn() => $request->session()->get('error'),
            ],
        ];
    }
}
