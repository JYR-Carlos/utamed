<?php

use App\Http\Controllers\Admin\UsuarioController;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Services\Authorization\GlobalContextService;
use App\Services\Authorization\RoleAssignmentBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTransactions::class);

function getSuperAdminAutenticado(string $password = 'password-admin-segura'): Usuario
{
    $superadmin = Usuario::where('username', 'superadmin')->first();
    if (! $superadmin) {
        $sufijo = random_int(1000000, 9999999);
        $superadmin = Usuario::create([
            'username' => "sa_{$sufijo}",
            'passhash' => Hash::make($password),
            'email' => "sa_{$sufijo}@example.test",
            'rut' => "{$sufijo}-1",
            'nombre1' => 'Super',
            'apellido1' => 'Admin',
            'esta_activo' => true,
            'fecha_verificacion_email' => now(),
        ]);

        $permisoWildcard = Permiso::firstOrCreate(['slug' => '*'], ['nombre' => 'Wildcard']);
        $contextoGlobal = app(GlobalContextService::class)->getContextId();

        UsuarioPermisoEspecial::create([
            'id_usuario' => $superadmin->id_usuario,
            'id_permiso' => $permisoWildcard->id_permiso,
            'id_contexto' => $contextoGlobal,
            'esta_permitido' => true,
            'puede_delegar' => true,
            'fecha_fin_planificada' => now()->addYears(10),
            'creado_por' => $superadmin->id_usuario,
            'esta_activo' => true,
        ]);
    } else {
        $superadmin->passhash = Hash::make($password);
        $superadmin->fecha_cambio_passhash = now();
        $superadmin->fecha_verificacion_email = now();
        $superadmin->save();
    }

    return $superadmin;
}

function crearAdminRegular(string $password = 'password-admin-regular'): Usuario
{
    $sufijo = random_int(1000000, 9999999);
    $admin = Usuario::create([
        'username' => "admin_{$sufijo}",
        'passhash' => Hash::make($password),
        'email' => "admin_{$sufijo}@example.test",
        'rut' => "{$sufijo}-2",
        'nombre1' => 'Admin',
        'apellido1' => 'Regular',
        'esta_activo' => true,
        'fecha_verificacion_email' => now(),
    ]);

    $admin->fecha_cambio_passhash = now();
    $admin->save();

    $rolAdmin = Rol::firstOrCreate(['nombre' => 'Administrador'], ['creado_por' => 1]);
    $permisoEditar = Permiso::where('slug', 'usuarios:editar')->first();

    DB::table('usuario.asignacion_rol_permiso')->insertOrIgnore([
        'id_rol' => $rolAdmin->id_rol,
        'id_permiso' => $permisoEditar->id_permiso,
        'puede_delegar_permiso' => false,
    ]);

    $superadmin = Usuario::where('username', 'superadmin')->first();
    (new RoleAssignmentBuilder($admin, $rolAdmin, $superadmin))->inGlobalContext()->save();

    return $admin;
}

function crearUsuarioParaReset(array $datos = []): Usuario
{
    $sufijo = random_int(1000000, 9999999);
    $fechaCambio = array_key_exists('fecha_cambio_passhash', $datos)
        ? $datos['fecha_cambio_passhash']
        : now();
    unset($datos['fecha_cambio_passhash']);

    $usuario = Usuario::create(array_merge([
        'username' => "reset_target_{$sufijo}",
        'passhash' => Hash::make('clave_anterior_segura_123'),
        'email' => "reset_target_{$sufijo}@example.test",
        'rut' => "{$sufijo}-8",
        'nombre1' => 'Estudiante',
        'apellido1' => 'Prueba',
        'esta_activo' => true,
        'fecha_verificacion_email' => now(),
    ], $datos));

    $usuario->fecha_cambio_passhash = $fechaCambio;
    $usuario->save();

    return $usuario;
}

it('resetea con éxito la contraseña al cuerpo del RUT, anula fecha_cambio_passhash, elimina sesiones y emite flash', function () {
    $superadmin = getSuperAdminAutenticado('admin-pass-123');
    $target = crearUsuarioParaReset([
        'rut' => '19876543-2',
        'fecha_cambio_passhash' => now(),
    ]);

    config(['session.driver' => 'database', 'session.table' => 'sessions']);
    DB::table('sessions')->insert([
        'id' => 'test-session-id-' . random_int(10000, 99999),
        'user_id' => $target->id_usuario,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestBrowser',
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    $response = $this->actingAs($superadmin)->post(route('admin.usuarios.set-default-password', $target), [
        'current_password' => 'admin-pass-123',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    $target->refresh();
    expect(Hash::check('19876543', $target->passhash))->toBeTrue()
        ->and($target->fecha_cambio_passhash)->toBeNull()
        ->and($target->token_recuerdame_sesion)->toBeNull();

    $sesionesRestantes = DB::table('sessions')->where('user_id', $target->id_usuario)->count();
    expect($sesionesRestantes)->toBe(0);
});

it('obliga a cambiar la contraseña en el próximo inicio de sesión tras el reseteo', function () {
    $superadmin = getSuperAdminAutenticado('admin-pass-123');
    $target = crearUsuarioParaReset([
        'rut' => '18234567-8',
        'fecha_cambio_passhash' => now(),
    ]);

    expect($target->debeCambiarPassword())->toBeFalse();

    $this->actingAs($superadmin)->post(route('admin.usuarios.set-default-password', $target), [
        'current_password' => 'admin-pass-123',
    ]);

    $target->refresh();
    expect($target->fecha_cambio_passhash)->toBeNull()
        ->and($target->motivoCambioPasswordObligatorio())->toBe(Usuario::CAMBIO_PASSWORD_PRIMER_INGRESO)
        ->and($target->debeCambiarPassword())->toBeTrue();
});

it('falla con 422 si falta current_password o si es incorrecta', function () {
    $superadmin = getSuperAdminAutenticado('admin-pass-123');
    $target = crearUsuarioParaReset(['rut' => '17234567-9']);

    // Sin current_password
    $responseSinClave = $this->actingAs($superadmin)->post(route('admin.usuarios.set-default-password', $target), []);
    $responseSinClave->assertSessionHasErrors('current_password');

    // Con clave incorrecta
    $responseClaveErronea = $this->actingAs($superadmin)->post(route('admin.usuarios.set-default-password', $target), [
        'current_password' => 'clave-erronea-xyz',
    ]);
    $responseClaveErronea->assertSessionHasErrors('current_password');
});

it('rechaza con 403 si un admin no-SuperAdmin intenta resetear la contraseña de un SuperAdmin', function () {
    $adminRegular = crearAdminRegular('admin-reg-123');
    $superadmin = getSuperAdminAutenticado();

    $response = $this->actingAs($adminRegular)->post(route('admin.usuarios.set-default-password', $superadmin), [
        'current_password' => 'admin-reg-123',
    ]);

    $response->assertStatus(403);
});

it('rechaza con 302 o 401 si no hay usuario autenticado', function () {
    $target = crearUsuarioParaReset();

    $response = $this->post(route('admin.usuarios.set-default-password', $target), [
        'current_password' => 'cualquiera',
    ]);

    // Como está bajo middleware 'auth', redirige a login
    $response->assertRedirect('/login');
});

it('falla con 404 si el usuario objetivo no existe', function () {
    $superadmin = getSuperAdminAutenticado('admin-pass-123');

    $response = $this->actingAs($superadmin)->post('/admin/usuarios/999999999/set-default-password', [
        'current_password' => 'admin-pass-123',
    ]);

    $response->assertStatus(404);
});

it('aborta con 422 controlado si el usuario objetivo no tiene RUT o el cuerpo queda vacío', function () {
    $superadmin = getSuperAdminAutenticado('admin-pass-123');

    // 1. RUT en BD con formato que no produce cuerpo numérico válido (ej: -sufijo)
    $sufijo = random_int(1000000, 9999999);
    $targetSinCuerpo = crearUsuarioParaReset(['rut' => "-{$sufijo}"]);
    $response = $this->actingAs($superadmin)->post(route('admin.usuarios.set-default-password', $targetSinCuerpo), [
        'current_password' => 'admin-pass-123',
    ]);
    $response->assertStatus(422);

    // 2. Modelo sin RUT (atributo nulo en memoria)
    $targetSinRut = crearUsuarioParaReset();
    $targetSinRut->rut = null;

    $controller = app(UsuarioController::class);
    $request = Request::create(
        route('admin.usuarios.set-default-password', $targetSinRut),
        'POST',
        ['current_password' => 'admin-pass-123']
    );
    $request->setUserResolver(fn () => $superadmin);

    try {
        $controller->setDefaultPassword($request, $targetSinRut);
        test()->fail('Se esperaba HttpException 422');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toContain('RUT');
    }
});

it('extrae correctamente la contraseña según diferentes formatos de RUT', function () {
    $superadmin = getSuperAdminAutenticado('admin-pass-123');

    // RUT con guion y dígito verificador K
    $targetK = crearUsuarioParaReset(['rut' => '9876543-K']);
    $this->actingAs($superadmin)->post(route('admin.usuarios.set-default-password', $targetK), [
        'current_password' => 'admin-pass-123',
    ]);
    expect(Hash::check('9876543', $targetK->fresh()->passhash))->toBeTrue();

    // RUT con formato plano 123456789
    $targetPlano = crearUsuarioParaReset(['rut' => '12345678-9']);
    $this->actingAs($superadmin)->post(route('admin.usuarios.set-default-password', $targetPlano), [
        'current_password' => 'admin-pass-123',
    ]);
    expect(Hash::check('12345678', $targetPlano->fresh()->passhash))->toBeTrue();
});
