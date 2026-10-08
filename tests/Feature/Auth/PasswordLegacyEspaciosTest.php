<?php

/*
 * Claves con espacios en los extremos guardadas antes de T54 (o desde una
 * planilla de importación). TrimStrings recorta la contraseña al compararla,
 * así que esas claves dejaban al usuario fuera. Ahora:
 *
 * - TrimStrings conserva la clave original en un atributo de la request.
 * - El login (FortifyServiceProvider) prueba la original si la recortada no
 *   coincide y, si entra, vuelve a guardar el hash recortado sin mover
 *   fecha_cambio_passhash.
 * - La importación masiva (UsuarioController::mapearFila) recorta la clave
 *   de la planilla antes de hashearla.
 */

use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Support\DataGenerators\ChileanNameGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(DatabaseTransactions::class);

/**
 * Usuario cuya clave quedó guardada con los espacios incluidos, como antes de T54.
 */
function usuarioConClaveAntigua(string $clave): Usuario
{
    $user = Usuario::factory()->create();
    $user->forceFill([
        'passhash' => Hash::make($clave),
        'fecha_cambio_passhash' => now()->subDays(3)->startOfSecond(),
    ])->save();

    return $user->refresh();
}

test('una clave antigua con espacios en los extremos permite entrar escribiéndola tal cual', function () {
    $user = usuarioConClaveAntigua(' Clave1! ');
    $fechaAntes = $user->fecha_cambio_passhash;

    $this->post('/login', [
        'email' => $user->rut,
        'password' => ' Clave1! ',
    ]);

    $this->assertAuthenticatedAs($user);

    $user->refresh();

    // El hash queda recortado: ahora valida la clave sin espacios y ya no la original.
    expect(Hash::check('Clave1!', $user->passhash))->toBeTrue()
        ->and(Hash::check(' Clave1! ', $user->passhash))->toBeFalse()
        // No es un cambio voluntario de clave: la fecha no se mueve.
        ->and($user->fecha_cambio_passhash?->toDateTimeString())
        ->toBe($fechaAntes?->toDateTimeString());
});

test('tras normalizarse, la clave antigua también entra sin los espacios', function () {
    $user = usuarioConClaveAntigua(' Clave1! ');

    $this->post('/login', [
        'email' => $user->rut,
        'password' => ' Clave1! ',
    ]);
    $this->assertAuthenticatedAs($user);

    $this->post('/logout');
    $this->assertGuest();

    $this->post('/login', [
        'email' => $user->rut,
        'password' => 'Clave1!',
    ]);
    $this->assertAuthenticatedAs($user);
});

test('una clave incorrecta con espacios no entra ni toca el hash guardado', function () {
    $user = usuarioConClaveAntigua(' Clave1! ');
    $hashAntes = $user->passhash;

    $this->post('/login', [
        'email' => $user->rut,
        'password' => ' Otra9! ',
    ]);

    $this->assertGuest();
    expect($user->refresh()->passhash)->toBe($hashAntes);
});

test('un usuario con clave normal sigue entrando y su hash no se reescribe', function () {
    $user = Usuario::factory()->create();
    $hashAntes = $user->passhash;

    $this->post('/login', [
        'email' => $user->rut,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->passhash)->toBe($hashAntes);
});

test('la importación masiva guarda recortada la clave de la planilla', function () {
    // Fuera de local la política de claves consulta HIBP; se responde "no filtrada".
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

    $admin = Usuario::factory()->create();
    $admin->forceFill(['fecha_cambio_passhash' => now()])->save();
    $rolSuperAdmin = Rol::firstOrCreate(['nombre' => 'SuperAdmin'], ['creado_por' => $admin->id_usuario]);
    UsuarioRolAsignacion::create([
        'id_usuario' => $admin->id_usuario,
        'id_rol' => $rolSuperAdmin->id_rol,
        'id_contexto' => 1,
        'asignado_por' => $admin->id_usuario,
        'fecha_inicio_planificada' => now()->subMinute(),
        'fecha_fin_planificada' => now()->addYears(100),
        'esta_activo' => true,
        'fue_eliminado' => false,
        'creado_por' => $admin->id_usuario,
    ]);

    do {
        $rut = ChileanNameGenerator::generarRUT();
    } while (Usuario::where('rut', $rut)->exists());
    $username = 'imp' . substr(md5(uniqid('', true)), 0, 7);

    // Planilla con el formato de UsuarioController::columnasImportacion('administrador').
    $hoja = new Spreadsheet();
    $hoja->getActiveSheet()->fromArray([
        ['RUT', 'Primer nombre', 'Segundo nombre', 'Primer apellido', 'Segundo apellido', 'Email', 'Usuario', 'Contraseña'],
        [$rut, 'Importado', null, 'Prueba', null, null, $username, ' Import9! '],
    ]);
    $base = tempnam(sys_get_temp_dir(), 'imp');
    $ruta = $base . '.xlsx';
    (new Xlsx($hoja))->save($ruta);
    @unlink($base);

    $archivo = new UploadedFile(
        $ruta,
        'usuarios.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $this->actingAs($admin)
        ->post('/admin/usuarios/importar', ['file' => $archivo, 'tipo' => 'administrador'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $importado = Usuario::where('username', $username)->first();

    expect($importado)->not->toBeNull()
        ->and(Hash::check('Import9!', $importado->passhash))->toBeTrue()
        ->and(Hash::check(' Import9! ', $importado->passhash))->toBeFalse();

    @unlink($ruta);
});
