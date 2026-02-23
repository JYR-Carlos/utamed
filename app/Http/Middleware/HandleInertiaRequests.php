<?php

namespace App\Http\Middleware;

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
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     * 
     * Comparte información de usuario, roles, permisos y datos globales con cada response.
     * Los datos aquí están disponibles en todos los componentes Svelte/React.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>  Datos compartidos con frontend
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $roles = [];
        $user = $request->user();
        $docente = null;
        $estudiante = null;

        if ($user) {
            $user->load([
                'rolesAsignados' => fn($q) => $q->where('esta_activo', true)
                    ->where('fue_eliminado', false),
                'docente',
                'estudiante'
            ]);
            // Fetch active roles for Global context (ID 5) or all active roles if strict context not required yet.
            // For dashboard purposes, we usually want "What is this user system-wide?".
            // We'll fetch ALL active assignments for now.
            // 2. Ahora procesamos sobre la COLECCIÓN (en memoria), no sobre el Query Builder
            $roles = $user->rolesAsignados // <--- Sin paréntesis (), usamos la colección ya cargada
                ->pluck('nombre')
                ->values()
                ->toArray();

            \Illuminate\Support\Facades\Log::info('HandleInertiaRequests - User roles:', [
                'user_id' => $user->id_usuario,
                'roles_count' => count($roles),
                'roles' => $roles,
                'assignments' => $user->rolesAsignados->toArray()
            ]);

            // \Log::info('HandleInertiaRequests - User Roles:', ['id' => $user->id_usuario, 'roles' => $roles]);

            // 3. Ya están cargados, así que esto no dispara más SQL
            $docente = $user->docente;
            $estudiante = $user->estudiante;
            $estudiante = $user->estudiante;
        }


        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
                'roles' => $roles,
                'docente' => $docente,
                'estudiante' => $estudiante,
                'docente_courses' => $docente ? \App\Models\Curso\Curso::join('curso.seccion', 'curso.curso.id_curso', '=', 'curso.seccion.id_curso')
                    ->where('curso.seccion.id_docente', $docente->id_docente)
                    ->distinct()
                    ->select('curso.curso.id_curso', 'curso.curso.nombre', 'curso.curso.cod_curso')
                    ->with(['asignacionPlan.plan.carrera'])
                    ->get()
                    ->map(function ($curso) {
                        return [
                            'id_curso' => $curso->id_curso,
                            'nombre' => $curso->nombre,
                            'cod_curso' => $curso->cod_curso,
                            'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                            'tiene_programa' => \App\Models\Administrativo\Programa::where('id_curso', $curso->id_curso)->exists(),
                        ];
                    })
                    ->values() : [],
                'estudiante_courses' => $estudiante ? \App\Models\Curso\InscripcionSeccion::where('id_estudiante', $estudiante->id_estudiante)
                    ->with('seccion.curso')
                    ->get()
                    ->pluck('seccion.curso')
                    ->filter()
                    ->unique('id_curso')
                    ->values()
                    ->map(fn($c) => [
                        'id_curso' => $c->id_curso,
                        'nombre' => $c->nombre,
                        'cod_curso' => $c->cod_curso,
                    ]) : [],
                'ayudante_courses' => ($estudiante && in_array('Ayudante', $roles))
                    ? \App\Models\Curso\Seccion::where('id_ayudante', $estudiante->id_estudiante)
                        ->with('curso')
                        ->get()
                        ->pluck('curso')
                        ->filter()
                        ->unique('id_curso')
                        ->values()
                        ->map(fn($c) => [
                            'id_curso' => $c->id_curso,
                            'nombre' => $c->nombre,
                            'cod_curso' => $c->cod_curso,
                        ]) : [],

            ],
            'sidebarOpen' => !$request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
            ],
        ];
    }
}
