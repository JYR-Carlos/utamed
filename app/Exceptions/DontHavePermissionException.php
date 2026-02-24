<?php

namespace App\Exceptions;

use App\Contracts\HasOwnedContext;
use App\Models\Usuario\Contexto;
use App\Support\Permissions;
use Illuminate\Support\Collection;

/**
 * Excepción lanzada cuando un usuario no tiene los permisos necesarios
 * para realizar una acción (asignar permisos, revocar, delegar, etc).
 * 
 * Genera mensajes detallados y seguros que muestran los objetos relevantes
 * (ej: "Facultad: Ingeniería") en lugar de exponer IDs de contexto.
 * 
 * Extiende RuntimeException para ser compatible con Throwable en PHP y Laravel.
 * Se propaga como excepción HTTP 403 Forbidden cuando no es capturada.
 * 
 * @example Asignar a objetos directos (HasOwnedContext):
 *   throw new DontHavePermissionException(
 *     objects: $facultad,
 *     permission: Permissions::FACULTADES_EDITAR
 *   );
 *   // Resultado: "No tienes permiso para 'facultad:editar' en Facultad: Ingeniería"
 * 
 * @example Asignar a múltiples contextos:
 *   throw new DontHavePermissionException(
 *     objects: [$facultad, $carrera],
 *     permission: Permissions::CURSOS_CREAR
 *   );
 *   // Resultado: "No tienes permiso para 'cursos:crear' en Facultad: Ingeniería; Carrera: Sistemas"
 */
class DontHavePermissionException extends \RuntimeException
{
    // TODO: revisar implementacion
    protected string $defaultMessage = 'You do not have permission to perform this action';

    /**
     * Constructor mejorado con resolución de objetos contextualizados.
     * 
     * @param Permissions|null $permission El permiso que se intentó verificar
     * @param HasOwnedContext|array<HasOwnedContext|int>|null $objects 
     *        Objeto(s) contextualizado(s) o ID(s) de contexto. Se resuelven automáticamente
     *        a los modelos reales (Facultad, Carrera, Curso, etc).
     * @param string|null $message Mensaje personalizado (opcional, fallback a generado)
     * @param \Throwable|null $previous Excepción anterior (para encadenamiento)
     * @throws \InvalidArgumentException Si objects contiene tipos no soportados
     */
    public function __construct(
        ?Permissions $permission = null,
        HasOwnedContext|array|null $objects = null,
        ?string $message = null,
        ?\Throwable $previous = null
    ) {
        // Normalizar objetos a array
        $objectsArray = $this->normalizeObjects($objects);

        // Si no se proporciona mensaje, generarlo desde la información disponible
        if ($message === null) {
            $message = $this->generateMessage($permission, $objectsArray);
        }

        parent::__construct($message, 0, $previous);
    }

    /**
     * Normalizar input de objetos a array consistente.
     * 
     * Acepta:
     * - null → []
     * - objeto singular (HasOwnedContext o int) → [objeto]
     * - array mixto de (HasOwnedContext | int) → procesado
     * 
     * @param HasOwnedContext|array|null $objects
     * @return array Array de objetos contextualizados resueltos
     * @throws \InvalidArgumentException
     */
    protected function normalizeObjects(HasOwnedContext|array|null $objects): array
    {
        if ($objects === null) {
            return [];
        }

        // Convertir objeto singular a array
        $items = is_array($objects) ? $objects : [$objects];

        // Resolver cada item: si es int (contextId), cargar desde DB; si es modelo, usar directamente
        $resolved = [];
        foreach ($items as $item) {
            if (is_int($item)) {
                // Contextuar: obtener el objeto del contexto
                $resolved[] = $this->resolveContextToObject($item);
            } elseif ($item instanceof HasOwnedContext) {
                $resolved[] = $item;
            } else {
                throw new \InvalidArgumentException(
                    'objects debe contenar HasOwnedContext o int (context ID), recibido: '
                    . (is_object($item) ? get_class($item) : gettype($item))
                );
            }
        }

        return array_filter($resolved); // Eliminar nulls
    }

    /**
     * Resolver un ID de contexto al objeto contextualizado (Facultad, Carrera, etc).
     * 
     * Utiliza las relaciones hasOne de Contexto para encontrar el modelo correcto.
     * 
     * @param int $contextId ID de contexto
     * @return HasOwnedContext|null El objeto contextualizado o null si no existe
     */
    protected function resolveContextToObject(int $contextId): ?HasOwnedContext
    {
        /** @var Contexto|null */
        $contexto = Contexto::find($contextId);

        if ($contexto === null) {
            return null;
        }

        // Intentar cargar desde relaciones hasOne (en orden de probabilidad)
        // El modelo relevante será el que NO sea null
        $models = [
            $contexto->facultad,
            $contexto->carrera,
            $contexto->departamento,
            $contexto->curso,
            $contexto->actividad,
        ];

        foreach ($models as $model) {
            if ($model !== null) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Generar mensaje descriptivo y seguro basado en permisos y objetos.
     * 
     * Muestra: "No tienes permiso para '{permiso}' en {Tipo}: {nombre}; {Tipo}: {nombre}"
     * 
     * @param Permissions|null $permission
     * @param array<HasOwnedContext> $objects
     * @return string Mensaje generado
     */
    protected function generateMessage(?Permissions $permission, array $objects): string
    {
        $parts = ['No tienes permiso'];

        if ($permission !== null) {
            $parts[] = "para '{$permission->value}'";
        }

        if (!empty($objects)) {
            $objectDescriptions = [];
            foreach ($objects as $object) {
                $description = $this->describeObject($object);
                if ($description !== null) {
                    $objectDescriptions[] = $description;
                }
            }

            if (!empty($objectDescriptions)) {
                $parts[] = 'en ' . implode('; ', $objectDescriptions);
            }
        }

        return implode(' ', $parts) . '.';
    }

    /**
     * Formatear descripción segura de un objeto.
     * 
     * Emite un string tipo: "Facultad: Ingeniería" o "Carrera: Sistemas"
     * 
     * Busca atributos comunes en orden: name, nombre, descripcion, titulo, title
     * 
     * @param HasOwnedContext $object
     * @return string|null Descripción o null si no se puede formatear
     */
    protected function describeObject(HasOwnedContext $object): ?string
    {
        // Obtener el tipo legible del modelo
        $type = $this->getHumanReadableType($object);

        // Encontrar un nombre/descripción
        $displayAttrs = ['nombre', 'name', 'descripcion', 'titulo', 'title', 'display_name'];
        $displayValue = null;

        foreach ($displayAttrs as $attr) {
            if ($object->getAttribute($attr) !== null) {
                $displayValue = $object->getAttribute($attr);
                break;
            }
        }

        if ($displayValue === null) {
            return $type;
        }

        // Truncar si es muy largo (evitar spam en logs)
        if (strlen($displayValue) > 100) {
            $displayValue = substr($displayValue, 0, 97) . '…';
        }

        return "{$type}: {$displayValue}";
    }

    /**
     * Obtener nombre legible del tipo de modelo (ej: "Facultad", "Carrera").
     * 
     * @param HasOwnedContext $object
     * @return string Nombre del modelo en singular, capitalizado
     */
    protected function getHumanReadableType(HasOwnedContext $object): string
    {
        $className = class_basename($object);

        // Convertir CamelCase a espacio (ej: 'UsuarioPermiso' → 'Usuario Permiso')
        // y luego capitalizar cada palabra
        $words = preg_split(
            '/(?=[A-Z])/',
            $className,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return implode(' ', $words);
    }
}
