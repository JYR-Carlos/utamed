<?php

namespace App\Api\V1\Middleware;

use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que el usuario autenticado por Sanctum sea un estudiante activo
 * y que cuente con matrícula activa (estado INSCRITO) en el curso solicitado.
 */
class IsEnrolledInCourse
{
    public const NO_INSCRITO_MENSAJE = 'No estás inscrito en este curso.';

    public static function estaInscritoEnCurso(?Usuario $user, ?Curso $curso): bool
    {
        if ($user === null || ! $user->esta_activo || ! $user->hasRole('Estudiante') || $user->estudiante === null) {
            return false;
        }

        if ($curso === null) {
            return false;
        }

        return $user->estudiante->inscripcionCursos()
            ->where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->exists();
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Usuario|null $user */
        $user = $request->user();

        // Obtener el curso de la ruta
        $cursoParam = $request->route('curso');

        if ($cursoParam instanceof Curso) {
            $curso = $cursoParam;
        } elseif (is_numeric($cursoParam)) {
            $curso = Curso::find($cursoParam);
            if (! $curso) {
                abort(404, 'Curso no encontrado.');
            }
        } else {
            abort(404, 'Curso no encontrado.');
        }

        if (! self::estaInscritoEnCurso($user, $curso)) {
            return response()->json(['message' => self::NO_INSCRITO_MENSAJE], 403);
        }

        return $next($request);
    }
}
