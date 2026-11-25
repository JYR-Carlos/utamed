<script lang="ts">
  /**
   * Columna de lectura del syllabus — 760 px, una sola medida, sin tarjetas por
   * sección (lámina «Visor de syllabus (documento)»).
   *
   * El documento se lee; escribir ocurre en el wizard (`SyllabusModal`). Por eso
   * aquí no hay ningún control de edición: el pie de acciones vive en la página.
   *
   * Las secciones llegan ya aplanadas a texto por `App\Traits\ParsesSyllabus`;
   * `bloquesDeSeccion` recupera su estructura (campos, subtítulos, listas) para
   * componerlas tipográficamente. La única sección con datos estructurados de
   * verdad es la IX, que trae su tabla de componentes de evaluación.
   */
  import type { Snippet } from 'svelte';
  import { bloquesDeSeccion, segmentarTexto, textoDeSeccion } from '../utils/syllabusTexto';

  interface ComponenteEvaluacion {
    componente: string;
    porcentaje: number | string;
    genera_acta?: boolean;
    aprobacion_obligatoria?: boolean;
    asistencia_obligatoria?: number | string | null;
  }

  interface Seccion {
    numeral_romano?: string;
    nombre_seccion: string;
    contenidos?: Array<{ texto_contenido: string | null }>;
    contenidos_programa?: Array<{ texto_contenido: string | null }>;
    componentes?: ComponenteEvaluacion[];
    ponderacion_optativa?: { porcentaje?: number } | null;
  }

  interface Props {
    secciones: Seccion[];
    /** Secciones exigidas por el tipo de syllabus que aún no tienen contenido. */
    pendientes?: Seccion[];
    /** Snippet para el sello/acciones que el padre inyecta bajo el documento. */
    actions?: Snippet;
  }

  // El alumno lee el programa con sus propios componentes (pages/student/Courses).
  let { secciones, pendientes = [], actions }: Props = $props();

  const contenidoDe = (s: Seccion) => textoDeSeccion(s.contenidos ?? s.contenidos_programa);

  /** Sólo se dibujan las secciones con contenido; las vacías van a «pendientes». */
  const conContenido = $derived(secciones.filter((s) => contenidoDe(s).trim() !== ''));

  // Número y título en la misma letra (formato oficial del programa).
  const NUM = 'min-w-[36px] shrink-0 whitespace-nowrap';
  const TITULO =
    'm-0 flex min-w-0 items-baseline break-words border-b-2 border-ink pb-2.5 font-inst-titulo text-[19px] leading-[25px] font-bold text-ink';
  /** Etiqueta y valor en columnas. */
  const CAMPOS = 'm-0 grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-[180px_minmax(0,1fr)]';
  const JUSTO = 'm-0 text-justify hyphens-auto';
</script>

{#snippet renderizarTexto(texto: string | null | undefined)}
  {#each segmentarTexto(texto) as seg (seg.texto + seg.tipo)}
    {#if seg.tipo === 'archivo'}
      <a
        href={seg.href}
        target="_blank"
        rel="noopener noreferrer"
        class="font-medium text-navy underline hover:text-navy-2"
      >
        Ver archivo
      </a>
    {:else if seg.tipo === 'url'}
      <a
        href={seg.href}
        target="_blank"
        rel="noopener noreferrer"
        class="text-navy underline hover:text-navy-2 break-all"
      >
        {seg.texto}
      </a>
    {:else}
      {seg.texto}
    {/if}
  {/each}
{/snippet}

<article
  class="flex w-full max-w-190 flex-col gap-7.5 rounded-[10px] border border-gray-200 bg-white px-6 py-9 text-[15px] leading-[1.65] text-ink sm:px-12 sm:pb-10"
>
  {#each conContenido as seccion (seccion.numeral_romano ?? seccion.nombre_seccion)}
    {@const bloques = bloquesDeSeccion(contenidoDe(seccion), seccion.numeral_romano)}
    <section
      id="seccion-{seccion.numeral_romano}"
      class="flex scroll-mt-[calc(var(--alto-cabecera,0px)+24px)] flex-col gap-3"
      aria-labelledby="titulo-{seccion.numeral_romano}"
    >
      <h2 id="titulo-{seccion.numeral_romano}" class={TITULO}>
        <span class={NUM}>{seccion.numeral_romano}.</span>
        <span>{seccion.nombre_seccion}</span>
      </h2>

      <!-- Sección I: rejilla de identificación a dos columnas -->
      {#if seccion.numeral_romano === 'I'}
        <dl class={CAMPOS}>
          {#each bloques as bloque, index (bloque.tipo + index)}
            {#if bloque.tipo === 'campo'}
              <dt class="font-bold">{bloque.etiqueta}</dt>
              <dd class="m-0">{bloque.valor}</dd>
            {:else if bloque.tipo === 'parrafo'}
              <p class="{JUSTO} sm:col-span-2">{@render renderizarTexto(bloque.texto)}</p>
            {/if}
          {/each}
        </dl>
      {:else}
        {#each bloques as bloque, index (bloque.tipo + index)}
          {#if bloque.tipo === 'subtitulo'}
            <h3 class="m-0 mt-2 text-[15px] font-bold text-ink first:mt-0">{bloque.texto}</h3>
          {:else if bloque.tipo === 'campo'}
            <p class={CAMPOS}>
              <span class="font-bold">{bloque.etiqueta}</span>
              <span class="min-w-0 text-justify hyphens-auto">{@render renderizarTexto(bloque.valor)}</span>
            </p>
          {:else if bloque.tipo === 'lista'}
            {@const esListaBiblio =
              seccion.numeral_romano === 'VIII' ||
              bloque.items.some((i) => i.texto.includes('Biblioteca UTA'))}
            <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 marker:text-inst-enlace">
              {#each bloque.items as item (item.texto)}
                {@const esUta = item.texto.includes('Biblioteca UTA')}
                <li
                  class:ml-5={item.anidado}
                  class="
                    text-justify hyphens-auto pl-1 transition-colors {esListaBiblio
                    ? `px-2 py-0.5 border border-transparent ${esUta ? 'bg-brand-light' : ''}`
                    : ``}"
                >
                  <span>{@render renderizarTexto(item.texto)}</span>
                  {#if item.cita}
                    <span class="block mt-0.5 italic text-ink-2">
                      {@render renderizarTexto(item.cita)}
                    </span>
                  {/if}
                </li>
              {/each}
            </ul>
          {:else}
            <p class={JUSTO}>{@render renderizarTexto(bloque.texto)}</p>
          {/if}
        {/each}
      {/if}

      <!-- Sección IX: tabla de componentes de evaluación (dato estructurado real) -->
      {#if seccion.componentes && seccion.componentes.length > 0}
        <!-- En móvil la tabla no cabe (quedaba una columna oculta tras un scroll
             lateral): una tarjeta por componente, con una fila por dato. -->
        <div class="flex flex-col gap-3 sm:hidden">
          {#each seccion.componentes as comp, index (comp.componente + index)}
            <div class="flex flex-col rounded-xl border border-slate-200 bg-white px-4 pt-4 pb-1">
              <p class="m-0 pb-2 text-[15px] font-bold text-slate-900">{comp.componente}</p>
              {#each [
                { etiqueta: 'Ponderación', valor: `${comp.porcentaje}%` },
                { etiqueta: 'Genera acta', valor: comp.genera_acta ? 'Sí' : 'No' },
                { etiqueta: 'Aprobación oblig.', valor: comp.aprobacion_obligatoria ? 'Sí' : 'No' },
                { etiqueta: 'Asistencia oblig.', valor: comp.asistencia_obligatoria != null ? `${comp.asistencia_obligatoria}%` : '—' },
              ] as fila (fila.etiqueta)}
                <div class="flex items-center justify-between border-t border-slate-200 py-2.5 text-[15px]">
                  <span class="font-medium text-slate-600">{fila.etiqueta}</span>
                  <span class="font-semibold tabular-nums text-slate-900">{fila.valor}</span>
                </div>
              {/each}
            </div>
          {/each}
        </div>
        <div class="overflow-x-auto rounded-lg border border-gray-100 max-sm:hidden">
          <table class="w-full border-collapse text-[15px]">
            <thead>
              <tr class="bg-stone-50">
                <th
                  class="px-3.5 py-2.5 text-left text-[11px] font-semibold tracking-[0.04em] text-ink-2 uppercase"
                  >Componente</th
                >
                <th
                  class="px-3.5 py-2.5 text-left text-[11px] font-semibold tracking-[0.04em] text-ink-2 uppercase"
                  >%</th
                >
                <th
                  class="px-3.5 py-2.5 text-left text-[11px] font-semibold tracking-[0.04em] text-ink-2 uppercase"
                  >Acta</th
                >
                <th
                  class="px-3.5 py-2.5 text-left text-[11px] font-semibold tracking-[0.04em] text-ink-2 uppercase"
                  >Aprobación oblig.</th
                >
                <th
                  class="px-3.5 py-2.5 text-left text-[11px] font-semibold tracking-[0.04em] text-ink-2 uppercase"
                  >Asistencia oblig.</th
                >
              </tr>
            </thead>
            <tbody>
              {#each seccion.componentes as comp, index (comp.componente + index)}
                <tr class="border-t border-gray-100">
                  <td class="px-3.5 py-2.5 font-medium">{comp.componente}</td>
                  <td class="px-3.5 py-2.5 tabular-nums">{comp.porcentaje}%</td>
                  <td class="px-3.5 py-2.5 text-ink-2">{comp.genera_acta ? 'Sí' : 'No'}</td>
                  <td class="px-3.5 py-2.5 text-ink-2"
                    >{comp.aprobacion_obligatoria ? 'Sí' : 'No'}</td
                  >
                  <td class="px-3.5 py-2.5 tabular-nums text-ink-2">
                    {comp.asistencia_obligatoria != null ? `${comp.asistencia_obligatoria}%` : '—'}
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
        {#if seccion.ponderacion_optativa?.porcentaje}
          <p class="m-0">
            Ponderación de la prueba optativa:
            <strong
              >{seccion.ponderacion_optativa.porcentaje}%</strong
            >
          </p>
        {/if}
      {/if}
    </section>
  {/each}

  <!-- Lo que el tipo de syllabus exige y todavía no está escrito -->
  {#if pendientes.length > 0}
    <div class="flex flex-col gap-3 border-t border-gray-100 pt-[22px] print:hidden">
      <span class="text-[12px] font-semibold tracking-[0.06em] text-ink-2 uppercase">
        Pendientes de redactar
      </span>
      <div class="flex flex-col gap-2.5">
        {#each pendientes as seccion (seccion.numeral_romano)}
          <div
            class="flex items-center gap-3 rounded-lg border border-dashed border-line bg-stone-50 px-4 py-3.5"
          >
            <span class={NUM}>{seccion.numeral_romano}.</span>
            <span class="text-[14.5px] font-medium text-ink-2">{seccion.nombre_seccion}</span>
            <span class="ml-auto text-[12.5px] text-gray-400">Sin contenido</span>
          </div>
        {/each}
      </div>
    </div>
  {/if}

  {#if conContenido.length === 0 && pendientes.length === 0}
    <p class="m-0 text-[14px] text-gray-400">Este documento todavía no tiene contenido.</p>
  {/if}

  {#if actions}
    {@render actions()}
  {/if}
</article>
