<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $roles = [];
        $user = $request->user();

        if ($user) {
            // Fetch active roles for Global context (ID 5) or all active roles if strict context not required yet.
            // For dashboard purposes, we usually want "What is this user system-wide?".
            // We'll fetch ALL active assignments for now.
            $roles = $user->rolesAsignados()
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->with('rol')
                ->get()
                ->pluck('rol.nombre')
                ->unique()
                ->values()
                ->toArray();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
                'roles' => $roles,
            ],
            'sidebarOpen' => !$request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
