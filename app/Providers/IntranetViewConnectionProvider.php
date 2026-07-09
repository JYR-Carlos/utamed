<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;
use App\Models\External\VwCarreraCurso;
use App\Models\External\VwInscripcion;
use App\DTOs\External\ComponenteCursoData;
use App\DTOs\External\InscripcionData;
use App\Enums\External\TipoAsignatura;

/**
 * LO QUE HACE ESTE PROVIDER
 * 
 * TRAER VISTAS DE INTRANET Y TIPARLAS
 * ACTUALMENTE LAS VISTAS SON:
 * 
 *      INSCRIPCION (información de la inscripción de alumnos a las componentes de cada curso)
 *      CARRERA_CURSO (información de las componentes como tal (no representa al curso, representa a las componentes) )
 *      ALUMNO (informacion de los alumnos inscritos a la UTA)
 * 
 * CREAR CLASES PARA TIPAR EL RETORNO DE LAS VISTAS (NO MODELOS PORQUE NO SON DE ESTE SISTEMA)
 * 
 * metodos:
 *  traer_cur_codigo(semestre real, año real, cod carrera, cod plan, cod asignatura)
 *  traer_ins_id(cur_codigo) {}
 * 
 * 
 */
class IntranetViewConnectionProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('OracleDataService', function ($app) {
            return new class {

                /**
                 * @return Collection<int, ComponenteCursoData>
                 */
                public function traer_cur_codigos(
                    int $semestre,
                    int $ano,
                    int $carreraCod,
                    int $planCod,
                    string $asigCodigo
                ): Collection {
                    return VwCarreraCurso::select(['CUR_CODIGO', 'CURSO_TIPO_ASIG', 'CURSO_GRUPO_ASIG'])
                        ->where('CURSO_SEMESTRE_ASIG', $semestre)
                        ->where('CURSO_ANO', $ano)
                        ->where('CARRERA_COD', $carreraCod)
                        ->where('PLAN_ANO', $planCod)
                        ->where('ASIG_CODIGO', $asigCodigo)
                        ->get()
                        ->map(
                            fn(VwCarreraCurso $curso) => new ComponenteCursoData(
                                cur_codigo: $curso->CUR_CODIGO,
                                // 'from()' lanzará error si Oracle devuelve una letra que no está en el Enum
                                // Si quieres evitar caídas por datos sucios, usa 'tryFrom()'
                                curso_tipo_asig: TipoAsignatura::from($curso->CURSO_TIPO_ASIG),
                                curso_grupo_asig: $curso->CURSO_GRUPO_ASIG
                            )
                        );
                }

                /**
                 * @param iterable<int> $curCodigos
                 * @return Collection<int, InscripcionData>
                 */
                public function traer_ins_id(iterable $curCodigos): Collection
                {
                    return VwInscripcion::select(['INS_ID', 'ALUM_RUT'])
                        ->whereIn('CUR_CODIGO', $curCodigos)
                        ->get()
                        ->map(fn(VwInscripcion $inscripcion) => new InscripcionData(
                            ins_id: $inscripcion->INS_ID,
                            alum_rut: $inscripcion->ALUM_RUT
                        ));
                }
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
