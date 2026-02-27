<?php

namespace App\Services\Authorization;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Contracts\HasOwnedContext;
use App\Enums\ContextualModelType;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\TipoContexto;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\ContextResolver;
use App\Exceptions\DontHavePermissionException;

/**
 * Builder declarativo para asignar roles (URA) a un usuario.
 *
 * Se persiste automaticamente al finalizar la cadena (via __destruct).
 *
 * @example Asignar rol con duracion:
 *   $user->giveRole($rol)->on($facultad)->for(60);
 *
 * @example Asignar a todos los contextos del tipo Facultad:
 *   $user->giveRole($rol)->onAll(Facultad::class)->for(30);
 *
 * @example Con inicio diferido:
 *   $user->giveRole($rol)->on($recurso)->for(60)->waitFor(5);
 */
class RoleAssignmentBuilder
{
  /** @var int[] IDs de contexto sobre los que se creara la asignacion */
  private array $contextIds = [];

  /** @var Carbon Fecha de inicio de la asignacion */
  private Carbon $startDate;

  /** @var Carbon|null Fecha de fin de la asignacion (null = indefinido) */
  private ?Carbon $endDate = null;

  /** @var bool Evita doble persistencia en __destruct */
  private bool $saved = false;

  protected PermissionValidator $validator;

  public function __construct(
    private readonly Usuario $recipient,
    private readonly Rol $rol,
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
          . "o asigna el rol directamente sin contexto."
        );
      }
      $ids = $resolver->getContextId($resource);
      $this->contextIds = array_unique(array_merge($this->contextIds, $ids));
    }

    return $this;
  }

  /**
   * Especificar un contexto directamente por su ID.
   *
   * Útil para asignar roles a modelos globales donde el contexto
   * se resuelve desde el servicio global (ej: al crear usuarios).
   *
   * @param int|int[] $contextIds ID o IDs del contexto
   * @example
   *   $user->giveRole($rol)->inContext($globalContextId)->for(365);
   */
  public function inContext(int|array $contextIds): static
  {
    $ids = is_array($contextIds) ? $contextIds : [$contextIds];
    $this->contextIds = array_unique(array_merge($this->contextIds, $ids));

    return $this;
  }

  /**
   * Asignar el rol al contexto global.
   *
   * Útil para roles que aplican a nivel sistema sin restricción de contexto.
   *
   * @example
   *   $user->giveRole($rol)->inGlobalContext()->for(365);
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
   *   $user->giveRole($rol)->on($recurso)->as($admin)->for(60);
   */
  public function as(Usuario $actor): static
  {
    $this->actor = $actor;
    return $this;
  }

  /**
   * Asignar el rol a TODOS los contextos del tipo dado.
   *
   * Usa el enum ContextualModelType para garantizar en tiempo de compilación
   * que sólo se pasan modelos que poseen un contexto real (tipo 'direct').
   *
   * @see ContextualModelType
   * @example $user->giveRole($rol)->onAll(ContextualModelType::FACULTAD)->for(30);
   *
   * @param ContextualModelType $modelType Tipo de modelo contextual
   * 
   * // FIX: onAll() deberia asignar al contexto global y validate deberia tener en cuenta eso
   */
  public function onAll(ContextualModelType $modelType): static
  {
    $category = strtolower(class_basename($modelType->value));

    $tipoId = TipoContexto::where('categoria', $category)->value('id_tipo_contexto');

    $ids = $tipoId
      ? Contexto::where('id_tipo_contexto', $tipoId)->pluck('id_contexto')->all()
      : [];

    $this->contextIds = array_unique(array_merge($this->contextIds, $ids));

    return $this;
  }

  /**
   * Duracion de la asignacion en dias a partir de la fecha de inicio.
   *
   * @param int $days Numero de dias
   */
  public function for(int $days): static
  {
    $this->endDate = $this->startDate->copy()->addDays($days);

    return $this;
  }

  /**
   * Diferir el inicio de la asignacion N dias a partir de hoy.
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
   * Validar que el actor tenga autorización para asignar este rol.
   * 
   * Reglas de autorización:
   * - Admin (permiso '*'): puede asignar cualquier rol
   * - No-admin: NO puede asignar roles (conservador)
   *
   * @throws DontHavePermissionException Si el actor no tiene autorización
   */
  private function validateActorAuthorization(): void
  {
    // Los admins pueden asignar roles
    if ($this->validator->isSuperAdmin($this->actor)) {
      return;
    }

    // TODO: agregar permisos administrativos

    // No-admin: no puede asignar roles
    throw new DontHavePermissionException(
      objects: $this->contextIds,
      message: 'Solo los administradores pueden asignar roles.'
    );
  }

  /**
   * Persiste los registros URA en la base de datos.
   *
   * Se llama automaticamente en __destruct. Puede invocarse
   * explicitamente si se necesita acceso a los modelos creados.
   *
   * Valida previamente que el actor tenga autorización para asignar el rol.
   * 
   * // FIX: esta funcion no valida el par contexto:permiso, porque son un conjunto de permisos
   * decidir si validarlos todos o hacer otra cosa. 
   * (quiza redefinir el concepto de rol, conjunto de permisos especiales en vez de permisos)
   *
   * @return UsuarioRolAsignacion|Collection<int, UsuarioRolAsignacion>
   * @throws \InvalidArgumentException Si no se especifico ningun contexto
   * @throws DontHavePermissionException Si el actor no tiene autorización
   */
  public function save(): UsuarioRolAsignacion|Collection
  {
    if ($this->saved) {
      return collect();
    }

    // Validar que el actor tenga autorización ANTES de persistir
    $this->validateActorAuthorization();

    $this->saved = true;

    if (empty($this->contextIds)) {
      throw new \InvalidArgumentException(
        'Debe especificar un contexto usando ->on($recurso) o ->onAll($class) antes de guardar.'
      );
    }

    $payload = [
      'id_usuario' => $this->recipient->id_usuario,
      'id_rol' => $this->rol->id_rol,
      'asignado_por' => $this->actor->id_usuario,
      'fecha_inicio_planificada' => $this->startDate,
      'fecha_fin_planificada' => $this->endDate ?? $this->startDate->copy()->addYears(100),
      'fecha_fin_real' => null,
      'fue_eliminado' => false,
      'esta_activo' => true,
      'creado_por' => $this->actor->id_usuario,
    ];

    if (count($this->contextIds) === 1) {
      return UsuarioRolAsignacion::create(
        array_merge($payload, ['id_contexto' => $this->contextIds[0]])
      );
    }

    $records = collect();
    foreach ($this->contextIds as $contextId) {
      $records->push(
        UsuarioRolAsignacion::create(
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
