<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Controlador del calendario académico del docente.
 *
 * Reúne todas las actividades con fecha límite de los cursos en los que el
 * docente participa (como titular del curso o como docente de un componente)
 * y las entrega a una vista tipo "Google Calendar" ambientada a UTAMED, donde
 * el docente visualiza de forma amplia qué tiene pendiente y a qué curso
 * pertenece cada actividad.
 *
 * Es una vista de solo lectura: no crea, edita ni elimina actividades.
 *
 * Tablas implicadas:
 * - curso.curso: Cursos del docente
 * - curso.componente / curso.tipo_componente: Componentes del curso
 * - curso.docente_componente: Asignación del docente a componentes
 * - agenda.actividad: Actividades con su fecha_limite
 * - administrativo.asignatura: Nombre de la asignatura del curso
 */
class CalendarioController extends Controller
{
    /**
     * Muestra el calendario del docente con todas sus actividades a vencer.
     */
    public function index()
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->docente) {
            return redirect()->route('dashboard')->with('error', 'No tienes un perfil docente asociado.');
        }

        $idDocente = $user->docente->id_docente;

        // Cursos donde el docente participa (titular del curso o de algún componente)
        $cursos = Curso::where(function ($q) use ($idDocente) {
                $q->where('id_docente_titular', $idDocente)
                    ->orWhereHas('componentes.docenteComponentes', function ($dq) use ($idDocente) {
                        $dq->where('id_docente', $idDocente);
                    });
            })
            ->whereNull('fecha_eliminacion')
            ->with([
                'asignacionPlan.asignatura',
                'componentes.tipoComponente',
                'componentes.actividades' => fn ($q) => $q->whereNotNull('fecha_limite'),
            ])
            ->orderBy('agno_real', 'desc')
            ->orderBy('semestre_real', 'desc')
            ->get();

        $cursosPayload = [];
        $eventos = [];

        foreach ($cursos as $curso) {
            $asignatura = $curso->asignacionPlan?->asignatura?->nombre ?? ($curso->nombre ?? 'Curso');

            // Nombre del curso = nombre asignatura + año + semestre
            $nombreCurso = trim(sprintf(
                '%s %s-%s',
                $asignatura,
                $curso->agno_real ?? '',
                $curso->semestre_real ?? ''
            ));

            $totalActividades = 0;

            foreach ($curso->componentes as $componente) {
                $tipoComponente = $componente->tipoComponente?->tipo ?? 'General';

                foreach ($componente->actividades as $actividad) {
                    if (!$actividad->fecha_limite) {
                        continue;
                    }

                    $eventos[] = [
                        'id_actividad'   => $actividad->id_actividad,
                        'id_curso'       => $curso->id_curso,
                        'titulo'         => $actividad->nombre,
                        'fecha'          => $actividad->fecha_limite->format('Y-m-d'),
                        'tipo_actividad' => $actividad->tipo_actividad?->value ?? (string) $actividad->tipo_actividad,
                        'tipo_entrega'   => $actividad->tipo_entrega,
                        'es_grupal'      => (bool) $actividad->es_grupal,
                        'visible'        => (bool) $actividad->visible,
                        'componente'     => $tipoComponente,
                        'id_componente'  => $componente->id_componente,
                    ];

                    $totalActividades++;
                }
            }

            $cursosPayload[] = [
                'id_curso'          => $curso->id_curso,
                'nombre'            => $nombreCurso,
                'asignatura'        => $asignatura,
                'cod_curso'         => $curso->cod_curso,
                'agno_real'         => $curso->agno_real,
                'semestre_real'     => $curso->semestre_real,
                'total_actividades' => $totalActividades,
            ];
        }

        return Inertia::render('docente/Calendario', [
            'cursos'  => $cursosPayload,
            'eventos' => $eventos,
        ]);
    }
}
