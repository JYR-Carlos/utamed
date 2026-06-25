<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\ContextualModelType;
use App\Enums\ContextType;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Services\Authorization\GlobalContextService;
use App\Services\Authorization\PermissionContextConstraints;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controlador API para el wizard de asignación de roles y permisos.
 *
 * Provee endpoints para:
 * - Listar tipos de contexto disponibles (Global + ContextualModelType)
 * - Listar objetos de un tipo de contexto específico
 * - Asignar un rol usando RoleAssignmentBuilder
 * - Asignar un permiso especial usando PermissionAssignmentBuilder
 */
class AssignmentWizardController extends Controller
{
  /**
   * GET /admin/assignment/context-types
   *
   * Retorna los tipos de contexto disponibles para asignar roles/permisos.
   * Usa ContextType enum directamente (source of truth para frontend).
   * - key: nombre uppercase para debugging
   * - value: valor lowercase del enum (para castear en frontend)
   */
  public function getContextTypes()
  {
    $globalContextId = app(GlobalContextService::class)->getContextId();
    $types = [];

    foreach (ContextType::cases() as $case) {
      // Usamos directamente $case->value (ej: 'global', 'curso')
      $count = null;
      
      if ($case !== ContextType::GLOBAL) {
        $count = Contexto::whereHas('tipoContexto', function ($query) use ($case) {
          $query->where('categoria', $case->value);
        })->count();
      }

      // Etiquetas y descripciones específicas para cada tipo de contexto (frontend)
      // TODO: establecer esta info en la db o en el enum mismo para evitar hardcodear aquí
      $label = match ($case) {
        ContextType::GLOBAL => 'Global (todo el sistema)',
        ContextType::CARRERA => 'Carrera',
        ContextType::DEPARTAMENTO => 'Departamento',
        ContextType::FACULTAD => 'Facultad',
        ContextType::ACTIVIDAD => 'Actividad',
        ContextType::CURSO => 'Curso',
        default => ucfirst($case->value),
      };

      // Descripciones para ayudar al usuario a entender cada opción
      // TODO: también esto podría venir de la bd o del enum para evitar hardcodear
      $description = match ($case) {
        ContextType::GLOBAL => 'Aplica en todos los contextos del sistema',
        default => "Asignar en un(a) {$label} específico(a)",
      };

      // Mapeo de valores
      $types[] = [
        // Claves para formar el enum en frontend
        'key' => $case->name,   // 'GLOBAL', 'CURSO', etc.
        'value' => $case->value, // 'global', 'curso', etc.
        // Información adicional para mostrar en el frontend
        'label' => $label,
        'description' => $description,
        'context_id' => $case === ContextType::GLOBAL ? $globalContextId : null,
        'count' => $count,
      ];
    }

    return response()->json($types);
  }

  /**
   * GET /admin/assignment/context-types/{type}/objects
   *
   * Retorna los objetos disponibles para un tipo de contexto.
   * Se usa para el paso de selección de objeto en el wizard.
   *
   * @param string $type  Clave del tipo de contexto (ej: "FACULTAD", "CURSO")
   */
  public function getContextObjects(string $type)
  {
    $type = strtoupper($type);

    // GLOBAL no tiene objetos específicos
    if ($type === 'GLOBAL') {
      $globalContextId = app(GlobalContextService::class)->getContextId();
      return response()->json([
        [
          'id' => 0,
          'label' => 'Contexto Global',
          'context_id' => $globalContextId,
        ],
      ]);
    }

    // Buscar el caso del enum
    $enumCase = null;
    foreach (ContextualModelType::cases() as $case) {
      if ($case->name === $type) {
        $enumCase = $case;
        break;
      }
    }

    if (!$enumCase) {
      return response()->json(['error' => 'Tipo de contexto no válido'], 400);
    }

    $modelClass = $enumCase->modelClass();
    $primaryKey = (new $modelClass())->getKeyName();

    $objects = $modelClass::query()
      ->select([$primaryKey, 'nombre', 'id_contexto'])
      ->orderBy('nombre')
      ->limit(500)
      ->get()
      ->map(fn($obj) => [
        'id' => $obj->$primaryKey,
        'label' => $obj->nombre,
        'context_id' => $obj->id_contexto,
      ]);

    return response()->json($objects);
  }

  /**
   * GET /admin/assignment/roles
   *
   * Retorna todos los roles asignables (excluyendo SuperAdmin).
   */
  public function getRoles()
  {
    $roles = Rol::whereNotIn('nombre', ['SuperAdmin', 'Super Admin'])
      ->orderBy('nombre')
      ->get()
      ->map(fn(Rol $rol) => [
        'id_rol' => $rol->id_rol,
        'nombre' => $rol->nombre,
        'valid_assignment_context_types' => array_map(
          fn($ct) => $ct->value,
          $rol->getCompatibleContexts()
        ),
      ])
      ->values();

    return response()->json($roles);
  }

  /**
   * GET /admin/assignment/roles/{roleId}/detail
   *
   * Retorna el nombre del rol y la lista de permisos que tiene asignados.
   * Se usa para el panel de detalle al hacer clic en un chip de rol.
   *
   * @param  int  $roleId  ID del rol
   */
  public function getRoleDetail(int $roleId)
  {
    $rol = Rol::findOrFail($roleId);

    $permisos = \Illuminate\Support\Facades\DB::table('usuario.asignacion_rol_permiso as arp')
      ->join('usuario.permiso as p', 'p.id_permiso', '=', 'arp.id_permiso')
      ->where('arp.id_rol', $roleId)
      ->select('p.id_permiso', 'p.slug', 'p.nombre', 'p.descripcion', 'arp.puede_delegar_permiso')
      ->orderBy('p.slug')
      ->get()
      ->map(fn($row) => [
        'id_permiso' => $row->id_permiso,
        'slug' => $row->slug,
        'nombre' => $row->nombre,
        'descripcion' => $row->descripcion,
        'puede_delegar_permisos' => (bool) $row->puede_delegar_permiso, // Fix: front usa puede_delegar_permisos, en plural
      ])
      ->values();

    return response()->json([
      'id_rol' => $rol->id_rol,
      'nombre' => $rol->nombre,
      'permisos' => $permisos,
    ]);
  }

  /**
   * GET /admin/assignment/permissions
   *
   * Retorna todos los permisos disponibles, agrupados por módulo (prefijo del slug).
   */
  public function getPermissions()
  {
    $permissions = Permiso::orderBy('slug')
      ->get()
      ->map(fn($p) => [
        'id_permiso' => $p->id_permiso,
        'slug' => $p->slug,
        'nombre' => $p->nombre,
        'descripcion' => $p->descripcion,
        'valid_context_types' => array_map(
          fn($t) => $t->value,
          PermissionContextConstraints::validContextTypesFor(
            Permissions::from($p->slug)
          )
        ),
      ]);

    // Agrupar por módulo (primer segmento del slug, antes de ":" o "/")
    $grouped = $permissions->groupBy(function ($perm) {
      $slug = $perm['slug'];
      if ($slug === '*')
        return 'Sistema';
      $parts = preg_split('/[\/:]/', $slug);
      return ucfirst($parts[0] ?? 'General');
    });

    return response()->json($grouped);
  }

  /**
   * POST /admin/usuarios/{usuario}/assign-role
   *
   * Asigna un rol a un usuario usando RoleAssignmentBuilder.
   *
   * Body esperado:
   * {
   *   "role_id": int,
   *   "context_type": "GLOBAL" | "FACULTAD" | "CURSO" | ...,
   *   "context_object_id": int|null,  (null si GLOBAL)
   *   "start_date": "Y-m-d"|null,
   *   "end_date": "Y-m-d"|null,
   * }
   */
  public function assignRole(Request $request, $usuarioId)
  {
    $validated = $request->validate([
      'role_id' => ['required', 'integer'],
      'context_type' => 'required|string',
      'context_object_id' => 'nullable|integer',
      'start_date' => 'nullable|date',
      'end_date' => 'nullable|date|after_or_equal:start_date',
    ]);

    $usuario = Usuario::findOrFail($usuarioId);
    $rol = Rol::findOrFail($validated['role_id']);

    try {
      $builder = $usuario->giveRole($rol);

      // Resolver contexto
      $contextType = strtoupper($validated['context_type']);
      if ($contextType === 'GLOBAL') {
        $globalContextId = app(GlobalContextService::class)->getContextId();
        $builder->inContext($globalContextId);
      } else {
        // Buscar el modelo específico y usar ->on()
        $enumCase = $this->resolveContextualModelType($contextType);
        if (!$enumCase) {
          return response()->json(['error' => 'Tipo de contexto no válido'], 400);
        }

        $modelClass = $enumCase->modelClass();
        $model = $modelClass::findOrFail($validated['context_object_id']);
        $builder->on($model);
      }

      // Fechas
      if ($validated['start_date'] && $validated['end_date']) {
        $start = \Carbon\Carbon::parse($validated['start_date']);
        $end = \Carbon\Carbon::parse($validated['end_date']);
        $days = (int) $start->diffInDays($end);

        if ($start->isAfter(now())) {
          $waitDays = (int) now()->diffInDays($start);
          $builder->waitFor($waitDays);
        }

        $builder->for($days);
      } elseif ($validated['end_date']) {
        $days = (int) now()->diffInDays(\Carbon\Carbon::parse($validated['end_date']));
        $builder->for($days);
      } else {
        $builder->for(365); // Default 1 year
      }

      $result = $builder->save();

      return response()->json([
        'success' => true,
        'message' => "Rol '{$rol->nombre}' asignado correctamente.",
      ]);
    } catch (\App\Exceptions\DontHavePermissionException $e) {
      return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
      ], 403);
    } catch (\Illuminate\Database\QueryException $e) {
      // SQLSTATE 23P01 = exclusion_violation (restricción uq_no_solapar_roles)
      if ($e->getCode() === '23P01') {
        return response()->json([
          'success' => false,
          'message' => "El usuario ya tiene el rol '{$rol->nombre}' asignado en ese contexto con un período que se superpone. Revoca o modifica la asignación existente antes de crear una nueva.",
        ], 409);
      }
      Log::error("AssignRole QueryException: " . $e->getMessage(), [
        'user_id' => $usuarioId,
        'payload' => $validated,
      ]);
      return response()->json([
        'success' => false,
        'message' => 'Error de base de datos al asignar rol: ' . $e->getMessage(),
      ], 500);
    } catch (\Exception $e) {
      Log::error("AssignRole Error: " . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'user_id' => $usuarioId,
        'payload' => $validated,
      ]);
      return response()->json([
        'success' => false,
        'message' => 'Error al asignar rol: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * POST /admin/usuarios/{usuario}/assign-permission
   *
   * Asigna un permiso especial a un usuario usando PermissionAssignmentBuilder.
   *
   * Body esperado:
   * {
   *   "permission_id": int,
   *   "context_type": "GLOBAL" | "FACULTAD" | "CURSO" | ...,
   *   "context_object_id": int|null,
   *   "start_date": "Y-m-d"|null,
   *   "end_date": "Y-m-d"|null,
   *   "allowed": bool,       (true=permitir, false=denegar)
   *   "can_delegate": bool,
   * }
   */
  public function assignPermission(Request $request, $usuarioId)
  {
    $validated = $request->validate([
      'permission_id' => ['required', 'integer'],
      'context_type' => 'required|string',
      'context_object_id' => 'nullable|integer',
      'start_date' => 'nullable|date',
      'end_date' => 'nullable|date|after_or_equal:start_date',
      'allowed' => 'required|boolean',
      'can_delegate' => 'boolean',
    ]);

    $usuario = Usuario::findOrFail($usuarioId);

    // Resolver el slug del permiso desde su ID
    $permiso = Permiso::findOrFail(id: $validated['permission_id']);

    // Resolver el enum Permissions desde el slug de la BD
    $permissionEnum = Permissions::tryFrom($permiso->slug);
    if (!$permissionEnum) {
      return response()->json([
        'error' => "No se encontró el enum de permiso para slug '{$permiso->slug}'",
      ], 400);
    }

    // Validar que el tipo de contexto sea válido para este permiso
    $contextTypeString = strtolower($validated['context_type']);
    $contextType = ContextType::tryFrom($contextTypeString);
    if ($contextType === null) {
      return response()->json([
        'success' => false,
        'message' => "Tipo de contexto inválido: '{$contextTypeString}'.",
      ], 422);
    }
    if (!PermissionContextConstraints::isValidAssignment($permissionEnum, $contextType)) {
      return response()->json([
        'success' => false,
        'message' => PermissionContextConstraints::invalidAssignmentMessage($permissionEnum, $contextType),
      ], 422);
    }

    try {
      $starter = $usuario->givePermission($permissionEnum);

      // Resolver contexto ($contextType ya fue validado arriba)
      if ($contextType === ContextType::GLOBAL) {
        $ready = $starter->onEveryInstance();
      } else {
        $enumCase = $this->resolveContextualModelType($contextType->name);
        if (!$enumCase) {
          return response()->json(
            ['error' => 'Tipo de contexto no válido'],
            400
          );
        }

        $modelClass = $enumCase->modelClass();
        $model = $modelClass::findOrFail($validated['context_object_id']);
        $ready = $starter->on($model);
      }

      // Fechas
      if ($validated['start_date'] && $validated['end_date']) {
        $start = \Carbon\Carbon::parse($validated['start_date']);
        $end = \Carbon\Carbon::parse($validated['end_date']);
        $days = (int) $start->diffInDays($end);

        if ($start->isAfter(now())) {
          $waitDays = (int) now()->diffInDays($start);
          $ready->waitFor($waitDays);
        }

        $ready->for($days);
      } elseif ($validated['end_date']) {
        $days = (int) now()->diffInDays(\Carbon\Carbon::parse($validated['end_date']));
        $ready->for($days);
      } else {
        $ready->for(365);
      }

      // Allow / Deny
      if (!$validated['allowed']) {
        $ready->revoke();
      }

      // Delegation
      if ($validated['can_delegate'] ?? false) {
        $ready->canDelegate();
      }

      $result = $ready->save();

      return response()->json([
        'success' => true,
        'message' => "Permiso '{$permiso->nombre}' asignado correctamente.",
      ]);
    } catch (\App\Exceptions\DontHavePermissionException $e) {
      return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
      ], 403);
    } catch (\Exception $e) {
      Log::error("AssignPermission Error: " . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'user_id' => $usuarioId,
        'payload' => $validated,
      ]);
      return response()->json([
        'success' => false,
        'message' => 'Error al asignar permiso: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Resuelve un string de tipo de contexto al caso del enum ContextualModelType.
   */
  private function resolveContextualModelType(string $type): ?ContextualModelType
  {
    foreach (ContextualModelType::cases() as $case) {
      if ($case->name === strtoupper($type)) {
        return $case;
      }
    }
    return null;
  }

  /**
   * DELETE /admin/usuarios/{usuario}/roles/{ura}
   *
   * Revoca una asignación de rol (URA) de un usuario.
   */
  public function revokeRole($usuarioId, $uraId)
  {
    $usuario = Usuario::findOrFail($usuarioId);
    $ura = UsuarioRolAsignacion::findOrFail($uraId);

    if ((int) $ura->id_usuario !== (int) $usuario->id_usuario) {
      return response()->json(['success' => false, 'message' => 'La asignación no pertenece a este usuario.'], 403);
    }

    try {
      $usuario->invalidateRole($uraId);
      return response()->json(['success' => true, 'message' => 'Rol revocado correctamente.']);
    } catch (\App\Exceptions\DontHavePermissionException $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
    } catch (\Exception $e) {
      Log::error('revokeRole error: ' . $e->getMessage(), ['ura_id' => $uraId, 'user_id' => $usuarioId]);
      return response()->json(['success' => false, 'message' => 'Error al revocar rol: ' . $e->getMessage()], 500);
    }
  }

  /**
   * DELETE /admin/usuarios/{usuario}/permissions/{upe}
   *
   * Revoca un permiso especial (UPE) de un usuario.
   */
  public function revokePermission($usuarioId, $upeId)
  {
    $usuario = Usuario::findOrFail($usuarioId);
    $upe = UsuarioPermisoEspecial::findOrFail($upeId);

    if ((int) $upe->id_usuario !== (int) $usuario->id_usuario) {
      return response()->json(['success' => false, 'message' => 'El permiso no pertenece a este usuario.'], 403);
    }

    try {
      $usuario->invalidatePermission($upeId);
      return response()->json(['success' => true, 'message' => 'Permiso revocado correctamente.']);
    } catch (\App\Exceptions\DontHavePermissionException $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
    } catch (\Exception $e) {
      Log::error('revokePermission error: ' . $e->getMessage(), ['upe_id' => $upeId, 'user_id' => $usuarioId]);
      return response()->json(['success' => false, 'message' => 'Error al revocar permiso: ' . $e->getMessage()], 500);
    }
  }
}
