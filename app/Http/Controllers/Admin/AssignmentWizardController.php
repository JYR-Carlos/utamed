<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\ContextualModelType;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Services\Authorization\GlobalContextService;
use App\Support\PermissionContextConstraints;
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
   * Incluye "GLOBAL" como opción especial + los 5 tipos de ContextualModelType.
   */
  public function getContextTypes()
  {
    $types = [
      [
        'key' => 'GLOBAL',
        'label' => 'Global (todo el sistema)',
        'description' => 'Aplica en todos los contextos del sistema',
      ],
    ];

    foreach (ContextualModelType::cases() as $case) {
      $modelClass = $case->modelClass();
      $count = $modelClass::count();
      $label = match ($case->name) {
        'CARRERA' => 'Carrera',
        'DEPARTAMENTO' => 'Departamento',
        'FACULTAD' => 'Facultad',
        'ACTIVIDAD' => 'Actividad',
        'CURSO' => 'Curso',
        default => $case->name,
      };

      $types[] = [
        'key' => $case->name,
        'label' => $label,
        'description' => "Asignar en un(a) {$label} específico(a)",
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
      ->map(fn($rol) => [
        'id_rol' => $rol->id_rol,
        'nombre' => $rol->nombre,
      ])
      ->values();

    return response()->json($roles);
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
        'id_permiso'          => $p->id_permiso,
        'slug'                => $p->slug,
        'nombre'              => $p->nombre,
        'descripcion'         => $p->descripcion,
        'valid_context_types' => PermissionContextConstraints::validContextTypesFor($p->slug),
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

    // Validar que el tipo de contexto sea válido para este permiso
    $contextType = strtoupper($validated['context_type']);
    if (!PermissionContextConstraints::isValidAssignment($permiso->slug, $contextType)) {
      return response()->json([
        'success' => false,
        'message' => PermissionContextConstraints::invalidAssignmentMessage($permiso->slug, $contextType),
      ], 422);
    }

    // Buscar el caso del enum Permissions que coincida con el slug
    $permissionEnum = null;
    foreach (Permissions::cases() as $case) {
      if ($case->value === $permiso->slug) {
        $permissionEnum = $case;
        break;
      }
    }

    if (!$permissionEnum) {
      return response()->json([
        'error' => "No se encontró el enum de permiso para slug '{$permiso->slug}'",
      ], 400);
    }

    try {
      $builder = $usuario->givePermission($permissionEnum);

      // Resolver contexto ($contextType ya fue validado arriba)
      if ($contextType === 'GLOBAL') {
        $globalContextId = app(GlobalContextService::class)->getContextId();
        $builder->inContext($globalContextId);
      } else {
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
        $builder->for(365);
      }

      // Allow / Deny
      if (!$validated['allowed']) {
        $builder->revoke();
      }

      // Delegation
      if ($validated['can_delegate'] ?? false) {
        $builder->canDelegate();
      }

      $result = $builder->save();

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
}
