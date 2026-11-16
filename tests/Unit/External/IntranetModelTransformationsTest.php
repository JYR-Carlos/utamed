<?php

use App\DTOs\External\AlumnoIntranetData;
use App\Models\External\VwAlumno;
use App\Models\External\VwCarreraCurso;
use App\Models\External\VwInscripcion;

describe('Transformaciones y Accesores de Modelos de Intranet (Oracle)', function () {

    test('VwAlumno normaliza y limpia espacios en nombres y DV a mayúsculas', function () {
        $alumno = new VwAlumno([
            'ALUM_RUT'          => 18401835,
            'ALUM_DIGITO'       => '  k  ',
            'ALUM_NOMBRE'       => "  walter   armand  \t",
            'ALUM_APELLIDO_PAT' => '  martinez  ',
            'ALUM_APELLIDO_MAT' => '  bravo  ',
        ]);

        expect($alumno->ALUM_DIGITO)->toBe('K');
        expect($alumno->alum_digito)->toBe('K');
        expect($alumno->ALUM_NOMBRE)->toBe('WALTER ARMAND');
        expect($alumno->alum_nombre)->toBe('WALTER ARMAND');
        expect($alumno->ALUM_APELLIDO_PAT)->toBe('MARTINEZ');
        expect($alumno->alum_apellido_pat)->toBe('MARTINEZ');
        expect($alumno->ALUM_APELLIDO_MAT)->toBe('BRAVO');
        expect($alumno->alum_apellido_mat)->toBe('BRAVO');
        expect($alumno->nombre_completo)->toBe('WALTER ARMAND MARTINEZ BRAVO');
        expect($alumno->rut_completo)->toBe('18401835-K');
    });

    test('VwAlumno maneja apellido materno nulo o vacío retornando null', function () {
        $alumno1 = new VwAlumno([
            'ALUM_RUT'          => 20400071,
            'ALUM_DIGITO'       => '9',
            'ALUM_NOMBRE'       => 'HANSEN',
            'ALUM_APELLIDO_PAT' => 'VARAS',
            'ALUM_APELLIDO_MAT' => null,
        ]);

        expect($alumno1->ALUM_APELLIDO_MAT)->toBeNull();
        expect($alumno1->nombre_completo)->toBe('HANSEN VARAS');

        $alumno2 = new VwAlumno([
            'ALUM_RUT'          => 20400072,
            'ALUM_DIGITO'       => '0',
            'ALUM_NOMBRE'       => 'HANSEN',
            'ALUM_APELLIDO_PAT' => 'VARAS',
            'ALUM_APELLIDO_MAT' => '   ',
        ]);

        expect($alumno2->ALUM_APELLIDO_MAT)->toBeNull();
        expect($alumno2->nombre_completo)->toBe('HANSEN VARAS');
    });

    test('VwAlumno preserva caracteres especiales (guiones, apóstrofes, acentos)', function () {
        $alumno = new VwAlumno([
            'ALUM_RUT'          => 21010922,
            'ALUM_DIGITO'       => '4',
            'ALUM_NOMBRE'       => "  bania   n'kara  ",
            'ALUM_APELLIDO_PAT' => '  peña  ',
            'ALUM_APELLIDO_MAT' => '  choquerive  ',
        ]);

        expect($alumno->ALUM_NOMBRE)->toBe("BANIA N'KARA");
        expect($alumno->ALUM_APELLIDO_PAT)->toBe('PEÑA');
        expect($alumno->nombre_completo)->toBe("BANIA N'KARA PEÑA CHOQUERIVE");
    });

    test('VwCarreraCurso normaliza código de asignatura, tipo y grupo', function () {
        $curso = new VwCarreraCurso([
            'CUR_CODIGO'          => 201320002661,
            'ASIG_CODIGO'         => '  en155   ',
            'CURSO_TIPO_ASIG'     => '  t  ',
            'CURSO_GRUPO_ASIG'    => '  b  ',
            'CURSO_SEMESTRE_ASIG' => 2,
            'CURSO_ANO'           => 2013,
            'CARRERA_COD'         => 153,
            'PLAN_ANO'            => 2009,
        ]);

        expect($curso->ASIG_CODIGO)->toBe('EN155');
        expect($curso->asig_codigo)->toBe('EN155');
        expect($curso->CURSO_TIPO_ASIG)->toBe('T');
        expect($curso->curso_tipo_asig)->toBe('T');
        expect($curso->CURSO_GRUPO_ASIG)->toBe('B');
        expect($curso->curso_grupo_asig)->toBe('B');
    });

    test('VwInscripcion normaliza código de asignatura, tipo y grupo', function () {
        $inscripcion = new VwInscripcion([
            'INS_ID'           => 13885,
            'ALUM_RUT'         => 18401835,
            'CUR_CODIGO'       => 201320000890,
            'ASIG_CODIGO'      => '  di021   ',
            'CURSO_TIPO_ASIG'  => '  c  ',
            'CURSO_GRUPO_ASIG' => '  a  ',
        ]);

        expect($inscripcion->ASIG_CODIGO)->toBe('DI021');
        expect($inscripcion->CURSO_TIPO_ASIG)->toBe('C');
        expect($inscripcion->CURSO_GRUPO_ASIG)->toBe('A');
    });

    test('VwAlumno normaliza ALUM_EMAIL con espacios y mayúsculas a minúsculas limpias', function () {
        $alumno = new VwAlumno([
            'ALUM_RUT'   => 18401835,
            'ALUM_EMAIL' => '  Alumno.Test@UTA.CL  ',
        ]);

        expect($alumno->ALUM_EMAIL)->toBe('alumno.test@uta.cl');
        expect($alumno->alum_email)->toBe('alumno.test@uta.cl');
    });

    test('VwAlumno maneja ALUM_EMAIL vacío o nulo retornando null', function () {
        $alumno1 = new VwAlumno([
            'ALUM_RUT'   => 18401835,
            'ALUM_EMAIL' => '   ',
        ]);
        expect($alumno1->ALUM_EMAIL)->toBeNull();
        expect($alumno1->alum_email)->toBeNull();

        $alumno2 = new VwAlumno([
            'ALUM_RUT'   => 18401836,
            'ALUM_EMAIL' => null,
        ]);
        expect($alumno2->ALUM_EMAIL)->toBeNull();
        expect($alumno2->alum_email)->toBeNull();
    });

    test('VwAlumno acepta alias alternativo ALUM_CORREO', function () {
        $alumno = new VwAlumno([
            'ALUM_RUT'    => 18401835,
            'ALUM_CORREO' => '  OTRO.CORREO@ALUMNOS.UTA.CL  ',
        ]);

        expect($alumno->alum_email)->toBe('otro.correo@alumnos.uta.cl');
    });

    test('AlumnoIntranetData normaliza email a minúsculas limpias y maneja null', function () {
        $dto1 = new AlumnoIntranetData(
            alum_rut: 18401835,
            alum_digito: 'K',
            alum_nombre: 'JUAN',
            alum_apellido_pat: 'PÉREZ',
            alum_email: '  JUAN.PEREZ@ALUMNOS.UTA.CL  '
        );
        expect($dto1->alum_email)->toBe('juan.perez@alumnos.uta.cl');
        expect($dto1->email)->toBe('juan.perez@alumnos.uta.cl');

        $dto2 = new AlumnoIntranetData(
            alum_rut: 18401835,
            alum_digito: 'K',
            alum_nombre: 'JUAN',
            alum_apellido_pat: 'PÉREZ',
            alum_email: '   '
        );
        expect($dto2->alum_email)->toBeNull();
        expect($dto2->email)->toBeNull();
    });

    test('AlumnoIntranetData acepta alias email en el constructor', function () {
        $dto = new AlumnoIntranetData(
            alum_rut: 18401835,
            alum_digito: 'K',
            alum_nombre: 'JUAN',
            alum_apellido_pat: 'PÉREZ',
            email: 'JUAN.ALIAS@UTA.CL'
        );
        expect($dto->alum_email)->toBe('juan.alias@uta.cl');
        expect($dto->email)->toBe('juan.alias@uta.cl');
    });
});
