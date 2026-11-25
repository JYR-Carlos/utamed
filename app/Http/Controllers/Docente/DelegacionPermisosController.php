<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Usuario;
use App\Services\Authorization\PermissionValidator;
use App\Services\Docente\PermisosCursoService;
use App\Support\CoursePermissionGraph;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DelegacionPermisosController
 *
 * Permite delegar permisos granulares tanto a nivel de Curso (Docente Titular
 * sobre `curso.id_contexto`) como a nivel de Componente (Docente Componente
 * sobre su propio `componente.id_contexto`), exigiendo que el actor posea cada
 * permiso activo y delegable (`puede_delegar = true`) en el ámbito objetivo.
 */
class DelegacionPermisosController extends Controller
{
    private const DELEGABLE_MATRIX = [
        'Curso' => [
            Permissions::CURSOS_VER->value,
            Permissions::CURSOS_EDITAR->value,
            Permissions::CURSOS_ELIMINAR->value,
        ],
        'Inscripciones' => [
            Permissions::CURSOS_INSCRIPCIONES_VER->value,
        ],
        'Unidades' => [
            Permissions::CURSOS_UNIDADES_VER->value,
            Permissions::CURSOS_UNIDADES_CREAR->value,
            Permissions::CURSOS_UNIDADES_CREAR_PLANTILLA->value,
            Permissions::CURSOS_UNIDADES_EDITAR->value,
            Permissions::CURSOS_UNIDADES_ELIMINAR->value,
        ],
        'Programas' => [
            Permissions::CURSOS_PROGRAMAS_VER_TODOS->value,
            Permissions::CURSOS_PROGRAMAS_AGREGAR->value,
            Permissions::CURSOS_PROGRAMAS_ELIMINAR->value,
            Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1->value,
            Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_2->value,
            Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_3->value,
            Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_4->value,
            Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_5->value,
            Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_6->value,
            Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_7->value,
            Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_8->value,
            Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_9->value,
        ],
        'Asistencia' => [
            Permissions::COMPONENTES_ASISTENCIA_VER->value,
            Permissions::COMPONENTES_ASISTENCIA_REGISTRAR->value,
            Permissions::COMPONENTES_ASISTENCIA_EDITAR->value,
            Permissions::COMPONENTES_ASISTENCIA_ELIMINAR->value,
        ],
        'Actividades' => [
            Permissions::ACTIVIDADES_VER->value,
            Permissions::ACTIVIDADES_CREAR->value,
            Permissions::ACTIVIDADES_CREAR_PLANTILLA->value,
            Permissions::ACTIVIDADES_EDITAR->value,
            Permissions::ACTIVIDADES_ELIMINAR->value,
            Permissions::ACTIVIDADES_EVALUAR->value,
            Permissions::ACTIVIDADES_DAR_FEEDBACK->value,
            Permissions::ACTIVIDADES_DESCARGAR_ENTREGAS->value,
            Permissions::ACTIVIDADES_ENVIAR_RECORDATORIOS->value,
            Permissions::ACTIVIDADES_SUBIR_ENTREGAS->value,
        ],
        'Grupos de Actividad' => [
            Permissions::ACTIVIDADES_GRUPOS_VER->value,
            Permissions::ACTIVIDADES_GRUPOS_CREAR->value,
            Permissions::ACTIVIDADES_GRUPOS_EDITAR->value,
            Permissions::ACTIVIDADES_GRUPOS_ELIMINAR->value,
        ],
    ];

    public function __construct(
        private readonly PermisosCursoService $permisosService,
        private readonly PermissionValidator $validator,
    ) {}

    public function index(Request $request, Curso $curso): Response
    {
        /** @var Usuario $authUser */
        $authUser = Auth::user();

        $componentesTodos = $curso->componentes()
            ->with('tipoComponente')
            ->get()
            ->map(fn ($c) => [
                'id_componente' => $c->id_componente,
                'tipo'          => $c->tipoComponente?->tipo ?? 'Componente',
                'id_contexto'   => $c->id_contexto,
            ]);

        $allSlugs = $this->allDelegableSlugs();
        $candidateEnums = array_map(Permissions::from(...), $allSlugs);

        $contextosCandidatos = array_values(array_filter(array_merge(
            [$curso->id_contexto],
            $componentesTodos->pluck('id_contexto')->all()
        )));

        $delegablesPorContexto = $this->validator->resolveDelegableSlugsByContexts(
            $authUser,
            $contextosCandidatos,
            $candidateEnums
        );

        $puedeDelegarCurso = !empty($delegablesPorContexto[$curso->id_contexto] ?? []);
        $componentesDelegables = $componentesTodos
            ->filter(fn ($c) => !empty($delegablesPorContexto[$c['id_contexto']] ?? []))
            ->values();

        if (!$puedeDelegarCurso && $componentesDelegables->isEmpty()) {
            abort(403, 'No tienes permisos delegables en este curso ni en sus componentes.');
        }

        $delegablesVisibles = [];
        if ($puedeDelegarCurso && $curso->id_contexto) {
            $delegablesVisibles[$curso->id_contexto] = $delegablesPorContexto[$curso->id_contexto] ?? [];
        }
        foreach ($componentesDelegables as $comp) {
            $ctxId = $comp['id_contexto'];
            $delegablesVisibles[$ctxId] = $delegablesPorContexto[$ctxId] ?? [];
        }

        $permisosDb = Permiso::whereIn('slug', $allSlugs)
            ->select(['id_permiso', 'slug', 'nombre'])
            ->get()
            ->keyBy('slug');

        $componenteIdsFiltro = $puedeDelegarCurso
            ? null
            : $componentesDelegables->pluck('id_componente')->all();

        $miembros = $this->permisosService->miembrosDelCurso($curso, $componenteIdsFiltro);
        $componentesParaMapa = $puedeDelegarCurso ? $componentesTodos : $componentesDelegables;

        $miembrosConPermisos = $miembros->map(function (array $miembro) use ($curso, $componentesParaMapa, $permisosDb) {
            return array_merge($miembro, [
                'permisos_por_contexto' => $this->permisosService->mapaPermisosPorContextosCurso(
                    $miembro['id_usuario'],
                    $curso,
                    $componentesParaMapa,
                    $permisosDb
                ),
            ]);
        })->values();

        return Inertia::render('docente/DelegacionPermisosCurso', [
            'curso'                => $curso->only(['id_curso', 'cod_curso', 'nombre', 'letra_grupo', 'semestre_real', 'agno_real'])
                + ['etiqueta' => $curso->etiqueta()],
            'componentes'          => $componentesDelegables,
            'id_contexto_curso'    => $curso->id_contexto,
            'puedeDelegarCurso'    => $puedeDelegarCurso,
            'delegablesPorContexto' => $delegablesVisibles,
            'miembros'             => $miembrosConPermisos,
            'grupos'               => $this->buildGruposDefinicion($permisosDb),
            'dependencias'         => CoursePermissionGraph::dependencyMap(),
        ]);
    }

    public function toggle(Request $request, Curso $curso): \Illuminate\Http\RedirectResponse
    {
        $allSlugs = $this->allDelegableSlugs();

        $validated = $request->validate([
            'id_usuario'              => ['required', 'integer', 'exists:usuario,id_usuario'],
            'slug'                    => ['required', 'string', 'in:' . implode(',', $allSlugs)],
            'estado'                  => ['nullable', 'string', 'in:asignado,neutro,revocado', 'required_without:otorgar'],
            'otorgar'                 => ['nullable', 'boolean', 'required_without:estado'],
            'id_contexto'             => ['nullable', 'integer'],
            'confirmar_cascada_hijos' => ['nullable', 'boolean'],
        ]);

        $estado = $validated['estado'] ?? ((bool) $validated['otorgar'] ? 'asignado' : 'neutro');
        $idUsuario = (int) $validated['id_usuario'];
        $slug = $validated['slug'];

        [$targetContexto, $targetComponenteId, $actorDelegables] =
            $this->authorizeDelegacionContexto($curso, $validated['id_contexto'] ?? null);

        $this->assertIsMiembroCurso($curso, $idUsuario, $targetComponenteId);
        $this->assertActorPuedeDelegarSlugs([$slug], $actorDelegables);

        // Si es un componente hijo, verificar superseding (tanto GRANT como DENY en el Curso)
        if ($targetContexto !== $curso->id_contexto) {
            $estadoPadre = $this->permisosService->estadosUpeEnContexto(
                $idUsuario,
                $curso->id_contexto,
                [$slug]
            )[$slug] ?? null;

            if ($estadoPadre === 'asignado') {
                return back()->withErrors([
                    'slug' => "El permiso '{$slug}' ya está otorgado a nivel de Curso y prevalece sobre el componente.",
                ]);
            }

            if ($estadoPadre === 'revocado') {
                return back()->withErrors([
                    'slug' => "El permiso '{$slug}' está revocado a nivel de Curso y no puede modificarse en este componente.",
                ]);
            }
        }

        $slugsAfectados = $estado === 'asignado'
            ? CoursePermissionGraph::resolveRequiredToGrant($slug)
            : CoursePermissionGraph::resolveCascadeToRevoke($slug);

        // En componente hijo, excluir los slugs bloqueados en el padre
        if ($targetContexto !== $curso->id_contexto) {
            $bloqueadosEnCurso = array_keys($this->permisosService->estadosUpeEnContexto(
                $idUsuario,
                $curso->id_contexto,
                $slugsAfectados
            ));

            $slugsAfectados = array_values(array_diff($slugsAfectados, $bloqueadosEnCurso));

            if (empty($slugsAfectados)) {
                return back()->with('success', 'El permiso ya se encuentra cubierto por la herencia del Curso.');
            }
        }

        $slugsPermitidos = array_values(array_intersect($slugsAfectados, $actorDelegables));
        if (empty($slugsPermitidos)) {
            abort(403, 'No tienes permisos delegables para realizar esta operación.');
        }

        // Regla de colisión padre-hijo: al revocar ('revocado') en Curso Completo,
        // detectar si existen permisos directos ('asignado') en componentes hijos.
        if ($targetContexto === $curso->id_contexto && $estado === 'revocado') {
            $colisiones = $this->permisosService->hijosConPermisosDirectos($idUsuario, $curso, $slugsAfectados);
            if (!empty($colisiones)) {
                if (!$request->boolean('confirmar_cascada_hijos')) {
                    return back()
                        ->withErrors([
                            'confirmar_cascada_hijos' => 'Existen permisos asignados directamente en componentes hijos que serán neutralizados al revocar en el curso.',
                        ])
                        ->with('colisiones_hijos', $colisiones);
                }

                // Neutralizar las asignaciones directas en los componentes hijos confirmados
                foreach ($colisiones as $col) {
                    $this->permisosService->syncPermisosBatchEstado(
                        idUsuario:  $idUsuario,
                        slugsOrIds: $col['slugs'],
                        idContexto: $col['id_contexto'],
                        estado:     'neutro',
                        origen:     'DelegacionPermisos:CascadaHijos',
                    );
                }
            }
        }

        if ($estado === 'revocado') {
            // Aplicar DENY explícito al permiso principal y neutralizar dependientes activos en cascada
            $this->permisosService->syncPermisosBatchEstado(
                idUsuario:  $idUsuario,
                slugsOrIds: [$slug],
                idContexto: $targetContexto,
                estado:     'revocado',
                origen:     'DelegacionPermisos',
            );

            $dependientes = array_values(array_diff($slugsPermitidos, [$slug]));
            $dependientesActivos = $this->permisosService->slugsActivosEnContexto($idUsuario, $targetContexto, $dependientes);
            if (!empty($dependientesActivos)) {
                $this->permisosService->syncPermisosBatchEstado(
                    idUsuario:  $idUsuario,
                    slugsOrIds: $dependientesActivos,
                    idContexto: $targetContexto,
                    estado:     'neutro',
                    origen:     'DelegacionPermisos',
                );
            }
        } else {
            $this->permisosService->syncPermisosBatchEstado(
                idUsuario:  $idUsuario,
                slugsOrIds: $slugsPermitidos,
                idContexto: $targetContexto,
                estado:     $estado,
                origen:     'DelegacionPermisos',
            );
        }

        return back()->with('success', 'Permiso actualizado correctamente');
    }

    public function batch(Request $request, Curso $curso): \Illuminate\Http\RedirectResponse
    {
        $allSlugs = $this->allDelegableSlugs();

        $validated = $request->validate([
            'id_usuario'              => ['required', 'integer', 'exists:usuario,id_usuario'],
            'slugs'                   => ['required', 'array', 'min:1'],
            'slugs.*'                 => ['required', 'string', 'in:' . implode(',', $allSlugs)],
            'estado'                  => ['nullable', 'string', 'in:asignado,neutro,revocado', 'required_without:otorgar'],
            'otorgar'                 => ['nullable', 'boolean', 'required_without:estado'],
            'id_contexto'             => ['nullable', 'integer'],
            'confirmar_cascada_hijos' => ['nullable', 'boolean'],
        ]);

        $estado = $validated['estado'] ?? ((bool) $validated['otorgar'] ? 'asignado' : 'neutro');
        $idUsuario = (int) $validated['id_usuario'];

        [$targetContexto, $targetComponenteId, $actorDelegables] =
            $this->authorizeDelegacionContexto($curso, $validated['id_contexto'] ?? null);

        $this->assertIsMiembroCurso($curso, $idUsuario, $targetComponenteId);
        $this->assertActorPuedeDelegarSlugs($validated['slugs'], $actorDelegables);

        $slugs = $validated['slugs'];

        if ($targetContexto !== $curso->id_contexto) {
            $bloqueadosEnCurso = array_keys($this->permisosService->estadosUpeEnContexto(
                $idUsuario,
                $curso->id_contexto,
                $slugs
            ));

            $slugs = array_values(array_diff($slugs, $bloqueadosEnCurso));

            if (empty($slugs)) {
                return back()->with('success', 'Todos los permisos seleccionados están gobernados por herencia o revocación del Curso.');
            }
        }

        $slugsAfectados = $estado === 'asignado'
            ? CoursePermissionGraph::resolveRequiredToGrant($slugs)
            : CoursePermissionGraph::resolveCascadeToRevoke($slugs);

        if ($targetContexto !== $curso->id_contexto) {
            $bloqueadosEnCurso = array_keys($this->permisosService->estadosUpeEnContexto(
                $idUsuario,
                $curso->id_contexto,
                $slugsAfectados
            ));

            $slugsAfectados = array_values(array_diff($slugsAfectados, $bloqueadosEnCurso));

            if (empty($slugsAfectados)) {
                return back()->with('success', 'Todos los permisos seleccionados están cubiertos por herencia o revocación del Curso.');
            }
        }

        $slugsPermitidos = array_values(array_intersect($slugsAfectados, $actorDelegables));
        if (empty($slugsPermitidos)) {
            abort(403, 'No tienes permisos delegables para realizar esta operación en lote.');
        }

        if ($targetContexto === $curso->id_contexto && $estado === 'revocado') {
            $colisiones = $this->permisosService->hijosConPermisosDirectos($idUsuario, $curso, $slugsAfectados);
            if (!empty($colisiones)) {
                if (!$request->boolean('confirmar_cascada_hijos')) {
                    return back()
                        ->withErrors([
                            'confirmar_cascada_hijos' => 'Existen permisos asignados directamente en componentes hijos que serán neutralizados al revocar en el curso.',
                        ])
                        ->with('colisiones_hijos', $colisiones);
                }

                foreach ($colisiones as $col) {
                    $this->permisosService->syncPermisosBatchEstado(
                        idUsuario:  $idUsuario,
                        slugsOrIds: $col['slugs'],
                        idContexto: $col['id_contexto'],
                        estado:     'neutro',
                        origen:     'DelegacionPermisos:CascadaHijos',
                    );
                }
            }
        }

        $this->permisosService->syncPermisosBatchEstado(
            idUsuario:  $idUsuario,
            slugsOrIds: $slugsPermitidos,
            idContexto: $targetContexto,
            estado:     $estado,
            origen:     'DelegacionPermisos',
        );

        $etiqueta = match ($estado) {
            'asignado' => 'otorgados',
            'revocado' => 'revocados',
            default    => 'restablecidos a neutro',
        };
        $cantidad = count($slugsPermitidos);

        return back()->with('success', "Se han {$etiqueta} {$cantidad} permisos correctamente.");
    }

    /**
     * Resuelve el contexto destino y verifica que el actor tenga al menos un permiso
     * delegable activo aplicable en ese contexto.
     *
     * @return array{int, int|null, string[]} [$targetContexto, $targetComponenteId, $actorDelegables]
     */
    private function authorizeDelegacionContexto(Curso $curso, ?int $requestedContextId): array
    {
        /** @var Usuario $authUser */
        $authUser = Auth::user();

        $componentesPorContexto = $curso->componentes()
            ->whereNotNull('id_contexto')
            ->pluck('id_componente', 'id_contexto')
            ->all();

        $targetContexto = ($requestedContextId !== null
            && ($requestedContextId === $curso->id_contexto || isset($componentesPorContexto[$requestedContextId])))
            ? $requestedContextId
            : $curso->id_contexto;

        $targetComponenteId = $targetContexto === $curso->id_contexto
            ? null
            : (int) $componentesPorContexto[$targetContexto];

        $candidateEnums = array_map(Permissions::from(...), $this->allDelegableSlugs());
        $byContext = $this->validator->resolveDelegableSlugsByContexts($authUser, [$targetContexto], $candidateEnums);
        $actorDelegables = $byContext[$targetContexto] ?? [];

        if (empty($actorDelegables)) {
            abort(403, 'No tienes permisos delegables en este ámbito del curso.');
        }

        return [$targetContexto, $targetComponenteId, $actorDelegables];
    }

    /**
     * Verifica que el usuario objetivo pertenezca al equipo del curso (o del componente cuando
     * el actor solo delega a nivel de componente) y previene auto-delegación.
     */
    private function assertIsMiembroCurso(Curso $curso, int $idUsuario, ?int $targetComponenteId = null): void
    {
        /** @var Usuario $authUser */
        $authUser = Auth::user();

        if ($idUsuario === $authUser->id_usuario) {
            abort(422, 'No puedes delegarte permisos a ti mismo.');
        }

        $esMiembro = $curso->componentes()
            ->when(
                $targetComponenteId !== null && !$authUser->can('manageTeam', $curso),
                fn ($q) => $q->where('id_componente', $targetComponenteId)
            )
            ->whereHas('docenteComponentes.docente', function ($q) use ($idUsuario) {
                $q->whereHas('usuario', fn ($u) => $u->where('id_usuario', $idUsuario));
            })
            ->exists();

        if (!$esMiembro) {
            Log::channel('seguridad')->warning('IDOR: intento de delegar permiso a usuario fuera del ámbito permitido', [
                'evento'            => 'IDOR_DELEGACION_USUARIO_EXTERNO',
                'id_usuario_auth'   => $authUser->id_usuario,
                'id_usuario_target' => $idUsuario,
                'id_curso'          => $curso->id_curso,
                'id_componente'     => $targetComponenteId,
                'ip'                => request()->ip(),
            ]);
            abort(403, 'El usuario no pertenece al ámbito docente habilitado para delegación.');
        }
    }

    /**
     * Exige que cada slug solicitado sea delegable por el actor en el contexto destino.
     *
     * @param string[] $requestedSlugs
     * @param string[] $actorDelegables
     */
    private function assertActorPuedeDelegarSlugs(array $requestedSlugs, array $actorDelegables): void
    {
        $allowedSet = array_flip($actorDelegables);

        foreach ($requestedSlugs as $slug) {
            if (!isset($allowedSet[$slug])) {
                abort(403, "No tienes capacidad delegable sobre el permiso '{$slug}' en este contexto.");
            }
        }
    }

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

    private function allDelegableSlugs(): array
    {
        return array_merge(...array_values(self::DELEGABLE_MATRIX));
    }
}
