<?php

namespace App\Services\Agenda;

use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\InscripcionCurso;
use Illuminate\Support\Facades\DB;

/**
 * Copia grupos de una actividad grupal a otra del mismo curso.
 *
 * Un grupo pertenece a una actividad. La copia es un grupo nuevo en el destino,
 * con el nombre y los integrantes del original; no se lleva entregas, mensajes
 * ni notas. El destino puede tener ya entregas: sus grupos no se tocan y quien
 * ya está en uno se queda ahí, porque un estudiante no puede estar en dos
 * grupos de la misma actividad.
 */
class CopiaGruposService
{
    /**
     * @param  array<int>  $idsGrupos  Grupos de `$origen`; los ajenos se ignoran.
     * @return array{copiados:int, sin_inscritos:int, ya_con_grupo:int, exceden:int, se_quedan:int}
     *         `se_quedan`: estudiantes que no se copiaron porque ya tienen grupo en el destino.
     */
    public function copiar(Actividad $origen, Actividad $destino, array $idsGrupos, int $idCurso): array
    {
        return DB::transaction(function () use ($origen, $destino, $idsGrupos, $idCurso) {
            // Dos copias a la vez hacia el mismo destino repetirían estudiantes.
            Actividad::whereKey($destino->id_actividad)->lockForUpdate()->first();

            $resultado = ['copiados' => 0, 'sin_inscritos' => 0, 'ya_con_grupo' => 0, 'exceden' => 0, 'se_quedan' => 0];

            $inscritos = InscripcionCurso::where('id_curso', $idCurso)->pluck('id_estudiante');
            $conGrupo = IntegranteGrupo::whereHas(
                'actividadAsignadaGrupo',
                fn ($q) => $q->where('id_actividad', $destino->id_actividad),
            )->pluck('id_estudiante');

            $grupos = ActividadAsignadaGrupo::where('id_actividad', $origen->id_actividad)
                ->whereIn('id_actividad_asignada_grupo', $idsGrupos)
                ->with('miembros')
                ->orderBy('id_actividad_asignada_grupo')
                ->get();

            foreach ($grupos as $grupo) {
                $vigentes = $grupo->miembros->pluck('id_estudiante')->intersect($inscritos);
                $libres = $vigentes->diff($conGrupo)->values();
                $resultado['se_quedan'] += $vigentes->count() - $libres->count();

                if ($vigentes->isEmpty()) {
                    $resultado['sin_inscritos']++;
                } elseif ($libres->isEmpty()) {
                    $resultado['ya_con_grupo']++;
                } elseif ($destino->max_integrantes && $libres->count() > $destino->max_integrantes) {
                    $resultado['exceden']++;
                } else {
                    $copia = ActividadAsignadaGrupo::create([
                        'id_actividad' => $destino->id_actividad,
                        'nombre_grupo' => $grupo->nombre_grupo,
                        'estado_actividad_asignada' => 'PLANIFICADA',
                    ]);
                    foreach ($libres as $idEstudiante) {
                        IntegranteGrupo::create([
                            'id_actividad_asignada_grupo' => $copia->id_actividad_asignada_grupo,
                            'id_estudiante' => $idEstudiante,
                        ]);
                    }
                    // El destino puede estar ya abierto o cerrado.
                    $copia->sincronizarEstado($destino);

                    $conGrupo = $conGrupo->concat($libres);
                    $resultado['copiados']++;
                }
            }

            return $resultado;
        });
    }

    /**
     * Frase para el docente con lo que se copió y lo que no, y por qué.
     *
     * @param  array{copiados:int, sin_inscritos:int, ya_con_grupo:int, exceden:int, se_quedan:int}  $r
     */
    public static function resumen(array $r): string
    {
        $partes = [match ($r['copiados']) {
            0 => 'No se copió ningún grupo',
            1 => 'Se copió 1 grupo',
            default => "Se copiaron {$r['copiados']} grupos",
        }];

        if ($r['se_quedan'] > 0) {
            $partes[] = $r['se_quedan'] === 1
                ? '1 estudiante ya tenía grupo en la actividad y se quedó en el suyo'
                : "{$r['se_quedan']} estudiantes ya tenían grupo en la actividad y se quedaron en el suyo";
        }
        if ($r['exceden'] > 0) {
            $partes[] = $r['exceden'] === 1
                ? '1 grupo supera el máximo de integrantes'
                : "{$r['exceden']} grupos superan el máximo de integrantes";
        }
        if ($r['sin_inscritos'] > 0) {
            $partes[] = $r['sin_inscritos'] === 1
                ? '1 grupo ya no tiene integrantes inscritos en el curso'
                : "{$r['sin_inscritos']} grupos ya no tienen integrantes inscritos en el curso";
        }

        return implode('. ', $partes).'.';
    }
}
