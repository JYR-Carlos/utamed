<?php

use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Búsqueda de usuarios del buscador de administración (Usuario::scopeBuscar).
 *
 * Los casos son los que fallaban con la búsqueda anterior —columna a columna
 * contra el término completo—: teclear el nombre completo de alguien que existe,
 * teclearlo sin acentos, o teclear el RUT con puntos.
 *
 * La columna guarda un solo formato ("23671848-4", ver App\Support\Rut), pero el
 * término lo escribe una persona: puede venir con puntos, sin guion o pegado de
 * otro sistema, y tiene que encontrar igual.
 */
uses(DatabaseTransactions::class);

/** Crea un usuario de prueba; la transacción del test lo revierte al terminar. */
function usuarioBuscable(array $datos = []): Usuario
{
    $sufijo = random_int(100000, 999999);

    return Usuario::create(array_merge([
        'username' => "tst{$sufijo}",
        'passhash' => 'x',
        'email' => "tst{$sufijo}@example.test",
        'rut' => "9{$sufijo}-3",
        'nombre1' => 'Íñigo',
        'nombre2' => 'Andrés',
        'apellido1' => 'Rodríguez',
        'apellido2' => 'Gutiérrez',
        'esta_activo' => true,
    ], $datos));
}

/** ¿La búsqueda encuentra a este usuario concreto? */
function encuentra(string $termino, Usuario $usuario): bool
{
    return Usuario::query()
        ->buscar($termino)
        ->whereKey($usuario->id_usuario)
        ->exists();
}

it('encuentra por nombre completo, que es como se busca a una persona', function () {
    $u = usuarioBuscable();

    expect(encuentra('Íñigo Rodríguez', $u))->toBeTrue()
        ->and(encuentra('Rodríguez Íñigo', $u))->toBeTrue()          // orden indistinto
        ->and(encuentra('Íñigo Andrés Rodríguez Gutiérrez', $u))->toBeTrue();
});

it('ignora acentos y mayúsculas', function () {
    $u = usuarioBuscable();

    expect(encuentra('inigo rodriguez', $u))->toBeTrue()
        ->and(encuentra('RODRIGUEZ', $u))->toBeTrue();
});

it('encuentra el RUT esté como esté escrito el término', function () {
    // El modelo normaliza al guardar: ambos quedan sin puntos y con guion.
    $conPuntos = usuarioBuscable(['rut' => '23.671.848-4', 'username' => 'tstpuntos']);
    $sinPuntos = usuarioBuscable(['rut' => '18167586-1', 'username' => 'tstplano']);

    expect($conPuntos->rut)->toBe('23671848-4')
        ->and(encuentra('23671848-4', $conPuntos))->toBeTrue()
        ->and(encuentra('23.671.848-4', $conPuntos))->toBeTrue()
        ->and(encuentra('236718484', $conPuntos))->toBeTrue()
        ->and(encuentra('18.167.586-1', $sinPuntos))->toBeTrue()
        ->and(encuentra('18167586-1', $sinPuntos))->toBeTrue();
});

it('encuentra por username y por email', function () {
    $u = usuarioBuscable(['username' => 'jperez99', 'email' => 'j.perez@utamed.test']);

    expect(encuentra('jperez99', $u))->toBeTrue()
        ->and(encuentra('j.perez@utamed.test', $u))->toBeTrue();
});

it('exige que todas las palabras coincidan', function () {
    $u = usuarioBuscable(['nombre1' => 'Íñigo', 'apellido1' => 'Rodríguez']);

    expect(encuentra('Íñigo Zurita', $u))->toBeFalse();
});

it('no filtra cuando el término viene vacío', function () {
    $u = usuarioBuscable();

    expect(encuentra('', $u))->toBeTrue()
        ->and(encuentra('   ', $u))->toBeTrue();
});

it('trata los comodines de LIKE como texto literal', function () {
    $u = usuarioBuscable();

    // Sin escapar, '%' devolvería la tabla entera.
    expect(encuentra('%', $u))->toBeFalse()
        ->and(encuentra('_', $u))->toBeFalse();
});

it('filtra el listado del administrador por nombre completo', function () {
    $admin = Usuario::whereHas(
        'rolesAsignados',
        fn($q) => $q->whereIn('rol.nombre', Usuario::ROLES_ADMINISTRATIVOS)
            ->where('usuario_rol_asignacion.esta_activo', true)
            ->where('usuario_rol_asignacion.fue_eliminado', false)
    )->first();

    if (!$admin) {
        $this->markTestSkipped('La base sembrada no tiene ningún usuario administrativo.');
    }

    // El listado de administradores excluye a docentes y estudiantes, así que el
    // usuario de prueba (sin perfil) cae justo en esa pestaña.
    $u = usuarioBuscable(['nombre1' => 'Ignacia', 'apellido1' => 'Zúñiga']);

    $ruts = fn(string $search) => collect(
        $this->actingAs($admin)
            ->getJson('/admin/usuarios?tipo=administrador&search=' . urlencode($search))
            ->assertOk()
            ->json('data')
    )->pluck('usuario.rut');

    expect($ruts('Ignacia Zúñiga'))->toContain($u->rut)
        ->and($ruts('ignacia zuniga'))->toContain($u->rut)
        ->and($ruts($u->rut))->toContain($u->rut);
});
