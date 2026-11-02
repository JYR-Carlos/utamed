<?php

use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

function crearEstudianteParaMiddleware(array $usuarioAttrs = []): array
{
    $carrera = Carrera::first() ?? Carrera::create([
        'nombre' => 'Medicina Test',
        'codigo_carrera' => 19999,
        'id_departamento' => 1,
        'id_contexto' => 1,
    ]);

    $usuario = Usuario::factory()->create(array_merge([
        'rut' => '12.345.678-5',
        'nombre1' => 'JUAN',
        'nombre2' => 'CARLOS',
        'apellido1' => 'PÉREZ',
        'apellido2' => 'GONZÁLEZ',
        'email' => 'juan.perez@alumnos.uta.cl',
        'passhash' => Hash::make('secreto-123'),
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ], $usuarioAttrs));

    $estudiante = Estudiante::factory()->create([
        'id_usuario' => $usuario->id_usuario,
        'id_carrera' => $carrera->id_carrera,
        'agno_ingreso' => 2024,
    ]);

    UsuarioRolAsignacion::create([
        'id_usuario' => $usuario->id_usuario,
        'id_rol' => Rol::where('nombre', 'Estudiante')->value('id_rol'),
        'id_contexto' => 1,
        'asignado_por' => $usuario->id_usuario,
        'creado_por' => $usuario->id_usuario,
        'fecha_inicio_planificada' => now()->subDay(),
        'fecha_fin_planificada' => now()->addYear(),
        'esta_activo' => true,
        'fue_eliminado' => false,
    ]);

    return [$usuario, $estudiante];
}

test('estudiante con contraseña vigente puede acceder a endpoints de estudiante', function () {
    [$usuario] = crearEstudianteParaMiddleware(['fecha_cambio_passhash' => now()]);

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $this->withToken($token)
        ->getJson('/api/v1/estudiante/perfil')
        ->assertOk();
});

test('estudiante con contraseña vencida es bloqueado con 423 Locked en endpoints de estudiante', function () {
    Carbon::setTestNow('2026-11-02 12:00:00');

    [$usuario] = crearEstudianteParaMiddleware([
        'fecha_cambio_passhash' => now()->subMonths(7),
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/estudiante/perfil');

    $res->assertStatus(423)
        ->assertJson([
            'message' => 'Debes actualizar tu contraseña antes de continuar.',
            'error' => 'password_change_required',
            'motivo' => 'vencida',
        ]);

    Carbon::setTestNow();
});

test('estudiante en primer ingreso es bloqueado con 423 Locked con motivo primer_ingreso', function () {
    [$usuario] = crearEstudianteParaMiddleware([
        'fecha_cambio_passhash' => null,
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/estudiante/dashboard');

    $res->assertStatus(423)
        ->assertJson([
            'message' => 'Debes actualizar tu contraseña antes de continuar.',
            'error' => 'password_change_required',
            'motivo' => 'primer_ingreso',
        ]);
});

test('rutas de cambio de contraseña, me y logout están exentas de bloqueo aunque la clave esté vencida', function () {
    Carbon::setTestNow('2026-11-02 12:00:00');

    [$usuario] = crearEstudianteParaMiddleware([
        'fecha_cambio_passhash' => now()->subMonths(7),
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    // 1. /auth/me responde 200
    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJson([
            'data' => [
                'requiere_cambio_password' => true,
                'motivo_cambio_password' => 'vencida',
            ],
        ]);

    // 2. /auth/cambio-password-obligatorio responde 200
    $this->withToken($token)
        ->postJson('/api/v1/auth/cambio-password-obligatorio', [
            'current_password' => 'secreto-123',
            'password' => 'NuevaClaveSegura2026!',
            'password_confirmation' => 'NuevaClaveSegura2026!',
        ])
        ->assertOk();

    // 3. Ahora que cambió la clave, perfil ya no da 423
    $this->withToken($token)
        ->getJson('/api/v1/estudiante/perfil')
        ->assertOk();

    // 4. Logout responde 200
    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    Carbon::setTestNow();
});
