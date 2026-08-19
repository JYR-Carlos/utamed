<?php

namespace App\Http\Middleware;

use App\Services\UserCoursesService;
use Closure;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware Inertia.js para compartir datos por defecto en cada response.
 * 
 * ¿QUÉ HACE?
 * -----------
 * Este middleware se ejecuta en CADA petición HTTP y automáticamente comparte datos
 * globales con todas las páginas frontend. Es como un "contenedor de props global"
 * que Inertia.js inyecta en todas las vistas Svelte sin que tengas que pasarlos
 * explícitamente desde cada controlador.
 * 
 * FLUJO DE DATOS:
 * ---------------
 * 1. Usuario hace petición (GET /dashboard, POST /cursos, etc.)
 * 2. Laravel procesa la solicitud
 * 3. Inertia intercepta la respuesta con este middleware
 * 4. share() genera los datos globales (usuario, roles, cursos, etc.)
 * 5. Los datos se envían al frontend en el JSON response
 * 6. Svelte recibe automaticamente estos props en cualquier página
 * 
 * DATOS COMPARTIDOS GLOBALMENTE:
 * ------------------------------
 * - auth.user: Usuario autenticado completo ({id, nombre, email, etc})
 * - auth.roles: Array de nombres de roles ([Docente, Admin, Ayudante])
 * - auth.is_super_admin: Boolean indicando si es SuperAdmin
 * - auth.docente: Objeto Docente si el usuario es docente
 * - auth.estudiante: Objeto Estudiante si el usuario es estudiante
 * - auth.docente_courses: Cursos donde dicta el docente
 * - auth.estudiante_courses: Cursos donde está inscrito el estudiante
 * - auth.ayudante_courses: Cursos donde asiste como ayudante
 * - flash.success: Mensajes de éxito desde sesión
 * - flash.error: Mensajes de error desde sesión
 * - quote: Quote inspirador aleatorio {message, author}
 * - sidebarOpen: Estado persistente de la barra lateral
 * 
 * ACCESO DESDE FRONTEND:
 * ----------------------
 * En cualquier página Svelte puedes acceder a estos datos:
 * 
 * import { page } from '@inertiajs/svelte';
 * 
 * let user = $derived($page.props.auth.user);
 * let roles = $derived($page.props.auth.roles);
 * let cursos = $derived($page.props.auth.docente_courses);
 * let successMsg = $derived($page.props.flash.success);
 * 
 * EJEMPLO DE RESPUESTA JSON:
 * --------------------------
 * {
 *   "component": "Dashboard",
 *   "props": {
 *     "auth": {
 *       "user": {
 *         "id_usuario": 1,
 *         "nombre1": "Juan",
 *         "email": "juan@utamed.cl"
 *       },
 *       "roles": ["Docente", "Ayudante"],
 *       "docente": {...},
 *       "docente_courses": [{id_curso: 1, nombre: "Intro a BD"}]
 *     },
 *     "flash": {"success": "Curso actualizado"},
 *     "quote": {"message": "The only...", "author": "Steve Jobs"}
 *   },
 *   "url": "/dashboard",
 *   "version": "abc123..."
 * }
 * 
 * OPTIMIZACIONES:
 * ----------------
 * - Pre-cargamos relaciones (eager loading) para evitar N+1 queries
 * - Para ayudantes, pre-cachamos permisos por contexto
 * - Solo cargamos datos activos (roles no eliminados)
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * Vista raíz que renderiza las páginas Svelte
     * Definida en resources/views/app.html
     */
    protected $rootView = 'app';

    /**
     * Inyectamos servicio para cargar cursos del usuario
     */
    public function __construct(private UserCoursesService $userCourses) {}

    /**
     * Impide que el navegador sirva páginas desde su caché al pulsar "atrás".
     *
     * Aplica a dos casos, por el mismo motivo en direcciones opuestas:
     *
     * - Páginas de invitado (login, home...): sin `no-store` el navegador las
     *   restaura desde bfcache tras iniciar sesión, saltándose el middleware
     *   `guest` de Fortify que redirigiría al dashboard.
     *
     * - Páginas autenticadas: sin `no-store` el navegador las restaura tras el
     *   logout y muestra los datos del usuario que cerró sesión. La defensa
     *   principal aquí es el cifrado del historial de Inertia (config
     *   `inertia.history.encrypt` + `Inertia::clearHistory()` en
     *   App\Http\Responses\LogoutResponse), que cubre la navegación SPA; esta
     *   cabecera cubre la recarga del documento HTML completo.
     *
     * Con `no-store` el navegador siempre re-solicita la página y es el
     * servidor quien decide a dónde va el usuario según su sesión real.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        $guestRoutes = ['login', 'home', 'register', 'password.request', 'password.reset', 'verification.notice'];

        $isGuestRoute = in_array($request->route()?->getName(), $guestRoutes);

        // `$request->user()` se evalúa después del controlador: en la petición
        // de logout ya devuelve null, que es justo lo que queremos (el redirect
        // no lleva datos).
        if ($isGuestRoute || $request->user()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    /**
     * Versión de assets para cache-busting
     *
     * Laravel genera un hash de los assets y lo compara con el cliente.
     * Si no coinciden, Inertia recarga automáticamente los assets nuevos.
     * Esto evita que los usuarios tengan versiones cacheadas de JS/CSS desactualizado.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Método principal que comparte datos globales con cada respuesta
     * 
     * Se ejecuta en CADA petición HTTP antes de renderizar la página.
     * Retorna un array que Inertia mezcla automáticamente con los props
     * específicos enviados desde cada controlador.
     * 
     * OPTIMIZACIÓN: Diferencia entre GET (navegación) y POST/PUT/DELETE (acciones)
     * - GET: Carga todos los datos (user, roles, cursos, permisos)
     * - POST/PUT/DELETE: Carga mínimo (user, roles) para evitar queries innecesarias
     * 
     * @param Request $request Objeto de la solicitud HTTP
     * @return array Props globales compartidos
     */
    public function share(Request $request): array
    {
        // Generar quote inspirador aleatorio para mostrar en sidebars/footers
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $roles = [];
        $permissions = [];
        $user = $request->user();
        $docente = null;
        $estudiante = null;

        // Si el usuario está autenticado, cargar sus relaciones
        if ($user) {
            // Eager load para evitar N+1 queries (ej: no cargar roles en un loop)
            $user->load([
                'rolesAsignados' => fn($q) => $q->with('permisos')
                    ->where('esta_activo', true)
                    ->where('fue_eliminado', false),
                'docente',
                'estudiante',
            ]);

            // Extraer nombres de roles simplemente para el frontend
            $roles = $user->rolesAsignados
                ->pluck('nombre')
                ->values()
                ->toArray();

            // Recopilar slugs de permisos únicos de todos los roles activos del usuario
            // FIX: esto esta mal debe ser contextual
            $permissions = $user->rolesAsignados
                ->flatMap(fn($r) => $r->permisos->pluck('slug'))
                ->unique()
                ->values()
                ->toArray();

            // Guardar referencias al perfil actual del usuario
            $docente = $user->docente;
            $estudiante = $user->estudiante;

            // Los permisos por contexto ya no se precargan aquí: se resolvían en
            // toda petición del ayudante, incluidas las de escritura que nunca
            // los usan. UserCoursesService los pide cuando de verdad hacen falta.
        }

        // OPTIMIZACIÓN: Solo cargar datos costosos en GET requests (navegación)
        // Para POST/PUT/DELETE, minimizar queries de lectura
        $isNavigationRequest = $request->method() === 'GET';

        // Retornar array de props compartidos que Inertia inyectará en TODAS las páginas
        return [
            ...parent::share($request),  // Props base de Inertia (errors, component, etc)

            'name'  => config('app.name'),  // Nombre de la aplicación (UTAMED)

            // Quote inspirador (opcional, para UI)
            'quote' => ['message' => trim($message), 'author' => trim($author)],

            // OBJETO AUTH: Toda la información del usuario autenticado
            'auth'  => [
                'user'          => $user,  // Usuario completo con todas sus propiedades
                'roles'         => $roles,  // Array de strings: ['Docente', 'Admin']
                'is_super_admin' => $user?->isSuperAdmin() ?? false,  // Boolean
                'docente'       => $docente,  // Objeto Docente si aplica, null si no
                'estudiante'    => $estudiante,  // Objeto Estudiante si aplica

                // PERMISOS: Slugs únicos de permisos del usuario vía sus roles activos
                // Solo en GET requests (navegación). Vacío en POST/PUT/DELETE.
                // Ej: ['cursos:ver', 'cursos:editar', 'facultades:ver']
                'permissions'   => $isNavigationRequest ? $permissions : [],

                // CURSOS: sólo en GET requests (navegación).
                //
                // Van en closures a propósito. Inertia filtra los props de una
                // recarga parcial ANTES de resolverlos, así que un closure que la
                // petición no pide nunca llega a ejecutarse; un valor calculado
                // aquí mismo, en cambio, se paga siempre aunque después se
                // descarte. Nada de `Inertia::optional()`: eso los excluiría
                // también de las visitas normales y dejaría el sidebar vacío.
                //
                // export interface SidebarCourse { <- Interfaz a la que se cargan
                //     id_curso: number;
                //     nombre: string;
                //     cod_curso: string;
                //     tiene_programa?: boolean;
                //     permisos: Permission[];
                // }
                'docente_courses'    => $isNavigationRequest && $docente
                    ? fn() => $this->userCourses->getDocenteCourses($docente)  // Cursos donde dicta
                    : [],

                'estudiante_courses' => $isNavigationRequest && $estudiante
                    ? fn() => $this->userCourses->getEstudianteCourses($estudiante)  // Cursos inscritos
                    : [],

                'ayudante_courses'   => $isNavigationRequest && in_array('ayudante', array_map('strtolower', $roles))
                    ? fn() => $this->userCourses->getAyudanteCourses($user)  // Cursos donde asiste
                    : [],
            ],

            // STATE: Estado persistente de UI (ej: sidebar abierto/cerrado)
            // Solo en GET requests (navegación)
            'sidebarOpen' => $isNavigationRequest
                ? (!$request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true')
                : null,

            // FLASH MESSAGES: Mensajes de una solicitud anterior (login, error, etc)
            'flash' => [
                'success' => fn() => $request->session()->get('success'),  // Lazy evaluation
                'error'   => fn() => $request->session()->get('error'),
                'data' => fn() => $request->session()->get('flash_data', []),
            ],

            // LOGIN ERROR: Errores específicos de autenticación (RUT_NOT_FOUND, PASSWORD_INCORRECT, etc)
            'loginError' => fn() => $request->session()->pull('login_error'),
        ];
    }
}
