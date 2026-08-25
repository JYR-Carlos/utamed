<?php

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
});
