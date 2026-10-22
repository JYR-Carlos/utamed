<script lang="ts" module>
  export interface ComponenteCentro {
    id_componente: number;
    tipo_componente: string;
    imparte: boolean;
    es_titular_componente: boolean;
  }

  export interface CursoCentro<K extends ComponenteCentro = ComponenteCentro> {
    id_curso: number;
    nombre: string;
    etiqueta: string;
    letra_grupo: string | null;
    cod_asignatura: string | null;
    cod_curso: string;
    agno_real: number | null;
    semestre_real: number | null;
    es_titular_curso: boolean;
    componentes: K[];
  }
</script>

<script lang="ts" generics="C extends CursoCentro">
  /**
   * Pasos de los centros transversales del docente (Calificaciones y
   * Asistencia): curso → componente → lo que cada centro muestre.
   *
   * Los cursos se agrupan por período con el mismo criterio que el menú
   * lateral: el período más reciente con cursos es el «actual». Las migas
   * aparecen recién al elegir un curso (antes no hay nada a qué volver).
   */
  import type { Snippet } from 'svelte';
  import { ChevronRight, Crown } from 'lucide-svelte';
  import { tonoGrupo } from '@/utils/grupoColor';

  /** El componente concreto de cada centro (con sus actividades, sus inscritos…). */
  type K = C['componentes'][number];

  interface Props {
    cursos: C[];
    /** Indicación del primer paso, p. ej. «Elige el curso que quieres calificar». */
    indicacionCursos: string;
    /** Nombre accesible de las migas. */
    etiquetaMigas: string;
    /** Línea de datos de la tarjeta de cada componente. */
    datosComponente: Snippet<[K]>;
    /** Tercer paso: lo propio de cada centro. */
    contenido: Snippet<[C, K]>;
  }

  let { cursos, indicacionCursos, etiquetaMigas, datosComponente, contenido }: Props = $props();

  let cursoSel = $state<number | null>(null);
  let componenteSel = $state<number | null>(null);

  // Con una sola opción se elige sola (evita clics innecesarios).
  $effect(() => {
    if (cursoSel === null && cursos.length === 1) {
      cursoSel = cursos[0].id_curso;
    }
  });
  $effect(() => {
    const c = cursos.find((x) => x.id_curso === cursoSel);
    if (cursoSel !== null && componenteSel === null && c && c.componentes.length === 1) {
      componenteSel = c.componentes[0].id_componente;
    }
  });

  const cursoActual = $derived(cursos.find((c) => c.id_curso === cursoSel) ?? null);
  const componenteActual = $derived(
    cursoActual?.componentes.find((c) => c.id_componente === componenteSel) ?? null,
  );

  function elegirCurso(id: number) {
    cursoSel = id;
    componenteSel = null;
  }

  function volverACursos() {
    cursoSel = null;
    componenteSel = null;
  }

  // ─── Períodos ───────────────────────────────────────────────────────────────
  const periodos = $derived.by(() => {
    const porClave = new Map<string, { agno: number; semestre: number; cursos: C[] }>();
    const sinPeriodo: C[] = [];

    for (const c of cursos) {
      if (!c.agno_real || !c.semestre_real) {
        sinPeriodo.push(c);
        continue;
      }
      const clave = `${c.agno_real}-${c.semestre_real}`;
      if (!porClave.has(clave)) porClave.set(clave, { agno: c.agno_real, semestre: c.semestre_real, cursos: [] });
      porClave.get(clave)!.cursos.push(c);
    }

    const ordenados = [...porClave.entries()]
      .sort(([, a], [, b]) => b.agno - a.agno || b.semestre - a.semestre)
      .map(([clave, p], i) => ({
        clave,
        etiqueta: `${i === 0 ? 'Período actual · ' : ''}Año ${p.agno} · Semestre ${p.semestre}`,
        cursos: p.cursos,
      }));

    if (sinPeriodo.length) ordenados.push({ clave: 'sin-periodo', etiqueta: 'Sin período asignado', cursos: sinPeriodo });
    return ordenados;
  });

  /** La etiqueta termina en «Grupo A»; el grupo va aparte, como chip. */
  function nombreSinGrupo(c: C): string {
    const base = c.etiqueta || c.nombre;
    return c.letra_grupo ? base.replace(new RegExp(`\\s+Grupo\\s+${c.letra_grupo}$`), '') : base;
  }

  const TARJETA =
    'flex flex-col gap-2.5 rounded-xl border border-slate-200 bg-white p-4 text-left transition-colors hover:border-uta-blue hover:bg-slate-50 sm:p-5';
  const CHIP = 'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-px text-xs font-semibold';
  const MIGA =
    'inline-flex min-h-11 items-center rounded-md px-1 font-semibold text-uta-blue underline-offset-4 hover:underline lg:min-h-8';
  const MIGA_ACTUAL = 'inline-flex min-h-11 items-center px-1 font-semibold text-ink lg:min-h-8';
</script>

{#if cursoActual}
  <nav aria-label={etiquetaMigas}>
    <ol class="m-0 flex list-none flex-wrap items-center gap-1 p-0 text-sm">
      {#if cursos.length > 1}
        <li><button type="button" class={MIGA} onclick={volverACursos}>Cursos</button></li>
        <li aria-hidden="true" class="flex"><ChevronRight size={14} class="text-slate-500" /></li>
      {/if}
      <li>
        {#if componenteActual && cursoActual.componentes.length > 1}
          <button type="button" class={MIGA} onclick={() => (componenteSel = null)}>{cursoActual.etiqueta}</button>
        {:else}
          <span class={MIGA_ACTUAL} aria-current={componenteActual ? undefined : 'step'}>{cursoActual.etiqueta}</span>
        {/if}
      </li>
      {#if componenteActual}
        <li aria-hidden="true" class="flex"><ChevronRight size={14} class="text-slate-500" /></li>
        <li><span class={MIGA_ACTUAL} aria-current="step">{componenteActual.tipo_componente}</span></li>
      {/if}
    </ol>
  </nav>
{/if}

{#if cursoSel === null}
  <!-- Paso 1: curso -->
  <p class="m-0 text-sm font-medium text-slate-600">{indicacionCursos}</p>
  {#each periodos as p (p.clave)}
    <section class="flex flex-col gap-3" aria-labelledby="periodo-{p.clave}">
      <div class="flex items-center gap-2.5">
        <h2 id="periodo-{p.clave}" class="m-0 whitespace-nowrap text-base font-semibold text-ink">{p.etiqueta}</h2>
        <span class="inline-flex h-[22px] min-w-[22px] items-center justify-center rounded-full border border-slate-200 bg-white px-1.5 text-xs font-semibold text-slate-600">
          {p.cursos.length}
        </span>
        <span class="h-px flex-1 bg-slate-200" aria-hidden="true"></span>
      </div>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {#each p.cursos as c (c.id_curso)}
          <button type="button" onclick={() => elegirCurso(c.id_curso)} class={TARJETA}>
            <span class="flex items-center justify-between gap-2">
              <span class="truncate font-mono text-xs font-medium text-slate-600">{c.cod_asignatura || c.cod_curso}</span>
              {#if c.es_titular_curso}
                <span class="{CHIP} bg-amber-100 text-ocre"><Crown size={12} aria-hidden="true" /> Titular</span>
              {/if}
            </span>
            <span class="line-clamp-2 text-[15px] font-semibold leading-snug text-ink">{nombreSinGrupo(c)}</span>
            <span class="mt-auto flex flex-wrap items-center gap-2 text-xs font-medium text-slate-600">
              {#if c.letra_grupo}
                <span class="{CHIP} {tonoGrupo(c.letra_grupo).chip}">Grupo {c.letra_grupo}</span>
              {/if}
              {c.componentes.length}
              {c.componentes.length === 1 ? 'componente' : 'componentes'}
            </span>
          </button>
        {/each}
      </div>
    </section>
  {/each}
{:else if componenteSel === null && cursoActual}
  <!-- Paso 2: componente -->
  <p class="m-0 text-sm font-medium text-slate-600">Elige el componente.</p>
  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
    {#each cursoActual.componentes as comp (comp.id_componente)}
      <button type="button" onclick={() => (componenteSel = comp.id_componente)} class={TARJETA}>
        <span class="flex items-center justify-between gap-2">
          <span class="text-[15px] font-semibold text-ink">{comp.tipo_componente}</span>
          {#if comp.es_titular_componente}
            <span class="{CHIP} bg-amber-100 text-ocre" title="Titular del componente">
              <Crown size={12} aria-hidden="true" /> Titular
            </span>
          {:else if comp.imparte}
            <span class="{CHIP} bg-emerald-50 text-emerald-700">Imparte</span>
          {:else}
            <span class="{CHIP} bg-slate-100 text-slate-600" title="Supervisión como titular del curso">Supervisión</span>
          {/if}
        </span>
        <span class="mt-auto text-xs font-medium text-slate-600">{@render datosComponente(comp)}</span>
      </button>
    {/each}
  </div>
{:else if cursoActual && componenteActual}
  <!-- Paso 3: lo propio del centro -->
  {@render contenido(cursoActual, componenteActual)}
{/if}
