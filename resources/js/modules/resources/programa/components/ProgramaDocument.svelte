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
  import { BookOpen, ExternalLink, Link } from 'lucide-svelte';
  import { bloquesDeSeccion, textoDeSeccion } from '../utils/syllabusTexto';

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
    /**
     * 'documento' (docente/admin): una sola columna de lectura sin tarjetas.
     * 'tarjetas' (estudiante, T28): cada sección en su propia tarjeta, mismo
     * contenido íntegro.
     */
    variante?: 'documento' | 'tarjetas';
  }

  let { secciones, pendientes = [], actions, variante = 'documento' }: Props = $props();

  const enTarjetas = $derived(variante === 'tarjetas');

  const contenidoDe = (s: Seccion) => textoDeSeccion(s.contenidos ?? s.contenidos_programa);

  /** Sólo se dibujan las secciones con contenido; las vacías van a «pendientes». */
  const conContenido = $derived(secciones.filter((s) => contenidoDe(s).trim() !== ''));

  interface SegmentoTexto {
    tipo: 'texto' | 'url' | 'archivo';
    texto: string;
    href?: string;
  }

  function segmentarTexto(texto: string | null | undefined): SegmentoTexto[] {
    if (!texto) return [];
    const partes = texto.split(
      /(https?:\/\/[^\s,\])>]+|\/api\/bibliografias\/[a-zA-Z0-9-]+\/archivo)/g,
    );
    return partes.map((parte): SegmentoTexto => {
      if (/^\/api\/bibliografias\/[a-zA-Z0-9-]+\/archivo$/.test(parte)) {
        return { tipo: 'archivo', texto: 'Ver archivo', href: parte };
      }
      if (/^https?:\/\//.test(parte)) {
        return { tipo: 'url', texto: parte, href: parte };
      }
      return { tipo: 'texto', texto: parte };
    });
  }

  /** Primer enlace del texto (URL o archivo), para el ícono de «abrir» del recurso. */
  const enlaceDe = (texto: string) => segmentarTexto(texto).find((s) => s.href)?.href;

  /** La cita llega entre «guillemets»; como línea propia se lee sin ellos. */
  const sinGuillemets = (cita: string) => cita.replace(/^«\s*/, '').replace(/\s*»$/, '');

  const NUM = 'min-w-[34px] shrink-0 whitespace-nowrap font-mono text-[13px] text-slate-600';
  const TITULO = 'm-0 min-w-0 break-words text-[19px] font-semibold tracking-[-0.01em] text-ink';
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
  class="flex w-full flex-col text-[15px] leading-[1.65] text-ink {enTarjetas
    ? 'programa-tarjetas gap-4'
    : 'max-w-190 gap-7.5 rounded-[10px] border border-gray-200 bg-white px-6 py-9 sm:px-12 sm:pb-10'}"
>
  {#each conContenido as seccion (seccion.numeral_romano ?? seccion.nombre_seccion)}
    {@const bloques = bloquesDeSeccion(contenidoDe(seccion), seccion.numeral_romano)}
    <section
      id="seccion-{seccion.numeral_romano}"
      class="flex scroll-mt-6 flex-col gap-3 {enTarjetas
        ? 'programa-tarjeta rounded-2xl border border-gray-200 bg-white p-5 sm:p-7'
        : ''}"
      aria-labelledby="titulo-{seccion.numeral_romano}"
    >
      <div class="flex items-baseline gap-2.5">
        <span class={NUM}>{seccion.numeral_romano}.</span>
        <h2 id="titulo-{seccion.numeral_romano}" class={TITULO}>{seccion.nombre_seccion}</h2>
        <a
          href="#seccion-{seccion.numeral_romano}"
          class="shrink-0 text-slate-300 transition-colors hover:text-navy"
          aria-label="Enlace a la sección {seccion.nombre_seccion}"
        >
          <Link size={13} aria-hidden="true" />
        </a>
      </div>

      <!-- Sección I: rejilla de identificación a dos columnas -->
      {#if seccion.numeral_romano === 'I'}
        <dl
          class="m-0 grid grid-cols-1 gap-x-7 gap-y-3 border-y border-gray-100 py-4 sm:grid-cols-2"
        >
          {#each bloques as bloque, index (bloque.tipo + index)}
            {#if bloque.tipo === 'campo'}
              <div class="flex flex-col">
                <dt class="text-[12px] text-ink-2">{bloque.etiqueta}</dt>
                <dd class="m-0 text-[14.5px] font-medium">{bloque.valor}</dd>
              </div>
            {:else if bloque.tipo === 'parrafo'}
              <p class="m-0 text-pretty sm:col-span-2">{@render renderizarTexto(bloque.texto)}</p>
            {/if}
          {/each}
        </dl>
      {:else}
        {#each bloques as bloque, index (bloque.tipo + index)}
          {#if bloque.tipo === 'subtitulo'}
            <div class="mt-3.5 border-b border-line pb-0.5 first:mt-0">
              <span
                class="font-mono text-[12px] font-bold tracking-[0.03em] text-navy uppercase"
              >
                {bloque.texto}
              </span>
            </div>
          {:else if bloque.tipo === 'campo'}
            <p class="m-0 flex flex-wrap gap-x-2.5">
              <span class="font-mono text-[12px] text-ink-2">{bloque.etiqueta}</span>
              <span class="min-w-0 flex-[1_1_12rem] text-pretty">{@render renderizarTexto(bloque.valor)}</span
              >
            </p>
          {:else if bloque.tipo === 'lista'}
            {@const esListaBiblio =
              seccion.numeral_romano === 'VIII' ||
              bloque.items.some((i) => i.texto.includes('Biblioteca UTA'))}
            {#if esListaBiblio && enTarjetas}
              <!-- Vista del alumno: una fila por recurso, con ícono de libro, la
                   referencia y, debajo, la cita en tamaño de apoyo (antes era
                   una segunda línea itálica de 11–12 px casi idéntica). -->
              <ul class="m-0 flex list-none flex-col p-0">
                {#each bloque.items as item (item.texto)}
                  {@const url = enlaceDe(item.texto)}
                  <li
                    class="flex items-start gap-3 border-b border-slate-200 py-4 last:border-b-0 {item.texto.includes('Biblioteca UTA')
                      ? 'bg-brand-light'
                      : ''}"
                  >
                    <BookOpen class="mt-0.5 h-5 w-5 shrink-0 text-uta-blue" aria-hidden="true" />
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                      <span class="text-pretty text-[15px] font-semibold leading-snug text-slate-900">
                        {@render renderizarTexto(item.texto)}
                      </span>
                      {#if item.cita}
                        <span class="text-pretty text-[13px] leading-snug text-slate-600">
                          {@render renderizarTexto(sinGuillemets(item.cita))}
                        </span>
                      {/if}
                    </div>
                    {#if url}
                      <a
                        href={url}
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-1 shrink-0 text-uta-blue"
                        aria-label="Abrir el recurso (se abre en otra pestaña)"
                      >
                        <ExternalLink class="h-3.5 w-3.5" aria-hidden="true" />
                      </a>
                    {/if}
                  </li>
                {/each}
              </ul>
            {:else}
            <ul class="m-0 flex list-disc flex-col gap-1.5 marker:text-gray-400">
              {#each bloque.items as item (item.texto)}
                {@const esUta = item.texto.includes('Biblioteca UTA')}
                <li
                  class:ml-5={item.anidado}
                  class="
                    text-pretty transition-colors list-inside {esListaBiblio
                    ? `px-2 py-0.5 border border-transparent ${esUta ? 'bg-brand-light' : ''}`
                    : ``}"
                >
                  <span>{@render renderizarTexto(item.texto)}</span>
                  {#if item.cita}
                    <span class="block mt-0.5 text-[13px] italic text-ink-2 leading-snug">
                      {@render renderizarTexto(item.cita)}
                    </span>
                  {/if}
                </li>
              {/each}
            </ul>
            {/if}
          {:else}
            <p class="m-0 text-pretty">{@render renderizarTexto(bloque.texto)}</p>
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
                <div class="flex items-center justify-between border-t border-slate-200 py-2.5 text-[14px]">
                  <span class="font-medium text-slate-600">{fila.etiqueta}</span>
                  <span class="font-semibold tabular-nums text-slate-900">{fila.valor}</span>
                </div>
              {/each}
            </div>
          {/each}
        </div>
        <div class="overflow-x-auto rounded-lg border border-gray-100 max-sm:hidden">
          <table class="w-full border-collapse text-[14px]">
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
          <p class="m-0 text-[13px] text-ink-2">
            Ponderación de la prueba optativa:
            <strong class="font-semibold text-ink"
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
