<?php

namespace App\Http\Controllers\Ayudante;

use App\Http\Controllers\Concerns\GestionaMensajeriaStaff;
use App\Http\Controllers\Controller;
use App\Services\MensajeriaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Mensajería del ayudante — misma bandeja que el docente, distinto alcance.
 *
 * Visibilidad: los componentes de los cursos donde el usuario tiene el rol
 * "Ayudante" sobre el contexto del curso (mismo criterio que
 * UserCoursesService::getAyudanteCourses).
 *
 * Un ayudante que responde entra en el mismo canal (componente, alumno) que el
 * docente: la conversación no se divide.
 */
class MensajeriaController extends Controller
{
    use GestionaMensajeriaStaff;

    public function __construct(private readonly MensajeriaService $mensajeria)
    {
    }

    protected function resolverComponentesVisibles(): Collection
    {
        return $this->mensajeria->componentesDeAyudante((int) Auth::id());
    }

    protected function vistaMensajeria(): string
    {
        return 'ayudante/Mensajeria';
    }

    protected function baseRutaMensajeria(): string
    {
        return '/ayudante/mensajeria';
    }
}
