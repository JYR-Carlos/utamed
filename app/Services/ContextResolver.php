<?php

namespace App\Services;

use App\Services\Authorization\GlobalContextService;
use App\Support\ContextColumnConfig;
use App\Enums\ContextType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Servicio para resolver contextos de modelos
 * 
 * Lee la configuración de context-mappings.json y resuelve
 * las rutas de contexto automáticamente siguiendo las relaciones
 * del modelo hasta encontrar un contexto directo o global.
 */
class ContextResolver
{
    /** Prefijo de la clave de caché de cadenas de ancestros. */
    private const CLAVE_ANCESTROS = 'ctx:ancestros:';

    /** La jerarquía de contextos sólo cambia al crear o mover unidades académicas. */
    private const TTL_ANCESTROS_SEGUNDOS = 3600;

    /**
     * Contexto mapeado cargado en memoria
     *
     * @var array|null
     */
    protected $mappings = null;

    /**
     * Cadenas de ancestros ya resueltas en este request.
     *
     * Evita ir al store de caché varias veces dentro de la misma petición, que
     * con el driver `database` es una consulta más.
     *
     * @var array<int, array>
     */
    private array $ancestrosEnMemoria = [];

    /**
     * Cache de contextos que se acaban de resolver
     *
     * @var array
     */
    protected $contextCache = [];

    /**
     * Nombre de la columna de contexto
     *
     * @var string|null
     */
    protected $contextColumn = null;

    public function __construct(
        protected GlobalContextService $globalContextService
    ) {
    }

    /**
     * Cargar los mappings del archivo
     * 
     * @return array
     */
    protected function loadGeneratedContextMappings(): array
    {
        if ($this->mappings !== null) {
            return $this->mappings;
        }

        $configPath = config_path('generated-context-mappings.php');

        if (!file_exists($configPath)) {
            throw new RuntimeException(
                "Context mappings file not found: {$configPath}\n" .
                "Run: php scripts/generate_models.php"
            );
        }

        $this->mappings = require $configPath;
        $this->contextColumn = ContextColumnConfig::contextColumn();

        return $this->mappings;
    }

    /**
     * Obtener todos los IDs de contexto configurados para un modelo
     * 
     * Para modelos con múltiples caminos de contexto (ej: InscripcionCurso),
     * retorna un array con todos los IDs únicos encontrados.
     * 
     * @throws RuntimeException Al no encontrar un mapping válido
     * @param object $model Instancia del modelo
     * @return array Array de IDs de contexto 
     * 
     */
    public function getModelContextId($model): array
    {
        $modelKey = $this->getModelKey($model);
        $mappings = $this->loadGeneratedContextMappings();

        if (!isset($mappings[$modelKey])) {
            \Log::warning("ContextResolver: No mapping found for model $modelKey");
            throw new RuntimeException("No context mapping found for model: $modelKey");
        }

        $mapping = $mappings[$modelKey];
        $contextIds = [];

        // Modelos con contexto directo
        if ($mapping['type'] === 'direct') {
            $contextId = $model->getAttribute($this->contextColumn);
            return $contextId !== null ? [$contextId] : [];
        }

        // Modelos con contexto global (sin contexto propio)
        if ($mapping['type'] === 'global') {
            return [$this->globalContextService->getContextId()];
        }

        // Modelos con contexto jerárquico - seguir TODAS las rutas
        if ($mapping['type'] === 'hierarchical' && !empty($mapping['paths'])) {
            foreach ($mapping['paths'] as $pathIndex => $path) {
                $contextId = $this->followPath($model, $path, $mappings);
                if ($contextId !== null) {
                    $contextIds[] = $contextId;
                }
            }
        }

        // Retornar IDs únicos
        return array_values(array_unique($contextIds));
    }

    /**
     * Obtener todos los tipos de contexto de un modelo.
     *
     * Para modelos con múltiples paths jerárquicos (ej: InscripcionCurso),
     * puede retornar más de un tipo (ej: ['curso', 'carrera']).
     *
     * @throws RuntimeException Al no encontrar un mapping válido
     * @param object $model Instancia del modelo
     * @return string[] (ej: ['carrera'], ['curso', 'carrera'])
     */
    public function getModelContextTypes($model): array
    {
        $modelKey = $this->getModelKey($model);
        $mappings = $this->loadGeneratedContextMappings();

        if (!isset($mappings[$modelKey])) {
            \Log::warning("ContextResolver: No mapping found for model $modelKey");
            throw new RuntimeException("No context mapping found for model: $modelKey");
        }

        $mapping = $mappings[$modelKey];

        // Modelos con contexto directo
        if ($mapping['type'] === 'direct') {
            return [$this->inferContextType($modelKey)];
        }

        // Modelos con contexto global (sin contexto propio)
        if ($mapping['type'] === 'global') {
            return [];
        }

        // Modelos con contexto jerárquico - acumular tipos de TODOS los paths
        $contextTypes = [];
        if ($mapping['type'] === 'hierarchical' && !empty($mapping['paths'])) {
            foreach ($mapping['paths'] as $path) {
                $contextType = $this->followPathType($model, $path, $mappings);
                if ($contextType !== null) {
                    $contextTypes[] = $contextType;
                }
            }
        }

        return array_values(array_unique($contextTypes));
    }

    /**
     * Obtener todos los IDs de contexto ancestros de un contexto dado.
     *
     * Sube la cadena de id_contexto_padre hasta llegar a la raíz.
     * Retorna el array ordenado de más cercano a más lejano,
     * incluyendo el contexto original.
     *
     * @param int $contextId ID del contexto de partida
     * @return int[]
     */
    public function getAncestorContextIds(int $contextId): array
    {
        $rows = \Illuminate\Support\Facades\DB::select(
            'SELECT id_contexto, nivel, categoria FROM usuario.fn_obtener_ids_contexto_ancestros(?)',
            [$contextId]
        );

        if (empty($rows)) {
            return [$contextId];
        }

        return array_map(fn($row) => (int) $row->id_contexto, $rows);
    }

    /**
     * Obtener la cadena de ancestros con sus tipos de contexto (tipificados).
     *
     * Similar a getAncestorContextIds() pero retorna también el ContextType enum
     * de cada contexto en la cadena, con type-safety desde la BD.
     *
     * @param int $contextId ID del contexto de partida
     * @return array<int, array{id_contexto: int, nivel: int, categoria: ContextType}>
     */
    public function getAncestorContextsWithType(int $contextId): array
    {
        // El motor de permisos llama aquí una vez por cada validación, y la
        // jerarquía sólo cambia al crear o mover facultades, carreras, planes o
        // cursos. Se cachea en memoria por request y en el store por una hora.
        if (isset($this->ancestrosEnMemoria[$contextId])) {
            return $this->ancestrosEnMemoria[$contextId];
        }

        $ancestros = Cache::remember(
            self::CLAVE_ANCESTROS . $contextId,
            self::TTL_ANCESTROS_SEGUNDOS,
            fn() => $this->fetchAncestorContextsWithType($contextId)
        );

        return $this->ancestrosEnMemoria[$contextId] = $ancestros;
    }

    /**
     * Invalida la cadena de ancestros cacheada de un contexto.
     *
     * Necesario si se reubica un contexto en la jerarquía; el TTL cubre el resto.
     */
    public function olvidarAncestros(int $contextId): void
    {
        unset($this->ancestrosEnMemoria[$contextId]);
        Cache::forget(self::CLAVE_ANCESTROS . $contextId);
    }

    /**
     * Consulta sin caché de {@see getAncestorContextsWithType()}.
     *
     * @return array<int, array{id_contexto: int, nivel: int, categoria: ContextType|null}>
     */
    private function fetchAncestorContextsWithType(int $contextId): array
    {
        $rows = DB::select(
            'SELECT id_contexto, nivel, categoria FROM usuario.fn_obtener_ids_contexto_ancestros(?)',
            [$contextId]
        );

        if (empty($rows)) {
            return [['id_contexto' => $contextId, 'nivel' => 0, 'categoria' => null]];
        }

        return array_map(fn($row) => [
            'id_contexto' => (int) $row->id_contexto,
            'nivel' => (int) $row->nivel,
            'categoria' => ContextType::from($row->categoria),
        ], $rows);
    }

    /**
     * Obtener el modelo padre que define el contexto
     * 
     * @param object $model Instancia del modelo
     * @return object|null Modelo padre que define el contexto, o null si es directo o global
     */
    public function getParentContextModel($model): ?object
    {
        $modelKey = $this->getModelKey($model);
        $mappings = $this->loadGeneratedContextMappings();

        if (!isset($mappings[$modelKey])) {
            return null;
        }

        $mapping = $mappings[$modelKey];

        // Modelos con contexto directo
        if ($mapping['type'] === 'direct') {
            return null;
        }

        // Modelos con contexto global (sin contexto)
        if ($mapping['type'] === 'global') {
            return null;
        }

        // Modelos con contexto jerárquico - retornar el modelo padre
        if ($mapping['type'] === 'hierarchical' && !empty($mapping['paths'])) {
            foreach ($mapping['paths'] as $pathIndex => $path) {
                $parentModel = $this->followPathToParent($model, $path);
                if ($parentModel !== null) {
                    return $parentModel;
                }
            }
        }

        return null;
    }

    /**
     * Seguir una ruta de relaciones para obtener el ID de contexto
     * 
     * @param object $model Modelo actual
     * @param array $path Pasos del path: [['target' => 'ClassName', 'method' => 'relationName'], ...]
     * @param array $mappings Mapeos completos
     * @return int|null
     */
    protected function followPath($model, array $path, array $mappings): ?int
    {
        // Inicializar el modelo actual con el que se pasó como parámetro
        $currentModel = $model;

        // Iterar sobre cada paso en la cadena de relaciones (ej: ['post' => 'user' => 'role'])
        foreach ($path as $step) {
            // Obtener el nombre del método de relación (ej: 'user', 'post', etc.)
            // Si no existe, asignar null
            $methodName = $step['method'] ?? null;

            // Obtener el nombre de la clase destino (ej: 'App\Models\User')
            // NO se usa en este método, pero está disponible en el array de configuración
            $targetClass = $step['target'] ?? null;

            // Si no hay un nombre de método definido, no se puede continuar
            if (!$methodName) {
                return null;
            }

            // Validar que el modelo actual tenga un método con ese nombre
            // (verifica que exista la relación)
            if (!method_exists($currentModel, $methodName)) {
                return null;
            }

            // Llamar el método de relación para obtener el modelo relacionado
            // Ej: $currentModel->user() retorna el Usuario relacionado
            $currentModel = $currentModel->$methodName;

            // Si la relación retorna null (no hay modelo relacionado), parar aquí
            if ($currentModel === null) {
                return null;
            }

            // Si la relación retorna una colección (relación 1:N), tomar el primer elemento
            if ($currentModel instanceof \Illuminate\Database\Eloquent\Collection) {
                // Si la colección está vacía, no hay nada que retornar
                if ($currentModel->isEmpty()) {
                    return null;
                }
                // Extraer el primer modelo de la colección
                $currentModel = $currentModel->first();
            }
        }

        // Cuando se terminan todos los pasos, obtener la clave única del modelo final
        // Formato: 'Schema\NombreModelo' (ej: 'Administrativo\Carrera')
        $finalModelKey = $this->getModelKey($currentModel);

        // Verificar que el modelo final sea un tipo "direct" (tiene context_id directo)
        // Y extraer su ID de contexto de la columna configurada (usualmente 'id_contexto')
        if (isset($mappings[$finalModelKey]) && $mappings[$finalModelKey]['type'] === 'direct') {
            return $currentModel->getAttribute($this->contextColumn);
        }

        // Si llegó a un modelo que no es "directo", retornar null (no tiene contexto)
        return null;
    }
    /**
     * Seguir una ruta de relaciones para obtener el tipo de contexto
     * 
     * @param object $model Modelo actual
     * @param array $path Pasos del path
     * @param array $mappings Mapeos completos
     * @return string|null
     */
    protected function followPathType($model, array $path, array $mappings): ?string
    {
        // Inicializar el modelo actual con el que se pasó como parámetro
        $currentModel = $model;

        // Iterar sobre cada paso en la cadena de relaciones (ej: ['post' => 'user' => 'role'])
        foreach ($path as $step) {
            // Obtener el nombre del método de relación (ej: 'user', 'post', 'carrera', etc.)
            // Si no existe en el array, asignar null
            $methodName = $step['method'] ?? null;

            // Si no hay un nombre de método definido, no se puede continuar
            if (!$methodName) {
                return null;
            }

            // Validar que el modelo actual tenga un método con ese nombre
            // (verifica que exista la relación)
            if (!method_exists($currentModel, $methodName)) {
                return null;
            }

            // Llamar el método de relación para obtener el modelo relacionado
            // Ej: $currentModel->carrera() retorna la Carrera relacionada
            $currentModel = $currentModel->$methodName;

            // Si la relación retorna null (no hay modelo relacionado), parar aquí
            if ($currentModel === null) {
                return null;
            }

            // Si la relación retorna una colección (relación 1:N), tomar el primer elemento
            if ($currentModel instanceof \Illuminate\Database\Eloquent\Collection) {
                // Si la colección está vacía, no hay nada que retornar
                if ($currentModel->isEmpty()) {
                    return null;
                }
                // Extraer el primer modelo de la colección
                $currentModel = $currentModel->first();
            }
        }

        // Cuando se terminan todos los pasos, obtener la clave única del modelo final
        // Formato: 'Schema\NombreModelo' (ej: 'Administrativo\Carrera')
        $finalModelKey = $this->getModelKey($currentModel);

        // Verificar que el modelo final sea un tipo "direct" (tiene context_id directo)
        // Si es directo, inferir el tipo de contexto del nombre del modelo
        // Ej: 'Administrativo\Carrera' → 'carrera'
        if (isset($mappings[$finalModelKey]) && $mappings[$finalModelKey]['type'] === 'direct') {
            return $this->inferContextType($finalModelKey);
        }

        // Si llegó a un modelo que no es "directo", retornar null (no es un tipo válido)
        return null;
    }

    /**
     * Seguir una ruta de relaciones hasta el penúltimo modelo (padre)
     * 
     * @param object $model Modelo actual
     * @param array $path Pasos del path
     * @return object|null
     */
    protected function followPathToParent($model, array $path): ?object
    {
        if (empty($path)) {
            return null;
        }

        $currentModel = $model;

        // Iterar hasta el penúltimo paso
        for ($i = 0; $i < \count($path) - 1; $i++) {
            $step = $path[$i];
            $methodName = $step['method'] ?? null;

            if (!$methodName || !method_exists($currentModel, $methodName)) {
                return null;
            }

            $currentModel = $currentModel->$methodName;

            if ($currentModel === null) {
                return null;
            }

            if ($currentModel instanceof \Illuminate\Database\Eloquent\Collection) {
                if ($currentModel->isEmpty()) {
                    return null;
                }
                $currentModel = $currentModel->first();
            }
        }

        // Obtener el último paso
        $lastStep = end($path);
        $methodName = $lastStep['method'] ?? null;

        if (!$methodName || !method_exists($currentModel, $methodName)) {
            return null;
        }

        $parentModel = $currentModel->$methodName;

        if ($parentModel === null) {
            return null;
        }

        if ($parentModel instanceof \Illuminate\Database\Eloquent\Collection) {
            if ($parentModel->isEmpty()) {
                return null;
            }
            return $parentModel->first();
        }

        return $parentModel;
    }

    /**
     * Obtener la clave del modelo (con namespace)
     * 
     * @param object $model Instancia del modelo
     * @return string (ej: 'Administrativo\Carrera')
     */
    protected function getModelKey($model): string
    {
        $class = get_class($model);
        $parts = explode('\\', $class);

        // Obtener Schema y Model Name
        // Estructura: App\Models\Administrativo\Carrera
        // Queremos: Administrativo\Carrera
        $schema = $parts[\count($parts) - 2] ?? 'Unknown';
        $modelName = $parts[\count($parts) - 1] ?? 'Unknown';

        return "{$schema}\\{$modelName}";
    }

    /**
     * Inferir el tipo de contexto del nombre del modelo
     * 
     * @param string $modelKey (ej: 'Administrativo\Carrera')
     * @return string (ej: 'carrera')
     */
    protected function inferContextType(string $modelKey): string
    {
        $parts = explode('\\', $modelKey);
        $modelName = end($parts);

        // Convertir PascalCase a camelCase
        return lcfirst($modelName);
    }

    // ----- CACHÉ (opcional, no implementado aún) -----

    // /**
    //  * Obtener la clave de contexto (namespace + contexto)
    //  *
    //  * Utilizado para caché y seguimiento
    //  */
    // protected function getContextCacheKey($model): string
    // {
    //     return spl_object_id($model);
    // }

    // /**
    //  * Invalidar el caché de un modelo
    //  * 
    //  * @param object $model
    //  * @return void
    //  */
    // public function invalidateCache($model): void
    // {
    //     unset($this->contextCache[$this->getContextCacheKey($model)]);
    // }

    // /**
    //  * Limpiar todo el caché
    //  * 
    //  * @return void
    //  */
    // public function clearCache(): void
    // {
    //     $this->contextCache = [];
    // }
}
