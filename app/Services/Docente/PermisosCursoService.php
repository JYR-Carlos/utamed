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
        $this->syncPermisosBatchEstado(
            idUsuario: $idUsuario,
            slugsOrIds: $slugsOrIds,
            idContexto: $idContexto,
            estado: $otorgar ? 'asignado' : 'neutro',
            origen: $origen,
        );
    }

    /**
     * Sincroniza múltiples permisos en modo tri-estado ('asignado' | 'neutro' | 'revocado')
     * dentro de una sola transacción:
     *  - 'asignado': UPE activa con esta_permitido = true (GRANT)
     *  - 'revocado': UPE activa con esta_permitido = false (DENY explícito)
     *  - 'neutro':   soft-delete / desactivación de la UPE (sin override)
     *
     * @param int $idUsuario
     * @param array<string|int> $slugsOrIds
     * @param int $idContexto
     * @param 'asignado'|'neutro'|'revocado' $estado
     * @param string $origen
     */
    public function syncPermisosBatchEstado(
        int $idUsuario,
        array $slugsOrIds,
        int $idContexto,
        string $estado,
        string $origen = 'PermisosCursoService'
    ): void {
        if (empty($slugsOrIds)) {
            return;
        }

        if (!in_array($estado, ['asignado', 'neutro', 'revocado'], true)) {
            throw new \InvalidArgumentException("Estado de permiso inválido: {$estado}");
        }

        DB::transaction(function () use ($idUsuario, $slugsOrIds, $idContexto, $estado, $origen) {
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

                if ($estado === 'asignado' || $estado === 'revocado') {
                    $permitido = $estado === 'asignado';

                    if ($existente) {
                        $existente->update([
                            'esta_activo'           => true,
                            'fue_borrado'           => false,
                            'esta_permitido'        => $permitido,
                            'fecha_fin_real'        => null,
                            'fecha_fin_planificada' => now()->addYears(5),
                            'creado_por'            => $authId,
                        ]);
                    } else {
                        UsuarioPermisoEspecial::create([
                            'id_usuario'               => $idUsuario,
                            'id_permiso'               => $permiso->id_permiso,
                            'id_contexto'              => $idContexto,
                            'esta_permitido'           => $permitido,
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

            Log::info("[{$origen}] syncPermisosBatchEstado", [
                'id_usuario'  => $idUsuario,
                'total'       => count($permisos),
                'id_contexto' => $idContexto,
                'estado'      => $estado,
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
     * Devuelve el mapa completo tri-estado de permisos por contexto para un miembro del curso,
     * identificando:
     *  - `estado`: 'asignado' | 'neutro' | 'revocado'
     *  - `activo`: bool (retrocompatibilidad: true cuando estado === 'asignado')
     *  - `origen`: 'directo' | 'heredado' | 'revocado' | 'revocado_padre' | 'inactivo'
     *  - `activoPorRol`: bool (true si el usuario ya posee ese permiso por rol URA en ese ámbito o en el curso)
     *  - `bloqueadoPor`: 'heredado' | 'revocado' | null
     *  - `motivoBloqueo`: ?string
     *
     * @param int $idUsuario
     * @param Curso $curso
     * @param Collection $componentes
     * @param Collection<string, Permiso> $permisosDb
     * @return array<int, array<string, array{activo: bool, origen: string, estado: string, activoPorRol: bool, bloqueadoPor: ?string, motivoBloqueo: ?string}>>
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

        // 1. UPEs activas en todos los contextos del curso (incluyendo GRANT y DENY)
        $upes = UsuarioPermisoEspecial::where('id_usuario', $idUsuario)
            ->whereIn('id_contexto', $allContextos)
            ->whereIn('id_permiso', $idPermisos)
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->get(['id_contexto', 'id_permiso', 'esta_permitido']);

        $upesPorContexto = [];
        foreach ($upes as $upe) {
            $upesPorContexto[(int) $upe->id_contexto][(int) $upe->id_permiso] = (bool) $upe->esta_permitido;
        }

        // 2. Permisos provenientes de Roles (URA) activos en el curso o sus componentes
        $rolRows = DB::connection('pgsql')
            ->table('vw_permisos_usuario')
            ->where('id_usuario', $idUsuario)
            ->where('tipo_asignacion', 'rol')
            ->where('esta_permitido', true)
            ->whereIn('id_contexto', $allContextos)
            ->get(['id_contexto', 'slug']);

        $rolSlugsPorContexto = [];
        foreach ($rolRows as $row) {
            $rolSlugsPorContexto[(int) $row->id_contexto][(string) $row->slug] = true;
        }

        $cursoUpeMap = $upesPorContexto[$idContextoCurso] ?? [];
        $cursoRolSlugs = $rolSlugsPorContexto[$idContextoCurso] ?? [];

        $map = [];

        // 3. Evaluar contexto PADRE (Curso Completo)
        $map[$idContextoCurso] = [];
        foreach ($permisosDb as $slug => $permiso) {
            $tieneUpe = array_key_exists($permiso->id_permiso, $cursoUpeMap);
            $permitidoUpe = $tieneUpe ? $cursoUpeMap[$permiso->id_permiso] : null;
            $activoPorRol = isset($cursoRolSlugs[$slug]) || isset($cursoRolSlugs['*']);

            if ($tieneUpe && $permitidoUpe === true) {
                $map[$idContextoCurso][$slug] = [
                    'activo'        => true,
                    'origen'        => 'directo',
                    'estado'        => 'asignado',
                    'activoPorRol'  => $activoPorRol,
                    'bloqueadoPor'  => null,
                    'motivoBloqueo' => null,
                ];
            } elseif ($tieneUpe && $permitidoUpe === false) {
                $map[$idContextoCurso][$slug] = [
                    'activo'        => false,
                    'origen'        => 'revocado',
                    'estado'        => 'revocado',
                    'activoPorRol'  => $activoPorRol,
                    'bloqueadoPor'  => null,
                    'motivoBloqueo' => null,
                ];
            } else {
                $map[$idContextoCurso][$slug] = [
                    'activo'        => false,
                    'origen'        => 'inactivo',
                    'estado'        => 'neutro',
                    'activoPorRol'  => $activoPorRol,
                    'bloqueadoPor'  => null,
                    'motivoBloqueo' => null,
                ];
            }
        }

        // 4. Evaluar contextos HIJOS (Componentes) aplicando superseding (GRANT o DENY en padre)
        foreach ($componentesContextos as $idCtxComp) {
            $compUpeMap = $upesPorContexto[(int) $idCtxComp] ?? [];
            $compRolSlugs = $rolSlugsPorContexto[(int) $idCtxComp] ?? [];
            $map[$idCtxComp] = [];

            foreach ($permisosDb as $slug => $permiso) {
                $estadoPadre = $map[$idContextoCurso][$slug]['estado'] ?? 'neutro';
                $activoPorRol = isset($compRolSlugs[$slug])
                    || isset($compRolSlugs['*'])
                    || isset($cursoRolSlugs[$slug])
                    || isset($cursoRolSlugs['*']);

                if ($estadoPadre === 'revocado') {
                    $map[$idCtxComp][$slug] = [
                        'activo'        => false,
                        'origen'        => 'revocado_padre',
                        'estado'        => 'revocado',
                        'activoPorRol'  => $activoPorRol,
                        'bloqueadoPor'  => 'revocado',
                        'motivoBloqueo' => 'Inhabilitado por revocación en nivel superior (Curso).',
                    ];
                } elseif ($estadoPadre === 'asignado') {
                    $map[$idCtxComp][$slug] = [
                        'activo'        => true,
                        'origen'        => 'heredado',
                        'estado'        => 'asignado',
                        'activoPorRol'  => $activoPorRol,
                        'bloqueadoPor'  => 'heredado',
                        'motivoBloqueo' => 'Heredado desde nivel superior (Curso) y no modificable en este componente.',
                    ];
                } elseif (array_key_exists($permiso->id_permiso, $compUpeMap)) {
                    $permitidoComp = $compUpeMap[$permiso->id_permiso];
                    $map[$idCtxComp][$slug] = [
                        'activo'        => $permitidoComp,
                        'origen'        => $permitidoComp ? 'directo' : 'revocado',
                        'estado'        => $permitidoComp ? 'asignado' : 'revocado',
                        'activoPorRol'  => $activoPorRol,
                        'bloqueadoPor'  => null,
                        'motivoBloqueo' => null,
                    ];
                } else {
                    $map[$idCtxComp][$slug] = [
                        'activo'        => false,
                        'origen'        => 'inactivo',
                        'estado'        => 'neutro',
                        'activoPorRol'  => $activoPorRol,
                        'bloqueadoPor'  => null,
                        'motivoBloqueo' => null,
                    ];
                }
            }
        }

        return $map;
    }

    /**
     * Retorna el mapa { slug => 'asignado' | 'revocado' } de UPEs directas activas
     * en el contexto dado para los slugs solicitados.
     *
     * @param int $idUsuario
     * @param int $idContexto
     * @param string[] $slugs
     * @return array<string, 'asignado'|'revocado'>
     */
    public function estadosUpeEnContexto(int $idUsuario, int $idContexto, array $slugs): array
    {
        if (empty($slugs)) {
            return [];
        }

        $permisosById = Permiso::whereIn('slug', $slugs)->pluck('slug', 'id_permiso')->all();
        if (empty($permisosById)) {
            return [];
        }

        $rows = UsuarioPermisoEspecial::where('id_usuario', $idUsuario)
            ->where('id_contexto', $idContexto)
            ->whereIn('id_permiso', array_keys($permisosById))
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->get(['id_permiso', 'esta_permitido']);

        $result = [];
        foreach ($rows as $row) {
            $slug = $permisosById[(int) $row->id_permiso] ?? null;
            if ($slug !== null) {
                $result[$slug] = $row->esta_permitido ? 'asignado' : 'revocado';
            }
        }

        return $result;
    }

    /**
     * Detecta asignaciones directas activas ('asignado') en componentes hijos del curso
     * para los slugs indicados (regla de colisión padre-hijo al revocar en el curso).
     *
     * @param int $idUsuario
     * @param Curso $curso
     * @param string[] $slugs
     * @return array<int, array{id_componente: int, id_contexto: int, tipo: string, slugs: string[]}>
     */
    public function hijosConPermisosDirectos(int $idUsuario, Curso $curso, array $slugs): array
    {
        if (empty($slugs)) {
            return [];
        }

        $componentes = $curso->componentes()->with('tipoComponente')->whereNotNull('id_contexto')->get();
        if ($componentes->isEmpty()) {
            return [];
        }

        $permisosById = Permiso::whereIn('slug', $slugs)->pluck('slug', 'id_permiso')->all();
        if (empty($permisosById)) {
            return [];
        }

        $contextosHijos = $componentes->pluck('id_contexto')->map(fn ($id) => (int) $id)->all();

        $rows = UsuarioPermisoEspecial::where('id_usuario', $idUsuario)
            ->whereIn('id_contexto', $contextosHijos)
            ->whereIn('id_permiso', array_keys($permisosById))
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->where('esta_permitido', true)
            ->get(['id_contexto', 'id_permiso']);

        if ($rows->isEmpty()) {
            return [];
        }

        $slugsPorCtx = [];
        foreach ($rows as $row) {
            $slug = $permisosById[(int) $row->id_permiso] ?? null;
            if ($slug !== null) {
                $slugsPorCtx[(int) $row->id_contexto][] = $slug;
            }
        }

        $colisiones = [];
        foreach ($componentes as $comp) {
            $ctxId = (int) $comp->id_contexto;
            if (!empty($slugsPorCtx[$ctxId])) {
                $colisiones[] = [
                    'id_componente' => $comp->id_componente,
                    'id_contexto'   => $ctxId,
                    'tipo'          => $comp->tipoComponente?->tipo ?? 'Componente',
                    'slugs'         => array_values(array_unique($slugsPorCtx[$ctxId])),
                ];
            }
        }

        return $colisiones;
    }

    /**
     * Verifica si un usuario tiene un permiso activo (GRANT) en un contexto dado.
     */
    public function tienePermisoActivoEnContexto(int $idUsuario, int $idContexto, string $slug): bool
    {
        return !empty($this->slugsActivosEnContexto($idUsuario, $idContexto, [$slug]));
    }

    /**
     * Retorna el subconjunto de slugs que están activos (esta_permitido = true)
     * para el usuario en el contexto dado.
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

        // UPEs directas GRANT en $idContexto
        $upesDirectas = UsuarioPermisoEspecial::where('id_usuario', $idUsuario)
            ->where('id_contexto', $idContexto)
            ->whereIn('id_permiso', array_values($permisosIds))
            ->where('esta_activo', true)
            ->where('fue_borrado', false)
            ->where('esta_permitido', true)
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
     * Si `$componenteIds` está acotado, lista únicamente integrantes de esos componentes
     * (permitiendo que un titular de componente vea incluso al DT del curso si participa
     * como colegiado dentro de su componente).
     *
     * @param  int[]|null $componenteIds
     * @return Collection<int, array{id_usuario: int, id_docente: int, nombre: string, es_titular: bool, tipo_componente: ?string}>
     */
    public function miembrosDelCurso(Curso $curso, ?array $componenteIds = null): Collection
    {
        /** @var \App\Models\Usuario\Usuario $authUser */
        $authUser = Auth::user();
        $idDocenteAuth = $authUser->docente?->id_docente;

        return $curso->componentes()
            ->when($componenteIds !== null, fn ($q) => $q->whereIn('id_componente', $componenteIds))
            ->with(['tipoComponente', 'docenteComponentes.docente.usuario'])
            ->get()
            ->flatMap(fn ($c) => $c->docenteComponentes->map(fn ($dc) => [$dc, $c]))
            ->filter(fn ($par) => $par[0]->docente
                && $par[0]->docente->usuario !== null
                && ($idDocenteAuth === null || $par[0]->id_docente !== $idDocenteAuth))
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
