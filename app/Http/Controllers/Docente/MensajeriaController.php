<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Concerns\GestionaMensajeriaStaff;
use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
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

    /**
     * «Mensajes para» — /docente/mensajeria (revisión «Mi UTAmed»): los cursos
     * del semestre vigente del docente, cada uno con sus mensajes sin leer y
     * un enlace directo a su mensajería. Sin cantidad de estudiantes ni
     * pendientes por revisar, como pidió el cliente.
     */
    public function cursos(): \Inertia\Response
    {
        $componentes = $this->resolverComponentesVisibles();

        $noPlantilla = Curso::whereIn('id_curso', $componentes->pluck('id_curso')->unique())
            ->where('es_plantilla', false)
            ->pluck('id_curso')
            ->flip();
        $componentes = $componentes->filter(fn ($c) => $noPlantilla->has((int) $c->id_curso));

        $ultimo = $componentes
            ->filter(fn ($c) => $c->agno_real !== null && $c->semestre_real !== null)
            ->sortByDesc(fn ($c) => (int) $c->agno_real * 10 + (int) $c->semestre_real)
            ->first();
        $vigentes = $ultimo
            ? $componentes->filter(fn ($c) => (int) $c->agno_real === (int) $ultimo->agno_real && (int) $c->semestre_real === (int) $ultimo->semestre_real)
            : $componentes;

        $noLeidos = $this->mensajeria->noLeidosPorComponente(
            $vigentes->pluck('id_componente')->map(fn ($id) => (int) $id)->all(),
            (int) Auth::id(),
            esStaff: true,
        );

        $cursos = $vigentes
            ->groupBy('id_curso')
            ->map(function ($delCurso) use ($noLeidos) {
                $c = $delCurso->first();

                return [
                    'id_curso' => (int) $c->id_curso,
                    'nombre' => $c->curso_nombre,
                    'codigo' => $c->cod_asignatura ?: $c->cod_curso,
                    'letra_grupo' => $c->letra_grupo,
                    'no_leidos' => $delCurso->sum(fn ($cmp) => $noLeidos[(int) $cmp->id_componente] ?? 0),
                ];
            })
            ->sortBy(fn ($c) => $c['codigo'] . ' ' . $c['letra_grupo'])
            ->values();

        return \Inertia\Inertia::render('docente/MensajesPara', [
            'periodo' => $ultimo ? ['sem' => (int) $ultimo->semestre_real, 'ano' => (int) $ultimo->agno_real] : null,
            'cursos' => $cursos,
        ]);
    }

    protected function vistaMensajeria(): string
    {
        return 'docente/Mensajeria';
    }

    protected function baseRutaMensajeria(Curso $curso): string
    {
        return "/docente/cursos/{$curso->id_curso}/mensajeria";
    }
}
