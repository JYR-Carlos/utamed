<?php

use App\Models\Usuario\Usuario;
use App\Models\Usuario\Estudiante;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Agenda\ActividadAsignadaGrupo;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class);

test('usuario can generate abbreviated name', function () {
    $usuario = Usuario::create([
        'username' => 'jprueba_' . uniqid(),
        'rut' => rand(10000000, 99999999) . '-' . rand(0, 9),
        'nombre1' => 'Juan',
        'apellido1' => 'Pérez',
        'email' => 'juan_' . uniqid() . '@test.local',
        'passhash' => Hash::make('password'),
        'esta_activo' => true,
    ]);

    $nombre = $usuario->nombreAbreviado();
    expect($nombre)->toBe('J Pérez');
});

test('usuario with empty nombre returns fallback', function () {
    $usuario = Usuario::create([
        'username' => 'jsinombre_' . uniqid(),
        'rut' => rand(10000000, 99999999) . '-' . rand(0, 9),
        'nombre1' => '',
        'apellido1' => 'Anónimo',
        'email' => 'anonimo_' . uniqid() . '@test.local',
        'passhash' => Hash::make('password'),
        'esta_activo' => true,
    ]);

    $nombre = $usuario->nombreAbreviado();
    expect($nombre)->toBe('N/A');
});
