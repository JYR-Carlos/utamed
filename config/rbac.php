<?php

return [
    /**
     * Tipo de contexto global en la tabla Contexto.
     * 
     * Usado para buscar dinámicamente el ID del contexto global.
     */
    'global_context_type' => 'GLOBAL',
    
    /**
     * TTL de caché de permisos (en segundos).
     * 
     * Por defecto: 3600 segundos (1 hora)
     */
    'cache_ttl' => env('RBAC_CACHE_TTL', 3600),

    /**
     * Prefijo de claves de caché.
     * 
     * Se usa para formar claves como: perm:{userId}:{permission}:{contextId}
     */
    'cache_prefix' => 'perm',

    /**
     * Habilitar caché de permisos.
     * 
     * Desactivar solo para debugging.
     */
    'cache_enabled' => env('RBAC_CACHE_ENABLED', true),
];
