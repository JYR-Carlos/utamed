<script lang="ts">
  /**
   * Contenido de una sección del programa en la vista del alumno, sin su
   * encabezado: lo comparten la pestaña Programa (`tarjeta`, cada sección en
   * su tarjeta) y la versión para imprimir (`documento`, una sola hoja).
   *
   * La I es una ficha de campos, la VI se arma como tarjetas de unidad, la
   * VIII como lista de recursos agrupada y la IX suma su tabla de componentes;
   * el resto se compone con los bloques del texto (párrafos, listas, campos).
   */
  import type { BloqueSyllabus } from '@/modules/resources/programa/utils/syllabusTexto';
  import { bloquesDeSeccion, textoDeSeccion } from '@/modules/resources/programa/utils/syllabusTexto';
  import {
    agruparPorUnidad,
    gruposDeRecursos,
    itemsDeEnumeracion,
    porcentaje,
    type SeccionPrograma,
  } from '@/lib/programaAlumno';
  import { etiqueta } from '@/lib/dm';
  import ListaRecursos from './ListaRecursos.svelte';
  import TextoEnlazado from './TextoEnlazado.svelte';

  interface Props {
    seccion: SeccionPrograma;
    variante?: 'tarjeta' | 'documento';
  }

  let { seccion, variante = 'tarjeta' }: Props = $props();

  const esDocumento = $derived(variante === 'documento');
  const bloques = $derived(bloquesDeSeccion(textoDeSeccion(seccion.contenidos), seccion.numeral_romano));
  const unidades = $derived(seccion.numeral_romano === 'VI' ? agruparPorUnidad(bloques) : null);
  const grupos = $derived(seccion.numeral_romano === 'VIII' ? gruposDeRecursos([seccion]) : []);

  const COLUMNAS_IX = 'md:grid md:grid-cols-[minmax(0,1fr)_120px_190px_150px] md:items-center md:gap-4';
</script>

<!-- Bloques genéricos. `apoyo`: texto secundario, como el de dentro de una unidad. -->
{#snippet bloquesGenericos(lista: BloqueSyllabus[], apoyo = false)}
  {#each lista as bloque, i (i)}
    {#if bloque.tipo === 'subtitulo'}
      <p class="{etiqueta} mt-2 text-dm-ink-3 first:mt-0">{bloque.texto}</p>
    {:else if bloque.tipo === 'campo'}
      {@const items = itemsDeEnumeracion(bloque.valor)}
      {#if items}
        <div class="flex flex-col gap-1">
          <p class="font-semibold {apoyo ? 'text-dm-ink' : ''}">{bloque.etiqueta}</p>
          <ul class="flex list-disc flex-col gap-1 pl-5 marker:text-dm-ink-3">
            {#each items as item, j (j)}<li>{item}</li>{/each}
          </ul>
        </div>
      {:else}
        <p class="text-pretty">
          <span class="text-dm-ink-2">{bloque.etiqueta}:</span>
          <TextoEnlazado texto={bloque.valor} />
        </p>
      {/if}
    {:else if bloque.tipo === 'lista'}
      <ul class="flex list-disc flex-col gap-1.5 pl-5 marker:text-dm-ink-3">
        {#each bloque.items as item, j (j)}
          <li class={item.anidado ? 'ml-5' : ''}>
            <TextoEnlazado texto={item.texto} />
            {#if item.cita}
              <span class="mt-0.5 block text-[13px] leading-[18px] text-dm-ink-2"><TextoEnlazado texto={item.cita} /></span>
            {/if}
          </li>
        {/each}
      </ul>
    {:else}
      <p class="max-w-170 text-pretty"><TextoEnlazado texto={bloque.texto} /></p>
    {/if}
  {/each}
{/snippet}

<div
  class="flex flex-col gap-3 {esDocumento ? 'text-base leading-[26px]' : 'text-[15px] leading-6'}"
>
  {#if seccion.numeral_romano === 'I'}
    <dl
      class="m-0 grid {esDocumento
        ? 'gap-x-6 gap-y-2 sm:grid-cols-[200px_minmax(0,1fr)]'
        : 'grid-cols-[repeat(auto-fill,minmax(200px,1fr))] gap-x-6 gap-y-4'}"
    >
      {#each bloques as bloque, i (i)}
        {#if bloque.tipo === 'campo'}
          {#if esDocumento}
            <dt class="text-dm-ink-2">{bloque.etiqueta}</dt>
            <dd class="m-0"><TextoEnlazado texto={bloque.valor} /></dd>
          {:else}
            <div class="flex flex-col gap-0.5">
              <dt class="text-[13px] leading-[18px] text-dm-ink-2">{bloque.etiqueta}</dt>
              <dd class="m-0 font-semibold"><TextoEnlazado texto={bloque.valor} /></dd>
            </div>
          {/if}
        {:else}
          <div class="col-span-full">{@render bloquesGenericos([bloque])}</div>
        {/if}
      {/each}
    </dl>
  {:else if unidades && unidades.unidades.length > 0}
    {@render bloquesGenericos(unidades.antes)}
    <div
      class="grid {esDocumento
        ? 'gap-x-8 gap-y-5 sm:grid-cols-2'
        : 'gap-3 lg:grid-cols-[repeat(auto-fill,minmax(280px,1fr))] lg:gap-4'}"
    >
      {#each unidades.unidades as unidad, i (i)}
        {#if esDocumento}
          <div class="flex flex-col gap-1 break-inside-avoid">
            <h3 class="text-base leading-[22px] font-bold">
              {[unidad.numero, unidad.nombre].filter(Boolean).join(' · ')}
            </h3>
            <div class="flex flex-col gap-2 text-[15px] leading-6 text-dm-ink-2">
              {@render bloquesGenericos(unidad.bloques, true)}
            </div>
          </div>
        {:else}
          <div
            class="flex flex-col gap-1.5 rounded-[10px] border border-l-4 border-dm-linea border-l-(--curso) p-3.5 lg:border-l lg:border-t-4 lg:border-l-dm-linea lg:border-t-(--curso) lg:p-4"
          >
            {#if unidad.numero}<span class="{etiqueta} text-dm-ink-3">{unidad.numero}</span>{/if}
            <h4 class="text-base leading-[22px] font-bold">{unidad.nombre}</h4>
            <div class="flex flex-col gap-2 text-sm leading-5 text-dm-ink-2">
              {@render bloquesGenericos(unidad.bloques, true)}
            </div>
          </div>
        {/if}
      {/each}
    </div>
  {:else if grupos.length > 0}
    {#each grupos as grupo (grupo.id)}
      <div class="flex flex-col">
        <p class="{etiqueta} border-b border-dm-linea pb-2 text-dm-ink-3">{grupo.titulo}</p>
        <ListaRecursos recursos={grupo.recursos} class="px-0" />
      </div>
    {/each}
  {:else}
    {@render bloquesGenericos(bloques)}
  {/if}

  {#if seccion.componentes && seccion.componentes.length > 0}
    <div class="overflow-hidden rounded-[10px] border border-dm-linea text-[15px] leading-[22px]">
      <div
        aria-hidden="true"
        class="{COLUMNAS_IX} {etiqueta} hidden border-b border-dm-linea bg-dm-sutil px-4 py-2.5 text-dm-ink-3"
      >
        <span>Componente</span><span>Ponderación</span><span>Aprobación por separado</span><span>Asistencia mínima</span>
      </div>
      {#each seccion.componentes as c, i (c.componente + i)}
        <div class="{COLUMNAS_IX} flex flex-col gap-0.5 border-b border-dm-linea px-4 py-3 last:border-b-0">
          <span class="font-semibold">{c.componente}</span>
          <span><span class="text-dm-ink-2 md:sr-only">Ponderación: </span>{porcentaje(c.porcentaje)}</span>
          <span
            ><span class="text-dm-ink-2 md:sr-only">Aprobación por separado: </span>{c.aprobacion_obligatoria ? 'Sí' : 'No'}</span
          >
          <span><span class="text-dm-ink-2 md:sr-only">Asistencia mínima: </span>{porcentaje(c.asistencia_obligatoria)}</span>
        </div>
      {/each}
    </div>
    {#if seccion.ponderacion_optativa?.porcentaje}
      <p class="text-sm text-dm-ink-2">
        Ponderación de la prueba optativa: <strong class="font-semibold text-dm-ink"
          >{porcentaje(seccion.ponderacion_optativa.porcentaje)}</strong
        >
      </p>
    {/if}
  {/if}
</div>
