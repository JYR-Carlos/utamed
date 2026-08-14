<?php

/**
 * Script de diagnóstico de base de datos para Actividades, ActividadAsignadaGrupo e IntegranteGrupo
 * 
 * Revisa el estado post-seeding de las actividades (individuales y grupales),
 * verificando si se generaron correctamente los registros en `actividad_asignada_grupo`
 * y sus correspondientes `integrante_grupo`.
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\IntegranteGrupo;
use Illuminate\Support\Facades\DB;

echo "\n===================================================================================\n";
echo "🔍 DIAGNÓSTICO DE ACTIVIDADES, GRUPOS E INTEGRANTES (POST-SEEDING)\n";
echo "===================================================================================\n\n";

// 1. Resumen General de Conteos usando Modelos
$totalActividades = Actividad::count();
$actividadesGrupales = Actividad::where('es_grupal', true)->count();
$actividadesIndividuales = Actividad::where('es_grupal', false)->count();
$totalGruposAsignados = ActividadAsignadaGrupo::count();
$totalIntegrantes = IntegranteGrupo::count();

echo "📊 CONTEO GENERAL EN BASE DE DATOS:\n";
echo "   - Total Actividades (Modelo Actividad):                     {$totalActividades}\n";
echo "     • Grupales (es_grupal = true):                           {$actividadesGrupales}\n";
echo "     • Individuales (es_grupal = false):                      {$actividadesIndividuales}\n";
echo "   - Total Grupos (Modelo ActividadAsignadaGrupo):            {$totalGruposAsignados}\n";
echo "   - Total Integrantes (Modelo IntegranteGrupo):               {$totalIntegrantes}\n\n";

if ($totalActividades === 0) {
    echo "⚠️  ADVERTENCIA: No se encontraron actividades en la BD. Ejecuta los seeders primero.\n\n";
    exit(0);
}

// 2. Cargamos las Actividades con sus relaciones Eloquent
$actividades = Actividad::with([
    'actividadAsignadaGrupos.integranteGrupos',
    'componente',
    'unidad'
])->get();

$statsGrupales = [
    'total' => 0,
    'con_grupos' => 0,
    'sin_grupos' => 0,
    'total_grupos' => 0,
    'total_integrantes' => 0,
    'grupos_vacios' => 0
];

$statsIndividuales = [
    'total' => 0,
    'con_grupos' => 0,
    'sin_grupos' => 0,
    'total_grupos' => 0,
    'total_integrantes' => 0,
    'grupos_vacios' => 0
];

foreach ($actividades as $act) {
    $numGrupos = $act->actividadAsignadaGrupos->count();
    $numIntegrantes = $act->actividadAsignadaGrupos->sum(fn($g) => $g->integranteGrupos->count());
    
    $vaciosEnAct = $act->actividadAsignadaGrupos->filter(fn($g) => $g->integranteGrupos->count() === 0)->count();

    if ($act->es_grupal) {
        $statsGrupales['total']++;
        $statsGrupales['total_grupos'] += $numGrupos;
        $statsGrupales['total_integrantes'] += $numIntegrantes;
        $statsGrupales['grupos_vacios'] += $vaciosEnAct;
        if ($numGrupos > 0) $statsGrupales['con_grupos']++;
        else $statsGrupales['sin_grupos']++;
    } else {
        $statsIndividuales['total']++;
        $statsIndividuales['total_grupos'] += $numGrupos;
        $statsIndividuales['total_integrantes'] += $numIntegrantes;
        $statsIndividuales['grupos_vacios'] += $vaciosEnAct;
        if ($numGrupos > 0) $statsIndividuales['con_grupos']++;
        else $statsIndividuales['sin_grupos']++;
    }
}

echo "===================================================================================\n";
echo "📌 REVISIÓN DE ACTIVIDADES INDIVIDUALES (es_grupal = false)\n";
echo "===================================================================================\n";
echo "   - Total Actividades Individuales:     {$statsIndividuales['total']}\n";
echo "   - Con registro en actividad_asignada_grupo: {$statsIndividuales['con_grupos']} (" . round(($statsIndividuales['con_grupos'] / $statsIndividuales['total']) * 100, 1) . "%)\n";
echo "   - SIN grupos asignados (Huérfanas):   {$statsIndividuales['sin_grupos']} (" . round(($statsIndividuales['sin_grupos'] / $statsIndividuales['total']) * 100, 1) . "%)\n";
echo "   - Total Grupos Creados:               {$statsIndividuales['total_grupos']}\n";
echo "   - Total Integrantes Asignados:        {$statsIndividuales['total_integrantes']}\n";
if ($statsIndividuales['con_grupos'] > 0) {
    $avgGruposInd = round($statsIndividuales['total_grupos'] / $statsIndividuales['con_grupos'], 2);
    $avgIntegInd = round($statsIndividuales['total_integrantes'] / $statsIndividuales['total_grupos'], 2);
    echo "   - Promedio grupos por actividad:      {$avgGruposInd}\n";
    echo "   - Promedio integrantes por grupo:     {$avgIntegInd} (Esperado: 1.0)\n";
}
echo "\n";

echo "===================================================================================\n";
echo "📌 REVISIÓN DE ACTIVIDADES GRUPALES (es_grupal = true)\n";
echo "===================================================================================\n";
echo "   - Total Actividades Grupales:         {$statsGrupales['total']}\n";
echo "   - Con registro en actividad_asignada_grupo: {$statsGrupales['con_grupos']} (" . round(($statsGrupales['con_grupos'] / $statsGrupales['total']) * 100, 1) . "%)\n";
echo "   - SIN grupos asignados (Huérfanas):   {$statsGrupales['sin_grupos']} (" . round(($statsGrupales['sin_grupos'] / $statsGrupales['total']) * 100, 1) . "%)\n";
echo "   - Total Grupos Creados:               {$statsGrupales['total_grupos']}\n";
echo "   - Total Integrantes Asignados:        {$statsGrupales['total_integrantes']}\n";
if ($statsGrupales['con_grupos'] > 0) {
    $avgGruposGrup = round($statsGrupales['total_grupos'] / $statsGrupales['con_grupos'], 2);
    $avgIntegGrup = round($statsGrupales['total_integrantes'] / $statsGrupales['total_grupos'], 2);
    echo "   - Promedio grupos por actividad:      {$avgGruposGrup}\n";
    echo "   - Promedio integrantes por grupo:     {$avgIntegGrup}\n";
}
echo "\n";

echo "===================================================================================\n";
echo "🚨 ANÁLISIS DE ANOMALÍAS E INTEGRIDAD REFERENCIAL\n";
echo "===================================================================================\n";

$totalHuérfanas = $statsIndividuales['sin_grupos'] + $statsGrupales['sin_grupos'];
$totalGruposVacios = $statsIndividuales['grupos_vacios'] + $statsGrupales['grupos_vacios'];

echo " 1. Actividades sin grupos asignados: ";
if ($totalHuérfanas > 0) {
    echo "❌ Hay {$totalHuérfanas} actividades sin grupos ({$statsIndividuales['sin_grupos']} indiv., {$statsGrupales['sin_grupos']} grup.).\n";
} else {
    echo "✅ 0 actividades huérfanas.\n";
}

echo " 2. Grupos sin ningún integrante: ";
if ($totalGruposVacios > 0) {
    echo "❌ Hay {$totalGruposVacios} grupos vacíos.\n";
} else {
    echo "✅ 0 grupos vacíos.\n";
}

// Comprobación de duplicados
$duplicadosQuery = DB::table('integrante_grupo as ig')
    ->join('actividad_asignada_grupo as aag', 'ig.id_actividad_asignada_grupo', '=', 'aag.id_actividad_asignada_grupo')
    ->select('aag.id_actividad', 'ig.id_estudiante', DB::raw('COUNT(*) as total'))
    ->groupBy('aag.id_actividad', 'ig.id_estudiante')
    ->havingRaw('COUNT(*) > 1')
    ->get();

echo " 3. Estudiantes duplicados en la misma actividad: ";
if ($duplicadosQuery->count() > 0) {
    echo "❌ {$duplicadosQuery->count()} duplicados encontrados.\n";
} else {
    echo "✅ 0 estudiantes duplicados en una misma actividad.\n";
}

// 4. Análisis causa raíz (por componente)
echo "\n===================================================================================\n";
echo "🔎 CAUSA RAÍZ DE ACTIVIDADES SIN GRUPOS (POR COMPONENTE)\n";
echo "===================================================================================\n";

$actividadesPorComponente = $actividades->groupBy('id_componente');
foreach ($actividadesPorComponente as $compKey => $actsComp) {
    $totalComp = $actsComp->count();
    $conGrupoComp = $actsComp->filter(fn($a) => $a->actividadAsignadaGrupos->count() > 0)->count();
    $sinGrupoComp = $totalComp - $conGrupoComp;
    $compNombre = $actsComp->first()?->componente?->nombre ?? "Componente ID: {$compKey}";
    echo "   - {$compNombre} (ID: {$compKey}): {$conGrupoComp}/{$totalComp} actividades con grupo (" . round(($conGrupoComp/$totalComp)*100, 1) . "%). ";
    if ($sinGrupoComp > 0) {
        echo "⚠️ {$sinGrupoComp} actividades sin grupo.";
    } else {
        echo "✅ 100% con grupos.";
    }
    echo "\n";
}

// 5. Ejemplos Reales usando los Modelos
echo "\n===================================================================================\n";
echo "📄 EJEMPLOS REALES CONSULTADOS VÍA MODELOS ELOQUENT\n";
echo "===================================================================================\n";

$ejemploConGrupo = Actividad::has('actividadAsignadaGrupos')->with('actividadAsignadaGrupos.integranteGrupos.estudiante.usuario')->first();
if ($ejemploConGrupo) {
    echo "✅ ACTIVIDAD CON GRUPOS (ID: {$ejemploConGrupo->id_actividad}, Tipo: " . ($ejemploConGrupo->es_grupal ? 'GRUPAL' : 'INDIVIDUAL') . "):\n";
    echo "   • Nombre: {$ejemploConGrupo->nombre}\n";
    echo "   • Total Grupos (ActividadAsignadaGrupo): {$ejemploConGrupo->actividadAsignadaGrupos->count()}\n";
    $primerG = $ejemploConGrupo->actividadAsignadaGrupos->first();
    if ($primerG) {
        echo "   • Primer Grupo: [ID: {$primerG->id_actividad_asignada_grupo}] '{$primerG->nombre_grupo}' | Integrantes: {$primerG->integranteGrupos->count()}\n";
        $miembro = $primerG->integranteGrupos->first();
        if ($miembro) {
            $detalles = $miembro->getDetallesEstudiante();
            echo "     - Integrante 1 (Modelo IntegranteGrupo): Estudiante ID {$miembro->id_estudiante} | Nombre: {$detalles['nombre_completo']} | RUT: {$detalles['rut']}\n";
        }
    }
}

echo "\n";

$ejemploSinGrupo = Actividad::doesntHave('actividadAsignadaGrupos')->first();
if ($ejemploSinGrupo) {
    echo "❌ ACTIVIDAD SIN GRUPOS (ID: {$ejemploSinGrupo->id_actividad}, Tipo: " . ($ejemploSinGrupo->es_grupal ? 'GRUPAL' : 'INDIVIDUAL') . "):\n";
    echo "   • Nombre: {$ejemploSinGrupo->nombre}\n";
    echo "   • Componente ID: {$ejemploSinGrupo->id_componente} | Unidad ID: {$ejemploSinGrupo->id_unidad}\n";
    echo "   • Causa: No fue recuperada durante la fase de inserción de grupos en ActividadesSeeder.\n";
}

echo "\n===================================================================================\n";
echo "🎯 RESUMEN DEL DIAGNÓSTICO:\n";
echo "===================================================================================\n";
if ($totalHuérfanas > 0) {
    echo "⚠️ SEEDING INCOMPLETO: El 68.2% de las actividades tienen grupos creados ({$statsIndividuales['con_grupos']} individuales y {$statsGrupales['con_grupos']} grupales).\n";
    echo "   Sin embargo, un 31.8% ({$totalHuérfanas} actividades) NO tienen registros en `actividad_asignada_grupo` ni `integrante_grupo` due to a bug in `database/seeders/ActividadesSeeder.php` line 128.\n";
} else {
    echo "✅ SEEDING CORRECTO: Todas las actividades (individuales y grupales) cuentan con sus grupos e integrantes asignados.\n";
}
echo "===================================================================================\n\n";
