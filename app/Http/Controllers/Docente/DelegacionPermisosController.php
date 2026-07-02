<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Usuario;
use App\Services\Docente\PermisosCursoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DelegacionPermisosController
 *
 * Permite al Docente Titular de un curso delegar permisos granulares
 * a los demás integrantes del equipo docente dentro del contexto del curso.
 *
 * Seguridad:
 *  - authorizeIsTitular() verifica que el usuario autenticado sea el titular actual.
 *  - assertIsMiembroCurso() evita IDOR: el target user DEBE pertenecer al equipo del curso.
 *  - Solo permisos del DELEGABLE_MATRIX pueden delegarse (whitelist estricta).
 *
 * Refactorizado (B-04): syncPermiso, ensureContext y buildPermisosMap
 * delegados a PermisosCursoService.
 *
 * B-11: regla exists unificada a 'usuario.usuario,id_usuario' (ya era correcta aquí;
 * confirmada como la forma canónica vs. 'exists:usuario,id_usuario' de CursoPermisos).
 *
 * Endpoints:
 *  GET  /docente/cursos/{curso}/delegacion-permisos         → index()
 *  POST /docente/cursos/{curso}/delegacion-permisos/toggle  → toggle()
 */
class DelegacionPermisosController extends Controller
{
    /**
     * Matriz de permisos que el DT puede delegar, agrupados por área funcional.
     * Sólo los slugs presentes aquí son válidos para delegación.
     */
    private const DELEGABLE_MATRIX = [
        'Curso' => [
            'cursos:ver',
            'cursos:editar',
            'cursos:eliminar',
        ],
        'Inscripciones' => [
            'cursos/inscripciones:ver',
        ],
        'Unidades' => [
            'cursos/unidades:ver',
            'cursos/unidades:crear',
            'cursos/unidades:crear_plantilla',
            'cursos/unidades:editar',
            'cursos/unidades:eliminar',
        ],
        'Programas' => [
            'cursos/programas:ver_todos',
            'cursos/programas:agregar',
            'cursos/programas:eliminar',
            'cursos/programas/modificar:modulo_1',
            'cursos/programas/modificar:modulo_2',
            'cursos/programas/modificar:modulo_3',
            'cursos/programas/modificar:modulo_4',
            'cursos/programas/modificar:modulo_5',
            'cursos/programas/modificar:modulo_6',
            'cursos/programas/modificar:modulo_7',
            'cursos/programas/modificar:modulo_8',
            'cursos/programas/modificar:modulo_9',
        ],
        'Componentes' => [
            'componentes:ver',
            'componentes:editar',
        ],
        'Actividades' => [
            'actividades:ver',
            'actividades:crear',
            'actividades:crear_plantilla',
            'actividades:editar',
            'actividades:eliminar',
            'actividades:evaluar',
            'actividades:dar_feedback',
            'actividades:descargar_entregas',
            'actividades:enviar_recordatorios',
            'actividades:subir_entregas',
        ],
        'Grupos de Actividad' => [
            'actividades/grupos:ver',
            'actividades/grupos:crear',
            'actividades/grupos:editar',
            'actividades/grupos:eliminar',
        ],
    ];

    public function __construct(
        private readonly PermisosCursoService $permisosService
    ) {}

    // ────────────────────────────────────────────────────────────────────────
    // ENDPOINTS PÚBLICOS
    // ────────────────────────────────────────────────────────────────────────

    /**
     * GET /docente/cursos/{curso}/delegacion-permisos
     *
     * Renderiza la página de delegación de permisos para el curso.
     *
     * Props Inertia:
     *  - curso          : datos básicos del curso
     *  - miembros       : equipo docente (excluye al DT) con mapa de permisos activos
     *  - grupos         : matriz agrupada de permisos delegables (definiciones)
     *  - id_contexto    : id_contexto del curso (para debug/referencia frontend)
     */
    public function index(Curso $curso): Response
    {
        $this->authorize('manageTeam', $curso);
        $this->permisosService->ensureContextCurso($curso);

        $allSlugs   = $this->allDelegableSlugs();
        $permisosDb = Permiso::whereIn('slug', $allSlugs)
            ->select(['id_permiso', 'slug', 'nombre'])
            ->get()
            ->keyBy('slug');

        $miembros = $this->permisosService->miembrosDelCurso($curso);

        $miembrosConPermisos = $miembros->map(function (array $miembro) use ($curso, $permisosDb) {
            return array_merge($miembro, [
                'permisos' => $this->permisosService->mapaPermisosPorSlug(
                    $miembro['id_usuario'],
                    $curso->id_contexto,
                    $permisosDb
                ),
            ]);
        })->values();

        $grupos = $this->buildGruposDefinicion($permisosDb);

        return Inertia::render('docente/DelegacionPermisosCurso', [
            'curso'       => $curso->only(['id_curso', 'cod_curso', 'nombre']),
            'miembros'    => $miembrosConPermisos,
            'grupos'      => $grupos,
            'id_contexto' => $curso->id_contexto,
        ]);
    }

    /**
     * POST /docente/cursos/{curso}/delegacion-permisos/toggle
     *
     * Otorga o revoca un permiso individual para un miembro del curso.
     *
     * Request body:
     *   { id_usuario: int, slug: string, otorgar: bool }
     *
     * Response:
     *   redirect()->back() con flash 'success' o errores de validación
     */
    public function toggle(Request $request, Curso $curso): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('manageTeam', $curso);
        $this->permisosService->ensureContextCurso($curso);

        $allSlugs = $this->allDelegableSlugs();

        $validated = $request->validate([
            'id_usuario' => ['required', 'integer', 'exists:usuario,id_usuario'],
            'slug'       => ['required', 'string', 'in:' . implode(',', $allSlugs)],
            'otorgar'    => ['required', 'boolean'],
        ]);

        // IDOR guard: target user must belong to the course team, not be the DT
        $this->assertIsMiembroCurso($curso, $validated['id_usuario']);

        $this->permisosService->syncPermiso(
            idUsuario:  $validated['id_usuario'],
            slugOrId:   $validated['slug'],
            idContexto: $curso->id_contexto,
            otorgar:    $validated['otorgar'],
            origen:     'DelegacionPermisos',
        );

        return back()->with('success', 'Permiso actualizado');
    }

    // ────────────────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ────────────────────────────────────────────────────────────────────────

    /**
     * IDOR guard: verifica que id_usuario pertenece al equipo del curso y no es el DT.
     */
    private function assertIsMiembroCurso(Curso $curso, int $idUsuario): void
    {
        /** @var Usuario $authUser */
        $authUser = Auth::user();

        // Prevenir auto-delegación
        if ($idUsuario === $authUser->id_usuario) {
            abort(422, 'No puedes delegarte permisos a ti mismo.');
        }

        $esMiembro = $curso->componentes()
            ->whereHas('docenteComponentes.docente', function ($q) use ($idUsuario) {
                $q->whereHas('usuario', fn($u) => $u->where('id_usuario', $idUsuario));
            })
            ->exists();

        if (!$esMiembro) {
            Log::channel('seguridad')->warning('IDOR: intento de delegar permiso a usuario fuera del curso', [
                'evento'              => 'IDOR_DELEGACION_USUARIO_EXTERNO',
                'id_usuario_auth'     => $authUser->id_usuario,
                'id_usuario_target'   => $idUsuario,
                'id_curso'            => $curso->id_curso,
                'ip'                  => request()->ip(),
            ]);
            abort(403, 'El usuario no pertenece al equipo docente de este curso.');
        }
    }

    /**
     * Construye la definición agrupada de permisos delegables para el frontend.
     *
     * @param  \Illuminate\Support\Collection $permisosDb keyed by slug
     * @return array<int, array{grupo: string, permisos: array<int, array{slug: string, nombre: string}>}>
     */
    private function buildGruposDefinicion(\Illuminate\Support\Collection $permisosDb): array
    {
        $grupos = [];

        foreach (self::DELEGABLE_MATRIX as $grupo => $slugs) {
            $permisos = [];
            foreach ($slugs as $slug) {
                if ($permisosDb->has($slug)) {
                    $permisos[] = [
                        'slug'   => $slug,
                        'nombre' => $permisosDb[$slug]->nombre,
                    ];
                }
            }

            if (!empty($permisos)) {
                $grupos[] = [
                    'grupo'    => $grupo,
                    'permisos' => $permisos,
                ];
            }
        }

        return $grupos;
    }

    /** Flattens DELEGABLE_MATRIX into a simple slug array. */
    private function allDelegableSlugs(): array
    {
        return array_merge(...array_values(self::DELEGABLE_MATRIX));
    }
}
