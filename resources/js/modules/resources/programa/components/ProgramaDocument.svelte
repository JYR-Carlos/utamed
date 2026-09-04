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
  import { Link } from 'lucide-svelte';
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
  }

  let { secciones, pendientes = [], actions }: Props = $props();

  const contenidoDe = (s: Seccion) => textoDeSeccion(s.contenidos ?? s.contenidos_programa);

  /** Sólo se dibujan las secciones con contenido; las vacías van a «pendientes». */
  const conContenido = $derived(secciones.filter((s) => contenidoDe(s).trim() !== ''));

  const NUM = 'w-[34px] shrink-0 font-mono text-[12.5px] text-[#9AA0AE]';
  const TITULO = 'm-0 text-[19px] font-semibold tracking-[-0.01em] text-[#1A1A24]';
</script>

<article
  class="flex w-full max-w-[760px] flex-col gap-[30px] rounded-[10px] border border-[#E5E7EB] bg-white px-6 py-9 text-[15px] leading-[1.65] text-[#1A1A24] sm:px-12 sm:pb-10"
>
  {#each conContenido as seccion (seccion.numeral_romano ?? seccion.nombre_seccion)}
    {@const bloques = bloquesDeSeccion(contenidoDe(seccion), seccion.numeral_romano)}
    <section
      id="seccion-{seccion.numeral_romano}"
      class="flex scroll-mt-6 flex-col gap-3"
      aria-labelledby="titulo-{seccion.numeral_romano}"
    >
      <div class="flex items-baseline gap-2.5">
        <span class={NUM}>{seccion.numeral_romano}.</span>
        <h2 id="titulo-{seccion.numeral_romano}" class={TITULO}>{seccion.nombre_seccion}</h2>
        <a
          href="#seccion-{seccion.numeral_romano}"
          class="text-[#C3C8D2] transition-colors hover:text-[#002F6C]"
          aria-label="Enlace a la sección {seccion.nombre_seccion}"
        >
          <Link size={13} aria-hidden="true" />
        </a>
      </div>

      <!-- Sección I: rejilla de identificación a dos columnas -->
      {#if seccion.numeral_romano === 'I'}
        <dl
          class="m-0 grid grid-cols-1 gap-x-7 gap-y-3 border-y border-[#EDEFF3] py-4 sm:grid-cols-2"
        >
          {#each bloques as bloque}
            {#if bloque.tipo === 'campo'}
              <div class="flex flex-col">
                <dt class="text-[12px] text-[#5A5E6E]">{bloque.etiqueta}</dt>
                <dd class="m-0 text-[14.5px] font-medium">{bloque.valor}</dd>
              </div>
            {:else if bloque.tipo === 'parrafo'}
              <p class="m-0 text-pretty sm:col-span-2">{bloque.texto}</p>
            {/if}
          {/each}
        </dl>
      {:else}
        {#each bloques as bloque}
          {#if bloque.tipo === 'subtitulo'}
            <p class="m-0 mt-1 text-[13px] font-semibold tracking-[0.02em] text-[#5A5E6E]">
              {bloque.texto}
            </p>
          {:else if bloque.tipo === 'campo'}
            <p class="m-0 flex flex-wrap gap-x-2.5">
              <span class="font-mono text-[12px] text-[#5A5E6E]">{bloque.etiqueta}</span>
              <span class="min-w-0 flex-1 text-pretty">{bloque.valor}</span>
            </p>
          {:else if bloque.tipo === 'lista'}
            <ul class="m-0 flex list-disc flex-col gap-[7px] pl-5 marker:text-[#9AA0AE]">
              {#each bloque.items as item}
                <li class:ml-5={item.anidado} class="text-pretty">{item.texto}</li>
              {/each}
            </ul>
          {:else}
            <p class="m-0 text-pretty">{bloque.texto}</p>
          {/if}
        {/each}
      {/if}

      <!-- Sección IX: tabla de componentes de evaluación (dato estructurado real) -->
      {#if seccion.componentes && seccion.componentes.length > 0}
        <div class="overflow-x-auto rounded-lg border border-[#EDEFF3]">
          <table class="w-full border-collapse text-[14px]">
            <thead>
              <tr class="bg-[#FCFBF9]">
                <th
                  class="px-3.5 py-2.5 text-left text-[11px] font-semibold tracking-[0.04em] text-[#5A5E6E] uppercase"
                  >Componente</th
                >
                <th
                  class="px-3.5 py-2.5 text-left text-[11px] font-semibold tracking-[0.04em] text-[#5A5E6E] uppercase"
                  >%</th
                >
                <th
                  class="px-3.5 py-2.5 text-left text-[11px] font-semibold tracking-[0.04em] text-[#5A5E6E] uppercase"
                  >Acta</th
                >
                <th
                  class="px-3.5 py-2.5 text-left text-[11px] font-semibold tracking-[0.04em] text-[#5A5E6E] uppercase"
                  >Aprobación oblig.</th
                >
                <th
                  class="px-3.5 py-2.5 text-left text-[11px] font-semibold tracking-[0.04em] text-[#5A5E6E] uppercase"
                  >Asistencia oblig.</th
                >
              </tr>
            </thead>
            <tbody>
              {#each seccion.componentes as comp}
                <tr class="border-t border-[#EDEFF3]">
                  <td class="px-3.5 py-2.5 font-medium">{comp.componente}</td>
                  <td class="px-3.5 py-2.5 tabular-nums">{comp.porcentaje}%</td>
                  <td class="px-3.5 py-2.5 text-[#5A5E6E]">{comp.genera_acta ? 'Sí' : 'No'}</td>
                  <td class="px-3.5 py-2.5 text-[#5A5E6E]"
                    >{comp.aprobacion_obligatoria ? 'Sí' : 'No'}</td
                  >
                  <td class="px-3.5 py-2.5 tabular-nums text-[#5A5E6E]">
                    {comp.asistencia_obligatoria != null ? `${comp.asistencia_obligatoria}%` : '—'}
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
        {#if seccion.ponderacion_optativa?.porcentaje}
          <p class="m-0 text-[13px] text-[#5A5E6E]">
            Ponderación de la prueba optativa:
            <strong class="font-semibold text-[#1A1A24]"
              >{seccion.ponderacion_optativa.porcentaje}%</strong
            >
          </p>
        {/if}
      {/if}
    </section>
  {/each}

  <!-- Lo que el tipo de syllabus exige y todavía no está escrito -->
  {#if pendientes.length > 0}
    <div class="flex flex-col gap-3 border-t border-[#EDEFF3] pt-[22px]">
      <span class="text-[12px] font-semibold tracking-[0.06em] text-[#5A5E6E] uppercase">
        Pendientes de redactar
      </span>
      <div class="flex flex-col gap-2.5">
        {#each pendientes as seccion (seccion.numeral_romano)}
          <div
            class="flex items-center gap-3 rounded-lg border border-dashed border-[#D6D9E0] bg-[#FCFBF9] px-4 py-3.5"
          >
            <span class={NUM}>{seccion.numeral_romano}.</span>
            <span class="text-[14.5px] font-medium text-[#5A5E6E]">{seccion.nombre_seccion}</span>
            <span class="ml-auto text-[12.5px] text-[#9AA0AE]">Sin contenido</span>
          </div>
        {/each}
      </div>
    </div>
  {/if}

  {#if conContenido.length === 0 && pendientes.length === 0}
    <p class="m-0 text-[14px] text-[#9AA0AE]">Este documento todavía no tiene contenido.</p>
  {/if}

  {#if actions}
    {@render actions()}
  {/if}
</article>
