<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Concerns\GestionaMensajeriaStaff;
use App\Http\Controllers\Controller;
use App\Services\MensajeriaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Mensajería del docente — hilos sostenidos por el componente.
 *
 * Distinta del centro de mensajes de MensajesController, que lee agenda.agenda y
 * cubre el feedback de entregas por actividad. Aquí no interviene la agenda: son
 * avisos al componente y conversaciones con cada alumno.
 *
 * Visibilidad: los componentes que el docente tiene asignados en
 * curso.docente_componente —colegiados incluidos, porque esa tabla admite varios
 * docentes por componente— más todos los componentes de los cursos donde es
 * titular.
 */
class MensajeriaController extends Controller
{
    use GestionaMensajeriaStaff;

    public function __construct(private readonly MensajeriaService $mensajeria)
    {
    }

    protected function resolverComponentesVisibles(): Collection
    {
        $docente = Auth::user()?->docente;

        if (!$docente) {
            return collect();
        }

        return $this->mensajeria->componentesDeDocente((int) $docente->id_docente);
    }

    protected function vistaMensajeria(): string
    {
        return 'docente/Mensajeria';
    }

    protected function baseRutaMensajeria(): string
    {
        return '/docente/mensajeria';
    }
}
