<?php

namespace App\Services\Docente;

use App\Models\Curso\Curso;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Services\Authorization\PermissionCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PermisosCursoService
 *
 * Centraliza la lógica de permisos especiales sobre cursos y componentes
 * que antes estaba triplicada en CursoPermisosController, DelegacionPermisosController
 * y DocenteCursoController (B-04).
 *
 * Mecanismo de escritura elegido: upsert directo sobre UsuarioPermisoEspecial
 * (create/update manual). Razón: los builders givePermission()->on($curso)->save()
 * de DocenteCursoController::syncMemberPermissions dependen de App\Support\Permissions
 * (un enum de slugs cerrado), lo que impide usarlos con slugs dinámicos en tiempo de
 * ejecución. El upsert directo es más simple, más portable, y ya era el mecanismo
 * canónico en CursoPermisosController y DelegacionPermisosController.
 *
 * Seguridad preservada:
 * - La verificación IDOR (assertIsMiembroCurso) y la whitelist de slugs delegables
 *   siguen siendo responsabilidad de DelegacionPermisosController, que conoce el
 *   contexto de negocio de cada acción. Este servicio no añade ni relaja guards.
 * - No se crea el accessor nombre_completo en Usuario (lo hace otro agente);
 *   las concatenaciones de nombre se mantienen locales en cada llamador.
 */
class PermisosCursoService
{
    // El contexto de cursos y componentes lo crean los triggers de la BD al
    // insertarlos (con tipo y padre); este servicio no crea contextos.

    // ────────────────────────────────────────────────────────────────────────
    // SYNC DE PERMISO
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Otorga o revoca un permiso especial para un usuario en un contexto dado.
     *
     * El permiso se identifica por slug (se resuelve a id_permiso con firstOrFail)
     * o directamente por id_permiso si $slugOrId es un entero.
     *
     * Mecanismo: upsert directo sobre usuario.usuario_permiso_especial.
     * Ver cabecera de clase para la justificación.
     *
     * @param int        $idUsuario  Usuario al que se otorga/revoca el permiso.
     * @param string|int $slugOrId   Slug del permiso (string) o id_permiso (int).
     * @param int        $idContexto Contexto en el que aplica el permiso.
     * @param bool       $otorgar    true = otorgar, false = revocar.
     * @param string     $origen     Etiqueta para el log (p. ej. 'CursoPermisos').
     */
    public function syncPermiso(
        int $idUsuario,
        string|int $slugOrId,
        int $idContexto,
        bool $otorgar,
        string $origen = 'PermisosCursoService'
    ): void {
        $permiso = is_int($slugOrId)
            ? Permiso::findOrFail($slugOrId)
            : Permiso::where('slug', $slugOrId)->firstOrFail();

        $existing = UsuarioPermisoEspecial::where('id_usuario', $idUsuario)
            ->where('id_contexto', $idContexto)
            ->where('id_permiso', $permiso->id_permiso)
            ->first();

        if ($otorgar) {
            if ($existing) {
                $existing->update([
                    'esta_activo'             => true,
                    'fue_borrado'             => false,
                    'esta_permitido'          => true,
                    'fecha_fin_real'          => null,
                    'fecha_fin_planificada'   => now()->addYears(5),
                    'creado_por'              => Auth::id(),
                ]);
            } else {
                UsuarioPermisoEspecial::create([
                    'id_usuario'               => $idUsuario,
                    'id_permiso'               => $permiso->id_permiso,
                    'id_contexto'              => $idContexto,
                    'esta_permitido'           => true,
                    'puede_delegar'            => false,
                    'esta_activo'              => true,
                    'fue_borrado'              => false,
                    'fecha_inicio_planificada' => now()->subMinute(),
                    'fecha_fin_planificada'    => now()->addYears(5),
                    'fecha_fin_real'           => null,
                    'creado_por'               => Auth::id(),
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

        Log::info("[{$origen}] syncPermiso", [
            'id_usuario'  => $idUsuario,
            'permiso'     => is_int($slugOrId) ? "id:{$slugOrId}" : $slugOrId,
            'id_contexto' => $idContexto,
            'otorgar'     => $otorgar,
            'auth_user'   => Auth::id(),
        ]);

        // Invalidar caché del usuario modificado para reflejo inmediato en can()
        app(PermissionCache::class)->olvidarUsuario($idUsuario);
    }

    /**
     * Sincroniza múltiples permisos para un usuario en un contexto específico en una sola transacción.
     *
     * @param int $idUsuario
     * @param array<string|int> $slugsOrIds
     * @param int $idContexto
     * @param bool $otorgar
     * @param string $origen
     */
    public function syncPermisosBatch(
        int $idUsuario,
        array $slugsOrIds,
        int $idContexto,
        bool $otorgar,
        string $origen = 'PermisosCursoService'
    ): void {
        if (empty($slugsOrIds)) {
            return;
        }

        DB::transaction(function () use ($idUsuario, $slugsOrIds, $idContexto, $otorgar, $origen) {
            $slugs = array_values(array_filter($slugsOrIds, 'is_string'));
            $ids   = array_values(array_filter($slugsOrIds, 'is_int'));

            $query = Permiso::query();
            if (!empty($slugs) && !empty($ids)) {
                $query->whereIn('slug', $slugs)->orWhereIn('id_permiso', $ids);
            } elseif (!empty($slugs)) {
                $query->whereIn('slug', $slugs);
            } else {
                $query->whereIn('id_permiso', $ids);
            }

            $permisos = $query->get()->keyBy('id_permiso');
            $permisosIds = $permisos->keys()->all();

            $existing = UsuarioPermisoEspecial::where('id_usuario', $idUsuario)
                ->where('id_contexto', $idContexto)
                ->whereIn('id_permiso', $permisosIds)
                ->get()
                ->keyBy('id_permiso');

            $authId = Auth::id();

            foreach ($permisos as $permiso) {
                $existente = $existing->get($permiso->id_permiso);

                if ($otorgar) {
                    if ($existente) {
                        $existente->update([
                            'esta_activo'             => true,
                            'fue_borrado'             => false,
                            'esta_permitido'          => true,
                            'fecha_fin_real'          => null,
                            'fecha_fin_planificada'   => now()->addYears(5),
                            'creado_por'              => $authId,
                        ]);
                    } else {
                        UsuarioPermisoEspecial::create([
                            'id_usuario'               => $idUsuario,
                            'id_permiso'               => $permiso->id_permiso,
                            'id_contexto'              => $idContexto,
                            'esta_permitido'           => true,
                            'puede_delegar'            => false,
                            'esta_activo'              => true,
                            'fue_borrado'              => false,
                            'fecha_inicio_planificada' => now()->subMinute(),
                            'fecha_fin_planificada'    => now()->addYears(5),
                            'fecha_fin_real'           => null,
                            'creado_por'               => $authId,
                        ]);
                    }
                } else {
                    if ($existente) {
                        $existente->update([
                            'esta_activo'    => false,
                            'fue_borrado'    => true,
                            'fecha_fin_real' => now(),
                            'eliminado_por'  => $authId,
                        ]);
                    }
                }
            }

            Log::info("[{$origen}] syncPermisosBatch", [
                'id_usuario'  => $idUsuario,
                'total'       => count($permisos),
                'id_contexto' => $idContexto,
                'otorgar'     => $otorgar,
                'auth_user'   => $authId,
            ]);
        });

        // Invalidar caché del usuario modificado una sola vez
        app(PermissionCache::class)->olvidarUsuario($idUsuario);
    }

    // ────────────────────────────────────────────────────────────────────────
    // MAPA DE PERMISOS ACTIVOS
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Devuelve un mapa { id_permiso => bool } indicando si el usuario tiene
     * cada permiso activo en el contexto dado.
     *
     * Usado por CursoPermisosController (que indexa por id_permiso).
     *
     * @param  int   $idUsuario
     * @param  int   $idContexto
     * @param  int[] $idPermisos
     * @return array<int, bool>
     */
    public function mapaPermisosPorId(int $idUsuario, int $idContexto, array $idPermisos): array
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
     * Devuelve un mapa { slug => bool } indicando si el usuario tiene
     * cada permiso activo en el contexto dado.
     *
     * Usado por DelegacionPermisosController (que indexa por slug).
     *
     * @param  int        $idUsuario
     * @param  int        $idContexto
     * @param  Collection $permisosDb  Collection<string, Permiso> keyed by slug
     * @return array<string, bool>
     */
    public function mapaPermisosPorSlug(int $idUsuario, int $idContexto, Collection $permisosDb): array
    {
        $idPermisos = $permisosDb->pluck('id_permiso')->toArray();

        $activosIds = UsuarioPermisoEspecial::where('id_usuario', $idUsuario)
            ->where('id_contexto', $idContexto)
            ->whereIn('id_permiso', $idPermisos)
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->pluck('id_permiso')
            ->toArray();

        $map = [];
        foreach ($permisosDb as $slug => $permiso) {
            $map[$slug] = in_array($permiso->id_permiso, $activosIds);
        }
        return $map;
    }

    /**
     * Devuelve el mapa completo de permisos por contexto para un miembro del curso,
     * identificando permisos directos, heredados de un contexto padre/global (superseded),
     * y permisos inactivos.
     *
     * Estructura:
     *   [
     *     id_contexto => [
     *       slug => [ 'activo' => bool, 'origen' => 'directo' | 'heredado' | 'inactivo' ]
     *     ]
     *   ]
     *
     * @param int $idUsuario
     * @param Curso $curso
     * @param Collection $componentes
     * @param Collection<string, Permiso> $permisosDb
     * @return array<int, array<string, array{activo: bool, origen: string}>>
     */
    public function mapaPermisosPorContextosCurso(
        int $idUsuario,
        Curso $curso,
        Collection $componentes,
        Collection $permisosDb
    ): array {
        $idContextoCurso = $curso->id_contexto;
        $componentesContextos = $componentes->pluck('id_contexto')->filter()->values()->all();
        $allContextos = array_merge([$idContextoCurso], $componentesContextos);

        $idPermisos = $permisosDb->pluck('id_permiso')->all();

        // 1. UPEs activas en todos los contextos del curso (curso + componentes)
        $upes = UsuarioPermisoEspecial::where('id_usuario', $idUsuario)
            ->whereIn('id_contexto', $allContextos)
            ->whereIn('id_permiso', $idPermisos)
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->get();

        $upesPorContexto = $upes->groupBy('id_contexto')->map(fn ($g) => $g->pluck('id_permiso')->all());
        $cursoUpeIds = $upesPorContexto->get($idContextoCurso, []);

        $map = [];

        // 2. Evaluar primero el contexto PADRE (Curso Completo)
        // En el nivel de curso, todos los permisos delegados provienen de asignaciones directas en el curso.
        // No se consideran roles transversales globales para no contaminar la matriz delegable del curso.
        $map[$idContextoCurso] = [];
        foreach ($permisosDb as $slug => $permiso) {
            $esDirecto = in_array($permiso->id_permiso, $cursoUpeIds, true);
            $map[$idContextoCurso][$slug] = [
                'activo' => $esDirecto,
                'origen' => $esDirecto ? 'directo' : 'inactivo',
            ];
        }

        // 3. Evaluar los contextos HIJOS (Componentes) aplicando superseding
        foreach ($componentesContextos as $idCtxComp) {
            $compUpeIds = $upesPorContexto->get($idCtxComp, []);
            $map[$idCtxComp] = [];

            foreach ($permisosDb as $slug => $permiso) {
                $activoEnPadre = $map[$idContextoCurso][$slug]['activo'] ?? false;

                if ($activoEnPadre) {
                    // Superseded: Ya está asignado a nivel de Curso
                    $map[$idCtxComp][$slug] = ['activo' => true, 'origen' => 'heredado'];
                } elseif (in_array($permiso->id_permiso, $compUpeIds, true)) {
                    // Asignado específicamente a este componente
                    $map[$idCtxComp][$slug] = ['activo' => true, 'origen' => 'directo'];
                } else {
                    $map[$idCtxComp][$slug] = ['activo' => false, 'origen' => 'inactivo'];
                }
            }
        }

        return $map;
    }

    /**
     * Verifica si un usuario tiene un permiso activo en un contexto dado (por UPE o global).
     */
    public function tienePermisoActivoEnContexto(int $idUsuario, int $idContexto, string $slug): bool
    {
        return !empty($this->slugsActivosEnContexto($idUsuario, $idContexto, [$slug]));
    }

    /**
     * Retorna el subconjunto de slugs que están activos para el usuario en el contexto dado.
     *
     * @param int $idUsuario
     * @param int $idContexto
     * @param array<string> $slugs
     * @return array<string>
     */
    public function slugsActivosEnContexto(int $idUsuario, int $idContexto, array $slugs): array
    {
        if (empty($slugs)) {
            return [];
        }

        $permisosIds = Permiso::whereIn('slug', $slugs)->pluck('id_permiso', 'slug')->all();
        if (empty($permisosIds)) {
            return [];
        }

        // UPEs directas en $idContexto
        $upesDirectas = UsuarioPermisoEspecial::where('id_usuario', $idUsuario)
            ->where('id_contexto', $idContexto)
            ->whereIn('id_permiso', array_values($permisosIds))
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->pluck('id_permiso')
            ->all();

        $activos = [];
        foreach ($permisosIds as $slug => $idPermiso) {
            if (in_array($idPermiso, $upesDirectas, true)) {
                $activos[] = $slug;
            }
        }

        return $activos;
    }

    // ────────────────────────────────────────────────────────────────────────
    // MIEMBROS DEL CURSO
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Retorna los docentes del curso distintos al usuario autenticado.
     *
     * Cada elemento: { id_usuario, id_docente, nombre, es_titular, tipo_componente }
     * `tipo_componente` es el del primer componente encontrado para ese docente
     * (Cátedra/Taller/Laboratorio) — sólo para mostrar contexto en la UI, no
     * afecta qué permisos puede tener.
     *
     * @return Collection<int, array{id_usuario: int, id_docente: int, nombre: string, es_titular: bool, tipo_componente: ?string}>
     */
    public function miembrosDelCurso(Curso $curso): Collection
    {
        /** @var \App\Models\Usuario\Usuario $authUser */
        $authUser = Auth::user();

        return $curso->componentes()
            ->with(['tipoComponente', 'docenteComponentes.docente.usuario'])
            ->get()
            ->flatMap(fn ($c) => $c->docenteComponentes->map(fn ($dc) => [$dc, $c]))
            ->filter(fn ($par) => $par[0]->docente && $par[0]->id_docente !== $authUser->docente->id_docente)
            ->map(function ($par) {
                [$dc, $componente] = $par;

                return [
                    'id_usuario' => $dc->docente->usuario->id_usuario,
                    'id_docente' => $dc->id_docente,
                    'nombre'     => trim(
                        ($dc->docente->usuario->nombre1   ?? '') . ' ' .
                        ($dc->docente->usuario->apellido1 ?? '')
                    ),
                    'es_titular'      => (bool) $dc->es_titular,
                    'tipo_componente' => $componente->tipoComponente->tipo ?? null,
                ];
            })
            ->unique('id_usuario')
            ->values();
    }
}
