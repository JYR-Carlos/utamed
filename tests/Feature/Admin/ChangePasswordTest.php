<?php

use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Services\Authorization\GlobalContextService;
use App\Services\Authorization\RoleAssignmentBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

function crearSuperAdminParaCambioPassword(string $password = 'admin-pass-123'): Usuario
{
    $superadmin = Usuario::where('username', 'superadmin')->first();
    if (! $superadmin) {
        $sufijo = random_int(1000000, 9999999);
        $superadmin = Usuario::create([
            'username' => "sa_cp_{$sufijo}",
            'passhash' => Hash::make($password),
            'email' => "sa_cp_{$sufijo}@example.test",
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

function crearAdminRegularParaCambioPassword(string $password = 'admin-reg-123'): Usuario
{
    $sufijo = random_int(1000000, 9999999);
    $admin = Usuario::create([
        'username' => "admin_cp_{$sufijo}",
        'passhash' => Hash::make($password),
        'email' => "admin_cp_{$sufijo}@example.test",
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

    $superadmin = Usuario::where('username', 'superadmin')->first() ?? $admin;
    (new RoleAssignmentBuilder($admin, $rolAdmin, $superadmin))->inGlobalContext()->save();

    return $admin;
}

function crearUsuarioObjetivoCambioPassword(): Usuario
{
    $sufijo = random_int(1000000, 9999999);
    $usuario = Usuario::create([
        'username' => "cp_target_{$sufijo}",
        'passhash' => Hash::make('ClaveAnterior!123'),
        'email' => "cp_target_{$sufijo}@example.test",
        'rut' => "{$sufijo}-8",
        'nombre1' => 'Estudiante',
        'apellido1' => 'Prueba',
        'esta_activo' => true,
        'fecha_verificacion_email' => now(),
    ]);

    $usuario->fecha_cambio_passhash = null;
    $usuario->token_recuerdame_sesion = 'token-viejo-xyz';
    $usuario->save();

    return $usuario;
}

it('cambia con éxito la contraseña de un usuario sin requerir current_password y cierra sus sesiones', function () {
    $superadmin = crearSuperAdminParaCambioPassword();
    $target = crearUsuarioObjetivoCambioPassword();

    config(['session.driver' => 'database', 'session.table' => 'sessions']);
    DB::table('sessions')->insert([
        'id' => 'test-cp-session-' . random_int(10000, 99999),
        'user_id' => $target->id_usuario,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestBrowser',
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    $nuevaClave = 'NuevaClave!2026';

    $response = $this->actingAs($superadmin)->post(route('admin.usuarios.change-password', $target), [
        'password' => $nuevaClave,
        'password_confirmation' => $nuevaClave,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    $target->refresh();
    expect(Hash::check($nuevaClave, $target->passhash))->toBeTrue()
        ->and($target->fecha_cambio_passhash)->not->toBeNull()
        ->and($target->token_recuerdame_sesion)->toBeNull();

    $sesionesRestantes = DB::table('sessions')->where('user_id', $target->id_usuario)->count();
    expect($sesionesRestantes)->toBe(0);
});

it('falla con 422 si falta confirmación o no coincide con la nueva contraseña', function () {
    $superadmin = crearSuperAdminParaCambioPassword();
    $target = crearUsuarioObjetivoCambioPassword();

    $response = $this->actingAs($superadmin)->post(route('admin.usuarios.change-password', $target), [
        'password' => 'NuevaClave!2026',
        'password_confirmation' => 'OtraClave!2026',
    ]);

    $response->assertSessionHasErrors('password');
});

it('rechaza con 403 si un admin no-SuperAdmin intenta cambiar la contraseña de un SuperAdmin', function () {
    $adminRegular = crearAdminRegularParaCambioPassword();
    $superadmin = crearSuperAdminParaCambioPassword();

    $response = $this->actingAs($adminRegular)->post(route('admin.usuarios.change-password', $superadmin), [
        'password' => 'NuevaClave!2026',
        'password_confirmation' => 'NuevaClave!2026',
    ]);

    $response->assertStatus(403);
});

it('rechaza con redirección al login si no hay usuario autenticado', function () {
    $target = crearUsuarioObjetivoCambioPassword();

    $response = $this->post(route('admin.usuarios.change-password', $target), [
        'password' => 'NuevaClave!2026',
        'password_confirmation' => 'NuevaClave!2026',
    ]);

    $response->assertRedirect('/login');
});
