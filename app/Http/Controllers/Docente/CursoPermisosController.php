<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioPermisoEspecial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controlador para que el Docente Titular gestione permisos granulares
 * sobre otros docentes de su curso o componente.
 *
 * Casos de uso:
 *   1. DT del curso: configurar quién puede editar el programa/syllabus.
 *   2. DT de un componente colegiado: configurar permisos de notas/asistencia
 *      para los demás docentes del componente.
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

        $this->ensureContext($curso);

        $slugsDisponibles = $this->resolvePermisos(self::SYLLABUS_SLUGS);

        // Docentes del curso: titular de componentes + docenteComponentes
        $docentes = $this->getDocentesCurso($curso);

        $matrix = $docentes->map(function ($docente) use ($curso, $slugsDisponibles) {
            $permisos = $this->getPermisosEnContexto(
                $docente['id_usuario'],
                $curso->id_contexto,
                $slugsDisponibles->pluck('id_permiso')->toArray()
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
        $this->ensureContext($curso);

        $validated = $request->validate([
            'id_usuario' => ['required', 'integer', 'exists:usuario,id_usuario'],
            'slug'       => ['required', 'string', 'in:' . implode(',', self::SYLLABUS_SLUGS)],
            'otorgar'    => ['required', 'boolean'],
        ]);

        $this->syncPermiso(
            idUsuario: $validated['id_usuario'],
            slug:      $validated['slug'],
            idContexto: $curso->id_contexto,
            otorgar:   $validated['otorgar'],
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

        $this->ensureContextComponente($componente);

        $slugsDisponibles = $this->resolvePermisos(self::COMPONENTE_SLUGS);

        $docentes = $this->getDocentesComponente($componente);

        $matrix = $docentes->map(function ($docente) use ($componente, $slugsDisponibles) {
            $permisos = $this->getPermisosEnContexto(
                $docente['id_usuario'],
                $componente->id_contexto,
                $slugsDisponibles->pluck('id_permiso')->toArray()
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
        $this->ensureContextComponente($componente);

        $validated = $request->validate([
            'id_usuario' => ['required', 'integer', 'exists:usuario,id_usuario'],
            'slug'       => ['required', 'string', 'in:' . implode(',', self::COMPONENTE_SLUGS)],
            'otorgar'    => ['required', 'boolean'],
        ]);

        $this->syncPermiso(
            idUsuario:  $validated['id_usuario'],
            slug:       $validated['slug'],
            idContexto: $componente->id_contexto,
            otorgar:    $validated['otorgar'],
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

    private function ensureContext(Curso $curso): void
    {
        if (!$curso->id_contexto || $curso->id_contexto == 1) {
            $contexto = \App\Models\Usuario\Contexto::firstOrCreate(
                ['contexto_display' => 'Curso: ' . $curso->cod_curso],
                ['descripcion'      => 'Contexto para el curso ' . $curso->cod_curso]
            );
            $curso->update(['id_contexto' => $contexto->id_contexto]);
        }
    }

    private function ensureContextComponente(Componente $componente): void
    {
        if (!$componente->id_contexto || $componente->id_contexto == 1) {
            $tipo   = $componente->tipoComponente?->tipo ?? 'Componente';
            $nombre = "{$tipo} del curso #{$componente->id_curso}";
            $contexto = \App\Models\Usuario\Contexto::firstOrCreate(
                ['contexto_display' => $nombre],
                ['descripcion'      => $nombre]
            );
            $componente->update(['id_contexto' => $contexto->id_contexto]);
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

    /**
     * Para cada permiso, indica si el usuario lo tiene activo en ese contexto.
     * Devuelve: { [id_permiso]: bool }
     */
    private function getPermisosEnContexto(int $idUsuario, int $idContexto, array $idPermisos): array
    {
        $activos = UsuarioPermisoEspecial::where('id_usuario', $idUsuario)
            ->where('id_contexto', $idContexto)
            ->whereIn('id_permiso', $idPermisos)
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->pluck('id_permiso')
            ->toArray();

        $result = [];
        foreach ($idPermisos as $id) {
            $result[$id] = in_array($id, $activos);
        }
        return $result;
    }

    /**
     * Otorga o revoca un permiso para un usuario en un contexto dado.
     */
    private function syncPermiso(int $idUsuario, string $slug, int $idContexto, bool $otorgar): void
    {
        $permiso = Permiso::where('slug', $slug)->firstOrFail();

        $existing = UsuarioPermisoEspecial::where('id_usuario', $idUsuario)
            ->where('id_contexto', $idContexto)
            ->where('id_permiso', $permiso->id_permiso)
            ->first();

        if ($otorgar) {
            if ($existing) {
                $existing->update([
                    'esta_activo'           => true,
                    'fue_borrado'           => false,
                    'esta_permitido'        => true,
                    'fecha_fin_real'        => null,
                    'fecha_fin_planificada' => now()->addYears(5),
                    'creado_por'            => Auth::id(),
                ]);
            } else {
                UsuarioPermisoEspecial::create([
                    'id_usuario'              => $idUsuario,
                    'id_permiso'              => $permiso->id_permiso,
                    'id_contexto'             => $idContexto,
                    'esta_permitido'          => true,
                    'puede_delegar'           => false,
                    'esta_activo'             => true,
                    'fue_borrado'             => false,
                    'fecha_inicio_planificada'=> now(),
                    'fecha_fin_planificada'   => now()->addYears(5),
                    'fecha_fin_real'          => null,
                    'creado_por'              => Auth::id(),
                ]);
            }
        } else {
            if ($existing) {
                $existing->update([
                    'esta_activo'    => false,
                    'fue_borrado'    => true,
                    'fecha_fin_real' => now(),
                    'eliminado_por'  => Auth::id(),
                ]);
            }
        }

        Log::info('[CursoPermisos] Sync permiso', [
            'id_usuario' => $idUsuario,
            'slug'       => $slug,
            'id_contexto'=> $idContexto,
            'otorgar'    => $otorgar,
        ]);
    }
}
