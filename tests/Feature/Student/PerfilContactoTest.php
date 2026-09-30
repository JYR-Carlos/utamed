<?php

/*
 * T10: el estudiante mantiene su correo personal, celular y redes sociales.
 * Sólo esos tres campos son editables y nunca se exponen a otros alumnos.
 *
 * Requiere la migración 07_add_contacto_to_estudiante_table.
 */

use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function alumnoConPerfil(): array
{
    $user = Usuario::factory()->create();
    $user->forceFill(['fecha_cambio_passhash' => now()])->save();
    $estudiante = Estudiante::factory()->create([
        'id_usuario' => $user->id_usuario,
        'agno_ingreso' => 2024,
    ]);

    return [$user->fresh(), $estudiante];
}

test('el contacto personal no se serializa con el modelo', function () {
    $estudiante = new Estudiante();
    $estudiante->forceFill([
        'correo_personal' => 'alguien@gmail.com',
        'celular' => '+56 9 1234 5678',
        'redes_sociales' => ['instagram' => 'https://instagram.com/alguien'],
    ]);

    expect($estudiante->toArray())
        ->not->toHaveKeys(['correo_personal', 'celular', 'redes_sociales']);
});

test('el estudiante guarda su contacto personal', function () {
    [$user, $estudiante] = alumnoConPerfil();

    $this->actingAs($user)
        ->from('/estudiante/perfil')
        ->patch('/estudiante/perfil/contacto', [
            'correo_personal' => 'alumno.t10@gmail.com',
            'celular' => '+56 9 1234 5678',
            'redes_sociales' => [
                'linkedin' => 'https://www.linkedin.com/in/alumno-t10',
                'youtube' => '',
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/estudiante/perfil');

    $contacto = $estudiante->fresh()->contacto();

    expect($contacto['correo_personal'])->toBe('alumno.t10@gmail.com')
        ->and($contacto['celular'])->toBe('+56 9 1234 5678')
        ->and($contacto['redes_sociales']['linkedin'])->toBe('https://www.linkedin.com/in/alumno-t10')
        ->and($contacto['redes_sociales']['youtube'])->toBeNull();
});

test('el endpoint no toca los datos institucionales', function () {
    [$user, $estudiante] = alumnoConPerfil();
    $emailOriginal = $user->email;

    $this->actingAs($user)
        ->patch('/estudiante/perfil/contacto', [
            'celular' => '+56 9 1234 5678',
            'agno_ingreso' => 1999,
            'id_carrera' => 999999,
            'email' => 'hackeado@gmail.com',
        ])
        ->assertSessionHasNoErrors();

    expect($estudiante->fresh()->agno_ingreso)->toBe(2024)
        ->and($user->fresh()->email)->toBe($emailOriginal);
});

test('rechaza enlaces que no son de la red indicada y redes no soportadas', function () {
    [$user] = alumnoConPerfil();

    $this->actingAs($user)
        ->patch('/estudiante/perfil/contacto', [
            'redes_sociales' => [
                'instagram' => 'https://sitio-cualquiera.com/perfil',
                'tiktok' => 'https://www.tiktok.com/@alguien',
            ],
        ])
        ->assertSessionHasErrors(['redes_sociales', 'redes_sociales.instagram']);
});

test('rechaza un celular o correo con formato inválido', function () {
    [$user] = alumnoConPerfil();

    $this->actingAs($user)
        ->patch('/estudiante/perfil/contacto', [
            'correo_personal' => 'no-es-un-correo',
            'celular' => 'llámame',
        ])
        ->assertSessionHasErrors(['correo_personal', 'celular']);
});
