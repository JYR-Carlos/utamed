<?php

namespace App\Services\Agenda;

use App\Services\Docente\NombreUsuario;
use Illuminate\Support\Facades\DB;

/**
 * Confirmación de lectura de la agenda (T07), en agenda.lectura_agenda.
 *
 * Se guarda una fila por lector y mensaje: en una actividad grupal leen
 * varios integrantes y docentes. Al abrir un hilo se marcan como leídos todos
 * los mensajes que el usuario recibió; los propios no cuentan.
 *
 * En pantalla las lecturas se agrupan como en Instagram: sólo debajo del
 * último mensaje del hilo aparece «Visto por …» con quiénes lo han visto.
 */
class LecturaAgendaService
{
    /**
     * Registra que el usuario leyó los mensajes indicados. Ignora los que él
     * mismo envió y los que ya tenía registrados.
     *
     * @param  iterable<int>  $idsAgenda
     */
    public function registrar(int $idUsuario, iterable $idsAgenda): void
    {
        $ids = collect($idsAgenda)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        DB::statement(
            'INSERT INTO agenda.lectura_agenda (id_agenda, id_usuario_lector)
             SELECT a.id_agenda, ? FROM agenda.agenda a
             WHERE a.id_agenda = ANY(?::int[]) AND a.id_usuario_emisor <> ?
             ON CONFLICT (id_agenda, id_usuario_lector) DO NOTHING',
            [$idUsuario, '{' . $ids->implode(',') . '}', $idUsuario]
        );
    }

    /**
     * Quiénes vieron cada mensaje, del primero al último, sin contar al
     * usuario que está mirando la pantalla (él ya sabe que lo vio).
     *
     * @param  iterable<int>  $idsAgenda
     * @return array<int, array<int, array{nombre: string, fecha_lectura: string}>>  Por id_agenda.
     */
    public function vistoPor(iterable $idsAgenda, int $idUsuarioActual): array
    {
        $ids = collect($idsAgenda)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('agenda.lectura_agenda as l')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'l.id_usuario_lector')
            ->whereIn('l.id_agenda', $ids)
            ->where('l.id_usuario_lector', '<>', $idUsuarioActual)
            ->orderBy('l.fecha_lectura')
            ->select('l.id_agenda', NombreUsuario::sqlConcat('u', 'nombre'), 'l.fecha_lectura')
            ->get()
            ->groupBy('id_agenda')
            ->map(fn ($filas) => $filas
                ->map(fn ($l) => ['nombre' => $l->nombre, 'fecha_lectura' => (string) $l->fecha_lectura])
                ->values()
                ->all())
            ->all();
    }

    /**
     * Registra la lectura de uno o más hilos y agrega `visto_por` al último
     * mensaje de cada uno. Los mensajes pueden ser arrays u objetos; basta con
     * que traigan `id_agenda` (o `id_interaccion`, que vale lo mismo).
     *
     * @param  array<array-key, iterable>  $hilos  Cada hilo en orden cronológico.
     * @return array<array-key, array>  Los mismos hilos, con las mismas claves.
     */
    public function leerHilos(int $idUsuario, array $hilos): array
    {
        $idDe = fn ($m) => (int) data_get($m, 'id_agenda', data_get($m, 'id_interaccion'));

        $hilos = array_map(fn ($hilo) => collect($hilo)->values()->all(), $hilos);

        $this->registrar($idUsuario, collect($hilos)->flatten(1)->map($idDe));

        $indiceUltimoVisible = function (array $hilo): ?int {
            for ($i = count($hilo) - 1; $i >= 0; $i--) {
                $tipo = data_get($hilo[$i], 'tipo_mensaje', data_get($hilo[$i], 'tipo_interaccion', data_get($hilo[$i], 'tipo_registro')));
                $tipoStr = is_object($tipo) && property_exists($tipo, 'value') ? (string) $tipo->value : (string) $tipo;
                if ($tipoStr !== 'Cancelación de entrega') {
                    return $i;
                }
            }
            return array_key_last($hilo);
        };

        $ultimos = collect($hilos)->filter()->map(function (array $hilo) use ($idDe, $indiceUltimoVisible) {
            $idx = $indiceUltimoVisible($hilo);
            return $idx !== null ? $idDe($hilo[$idx]) : null;
        })->filter();

        $vistos = $this->vistoPor($ultimos, $idUsuario);

        foreach ($hilos as $clave => $hilo) {
            if ($hilo === []) {
                continue;
            }
            $ultimo = $indiceUltimoVisible($hilo);
            if ($ultimo === null) {
                continue;
            }
            $vistoPor = $vistos[$idDe($hilo[$ultimo])] ?? [];
            if (is_array($hilo[$ultimo])) {
                $hilos[$clave][$ultimo]['visto_por'] = $vistoPor;
            } else {
                $hilos[$clave][$ultimo]->visto_por = $vistoPor;
            }
        }

        return $hilos;
    }

    /**
     * Mensajes que el usuario recibió y todavía no ha visto, por grupo.
     * Los que él mismo envió no cuentan. `$acotar` recibe la consulta (con
     * los alias a = agenda, aag = grupo, act = actividad, c = componente)
     * para limitarla a ciertos cursos o a una actividad.
     *
     * @param  array<int, string>  $tipos  Tipos de agenda que cuentan como mensaje.
     * @return \Illuminate\Support\Collection<int, object{id_actividad: int, grupo: int, no_leidos: int, ultima_fecha: string}>
     */
    public function noLeidosPorGrupo(int $idUsuario, array $tipos, callable $acotar): \Illuminate\Support\Collection
    {
        $consulta = DB::table('agenda.agenda as a')
            ->join('agenda.actividad_asignada_grupo as aag', 'aag.id_actividad_asignada_grupo', '=', 'a.id_actividad_asignada_grupo')
            ->join('agenda.actividad as act', 'act.id_actividad', '=', 'aag.id_actividad')
            ->join('curso.componente as c', 'c.id_componente', '=', 'act.id_componente')
            ->whereIn('a.tipo_mensaje', $tipos)
            ->where('a.id_usuario_emisor', '<>', $idUsuario)
            ->whereNotExists(fn ($q) => $q->from('agenda.lectura_agenda as l')
                ->whereColumn('l.id_agenda', 'a.id_agenda')
                ->where('l.id_usuario_lector', $idUsuario))
            ->groupBy('aag.id_actividad', 'a.id_actividad_asignada_grupo')
            ->select(
                'aag.id_actividad',
                'a.id_actividad_asignada_grupo as grupo',
                DB::raw('COUNT(*) as no_leidos'),
                DB::raw('MAX(a.fecha_envio) as ultima_fecha'),
            );

        $acotar($consulta);

        return $consulta->get()->map(function ($fila) {
            $fila->id_actividad = (int) $fila->id_actividad;
            $fila->grupo = (int) $fila->grupo;
            $fila->no_leidos = (int) $fila->no_leidos;
            return $fila;
        });
    }

    /** Igual que leerHilos() para un solo hilo. */
    public function leerHilo(int $idUsuario, iterable $hilo): array
    {
        return $this->leerHilos($idUsuario, [$hilo])[0];
    }
}
