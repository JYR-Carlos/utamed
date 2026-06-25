<?php

namespace App\Services\Docente;

use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\UsuarioPermisoEspecial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
    // ────────────────────────────────────────────────────────────────────────
    // CONTEXTO
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Garantiza que el Curso tenga un contexto asignado (distinto de 1/null).
     * Si no existe, crea uno en la tabla usuario.contexto y lo vincula al curso.
     *
     * NOTA (B-14): el caller ideal sería un job/observer al crear el curso;
     * mientras tanto este método sigue siendo llamado desde puntos de escritura.
     */
    public function ensureContextCurso(Curso $curso): void
    {
        if (empty($curso->id_contexto) || $curso->id_contexto === 1) {
            $contexto = Contexto::firstOrCreate(
                ['contexto_display' => 'Curso: ' . $curso->cod_curso],
                ['descripcion'      => 'Contexto para el curso ' . $curso->cod_curso]
            );
            $curso->update(['id_contexto' => $contexto->id_contexto]);
        }
    }

    /**
     * Garantiza que el Componente tenga un contexto asignado (distinto de 1/null).
     */
    public function ensureContextComponente(Componente $componente): void
    {
        if (empty($componente->id_contexto) || $componente->id_contexto === 1) {
            $tipo   = $componente->tipoComponente?->tipo ?? 'Componente';
            $nombre = "{$tipo} del curso #{$componente->id_curso}";
            $contexto = Contexto::firstOrCreate(
                ['contexto_display' => $nombre],
                ['descripcion'      => $nombre]
            );
            $componente->update(['id_contexto' => $contexto->id_contexto]);
        }
    }

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
                    'fecha_inicio_planificada' => now(),
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

    // ────────────────────────────────────────────────────────────────────────
    // MIEMBROS DEL CURSO
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Retorna los docentes del curso distintos al usuario autenticado.
     *
     * Cada elemento: { id_usuario, id_docente, nombre }
     *
     * @return Collection<int, array{id_usuario: int, id_docente: int, nombre: string}>
     */
    public function miembrosDelCurso(Curso $curso): Collection
    {
        /** @var \App\Models\Usuario\Usuario $authUser */
        $authUser = Auth::user();

        return $curso->componentes()
            ->with(['docenteComponentes.docente.usuario'])
            ->get()
            ->flatMap(fn ($c) => $c->docenteComponentes)
            ->filter(fn ($dc) => $dc->docente && $dc->id_docente !== $authUser->docente->id_docente)
            ->map(fn ($dc) => [
                'id_usuario' => $dc->docente->usuario->id_usuario,
                'id_docente' => $dc->id_docente,
                'nombre'     => trim(
                    ($dc->docente->usuario->nombre1   ?? '') . ' ' .
                    ($dc->docente->usuario->apellido1 ?? '')
                ),
                'es_titular' => (bool) $dc->es_titular,
            ])
            ->unique('id_usuario')
            ->values();
    }
}
