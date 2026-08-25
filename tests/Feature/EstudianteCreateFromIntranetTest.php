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
            ->toThrow(\InvalidArgumentException::class);
    });
});
