<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Usuario;
use App\Services\Docente\PermisosCursoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Controlador para que el Docente Titular gestione permisos granulares
 * sobre otros docentes de su curso o componente.
 *
 * Casos de uso:
 *   1. DT del curso: configurar quién puede editar el programa/syllabus.
 *   2. DT de un componente colegiado: configurar permisos de notas/asistencia
 *      para los demás docentes del componente.
 *
 * Refactorizado (B-04): la lógica de syncPermiso, ensureContext y mapaPermisos
 * se delega a PermisosCursoService para eliminar la triplicación.
 *
 * B-11: regla exists unificada a 'usuario.usuario,id_usuario' (esquema.tabla)
 * que es la forma correcta en PostgreSQL multi-esquema; la tabla usuario
 * vive en el esquema 'usuario' (ver search_path en config/database.php).
 */
class CursoPermisosController extends Controller
{
    // ─── Permisos relevantes para el syllabus ───────────────────────────────

    private const SYLLABUS_SLUGS = [
        'cursos/programas:agregar',
        'cursos/programas/modificar:modulo_1',
        'cursos/programas/modificar:modulo_2',
        'cursos/programas/modificar:modulo_3',
        'cursos/programas/modificar:modulo_4',
        'cursos/programas/modificar:modulo_5',
        'cursos/programas/modificar:modulo_6',
        'cursos/programas/modificar:modulo_7',
        'cursos/programas/modificar:modulo_8',
        'cursos/programas/modificar:modulo_9',
    ];

    // ─── Permisos relevantes para un componente colegiado ───────────────────

    private const COMPONENTE_SLUGS = [
        'actividades:evaluar',
        'actividades:editar',
        'componentes/asistencia:registrar',
        'componentes/asistencia:editar',
    ];

    public function __construct(
        private readonly PermisosCursoService $permisosService
    ) {}

    // ────────────────────────────────────────────────────────────────────────
    // SYLLABUS — permisos del DT sobre los docentes del curso
    // ────────────────────────────────────────────────────────────────────────

    /**
     * GET /docente/cursos/{curso}/permisos-syllabus
     *
     * Retorna una página Inertia con:
     *  - Lista de docentes del curso (excluyendo al DT)
     *  - Para cada docente: qué permisos de syllabus tiene en el contexto del curso
     *  - Los slugs disponibles para gestionar
     */
    public function syllabusIndex(Curso $curso)
    {
        $this->authorize('manageTeam', $curso);

        $this->permisosService->ensureContextCurso($curso);

        $slugsDisponibles = $this->resolvePermisos(self::SYLLABUS_SLUGS);

        // Docentes del curso: titular de componentes + docenteComponentes
        $docentes = $this->getDocentesCurso($curso);

        $idPermisos = $slugsDisponibles->pluck('id_permiso')->toArray();

        $matrix = $docentes->map(function ($docente) use ($curso, $idPermisos) {
            $permisos = $this->permisosService->mapaPermisosPorId(
                $docente['id_usuario'],
                $curso->id_contexto,
                $idPermisos
            );
            return array_merge($docente, ['permisos' => $permisos]);
        });

        return Inertia::render('docente/PermisosSyllabus', [
            'curso'              => $curso,
            'docentes'           => $matrix->values(),
            'slugs_disponibles'  => $slugsDisponibles->values(),
            'id_contexto_curso'  => $curso->id_contexto,
        ]);
    }

    /**
     * POST /docente/cursos/{curso}/permisos-syllabus
     *
     * Body: { id_usuario: int, slug: string, otorgar: bool }
     * Otorga o revoca un permiso de syllabus a un docente del curso.
     * Retorna un redirect a la página de permisos.
     */
    public function syllabusSync(Request $request, Curso $curso)
    {
        $this->authorize('manageTeam', $curso);
        $this->permisosService->ensureContextCurso($curso);

        // B-11: corregido de 'exists:usuario,id_usuario' → 'exists:usuario.usuario,id_usuario'
        $validated = $request->validate([
            'id_usuario' => ['required', 'integer', 'exists:usuario.usuario,id_usuario'],
            'slug'       => ['required', 'string', 'in:' . implode(',', self::SYLLABUS_SLUGS)],
            'otorgar'    => ['required', 'boolean'],
        ]);

        $this->permisosService->syncPermiso(
            idUsuario:  $validated['id_usuario'],
            slugOrId:   $validated['slug'],
            idContexto: $curso->id_contexto,
            otorgar:    $validated['otorgar'],
            origen:     'CursoPermisos:syllabus',
        );

        return back()->with('success', 'Permiso actualizado exitosamente.');
    }

    // ────────────────────────────────────────────────────────────────────────
    // COMPONENTE COLEGIADO — permisos del DT del componente
    // ────────────────────────────────────────────────────────────────────────

    /**
     * GET /docente/cursos/{curso}/componentes/{componente}/permisos
     *
     * Retorna una página Inertia con:
     *  - Lista de docentes asignados al componente (excluyendo al DT)
     *  - Para cada docente: qué permisos de notas/asistencia tiene en el contexto del componente
     *  - Los slugs disponibles
     */
    public function componenteIndex(Curso $curso, Componente $componente)
    {
        $this->authorizeEsTitularComponente($componente);

        $this->permisosService->ensureContextComponente($componente);

        $slugsDisponibles = $this->resolvePermisos(self::COMPONENTE_SLUGS);

        $docentes = $this->getDocentesComponente($componente);

        $idPermisos = $slugsDisponibles->pluck('id_permiso')->toArray();

        $matrix = $docentes->map(function ($docente) use ($componente, $idPermisos) {
            $permisos = $this->permisosService->mapaPermisosPorId(
                $docente['id_usuario'],
                $componente->id_contexto,
                $idPermisos
            );
            return array_merge($docente, ['permisos' => $permisos]);
        });

        return Inertia::render('docente/PermisosComponente', [
            'curso'                  => $curso,
            'componente'             => $componente,
            'docentes'               => $matrix->values(),
            'slugs_disponibles'      => $slugsDisponibles->values(),
            'id_contexto_componente' => $componente->id_contexto,
        ]);
    }

    /**
     * POST /docente/cursos/{curso}/componentes/{componente}/permisos
     *
     * Body: { id_usuario: int, slug: string, otorgar: bool }
     * Retorna un redirect a la página de permisos del componente.
     */
    public function componenteSync(Request $request, Curso $curso, Componente $componente)
    {
        $this->authorizeEsTitularComponente($componente);
        $this->permisosService->ensureContextComponente($componente);

        // B-11: corregido de 'exists:usuario,id_usuario' → 'exists:usuario.usuario,id_usuario'
        $validated = $request->validate([
            'id_usuario' => ['required', 'integer', 'exists:usuario.usuario,id_usuario'],
            'slug'       => ['required', 'string', 'in:' . implode(',', self::COMPONENTE_SLUGS)],
            'otorgar'    => ['required', 'boolean'],
        ]);

        $this->permisosService->syncPermiso(
            idUsuario:  $validated['id_usuario'],
            slugOrId:   $validated['slug'],
            idContexto: $componente->id_contexto,
            otorgar:    $validated['otorgar'],
            origen:     'CursoPermisos:componente',
        );

        return back()->with('success', 'Permiso actualizado exitosamente.');
    }

    // ────────────────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ────────────────────────────────────────────────────────────────────────

    private function authorizeEsTitularComponente(Componente $componente): void
    {
        /** @var Usuario $user */
        $user = Auth::user();
        if (!$user->docente) {
            abort(403);
        }
        $esTitular = $componente->docenteComponentes()
            ->where('id_docente', $user->docente->id_docente)
            ->where('es_titular', true)
            ->exists();

        if (!$esTitular) {
            abort(403, 'Solo el docente titular del componente puede gestionar estos permisos.');
        }
    }

    /** Resuelve Permiso[] a partir de slugs, ignorando los que no existen en DB */
    private function resolvePermisos(array $slugs): \Illuminate\Support\Collection
    {
        return Permiso::whereIn('slug', $slugs)
            ->select(['id_permiso', 'slug', 'nombre'])
            ->get();
    }

    /** Retorna los docentes del curso distintos al DT, con id_usuario */
    private function getDocentesCurso(Curso $curso): \Illuminate\Support\Collection
    {
        /** @var Usuario $user */
        $user = Auth::user();

        return $curso->componentes()
            ->with(['docenteComponentes.docente.usuario'])
            ->get()
            ->flatMap(fn($c) => $c->docenteComponentes)
            ->filter(fn($dc) => $dc->docente && $dc->id_docente !== $user->docente->id_docente)
            ->map(fn($dc) => [
                'id_usuario'      => $dc->docente->usuario->id_usuario,
                'id_docente'      => $dc->id_docente,
                'nombre'          => trim(
                    ($dc->docente->usuario->nombre1   ?? '') . ' ' .
                    ($dc->docente->usuario->apellido1 ?? '')
                ),
            ])
            ->unique('id_usuario')
            ->values();
    }

    /** Retorna los docentes del componente distintos al DT del componente */
    private function getDocentesComponente(Componente $componente): \Illuminate\Support\Collection
    {
        /** @var Usuario $user */
        $user = Auth::user();

        return $componente->docenteComponentes()
            ->with(['docente.usuario'])
            ->get()
            ->filter(fn($dc) => $dc->docente && $dc->id_docente !== $user->docente->id_docente)
            ->map(fn($dc) => [
                'id_usuario'  => $dc->docente->usuario->id_usuario,
                'id_docente'  => $dc->id_docente,
                'nombre'      => trim(
                    ($dc->docente->usuario->nombre1   ?? '') . ' ' .
                    ($dc->docente->usuario->apellido1 ?? '')
                ),
                'es_titular'  => $dc->es_titular,
            ])
            ->values();
    }
}
