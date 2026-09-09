<?php

namespace App\Http\Middleware;

use App\Models\Usuario\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retiene al usuario en la pantalla de cambio de contraseña mientras su clave
 * no cumpla la política: hay que cambiarla en el primer ingreso y cada
 * {@see Usuario::VIGENCIA_PASSWORD_MESES} meses.
 *
 * POR QUÉ VA EN EL GRUPO `web` Y NO EN CADA RUTA: la política dice "bloquear la
 * navegación", no "bloquear estas pantallas". Una lista de rutas protegidas
 * envejece —cada módulo nuevo habría que acordarse de sumarlo— y el olvido no
 * se nota, porque la pantalla sigue funcionando. Con el bloqueo puesto en el
 * grupo pasa lo contrario: lo que se olvide queda cerrado, que es el lado
 * seguro del error. Por eso aquí sólo hay una lista de excepciones, y es corta
 * y estable.
 *
 * Se apoya en `$request->user()`, así que las peticiones de invitado lo
 * atraviesan sin tocar la sesión ni la base de datos.
 */
class ForcePasswordChange
{
    /**
     * Lo único que un usuario con la clave caducada puede seguir haciendo.
     *
     * - `user-password.*`: la pantalla a la que se le manda y el envío del
     *   formulario. Sin ellas el redirect apuntaría a una ruta bloqueada y el
     *   navegador giraría en redirecciones para siempre.
     * - `logout`: dejar a alguien encerrado sin poder cerrar sesión convierte
     *   un aviso de seguridad en una cuenta inutilizable.
     * - `verification.*`: son de Fortify y tienen su propio middleware que
     *   redirige hacia ellas. Si las bloqueáramos, ambos middlewares se
     *   mandarían al usuario de vuelta y de ida sin parar.
     */
    private const RUTAS_PERMITIDAS = [
        'user-password.edit',
        'user-password.update',
        'logout',
        'verification.notice',
        'verification.verify',
        'verification.send',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        if (!$user || !$user->debeCambiarPassword()) {
            return $next($request);
        }

        $nombreRuta = $request->route()?->getName();

        if (in_array($nombreRuta, self::RUTAS_PERMITIDAS, true)) {
            return $next($request);
        }

        // Las llamadas fetch/axios de la aplicación esperan JSON: un 302 hacia
        // una pantalla HTML las haría fallar con un error de parseo que no dice
        // nada. 423 Locked describe el caso —el recurso existe pero está
        // retenido por una condición del propio usuario— y el frontend puede
        // reaccionar al código en vez de al texto.
        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Debes actualizar tu contraseña antes de seguir usando el sistema.',
                'redirect' => route('user-password.edit'),
            ], Response::HTTP_LOCKED);
        }

        // Inertia sigue este 302 como una visita normal (y lo convierte en 303
        // cuando la petición original no era GET), así que la redirección vale
        // igual para la navegación SPA y para una recarga completa.
        return redirect()->route('user-password.edit');
    }
}
