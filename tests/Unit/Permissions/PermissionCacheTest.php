<?php

use App\Services\Authorization\PermissionCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->cache = new PermissionCache();
});

test('el veredicto UPE se calcula una sola vez', function () {
    $llamadas = 0;
    $calcular = function () use (&$llamadas) {
        $llamadas++;
        return true;
    };

    expect($this->cache->recordarUpe(1, 'facultades:ver', 5, $calcular))->toBeTrue();
    expect($this->cache->recordarUpe(1, 'facultades:ver', 5, $calcular))->toBeTrue();

    expect($llamadas)->toBe(1);
});

test('el "sin coincidencia" también se cachea', function () {
    // null no puede guardarse tal cual: Cache::remember lo trata como fallo y
    // repetiría la consulta en cada llamada. Se codifica como 'null'.
    $llamadas = 0;
    $calcular = function () use (&$llamadas) {
        $llamadas++;
        return null;
    };

    expect($this->cache->recordarUpe(2, 'facultades:ver', 5, $calcular))->toBeNull();
    expect($this->cache->recordarUpe(2, 'facultades:ver', 5, $calcular))->toBeNull();

    expect($llamadas)->toBe(1);
});

test('DENY cacheado se recupera como false, no como "sin coincidencia"', function () {
    expect($this->cache->recordarUpe(3, 'facultades:ver', 5, fn() => false))->toBeFalse();
    expect($this->cache->recordarUpe(3, 'facultades:ver', 5, fn() => true))->toBeFalse();
});

test('las claves no se pisan entre usuarios, permisos ni contextos', function () {
    $this->cache->recordarUpe(10, 'facultades:ver', 5, fn() => true);

    expect($this->cache->recordarUpe(11, 'facultades:ver', 5, fn() => false))->toBeFalse()
        ->and($this->cache->recordarUpe(10, 'cursos:ver', 5, fn() => false))->toBeFalse()
        ->and($this->cache->recordarUpe(10, 'facultades:ver', 9, fn() => false))->toBeFalse()
        ->and($this->cache->recordarUpe(10, 'facultades:ver', 5, fn() => false))->toBeTrue();
});

test('UPE y URA no comparten clave para el mismo permiso y contexto', function () {
    expect($this->cache->recordarUpe(12, 'facultades:ver', 5, fn() => false))->toBeFalse()
        ->and($this->cache->recordarUra(12, 'facultades:ver', 5, fn() => true))->toBeTrue();
});

test('olvidarUsuario invalida todo lo del usuario y nada de los demás', function () {
    $this->cache->recordarUpe(20, 'facultades:ver', 5, fn() => true);
    $this->cache->recordarUra(20, 'cursos:ver', 7, fn() => true);
    $this->cache->recordarSuperAdmin(21, fn() => true);

    $this->cache->olvidarUsuario(20);

    expect($this->cache->recordarUpe(20, 'facultades:ver', 5, fn() => false))->toBeFalse()
        ->and($this->cache->recordarUra(20, 'cursos:ver', 7, fn() => false))->toBeFalse()
        // El usuario 21 no se toca.
        ->and($this->cache->recordarSuperAdmin(21, fn() => false))->toBeTrue();
});

test('olvidarUsuario funciona aunque el usuario no tuviera nada cacheado', function () {
    // `Cache::increment` sobre una clave inexistente no la crea en todos los
    // drivers; la primera invalidación tiene que sembrarla.
    $this->cache->olvidarUsuario(30);

    expect($this->cache->recordarSuperAdmin(30, fn() => true))->toBeTrue();
});

test('los contextos de un permiso se cachean como array', function () {
    $llamadas = 0;
    $calcular = function () use (&$llamadas) {
        $llamadas++;
        return [4, 8, 15];
    };

    expect($this->cache->recordarContextos(40, 'cursos:ver', $calcular))->toBe([4, 8, 15]);
    expect($this->cache->recordarContextos(40, 'cursos:ver', $calcular))->toBe([4, 8, 15]);

    expect($llamadas)->toBe(1);
});
