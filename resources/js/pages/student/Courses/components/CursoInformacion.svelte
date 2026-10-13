<script lang="ts">
  /**
   * "Sobre el curso" — resumen académico que abre la ficha del alumno.
   *
   * Toma los mismos props que student/Courses/Syllabus.svelte pero muestra sólo
   * lo que el alumno necesita para situarse: de qué trata, cómo se evalúa y qué
   * unidades cubre. El equipo docente es una sección aparte (CursoEquipoDocente).
   * El documento completo (competencias, bibliografía, normativa extendida)
   * sigue en /estudiante/cursos/{id}/programa, a un clic desde el encabezado.
   *
   * Estructura visual: tarjetas redondeadas con borde gris y encabezado de
   * icono + título, el mismo patrón que los paneles del dashboard.
   */
  import { BookOpen, Info, ListOrdered, Scale } from 'lucide-svelte';
  import type { Curso } from '@/types';
  import type { DatosSyllabusAlumno } from '@/types/syllabus.types';

  interface Props {
    curso?: Curso | null;
    programa?: { version_programa: string } | null;
    datos?: DatosSyllabusAlumno | null;
    /** Actividades visibles del curso, para contar cuántas tiene cada componente. */
    actividades?: Array<{ es_sumativa: boolean; componente_tipo?: string | null }>;
  }

  let { curso = null, programa = null, datos = null, actividades = [] }: Props = $props();

  const descripcion = $derived(datos?.descripcion ?? '');
  const componentes = $derived(datos?.componentes ?? []);
  const normativa = $derived(datos?.normativa ?? '');
  const unidades = $derived(datos?.unidades ?? []);

  // ─── «Cómo se evalúa» ──────────────────────────────────────────────────────
  // Antes cada componente era una barra de progreso, que se lee como «avance»
  // y no como «peso», con la cifra al otro lado de la pantalla. Ahora: una
  // barra que reparte el 100 % entre componentes y una tarjeta por cada uno.
  // Tonos del azul institucional con distinta luminosidad, para que los
  // segmentos se distingan también sin percibir el color.
  const TONOS = ['#002855', '#7A9CC6', '#3D5F8F', '#B9CBE3'];

  /** Conteo por componente; el programa y el curso nombran igual al componente. */
  const conteo = $derived.by(() => {
    const mapa = new Map<string, { total: number; sumativas: number }>();
    for (const a of actividades) {
      const clave = (a.componente_tipo ?? '').trim().toLowerCase();
      if (!clave) continue;
      const c = mapa.get(clave) ?? { total: 0, sumativas: 0 };
      c.total += 1;
      if (a.es_sumativa) c.sumativas += 1;
      mapa.set(clave, c);
    }
    return mapa;
  });

  function textoConteo(nombre: string): string {
    const c = conteo.get(nombre.trim().toLowerCase());
    if (!c || c.total === 0) return 'Aún sin actividades';
    const act = `${c.total} ${c.total === 1 ? 'actividad' : 'actividades'}`;
    if (c.sumativas === 0) return act;
    return `${act} · ${c.sumativas} ${c.sumativas === 1 ? 'sumativa' : 'sumativas'}`;
  }

</script>

{#snippet encabezado(Icono: typeof BookOpen, texto: string)}
  <div class="flex items-center gap-2 mb-4">
    <Icono class="w-4 h-4 text-uta-blue" />
    <h3 class="text-sm text-gray-700 font-medium">{texto}</h3>
  </div>
{/snippet}

<div class="space-y-6">
  <!-- Descripción -->
  {#if descripcion}
    <div class="rounded-3xl border border-gray-200 bg-white p-6">
      {@render encabezado(BookOpen, 'De qué trata')}
      <p class="text-[15px] leading-relaxed text-gray-700 whitespace-pre-line">{descripcion}</p>
    </div>
  {/if}

  <!-- Evaluación -->
  {#if componentes.length > 0}
    <div class="rounded-3xl border border-gray-200 bg-white p-6">
      {@render encabezado(Scale, 'Cómo se evalúa')}

      <div class="grid sm:grid-cols-2 grid-cols-1 justify-between items-start gap-6">

      
      <ul class="grid grid-cols-1 gap-3">
        {#each componentes as componente, i (componente.componente)}
          {@const asistencia = componente.asistencia_obligatoria ?? 0}
          {@const sinActividades = textoConteo(componente.componente) === 'Aún sin actividades'}
          <li class="flex flex-col gap-3 rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center gap-2.5">
              <span class="h-3 w-3 shrink-0 rounded-[3px]" style="background: {TONOS[i % TONOS.length]}"></span>
              <span class="font-semibold text-gray-900">{componente.componente}</span>
            </div>
            <div class="flex items-baseline gap-2">
              <span class="text-4xl font-bold leading-none tracking-tight text-uta-blue">{componente.porcentaje} %</span>
              <span class="text-sm text-gray-500">de la nota final</span>
            </div>
            <div class="flex flex-wrap gap-1.5">
              {#if asistencia > 0}
                <span class="rounded-full border border-gray-200 bg-[#F5F1EA] px-2.5 py-1 text-xs text-gray-700">
                  Asistencia mínima {asistencia} %
                </span>
              {/if}
              {#if componente.aprobacion_obligatoria}
                <span class="rounded-full border border-gray-200 bg-[#F5F1EA] px-2.5 py-1 text-xs text-gray-700">
                  Se aprueba por separado
                </span>
              {/if}
              <span
                class="rounded-full border px-2.5 py-1 text-xs {sinActividades
                  ? 'border-amber-200 bg-amber-50 text-amber-800'
                  : 'border-gray-200 bg-[#F5F1EA] text-gray-700'}"
              >
                {textoConteo(componente.componente)}
              </span>
            </div>
          </li>
        {/each}
      </ul>

      {#if normativa}
        <div class="p-4 size-full rounded-2xl border ">
          <p class="text-sm font-semibold text-gray-700 mb-1">Requisito de aprobación</p>
          <p class="text-sm text-gray-700 leading-relaxed">{normativa}</p>
        </div>
      {/if}
      </div>
    </div>
  {/if}

  <!-- Unidades: la numeración es real, marca el orden en que se pasan. -->
  {#if unidades.length > 0}
    <div class="rounded-3xl border border-gray-200 bg-white p-6">
      {@render encabezado(ListOrdered, 'Unidades del curso')}
      <ol class="divide-y divide-gray-100">
        {#each unidades as unidad (unidad.numero)}
          <li class="py-4 first:pt-0 last:pb-0">
            <details class="group">
              <summary class="flex items-start gap-4 cursor-pointer list-none rounded-lg">
                <span class="shrink-0 w-6 text-sm font-bold text-gray-400" aria-hidden="true">
                  {unidad.numero}
                </span>
                <span class="flex-1 font-semibold text-gray-900 leading-snug">
                  {unidad.titulo}
                </span>
                {#if unidad.contenidos_items?.length}
                  <span class="shrink-0 text-xs font-medium text-gray-400 group-open:hidden">
                    Ver contenidos
                  </span>
                  <span class="shrink-0 text-xs font-medium text-gray-400 hidden group-open:inline">
                    Ocultar
                  </span>
                {/if}
              </summary>
              {#if unidad.contenidos_items?.length}
                <div class="pl-10 pr-2 mt-3 space-y-2">
                  {#each unidad.contenidos_items as contenido, i (i)}
                    <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">
                      {contenido.item}
                    </p>
                  {/each}
                </div>
              {/if}
            </details>
          </li>
        {/each}
      </ol>
    </div>
  {/if}

  <!-- Aviso si el programa aún no está publicado. Cuando lo está, el acceso
       es el botón «Ver programa» del encabezado (T23): aquí sería un duplicado. -->
  {#if !programa}
    <div class="flex items-start gap-4 p-5 rounded-3xl border border-gray-200 bg-gray-50">
      <span class="shrink-0 text-gray-400 mt-0.5">
        <Info class="w-[18px] h-[18px]" />
      </span>
      <p class="text-sm text-gray-600 leading-relaxed">
        El programa de este curso todavía no está publicado. Cuando tu escuela lo apruebe vas a
        poder revisarlo aquí mismo.
      </p>
    </div>
  {/if}
</div>
