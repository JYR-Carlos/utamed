<?php

use App\Models\External\VwAlumno;
use App\Models\External\VwCarreraCurso;
use App\Models\External\VwInscripcion;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

describe('Conexión y Modelos de Intranet Externa (Oracle)', function () {

    test('puede conectarse a la base de datos Oracle y consultar DUAL', function () {
        $result = DB::connection('oracle')->select('SELECT 1 AS TEST FROM DUAL');

        expect($result)->not->toBeEmpty();
    });

    test('puede consultar el modelo VwAlumno', function () {
        $count = VwAlumno::count();
        expect($count)->toBeGreaterThanOrEqual(0);

        $alumno = VwAlumno::first();
        if ($alumno) {
            expect($alumno)->toBeInstanceOf(VwAlumno::class);
            expect($alumno->ALUM_RUT)->not->toBeNull();
        }
    });

    test('puede consultar el modelo VwCarreraCurso', function () {
        $count = VwCarreraCurso::count();
        expect($count)->toBeGreaterThanOrEqual(0);

        $curso = VwCarreraCurso::first();
        if ($curso) {
            expect($curso)->toBeInstanceOf(VwCarreraCurso::class);
            expect($curso->CUR_CODIGO)->not->toBeNull();
        }
    });

    test('puede consultar el modelo VwInscripcion y sus relaciones', function () {
        $count = VwInscripcion::count();
        expect($count)->toBeGreaterThanOrEqual(0);

        $inscripcion = VwInscripcion::with(['alumno', 'carreraCurso'])->first();
        if ($inscripcion) {
            expect($inscripcion)->toBeInstanceOf(VwInscripcion::class);
            expect($inscripcion->INS_ID)->not->toBeNull();

            // Si tiene alumno asociado, verificar instancia
            if ($inscripcion->ALUM_RUT && $inscripcion->alumno) {
                expect($inscripcion->alumno)->toBeInstanceOf(VwAlumno::class);
                expect($inscripcion->alumno->ALUM_RUT)->toBe($inscripcion->ALUM_RUT);
            }

            // Si tiene curso asociado, verificar instancia
            if ($inscripcion->CUR_CODIGO && $inscripcion->carreraCurso) {
                expect($inscripcion->carreraCurso)->toBeInstanceOf(VwCarreraCurso::class);
                expect($inscripcion->carreraCurso->CUR_CODIGO)->toBe($inscripcion->CUR_CODIGO);
            }
        }
    });
});
