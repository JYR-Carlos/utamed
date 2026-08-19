<?php

use App\Models\Usuario\Usuario;
use App\Services\Sso\SgeqSsoService;

/**
 * SSO hacia SGEQ: quién puede entrar, con qué rol y qué lleva el token.
 *
 * No toca la base de datos ni se apoya en Eloquent: el usuario de prueba es una
 * subclase anónima de Usuario que declara sus propios campos y su propia respuesta
 * a esAdministradorGlobal(), y las relaciones son objetos simples.
 *
 * Esa independencia es a propósito. tests/Stubs/UsuarioStub.php declara otra clase
 * llamada App\Models\Usuario\Usuario —sin Eloquent detrás— y, según el orden en que
 * la suite cargue los archivos, esta subclase termina heredando de una o de la otra.
 * Al no necesitar nada del padre, el resultado es el mismo en ambos casos.
 */

/** Usuario de prueba sin base de datos detrás. */
function usuarioSso(bool $admin = false, ?int $idCarrera = null, bool $activo = true, ?string $rut = '12345678-9'): Usuario
{
    $usuario = new class extends Usuario
    {
        public bool $esAdmin = false;

        // Propiedades reales, no atributos de Eloquent: es lo que hace que el
        // objeto se comporte igual herede de donde herede.
        public $id_usuario = 7;
        public $esta_activo = true;
        public $rut;
        public $email;
        public $nombre1;
        public $apellido1;
        public $apellido2;
        public $estudiante;

        public function esAdministradorGlobal(): bool
        {
            return $this->esAdmin;
        }
    };

    $usuario->esAdmin = $admin;
    $usuario->esta_activo = $activo;
    $usuario->rut = $rut;
    $usuario->email = 'ana.perez@uta.cl';
    $usuario->nombre1 = 'Ana';
    $usuario->apellido1 = 'Pérez';
    $usuario->apellido2 = 'Soto';

    $usuario->estudiante = $idCarrera === null ? null : (object) [
        'id_carrera' => $idCarrera,
        'carrera' => (object) ['nombre' => 'Diseño Multimedia'],
    ];

    return $usuario;
}

/**
 * Verifica la firma con la clave pública y devuelve los claims.
 *
 * @return array<string, mixed>
 */
function abrirToken(string $jwt, string $clavePublica): array
{
    [$header, $payload, $firma] = explode('.', $jwt);

    $desdeB64Url = fn (string $v) => base64_decode(strtr($v, '-_', '+/') . str_repeat('=', (4 - strlen($v) % 4) % 4));

    expect(json_decode($desdeB64Url($header), true))->toBe(['alg' => 'RS256', 'typ' => 'JWT']);

    $valida = openssl_verify(
        "{$header}.{$payload}",
        $desdeB64Url($firma),
        $clavePublica,
        OPENSSL_ALGO_SHA256
    );

    expect($valida)->toBe(1, 'La firma del token no valida contra la clave pública');

    return json_decode($desdeB64Url($payload), true);
}

/**
 * Genera un par RSA efímero, deja la privada en un archivo temporal apuntado por
 * la configuración y devuelve la pública.
 *
 * En Windows el openssl de PHP no encuentra su openssl.cnf por sí solo y no puede
 * *generar* claves (leerlas sí), así que se le pasa el que viene con la build.
 * Donde tampoco esté, el test se salta en vez de fallar: lo que faltaría es el
 * entorno, no el código.
 */
function clavePublicaDePrueba(): string
{
    $args = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
    $cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';

    if (is_file($cnf)) {
        $args['config'] = $cnf;
    }

    $par = @openssl_pkey_new($args);

    if ($par === false) {
        test()->markTestSkipped('openssl no puede generar claves en este entorno (falta openssl.cnf).');
    }

    @openssl_pkey_export($par, $privada, null, $args);

    $ruta = tempnam(sys_get_temp_dir(), 'sso-test-');
    file_put_contents($ruta, $privada);

    test()->rutaClave = $ruta;
    config()->set('services.sgeq.private_key_path', $ruta);

    return openssl_pkey_get_details($par)['key'];
}

beforeEach(function () {
    $this->rutaClave = null;

    config()->set('services.sgeq', [
        'url' => 'http://sgeq.test/',
        'issuer' => 'utamed',
        'audience' => 'sgeq',
        'private_key_path' => '/clave/que/se/define/en/cada/test.pem',
        'ttl' => 60,
        'carreras' => ['1'],
    ]);

    $this->sso = new SgeqSsoService();
});

afterEach(function () {
    if ($this->rutaClave !== null) {
        @unlink($this->rutaClave);
    }
});

// ============================================================================
// Quién puede entrar
// ============================================================================

it('deja entrar al administrador como ADMIN', function () {
    expect($this->sso->resolverRol(usuarioSso(admin: true)))->toBe('ADMIN');
});

it('deja entrar como ALUMNO al estudiante de una carrera habilitada', function () {
    expect($this->sso->resolverRol(usuarioSso(idCarrera: 1)))->toBe('ALUMNO');
});

it('no deja entrar al estudiante de otra carrera', function () {
    expect($this->sso->resolverRol(usuarioSso(idCarrera: 2)))->toBeNull();
});

it('no deja entrar a quien no es ni administrador ni estudiante', function () {
    // Docentes, ayudantes, jefaturas: no usan SGEQ.
    expect($this->sso->resolverRol(usuarioSso()))->toBeNull();
});

it('no deja entrar a una cuenta desactivada, aunque sea administradora', function () {
    expect($this->sso->resolverRol(usuarioSso(admin: true, activo: false)))->toBeNull();
});

it('sin carreras configuradas no entra ningún estudiante', function () {
    config()->set('services.sgeq.carreras', []);

    expect($this->sso->resolverRol(usuarioSso(idCarrera: 1)))->toBeNull();
});

it('el administrador entra aunque no tenga carrera', function () {
    // Los administradores son globales; el filtro de carrera es sólo para alumnos.
    config()->set('services.sgeq.carreras', []);

    expect($this->sso->resolverRol(usuarioSso(admin: true)))->toBe('ADMIN');
});

// ============================================================================
// Qué lleva el token
// ============================================================================

it('firma un token que valida con la clave pública y trae la identidad', function () {
    $publica = clavePublicaDePrueba();

    $jwt = $this->sso->emitirToken(usuarioSso(idCarrera: 1), 'ALUMNO');

    $claims = abrirToken($jwt, $publica);

    expect($claims['iss'])->toBe('utamed')
        ->and($claims['aud'])->toBe('sgeq')
        ->and($claims['rut'])->toBe('12345678-9')
        ->and($claims['sub'])->toBe('12345678-9')
        ->and($claims['email'])->toBe('ana.perez@uta.cl')
        ->and($claims['nombre1'])->toBe('Ana')
        ->and($claims['apellido1'])->toBe('Pérez')
        ->and($claims['apellido2'])->toBe('Soto')
        ->and($claims['rol'])->toBe('ALUMNO')
        ->and($claims['carrera'])->toBe('Diseño Multimedia');
});

it('normaliza el RUT antes de firmarlo', function () {
    // SGEQ busca a la persona por este valor: si sale con puntos, no la encuentra
    // y le crea un duplicado.
    $publica = clavePublicaDePrueba();

    $jwt = $this->sso->emitirToken(usuarioSso(idCarrera: 1, rut: '12.345.678-9'), 'ALUMNO');

    expect(abrirToken($jwt, $publica)['rut'])->toBe('12345678-9');
});

it('el token expira en menos de un minuto', function () {
    $publica = clavePublicaDePrueba();

    $claims = abrirToken($this->sso->emitirToken(usuarioSso(admin: true), 'ADMIN'), $publica);

    expect($claims['exp'] - $claims['iat'])->toBe(60)
        ->and($claims['exp'])->toBeGreaterThan(now()->timestamp);
});

it('usa un jti distinto en cada emisión', function () {
    // Es lo que le permite a SGEQ rechazar un token reutilizado.
    $publica = clavePublicaDePrueba();
    $usuario = usuarioSso(admin: true);

    $primero = abrirToken($this->sso->emitirToken($usuario, 'ADMIN'), $publica)['jti'];
    $segundo = abrirToken($this->sso->emitirToken($usuario, 'ADMIN'), $publica)['jti'];

    expect($primero)->not->toBe($segundo);
});

it('no firma nada si el usuario no tiene RUT', function () {
    $this->sso->emitirToken(usuarioSso(admin: true, rut: null), 'ADMIN');
})->throws(RuntimeException::class, 'no tiene RUT');

it('avisa cuando falta la clave privada', function () {
    config()->set('services.sgeq.private_key_path', '/ruta/que/no/existe.pem');

    $this->sso->emitirToken(usuarioSso(admin: true), 'ADMIN');
})->throws(RuntimeException::class, 'clave privada');

it('avisa cuando falta la URL de SGEQ', function () {
    config()->set('services.sgeq.url', '');

    $this->sso->urlDeConsumo('token');
})->throws(RuntimeException::class, 'services.sgeq.url');

// ============================================================================
// A dónde se manda al navegador
// ============================================================================

it('arma la URL de consumo de SGEQ', function () {
    expect($this->sso->urlDeConsumo('abc.def.ghi'))
        ->toBe('http://sgeq.test/auth/sso/consume?token=abc.def.ghi');
});
