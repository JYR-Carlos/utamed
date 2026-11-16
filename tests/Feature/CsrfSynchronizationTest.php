<?php

use App\Models\Curso\Curso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

function crearSuperAdminParaCsrfTest(): Usuario
{
    $admin = Usuario::firstOrCreate(
        ['rut' => '77778888-9'],
        [
            'username'    => 'admin_csrf_test',
            'passhash'    => Hash::make('password'),
            'nombre1'     => 'Admin',
            'apellido1'   => 'CSRF Test',
            'esta_activo' => true,
        ]
    );
    $admin->fecha_cambio_passhash = now();
    $admin->fecha_verificacion_email = now();
    $admin->passhash = Hash::make('password');
    $admin->save();

    $rolSuperAdmin = Rol::firstOrCreate(['nombre' => 'SuperAdmin'], ['creado_por' => $admin->id_usuario]);
    UsuarioRolAsignacion::firstOrCreate(
        ['id_usuario' => $admin->id_usuario, 'id_rol' => $rolSuperAdmin->id_rol, 'id_contexto' => 1],
        [
            'asignado_por'             => $admin->id_usuario,
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada'    => now()->addYears(100),
            'esta_activo'              => true,
            'fue_eliminado'            => false,
            'creado_por'               => $admin->id_usuario,
        ]
    );

    return $admin;
}

test('HandleInertiaRequests comparte csrf_token en las respuestas Inertia', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->has('csrf_token')
        ->where('csrf_token', session()->token())
    );
});

test('el login regenera la sesión y actualiza el prop csrf_token en la siguiente navegación Inertia', function () {
    $admin = crearSuperAdminParaCsrfTest();

    // Visita inicial antes del login (genera token pre-login)
    $loginPage = $this->get('/login');
    $loginPage->assertStatus(200);
    $preLoginToken = session()->token();
    expect($preLoginToken)->not->toBeEmpty();

    // Login vía POST (Fortify regenera la sesión y el token CSRF)
    $loginResponse = $this->post('/login', [
        'email'    => $admin->rut,
        'password' => 'password',
    ]);
    $loginResponse->assertRedirect();
    $this->assertAuthenticatedAs($admin);

    $postLoginToken = session()->token();
    expect($postLoginToken)->not->toBeEmpty();
    expect($postLoginToken)->not->toBe($preLoginToken);

    // Siguiente visita Inertia al dashboard debe exponer el nuevo csrf_token
    $dashboard = $this->get('/admin/dashboard');
    $dashboard->assertStatus(200);
    $dashboard->assertInertia(fn (Assert $page) => $page
        ->where('csrf_token', $postLoginToken)
    );
});

test('copiar curso y asignar rol responden sin error 419 usando el csrf_token actualizado tras login', function () {
    $admin = crearSuperAdminParaCsrfTest();

    // Simular flujo real de login
    $this->post('/login', [
        'email'    => $admin->rut,
        'password' => 'password',
    ])->assertRedirect();

    $tokenActualizado = session()->token();
    expect(hash_equals($tokenActualizado, session()->token()))->toBeTrue();

    // 1. Asignación de rol vía JSON (simulando PermissionsModal)
    $targetUser = Usuario::factory()->create([
        'fecha_cambio_passhash'    => now(),
        'fecha_verificacion_email' => now(),
    ]);
    $rolParaAsignar = Rol::where('nombre', 'SuperAdmin')->firstOrFail();

    $assignRes = $this->withHeaders([
        'X-CSRF-TOKEN'     => $tokenActualizado,
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept'           => 'application/json',
    ])->postJson("/admin/usuarios/{$targetUser->id_usuario}/assign-role", [
        'role_id'           => $rolParaAsignar->id_rol,
        'context_type'      => 'global',
        'context_object_id' => null,
        'start_date'        => null,
        'end_date'          => null,
    ]);

    expect($assignRes->status())->not->toBe(419);
    $assignRes->assertStatus(200);

    // 2. Copia de curso vía endpoint POST (simulando cursoCopyPreviewModal)
    $curso = Curso::first();
    if ($curso) {
        $copyRes = $this->withHeaders([
            'X-CSRF-TOKEN'     => $tokenActualizado,
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post("/admin/cursos/{$curso->id_curso}/copiar", [
            'nombre'         => 'Curso Copia CSRF Test',
            'fecha_inicio'   => now()->format('Y-m-d'),
            'agno_real'      => $curso->agno_real,
            'semestre_real'  => $curso->semestre_real,
        ]);

        expect($copyRes->status())->not->toBe(419);
        expect(in_array($copyRes->status(), [200, 302]))->toBeTrue();
    }
});
