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
                'rolesAsignados.rol',
                'docente',
                'estudiante'
            ]);
            // Fetch active roles for Global context (ID 5) or all active roles if strict context not required yet.
            // For dashboard purposes, we usually want "What is this user system-wide?".
            // We'll fetch ALL active assignments for now.
            // 2. Ahora procesamos sobre la COLECCIÓN (en memoria), no sobre el Query Builder
            $roles = $user->rolesAsignados // <--- Sin paréntesis (), usamos la colección ya cargada
                ->pluck('rol.nombre')
                ->unique()
                ->values()
                ->toArray();

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
            ],
            'sidebarOpen' => !$request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
            ],
        ];
    }
}
