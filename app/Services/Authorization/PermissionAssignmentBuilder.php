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
use App\Services\ContextResolver;
use App\Support\Permissions;

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
    private readonly Usuario $actor
  ) {
    $this->startDate = Carbon::now();
    $this->validator = app(PermissionValidator::class);
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
          . "o asigna el permiso directamente sin contexto."
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
   * Útil para asignar permisos a modelos globales donde el contexto
   * se resuelve desde el servicio global (ej: al crear usuarios).
   *
   * @param int|int[] $contextIds ID o IDs del contexto
   * @example
   *   $user->givePermission($perm)->inContext($globalContextId)->for(30);
   */
  public function inContext(int|array $contextIds): static
  {
    $ids = is_array($contextIds) ? $contextIds : [$contextIds];
    $this->contextIds = array_unique(array_merge($this->contextIds, $ids));

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
   */
  public function onAll(ContextualModelType $modelType): static
  {
    $category = strtolower(class_basename($modelType->value));

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
   * Persiste los registros UPE en la base de datos.
   *
   * Se llama automaticamente en __destruct. Puede invocarse
   * explicitamente si se necesita acceso a los modelos creados.
   *
   * Valida previamente que el actor tenga autorización para asignar el permiso.
   *
   * @return UsuarioPermisoEspecial|Collection<int, UsuarioPermisoEspecial>
   * @throws \InvalidArgumentException Si no se especifico ningun contexto
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
