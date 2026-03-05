<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignada;
use App\Models\Agenda\AsignadoActividad;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Controlador para que el estudiante vea sus actividades en un curso.
 * 
 * Tablas involucradas:
 * - agenda.actividad: Definición de la actividad (visible, fecha_límite, tipo, etc.)
 * - agenda.actividad_asignada: Grupo asignado (nota grupal, estado)
 * - agenda.asignado_actividad: Nota individual del estudiante
 * - curso.inscripcion_seccion: Para saber a qué secciones pertenece el alumno
 * - curso.seccion: Sección a la que pertenece la actividad
 */
class ActivityController extends Controller
{
    /**
     * Lista todas las actividades visibles del curso para el estudiante autenticado.
     *
     * El estudiante puede ver solo las actividades de las secciones en que esté inscrito
     * y que tengan `visible = true`.
     */
    public function index(Curso $curso)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            return redirect('/dashboard');
        }

        $estudiante = $user->estudiante;

        // Verificar inscripción al curso
        $inscrito = $estudiante->inscripcionCursos()
            ->where('id_curso', $curso->id_curso)
            ->first();

        if (!$inscrito) {
            abort(403, 'No estás inscrito en este curso.');
        }

        // Obtener IDs de secciones en las que está inscrito el alumno dentro de este curso
        $seccionIds = DB::table('curso.inscripcion_seccion as is')
            ->join('curso.seccion as s', 's.id_seccion', '=', 'is.id_seccion')
            ->where('is.id_estudiante', $estudiante->id_estudiante)
            ->where('s.id_curso', $curso->id_curso)
            ->pluck('is.id_seccion');

        if ($seccionIds->isEmpty()) {
            // Fallback: mostrar todas las actividades del curso (en caso de no haber inscripcion_seccion)
            $seccionIds = DB::table('curso.seccion')
                ->where('id_curso', $curso->id_curso)
                ->pluck('id_seccion');
        }

        // Obtener actividades visibles de las secciones correspondientes
        $actividades = Actividad::whereIn('id_seccion', $seccionIds)
            ->where('visible', true)
            ->with(['seccion.tipoSeccion', 'unidad'])
            ->orderBy('fecha_limite', 'asc')
            ->get();

        // Mapear cada actividad con el estado y notas del estudiante
        $actividadesData = $actividades->map(function (Actividad $actividad) use ($estudiante) {
            // Buscar si el alumno está en algún grupo de esta actividad
            $asignado = AsignadoActividad::where('id_estudiante', $estudiante->id_estudiante)
                ->whereHas('actividadAsignada', fn($q) => $q->where('id_actividad', $actividad->id_actividad))
                ->with('actividadAsignada.estadoActividad')
                ->first();

            $grupo = $asignado?->actividadAsignada;

            return [
                'id_actividad'     => $actividad->id_actividad,
                'nombre'           => $actividad->nombre,
                'fecha_limite'     => $actividad->fecha_limite,
                'tipo_actividad'   => $actividad->tipo_actividad,
                'tipo_entrega'     => $actividad->tipo_entrega,
                'es_grupal'        => $actividad->es_grupal,
                'max_integrantes'  => $actividad->max_integrantes,
                'seccion'          => $actividad->seccion ? [
                    'id_seccion' => $actividad->seccion->id_seccion,
                    'tipo'       => $actividad->seccion->tipoSeccion?->tipo ?? 'Sección',
                ] : null,
                'unidad' => $actividad->unidad ? [
                    'id_unidad' => $actividad->unidad->id_unidad,
                    'nombre'    => $actividad->unidad->nombre,
                ] : null,
                // Datos del grupo/asignación del estudiante
                'grupo_numero'       => $grupo?->grupo,
                'nota_grupal'        => $grupo?->nota,
                'nota_individual'    => $asignado?->nota_individual,
                'diferencia_decimas' => $asignado?->diferencia_decimas,
                'estado' => $grupo?->estadoActividad ? [
                    'id_estado' => $grupo->estadoActividad->id_estado,
                    'titulo'    => $grupo->estadoActividad->titulo,
                ] : null,
                'asignado'           => $asignado !== null,
            ];
        })->values();

        // Info del curso
        $curso->load(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera']);

        return Inertia::render('student/Courses/Actividades', [
            'curso'       => [
                'id_curso'         => $curso->id_curso,
                'nombre'           => $curso->nombre,
                'cod_curso'        => $curso->cod_curso,
                'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
            ],
            'actividades' => $actividadesData,
        ]);
    }
}
