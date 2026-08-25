<?php

use App\Models\Usuario\Usuario;

describe('Usuario: Estándar de Almacenamiento de Nombres en Mayúsculas', function () {

    test('los mutadores de Usuario convierten automáticamente nombres y apellidos a mayúsculas limpias', function () {
        $usuario = new Usuario();
        $usuario->nombre1 = "  sui-lan   danae  ";
        $usuario->nombre2 = "  alexandra  ";
        $usuario->apellido1 = "  lópez  ";
        $usuario->apellido2 = "  carmona  ";

        expect($usuario->nombre1)->toBe('SUI-LAN DANAE');
        expect($usuario->nombre2)->toBe('ALEXANDRA');
        expect($usuario->apellido1)->toBe('LÓPEZ');
        expect($usuario->apellido2)->toBe('CARMONA');
        expect($usuario->nombre_completo)->toBe('SUI-LAN DANAE ALEXANDRA LÓPEZ CARMONA');
    });

    test('los mutadores manejan valores nulos o vacíos en nombre2 y apellido2', function () {
        $usuario = new Usuario();
        $usuario->nombre1 = 'juan';
        $usuario->nombre2 = '   ';
        $usuario->apellido1 = 'pérez';
        $usuario->apellido2 = null;

        expect($usuario->nombre1)->toBe('JUAN');
        expect($usuario->nombre2)->toBeNull();
        expect($usuario->apellido1)->toBe('PÉREZ');
        expect($usuario->apellido2)->toBeNull();
        expect($usuario->nombre_completo)->toBe('JUAN PÉREZ');
    });

    test('los mutadores respetan caracteres especiales como apóstrofes, guiones y tildes', function () {
        $usuario = new Usuario();
        $usuario->nombre1 = "  maría-josé  ";
        $usuario->apellido1 = "  d'alessandra  ";
        $usuario->apellido2 = "  zúñiga  ";

        expect($usuario->nombre1)->toBe('MARÍA-JOSÉ');
        expect($usuario->apellido1)->toBe("D'ALESSANDRA");
        expect($usuario->apellido2)->toBe('ZÚÑIGA');
    });
});
