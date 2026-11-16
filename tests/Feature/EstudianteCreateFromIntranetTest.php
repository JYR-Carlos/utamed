<?php

use App\DTOs\External\AlumnoIntranetData;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\External\VwAlumno;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $facultad = Facultad::firstOrCreate(['nombre' => 'Facultad Test Estudiante Factory'], ['id_contexto' => 1]);
    $departamento = Departamento::firstOrCreate(
        ['nombre' => 'Departamento Test Estudiante Factory'],
        ['id_facultad' => $facultad->id_facultad, 'id_contexto' => 1]
    );
    $this->carrera = Carrera::firstOrCreate(
        ['nombre' => 'Carrera Test Estudiante Factory'],
        ['id_departamento' => $departamento->id_departamento, 'id_contexto' => 1]
    );
});

describe('Estudiante::createFromIntranet() Factory Method', function () {

    test('crea usuario y estudiante a partir de AlumnoIntranetData con transformaciones y password temporal', function () {
        $dto = new AlumnoIntranetData(
            alum_rut: 21148350,
            alum_digito: 'k', // minúscula a propósito
            alum_nombre: '  sui-lan danae  ', // minúsculas y espacios
            alum_apellido_pat: '  lópez  ',
            alum_apellido_mat: '  carmona  '
        );

        $estudiante = Estudiante::createFromIntranet($dto, $this->carrera, 2026);

        expect($estudiante)->toBeInstanceOf(Estudiante::class);
        expect($estudiante->id_carrera)->toBe($this->carrera->id_carrera);
        expect($estudiante->agno_ingreso)->toBe(2026);

        $usuario = $estudiante->usuario;
        expect($usuario)->not->toBeNull();
        expect($usuario->username)->toBe('21148350');
        expect($usuario->rut)->toBe('21148350-K'); // DV normalizado a K mayúscula
        expect($usuario->nombre1)->toBe('SUI-LAN DANAE'); // normalizado a mayúsculas limpias
        expect($usuario->apellido1)->toBe('LÓPEZ');
        expect($usuario->apellido2)->toBe('CARMONA');
        expect($usuario->esta_activo)->toBeTrue();
        expect(Hash::check('21148350', $usuario->passhash))->toBeTrue();
    });

    test('crea estudiante manejando apellido materno nulo', function () {
        $dto = new AlumnoIntranetData(
            alum_rut: 20400071,
            alum_digito: '5',
            alum_nombre: 'MC-KOY ALEJANDRO',
            alum_apellido_pat: 'VARAS',
            alum_apellido_mat: null
        );

        $estudiante = Estudiante::createFromIntranet($dto, $this->carrera);

        expect($estudiante)->toBeInstanceOf(Estudiante::class);
        $usuario = $estudiante->usuario;
        expect($usuario->apellido1)->toBe('VARAS');
        expect($usuario->apellido2)->toBeNull();
    });

    test('crea estudiante a partir de una instancia del modelo VwAlumno', function () {
        $alumno = new VwAlumno([
            'ALUM_RUT'          => 21010922,
            'ALUM_DIGITO'       => '4',
            'ALUM_NOMBRE'       => "  bania   n'kara  ",
            'ALUM_APELLIDO_PAT' => '  peña  ',
            'ALUM_APELLIDO_MAT' => '  choquerive  ',
        ]);

        $estudiante = Estudiante::createFromIntranet($alumno, $this->carrera);

        expect($estudiante)->toBeInstanceOf(Estudiante::class);
        $usuario = $estudiante->usuario;
        expect($usuario->nombre1)->toBe("BANIA N'KARA");
        expect($usuario->apellido1)->toBe('PEÑA');
        expect($usuario->apellido2)->toBe('CHOQUERIVE');
        expect($usuario->rut)->toBe('21010922-4');
    });

    test('crea estudiante a partir de un array asociativo', function () {
        $arrayData = [
            'alum_rut'          => 22445665,
            'alum_digito'       => 'K',
            'alum_nombre'       => 'JANNY KIHARA SU-YIN',
            'alum_apellido_pat' => 'GONZÁLEZ',
            'alum_apellido_mat' => 'SOTO',
        ];

        $estudiante = Estudiante::createFromIntranet($arrayData, $this->carrera);

        expect($estudiante)->toBeInstanceOf(Estudiante::class);
        $usuario = $estudiante->usuario;
        expect($usuario->nombre1)->toBe('JANNY KIHARA SU-YIN');
        expect($usuario->rut)->toBe('22445665-K');
    });

    test('si el usuario ya existe, no lo duplica y devuelve el estudiante', function () {
        $dto = new AlumnoIntranetData(
            alum_rut: 19999999,
            alum_digito: '9',
            alum_nombre: 'PEDRO',
            alum_apellido_pat: 'PÉREZ',
            alum_apellido_mat: 'GÓMEZ'
        );

        $estudiante1 = Estudiante::createFromIntranet($dto, $this->carrera);
        $estudiante2 = Estudiante::createFromIntranet($dto, $this->carrera);

        expect($estudiante1->id_estudiante)->toBe($estudiante2->id_estudiante);
        expect($estudiante1->id_usuario)->toBe($estudiante2->id_usuario);
        expect(Usuario::where('username', '19999999')->count())->toBe(1);
    });

    test('lanza InvalidArgumentException si el RUT está vacío', function () {
        expect(fn() => Estudiante::createFromIntranet(['alum_rut' => null], $this->carrera))
            ->toThrow(\InvalidArgumentException::class, 'El RUT del alumno es requerido para crear un Estudiante desde la Intranet.');
    });

    test('persiste email institucional desde AlumnoIntranetData en usuario.email', function () {
        $dto = new AlumnoIntranetData(
            alum_rut: 23111222,
            alum_digito: '3',
            alum_nombre: 'ESTEBAN',
            alum_apellido_pat: 'QUINTANA',
            alum_apellido_mat: 'SILVA',
            alum_email: '  Esteban.Quintana@ALUMNOS.UTA.CL  '
        );

        $estudiante = Estudiante::createFromIntranet($dto, $this->carrera);

        expect($estudiante->usuario->email)->toBe('esteban.quintana@alumnos.uta.cl');
    });

    test('persiste email institucional desde VwAlumno en usuario.email', function () {
        $alumno = new VwAlumno([
            'ALUM_RUT'          => 23222333,
            'ALUM_DIGITO'       => '4',
            'ALUM_NOMBRE'       => 'CARLA',
            'ALUM_APELLIDO_PAT' => 'MORA',
            'ALUM_APELLIDO_MAT' => 'PINO',
            'ALUM_EMAIL'        => 'Carla.Mora@Alumnos.UTA.CL',
        ]);

        $estudiante = Estudiante::createFromIntranet($alumno, $this->carrera);

        expect($estudiante->usuario->email)->toBe('carla.mora@alumnos.uta.cl');
    });

    test('politica de autoridad: sobreescribe autoritativamente el email si intranet provee uno valido', function () {
        // Alumno preexistente con email previo o desactualizado
        $dtoInicial = new AlumnoIntranetData(
            alum_rut: 23333444,
            alum_digito: '5',
            alum_nombre: 'DANIEL',
            alum_apellido_pat: 'CASTRO',
            alum_apellido_mat: 'VEGA',
            alum_email: 'daniel.antiguo@uta.cl'
        );
        $estudianteInicial = Estudiante::createFromIntranet($dtoInicial, $this->carrera);
        expect($estudianteInicial->usuario->email)->toBe('daniel.antiguo@uta.cl');

        // Nueva sincronización con email oficial institucional actualizado
        $dtoActualizado = new AlumnoIntranetData(
            alum_rut: 23333444,
            alum_digito: '5',
            alum_nombre: 'DANIEL',
            alum_apellido_pat: 'CASTRO',
            alum_apellido_mat: 'VEGA',
            alum_email: 'daniel.oficial@uta.cl'
        );
        $estudianteActualizado = Estudiante::createFromIntranet($dtoActualizado, $this->carrera);

        expect($estudianteActualizado->id_estudiante)->toBe($estudianteInicial->id_estudiante);
        $usuarioRefrescado = Usuario::where('username', '23333444')->first();
        expect($usuarioRefrescado->email)->toBe('daniel.oficial@uta.cl');
    });

    test('politica de autoridad: preserva email existente si el registro entrante viene con email null', function () {
        $dtoInicial = new AlumnoIntranetData(
            alum_rut: 23444555,
            alum_digito: '6',
            alum_nombre: 'ANDREA',
            alum_apellido_pat: 'ROJAS',
            alum_apellido_mat: 'LUNA',
            alum_email: 'andrea.rojas@uta.cl'
        );
        $estudianteInicial = Estudiante::createFromIntranet($dtoInicial, $this->carrera);
        expect($estudianteInicial->usuario->email)->toBe('andrea.rojas@uta.cl');

        // Sincronización entrante sin email (null o vacío)
        $dtoSinEmail = new AlumnoIntranetData(
            alum_rut: 23444555,
            alum_digito: '6',
            alum_nombre: 'ANDREA',
            alum_apellido_pat: 'ROJAS',
            alum_apellido_mat: 'LUNA',
            alum_email: null
        );
        $estudianteActualizado = Estudiante::createFromIntranet($dtoSinEmail, $this->carrera);

        $usuarioRefrescado = Usuario::where('username', '23444555')->first();
        expect($usuarioRefrescado->email)->toBe('andrea.rojas@uta.cl');
    });

    test('fallback defensivo: si email colisiona con otro usuario, degrada a null sin romper la transaccion', function () {
        // Crear primer usuario con un email
        $dto1 = new AlumnoIntranetData(
            alum_rut: 23555666,
            alum_digito: '7',
            alum_nombre: 'ALUMNO',
            alum_apellido_pat: 'UNO',
            alum_apellido_mat: null,
            alum_email: 'correo.compartido@uta.cl'
        );
        Estudiante::createFromIntranet($dto1, $this->carrera);

        // Crear segundo usuario con el mismo email (colisión con un tercero)
        $dto2 = new AlumnoIntranetData(
            alum_rut: 23666777,
            alum_digito: '8',
            alum_nombre: 'ALUMNO',
            alum_apellido_pat: 'DOS',
            alum_apellido_mat: null,
            alum_email: 'correo.compartido@uta.cl'
        );

        $estudiante2 = Estudiante::createFromIntranet($dto2, $this->carrera);

        expect($estudiante2)->toBeInstanceOf(Estudiante::class);
        $usuario2 = Usuario::where('username', '23666777')->first();
        expect($usuario2->email)->toBeNull();
    });

    test('fallback defensivo: si email es sintacticamente invalido, degrada a null sin fallar', function () {
        $dto = new AlumnoIntranetData(
            alum_rut: 23777888,
            alum_digito: '9',
            alum_nombre: 'FELIPE',
            alum_apellido_pat: 'DIAZ',
            alum_apellido_mat: null,
            alum_email: 'no-es-un-correo-valido'
        );

        $estudiante = Estudiante::createFromIntranet($dto, $this->carrera);

        expect($estudiante)->toBeInstanceOf(Estudiante::class);
        $usuario = Usuario::where('username', '23777888')->first();
        expect($usuario->email)->toBeNull();
    });
});
