<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Services\MensajeriaService;
use App\Services\Student\MensajeriaCursoEstudiante;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Mensajería del estudiante — hilos sostenidos por el componente.
 *
 * La bandeja es la pestaña «Mensajería» de la ficha del curso (la arma
 * {@see MensajeriaCursoEstudiante}); aquí queda el envío y la URL antigua, que
 * redirige a esa pestaña.
 */
class MensajeriaController extends Controller
{
    public function __construct(
        private readonly MensajeriaService $mensajeria,
        private readonly MensajeriaCursoEstudiante $bandeja,
    ) {
    }

    /**
     * GET /estudiante/cursos/{curso}/mensajeria — vive en la ficha del curso.
     */
    public function index(Request $request, Curso $curso): RedirectResponse
    {
        return redirect()->route('estudiante.cursos.show', array_filter([
            'curso'         => $curso->id_curso,
            'seccion'       => 'mensajeria',
            'componente_id' => $request->integer('componente_id') ?: null,
        ]));
    }

    /**
     * El alumno escribe a una persona del equipo del componente (titular,
     * docente o ayudante). La conversación es privada entre los dos (OBS-49).
     * POST /estudiante/cursos/{curso}/mensajeria/componentes/{componente}/mensaje
     */
    public function enviar(Request $request, Curso $curso, int $componente)
    {
        $idUsuario = (int) Auth::id();

        $visible = $this->bandeja->componentesDelCurso($curso, $idUsuario)
            ->contains(fn($c) => (int) $c->id_componente === $componente);

        if (!$visible) {
            abort(403, 'No estás inscrito en este componente.');
        }

        $datos = $request->validate([
            'mensaje'             => 'required|string|max:2000',
            'tema'                => 'nullable|string|max:150',
            'id_usuario_receptor' => 'required|integer',
        ]);

        $receptor = (int) $datos['id_usuario_receptor'];

        // Sólo a quien está hoy en el equipo: con quien ya no está, la
        // conversación queda de sólo lectura. Nunca a otro estudiante: no hay
        // mensajes entre compañeros (OBS-53, ver MensajeriaService).
        $esDelEquipo = $receptor !== $idUsuario && $this->mensajeria->equipoDeComponente($componente)
            ->contains(fn(array $p) => $p['id_usuario'] === $receptor);

        if (!$esDelEquipo) {
            return back()->withErrors([
                'mensaje' => 'Sólo puedes escribirle a alguien del equipo de este componente.',
            ]);
        }

        $this->mensajeria->enviarIndividual(
            idComponente: $componente,
            idUsuarioEmisor: $idUsuario,
            idUsuarioReceptor: $receptor,
            mensaje: $datos['mensaje'],
            tema: $datos['tema'] ?? null,
        );

        return back()->with('success', 'Mensaje enviado.');
    }
}
