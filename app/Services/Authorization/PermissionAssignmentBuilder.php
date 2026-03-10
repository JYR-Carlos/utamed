<?php

namespace App\Services\Authorization;

use Carbon\Carbon;
use Illuminate\Support\Collection;

use App\Models\Usuario\Permiso;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\TipoContexto;
use App\Models\Usuario\UsuarioPermisoEspecial;

use App\Contracts\HasOwnedContext;
use App\Enums\ContextualModelType;
use App\Enums\ContextType;
use App\Services\ContextResolver;
use App\Support\Permissions;
use App\Services\Authorization\PermissionContextConstraints;

use App\Exceptions\DontHavePermissionException;
use \Illuminate\Database\RecordNotFoundException;

/**
 * Builder declarativo para asignar permisos individuales (UPE) a un usuario.
 *
 * Se persiste automaticamente al finalizar la cadena (via __destruct).
 *
 * @example GRANT con duracion y delegacion:
 *   $user->givePermission($permiso)->on($facultad)->for(30)->canDelegate();
 *
 * @example DENY explicito (revocar acceso):
 *   $user->givePermission($permiso)->on($facultad)->for(30)->revoke();
 *
 * @example Asignar a todos los contextos del tipo Facultad:
 *   $user->givePermission($permiso)->onAll(Facultad::class)->for(60);
 *
 * @example Con inicio diferido (esperar 5 dias antes de activar):
 *   $user->givePermission($permiso)->on($recurso)->for(30)->waitFor(5);
 */
class PermissionAssignmentBuilder
{
  /** @var int[] IDs de contexto sobre los que se creara el permiso */
  private array $contextIds = [];

  /** @var Carbon Fecha de inicio del permiso */
  private Carbon $startDate;

  /** @var Carbon|null Fecha de fin del permiso (null = indefinido) */
  private ?Carbon $endDate = null;

  /** @var bool true = GRANT, false = DENY */
  private bool $granted = true;

  /** @var bool Si el usuario puede delegar este permiso */
  private bool $delegable = false;

  /** @var bool Evita doble persistencia en __destruct */
  private bool $saved = false;

  protected PermissionValidator $validator;

  public function __construct(
    private readonly Usuario $recipient,
    private readonly Permissions $permissionSlug,
    private ?Usuario $actor = null
  ) {
    $this->startDate = Carbon::now();
    $this->validator = app(PermissionValidator::class);
    $this->actor = $actor ?? auth()->user();
  }

  /**
   * Resolver el contexto desde una instancia o arreglo de recursos.
   *
   * @param HasOwnedContext|HasOwnedContext[] $resources Instancia o arreglo de recursos con contexto propio (ej: $facultad, [$carreraA, $carreraB])
   * @throws \InvalidArgumentException Si algún elemento del array no implementa HasOwnedContext
   * @throws \InvalidArgumentException Si el permiso no es compatible con el tipo de contexto
   */
  public function on(HasOwnedContext|array $resources): static
  {
    $resolver = app(ContextResolver::class);

    foreach (is_array($resources) ? $resources : [$resources] as $resource) {
      if (!$resource instanceof HasOwnedContext) {
        $class = is_object($resource) ? get_class($resource) : gettype($resource);
        throw new \InvalidArgumentException(
          "->on() sólo acepta modelos con contexto propio (HasOwnedContext). "
          . "'{$class}' es un modelo global — usa ->onAll(ContextualModelType::...) "
          . "o asigna el permiso directamente sin contexto."
        );
      }

      // Validar tempranamente que el permiso sea compatible con este recurso (OPCIÓN 2)
      $rawContextTypes = $resolver->getModelContextTypes($resource);
      $contextTypeEnums = array_values(array_filter(
        array_map(fn($t) => ContextType::tryFrom($t), $rawContextTypes)
      ));
      if (
        !empty($contextTypeEnums)
        &&
        !PermissionContextConstraints::isAnyTypeValid(
          $this->permissionSlug,
          $contextTypeEnums
        )
      ) {
        $valid = implode(
          ', ',
          array_map(
            fn(ContextType $t) => $t->value,
            PermissionContextConstraints::validContextTypesFor(
              $this->permissionSlug
            )
          )
        );

        $invalid = implode(
          ', ',
          array_map(
            fn(ContextType $ct) => $ct->value,
            $contextTypeEnums
          )
        );

        throw new \InvalidArgumentException(
          "El permiso '{$this->permissionSlug->value}' no puede asignarse a un contexto de tipo '{$invalid}'. "
          . "Tipos de contexto válidos: {$valid}."
        );
      }

      $ids = $resolver->getModelContextId($resource);
      $this->contextIds = array_unique(
        array_merge($this->contextIds, $ids)
      );
    }

    return $this;
  }

  /**
   * Especificar un contexto directamente por su ID.
   *
   * Útil para asignar permisos a modelos globales donde el contexto
   * se resuelve desde el servicio global (ej: al crear usuarios).
   *
   * @param int|int[] $contextIds ID o IDs del contexto
   * @example
   *   $user->givePermission($perm)->inContext($globalContextId)->for(30);
   * @throws \InvalidArgumentException Si el permiso no es compatible con el tipo de contexto
   */
  public function inContext(int|array $contextIds): static
  {
    $ids = is_array($contextIds) ? $contextIds : [$contextIds];

    // Validar tempranamente que el permiso sea compatible con cada contexto (OPCIÓN 2)
    foreach ($ids as $contextId) {
      $context = Contexto::find($contextId);
      if ($context && $context->tipoContexto) {
        $contextType = ContextType::from($context->tipoContexto->categoria);

        if (
          !PermissionContextConstraints::isValidAssignment(
            $this->permissionSlug,
            $contextType
          )
        ) {
          throw new \InvalidArgumentException(
            PermissionContextConstraints::invalidAssignmentMessage(
              $this->permissionSlug,
              $contextType
            )
          );
        }
      }
    }

    $this->contextIds = array_unique(array_merge($this->contextIds, $ids));

    return $this;
  }

  /**
   * Asignar el permiso al contexto global.
   *
   * Útil para permisos que aplican a nivel sistema sin restricción de contexto.
   *
   * @example
   *   $user->givePermission($perm)->inGlobalContext()->for(30);
   */
  public function inGlobalContext(): static
  {
    $globalContextId = app(GlobalContextService::class)->getContextId();
    return $this->inContext($globalContextId);
  }

  /**
   * Especificar quién realiza la asignación (sobrescribe el actor autenticado).
   *
   * @param Usuario $actor Usuario que realiza la asignación
   * @example
   *   $user->givePermission($perm)->on($recurso)->as($admin)->for(30);
   */
  public function as(Usuario $actor): static
  {
    $this->actor = $actor;
    return $this;
  }

  /**
   * Asignar el permiso a TODOS los contextos del tipo dado.
   *
   * Usa el enum ContextualModelType para garantizar en tiempo de compilación
   * que sólo se pasan modelos que poseen un contexto real (tipo 'direct').
   *
   * @see ContextualModelType
   * @example $user->givePermission($perm)->onAll(ContextualModelType::CARRERA)->for(60);
   *
   * @param ContextualModelType $modelType Tipo de modelo contextual
   * @throws \InvalidArgumentException Si el permiso no es compatible con este tipo de contexto
   * 
   * // FIX: onAll() deberia asignar al contexto global y validate deberia tener en cuenta eso
   */
  public function onAll(ContextualModelType $modelType): static
  {
    $category = strtolower(class_basename($modelType->value));
    $contextTypeEnum = ContextType::from($category);

    // Validar tempranamente que el permiso sea compatible (OPCIÓN 2)
    if (!PermissionContextConstraints::isValidAssignment($this->permissionSlug, $contextTypeEnum)) {
      throw new \InvalidArgumentException(
        PermissionContextConstraints::invalidAssignmentMessage($this->permissionSlug, $contextTypeEnum)
      );
    }

    // Buscar el tipo de contexto por la categoría
    $tipoId = TipoContexto::
      where('categoria', $category)
      ->value('id_tipo_contexto');

    // Todos los contextos del tipo extraído, del modelo propio solamente
    $ids = $tipoId
      ? Contexto::where('id_tipo_contexto', $tipoId)
        ->pluck('id_contexto')
        ->all()
      : [];

    $this->contextIds = array_merge($this->contextIds, $ids);

    return $this;
  }

  /**
   * Duracion del permiso en dias a partir de la fecha de inicio.
   *
   * @param int $days Numero de dias
   */
  public function for(int $days): static
  {
    $this->endDate = $this->startDate->copy()->addDays($days);

    return $this;
  }

  /**
   * Diferir el inicio del permiso N dias a partir de hoy.
   *
   * Si se combina con ->for($days), la fecha de fin se recalcula
   * desde la nueva fecha de inicio (manteniendo la duracion).
   *
   * @param int $daysDelay Dias de espera antes de activar
   */
  public function waitFor(int $daysDelay): static
  {
    $prevStart = $this->startDate->copy();
    $this->startDate = Carbon::now()->addDays($daysDelay);

    // Recalcular fecha de fin manteniendo duracion original
    if ($this->endDate !== null) {
      $duration = (int) $prevStart->diffInDays($this->endDate);
      $this->endDate = $this->startDate->copy()->addDays($duration);
    }

    return $this;
  }

  /**
   * Marcar el permiso como DENY (denegar acceso).
   * Sin llamar este metodo, el permiso es GRANT por defecto.
   */
  public function revoke(): static
  {
    $this->granted = false;

    return $this;
  }

  /**
   * Permitir que el receptor pueda delegar este permiso a otros.
   * Sin llamar este metodo, no puede delegar (false por defecto).
   */
  public function canDelegate(): static
  {
    $this->delegable = true;

    return $this;
  }

  /**
   * Validar que el actor tenga autorización para asignar este permiso.
   * 
   * Reglas de autorización:
   * - Admin (permiso '*'): puede hacer cualquier cosa (GRANT, DENY, canDelegate)
   * - No-admin (delegación): debe tener el permiso con puede_delegar=true en TODOS los contextos
   *   donde se va a asignar. Al delegar, automáticamente NO puede re-delegar.
   *
   * @throws DontHavePermissionException Si el actor no tiene autorización
   */
  private function validateActorAuthorization(): void
  {
    // Los admins pueden hacer cualquier cosa
    if ($this->validator->isSuperAdmin($this->actor)) {
      return;
    }

    // No-admin: validar que tenga el permiso con puede_delegar en todos los contextos
    if (empty($this->contextIds)) {
      throw new DontHavePermissionException(
        permission: $this->permissionSlug,
        message: 'No tienes contextos donde delegar este permiso.'
      );
    }

    // Obtener contextos donde el actor PUEDE delegar este permiso
    $delegableContexts = $this->validator->getContextsWhereDelegablePermission(
      $this->actor,
      $this->permissionSlug
    );

    // Validar que TODOS los contextos destino estén en los delegables
    $missingContexts = array_diff($this->contextIds, $delegableContexts);

    if (!empty($missingContexts)) {
      throw new DontHavePermissionException(
        permission: $this->permissionSlug,
        objects: $missingContexts,
        message: 'No tienes permiso para delegar en los contextos especificados.'
      );
    }

    // Si es delegación (no es admin), NO puede re-delegar
    // Automáticamente resetear delegable a false para que el receptor no pueda delegar
    $this->delegable = false;
  }

  /**
   * Validar que el permiso sea compatible con los tipos de contexto.
   * 
   * Validación de doble-check en tiempo de persistencia (OPCIÓN 1).
   * Complementa validaciones tempranas para prevenir peticiones malformadas en runtime.
   *
   * @throws \InvalidArgumentException Si un contexto no es compatible con el permiso
   */
  private function validateContextCompatibility(): void
  {
    if (empty($this->contextIds)) {
      return;
    }

    // Obtener todos los contextos con sus tipos
    $contexts = Contexto::whereIn('id_contexto', $this->contextIds)
      ->with('tipoContexto')
      ->get();

    foreach ($contexts as $context) {
      if (!$context->tipoContexto) {
        throw new \InvalidArgumentException(
          "Contexto {$context->id_contexto} no tiene tipo asociado"
        );
      }

      $contextType = ContextType::from($context->tipoContexto->categoria);
      if (!PermissionContextConstraints::isValidAssignment($this->permissionSlug, $contextType)) {
        throw new \InvalidArgumentException(
          PermissionContextConstraints::invalidAssignmentMessage($this->permissionSlug, $contextType)
        );
      }
    }
  }

  /**
   * Persiste los registros UPE en la base de datos.
   *
   * Se llama automaticamente en __destruct. Puede invocarse
   * explicitamente si se necesita acceso a los modelos creados.
   *
   * Valida previamente que el actor tenga autorización para asignar el permiso
   * y que el permiso sea compatible con los tipos de contexto.
   *
   * @return UsuarioPermisoEspecial|Collection<int, UsuarioPermisoEspecial>
   * @throws \InvalidArgumentException Si no se especifico ningun contexto
   * @throws \InvalidArgumentException Si el permiso no es compatible con los contextos
   * @throws RecordNotFoundException Si el slug del permiso no existe 
   * @throws DontHavePermissionException Si el actor no tiene autorización
   */
  public function save(): UsuarioPermisoEspecial|Collection
  {
    if ($this->saved) {
      return collect();
    }

    // Validar que el actor tenga autorización ANTES de persistir
    $this->validateActorAuthorization();

    // Validar que el permiso sea compatible con los contextos (OPCIÓN 1 - doble-check)
    $this->validateContextCompatibility();

    $this->saved = true;

    if (empty($this->contextIds)) {
      throw new \InvalidArgumentException(
        'Debe especificar un contexto usando ->on($recurso) o ->onAll($class) antes de guardar.'
      );
    }

    // Obtener o crear el modelo Permiso desde el slug
    $permiso = Permiso::where(
      'slug',
      $this->permissionSlug->value
    )
      ->firstOrFail();

    $payload = [
      'id_usuario' => $this->recipient->id_usuario,
      'id_permiso' => $permiso->id_permiso,
      'esta_permitido' => $this->granted,
      'puede_delegar' => $this->delegable,
      'fecha_inicio_planificada' => $this->startDate,
      'fecha_fin_planificada' => $this->endDate ?? $this->startDate->copy()->addYears(100),
      'fecha_fin_real' => null,
      'esta_activo' => true,
      'fue_borrado' => false,
      'creado_por' => $this->actor->id_usuario,
    ];

    if (count($this->contextIds) === 1) {
      return UsuarioPermisoEspecial::create(
        array_merge($payload, ['id_contexto' => $this->contextIds[0]])
      );
    }

    $records = collect();
    foreach ($this->contextIds as $contextId) {
      $records->push(
        UsuarioPermisoEspecial::create(
          array_merge($payload, ['id_contexto' => $contextId])
        )
      );
    }

    return $records;
  }

  /**
   * Auto-save al salir del scope.
   * El flag $saved evita doble persistencia si se llamo save() explicitamente.
   */
  public function __destruct()
  {
    if (!$this->saved && !empty($this->contextIds)) {
      $this->save();
    }
  }
}
