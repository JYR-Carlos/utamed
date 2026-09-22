<?php
// Consulta de SÓLO LECTURA para pegar en `php artisan tinker` (producción).
// Lista los cursos con su contexto (asignatura, carrera, paralelo, período, titular,
// componentes, unidades, actividades, programa) y los alumnos inscritos en cada uno.
//
// Uso en el servidor (desde la raíz del proyecto Laravel):
//
//   A) Ejecuta el archivo y termina (ideal para producción, se puede redirigir a un .txt):
//      php artisan tinker --execute="include 'database/seeders/data/consulta_cursos_alumnos.tinker.php';"
//      php artisan tinker --execute="include 'database/seeders/data/consulta_cursos_alumnos.tinker.php';" > cursos_prod.txt
//
//   B) Ejecuta el archivo y deja tinker abierto con $cursos, $letra, etc. disponibles para seguir consultando:
//      php artisan tinker database/seeders/data/consulta_cursos_alumnos.tinker.php
//
//   C) Pegar a mano dentro de `php artisan tinker`, desde la línea "$soloAsignatura" hasta el final.
//
// Si el archivo está fuera del proyecto, usar su ruta absoluta en el include / argumento.
//
// Filtros (editar antes de ejecutar):
$soloAsignatura  = null;   // p. ej. 'DM095' para ver sólo ese ramo; null = todos
$incluirPlantillas = false; // true para ver también los cursos plantilla
$listarAlumnos   = true;   // false para ver sólo la tabla de cursos

// Sin `use`: tinker ya tiene alias para los modelos y repetirlos da "name is already in use".
$q = \App\Models\Curso\Curso::query()
    ->with([
        'asignacionPlan.asignatura',
        'asignacionPlan.plan.carrera',
        'docenteTitular.usuario',
        'componentes.tipoComponente',
        'componentes.docenteComponentes.docente.usuario',
    ])
    ->withCount(['inscripcionCursos', 'unidades', 'programas'])
    ->orderBy('id_curso');

if (!$incluirPlantillas) {
    $q->where('es_plantilla', false);
}
if ($soloAsignatura) {
    $q->whereHas('asignacionPlan.asignatura', fn ($a) => $a->where('cod_asignatura', $soloAsignatura));
}

$cursos = $q->get();

$actividadesPorCurso = DB::table('agenda.actividad as a')
    ->join('curso.componente as k', 'k.id_componente', '=', 'a.id_componente')
    ->whereIn('k.id_curso', $cursos->pluck('id_curso'))
    ->groupBy('k.id_curso')
    ->selectRaw('k.id_curso, count(*) as n')
    ->pluck('n', 'id_curso');

$letra = fn ($c) => $c->letra_grupo ?: \App\Support\LetraGrupo::fromIndice($c->indice_grupo);

echo "\n=== CURSOS (" . $cursos->count() . ") — BD: " . DB::connection()->getDatabaseName() . " ===\n";
foreach ($cursos as $c) {
    $asig    = $c->asignacionPlan?->asignatura;
    $carrera = $c->asignacionPlan?->plan?->carrera?->nombre;
    $titular = $c->docenteTitular?->usuario;

    $componentes = $c->componentes->map(function ($k) {
        $docs = $k->docenteComponentes->map(fn ($dc) => $dc->docente?->usuario?->username)->filter()->implode('/');
        return $k->tipoComponente?->tipo . '#' . $k->id_componente . ($docs ? " ({$docs})" : '');
    })->implode(', ');

    printf(
        "\n[%d] cod_curso=%s  %s %s  (%s)\n     paralelo %s (idx %s)  período %s-%s  %s → %s  estado=%s%s\n     titular: %s <%s>\n     componentes: %s\n     unidades=%d  actividades=%d  programas=%d  inscritos=%d\n",
        $c->id_curso,
        $c->cod_curso,
        $asig?->cod_asignatura ?? '?',
        $asig?->nombre ?? $c->nombre,
        $carrera ?? 'sin carrera',
        $letra($c) ?: '-',
        $c->indice_grupo ?? '-',
        $c->agno_real ?? 'null',
        $c->semestre_real ?? 'null',
        optional($c->fecha_inicio)->format('Y-m-d'),
        optional($c->fecha_fin)->format('Y-m-d'),
        $c->estado_interno,
        $c->es_plantilla ? '  [PLANTILLA]' : '',
        $titular?->nombre_completo ?? $titular?->username ?? '-',
        $titular?->email ?? '-',
        $componentes ?: '(sin componentes)',
        $c->unidades_count,
        $actividadesPorCurso[$c->id_curso] ?? 0,
        $c->programas_count,
        $c->inscripcion_cursos_count
    );
}

if ($listarAlumnos) {
    echo "\n=== ALUMNOS POR CURSO ===\n";
    foreach ($cursos as $c) {
        $insc = DB::table('curso.inscripcion_curso as ic')
            ->join('usuario.estudiante as e', 'e.id_estudiante', '=', 'ic.id_estudiante')
            ->join('usuario.usuario as u', 'u.id_usuario', '=', 'e.id_usuario')
            ->where('ic.id_curso', $c->id_curso)
            ->orderBy('u.apellido1')->orderBy('u.nombre1')
            ->get(['e.id_estudiante', 'u.rut', 'u.nombre1', 'u.apellido1', 'u.apellido2', 'u.email', 'ic.estado_inscripcion', 'ic.fecha_inscripcion']);

        printf("\n[%d] %s %s — %d inscritos\n", $c->id_curso, $c->asignacionPlan?->asignatura?->cod_asignatura, $letra($c), $insc->count());
        foreach ($insc as $i => $s) {
            printf("   %2d. %-12s %-35s %-32s %s\n",
                $i + 1,
                $s->rut,
                trim("{$s->apellido1} {$s->apellido2}, {$s->nombre1}"),
                $s->email,
                $s->estado_inscripcion
            );
        }
    }
}

echo "\nTotales: cursos=" . $cursos->count()
    . "  estudiantes_distintos=" . DB::table('curso.inscripcion_curso')->whereIn('id_curso', $cursos->pluck('id_curso'))->distinct()->count('id_estudiante')
    . "  actividades=" . $actividadesPorCurso->sum()
    . "  programas=" . $cursos->sum('programas_count')
    . "  bibliografias=" . DB::table('curso.bibliografia')->count()
    . "\n";
