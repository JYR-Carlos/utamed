<?php

namespace App\Services;

/**
 * Servicio para resolver contextos de modelos
 * 
 * Lee la configuración de context-mappings.json y resuelve
 * las rutas de contexto automáticamente siguiendo las relaciones
 * del modelo hasta encontrar un contexto directo o global.
 */
class ContextResolver
{
    /**
     * Contexto mapeado cargado en memoria
     *
     * @var array|null
     */
    protected $mappings = null;

    /**
     * Cache de contextos que se acaban de resolver
     *
     * @var array
     */
    protected $contextCache = [];

    /**
     * Cargar los mappings del archivo
     * 
     * @return array
     */
    protected function loadMappings(): array
    {
        if ($this->mappings !== null) {
            return $this->mappings;
        }

        $configPath = config_path('generated-context-mappings.php');

        if (!file_exists($configPath)) {
            throw new \RuntimeException(
                "Context mappings file not found: $configPath\n"
                . "Run: php scripts/generate_models.php"
            );
        }

        $this->mappings = include $configPath;

        return $this->mappings;
    }

    /**
     * Obtener todos los IDs de contexto de un modelo
     * 
     * Para modelos con múltiples caminos de contexto (ej: InscripcionCurso),
     * retorna un array con todos los IDs únicos encontrados.
     * 
     * @param object $model Instancia del modelo
     * @return array Array de IDs de contexto (vacío si no tiene contextos)
     */
    public function getContextId($model): array
    {
        $modelKey = $this->getModelKey($model);
        $mappings = $this->loadMappings();

        if (!isset($mappings[$modelKey])) {
            return [];
        }

        $mapping = $mappings[$modelKey];
        $contextIds = [];

        // Modelos con contexto directo
        if ($mapping['type'] === 'direct') {
            $contextColumn = config('context-hierarchies.context_column', 'id_contexto');
            $contextId = $model->getAttribute($contextColumn);
            return $contextId !== null ? [$contextId] : [];
        }

        // Modelos con contexto global (sin contexto)
        if ($mapping['type'] === 'global') {
            return [];
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
     * Obtener el tipo de contexto de un modelo
     * 
     * Nota: Aunque un modelo puede tener múltiples paths, todos deberían
     * apuntar al mismo tipo de contexto (ej: 'carrera'). Retorna el primer
     * tipo encontrado.
     * 
     * @param object $model Instancia del modelo
     * @return string|null (ej: 'carrera', 'curso', 'departamento')
     */
    public function getContextType($model): ?string
    {
        $modelKey = $this->getModelKey($model);
        $mappings = $this->loadMappings();

        if (!isset($mappings[$modelKey])) {
            return null;
        }

        $mapping = $mappings[$modelKey];

        // Modelos con contexto directo
        if ($mapping['type'] === 'direct') {
            // Retorna el tipo del modelo actual (ej: 'carrera', 'curso')
            return $this->inferContextType($modelKey);
        }

        // Modelos con contexto global (sin contexto)
        if ($mapping['type'] === 'global') {
            return null;
        }

        // Modelos con contexto jerárquico - seguir rutas hasta encontrar un directo
        if ($mapping['type'] === 'hierarchical' && !empty($mapping['paths'])) {
            foreach ($mapping['paths'] as $pathIndex => $path) {
                $contextType = $this->followPathType($model, $path, $mappings);
                if ($contextType !== null) {
                    return $contextType;
                }
            }
        }

        return null;
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
        $mappings = $this->loadMappings();

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
        $currentModel = $model;

        foreach ($path as $step) {
            $methodName = $step['method'] ?? null;
            $targetClass = $step['target'] ?? null;

            if (!$methodName) {
                return null;
            }

            // Llamar al método de relación para obtener el modelo relacionado
            if (!method_exists($currentModel, $methodName)) {
                return null;
            }

            $currentModel = $currentModel->$methodName;

            if ($currentModel === null) {
                return null;
            }

            // Si es una colección, tomaremos el primero
            if ($currentModel instanceof \Illuminate\Database\Eloquent\Collection) {
                if ($currentModel->isEmpty()) {
                    return null;
                }
                $currentModel = $currentModel->first();
            }
        }

        // El modelo resultante debería ser directo
        $finalModelKey = $this->getModelKey($currentModel);

        if (isset($mappings[$finalModelKey]) && $mappings[$finalModelKey]['type'] === 'direct') {
            $contextColumn = config('context-hierarchies.context_column', 'id_contexto');
            return $currentModel->getAttribute($contextColumn);
        }

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
        $currentModel = $model;

        foreach ($path as $step) {
            $methodName = $step['method'] ?? null;

            if (!$methodName) {
                return null;
            }

            if (!method_exists($currentModel, $methodName)) {
                return null;
            }

            $currentModel = $currentModel->$methodName;

            if ($currentModel === null) {
                return null;
            }

            // Si es una colección, tomaremos el primero
            if ($currentModel instanceof \Illuminate\Database\Eloquent\Collection) {
                if ($currentModel->isEmpty()) {
                    return null;
                }
                $currentModel = $currentModel->first();
            }
        }

        // El modelo resultante debería ser directo
        $finalModelKey = $this->getModelKey($currentModel);

        if (isset($mappings[$finalModelKey]) && $mappings[$finalModelKey]['type'] === 'direct') {
            return $this->inferContextType($finalModelKey);
        }

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
        for ($i = 0; $i < count($path) - 1; $i++) {
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
     * @return string (ej: 'utamed.Administrativo.Carrera')
     */
    protected function getModelKey($model): string
    {
        $class = get_class($model);
        $parts = explode('\\', $class);

        // Obtener Schema y Model Name
        // Estructura: App\Models\Administrativo\Carrera
        // Queremos: utamed.Administrativo.Carrera
        $schema = $parts[count($parts) - 2] ?? 'Unknown';
        $modelName = $parts[count($parts) - 1] ?? 'Unknown';

        return "utamed.$schema.$modelName";
    }

    /**
     * Inferir el tipo de contexto del nombre del modelo
     * 
     * @param string $modelKey (ej: 'utamed.Administrativo.Carrera')
     * @return string (ej: 'carrera')
     */
    protected function inferContextType(string $modelKey): string
    {
        $parts = explode('.', $modelKey);
        $modelName = end($parts);

        // Convertir PascalCase a camelCase
        return lcfirst($modelName);
    }

    /**
     * Obtener la clave de contexto (namespace + contexto)
     *
     * Utilizado para caché y seguimiento
     */
    protected function getContextCacheKey($model): string
    {
        return spl_object_id($model);
    }

    /**
     * Invalidar el caché de un modelo
     * 
     * @param object $model
     * @return void
     */
    public function invalidateCache($model): void
    {
        unset($this->contextCache[$this->getContextCacheKey($model)]);
    }

    /**
     * Limpiar todo el caché
     * 
     * @return void
     */
    public function clearCache(): void
    {
        $this->contextCache = [];
    }
}
