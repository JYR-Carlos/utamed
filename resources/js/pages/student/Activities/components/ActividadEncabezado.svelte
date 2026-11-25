<script lang="ts" module>
  import type { FileText } from 'lucide-svelte';

  export type IconoLucide = typeof FileText;

  export interface PestanaActividad {
    id: string;
    etiqueta: string;
    /** Segunda línea en escritorio («4 criterios · 36 pts»). */
    detalle?: string;
    /** Cuántos mensajes nuevos: píldora en la pestaña. */
    nuevos?: number;
    icono: IconoLucide;
  }
</script>

<script lang="ts">
  /**
   * Cabecera noche de la página de una actividad: curso, nombre, plazo o
   * nota, el tipo y la modalidad con su ícono («Actividad sumativa», que va
   * resaltada porque lleva nota, «Individual · Taller») y, abajo, las pestañas
   * de la página. La pestaña elegida toma el color de la tarjeta y se funde con el panel de abajo. El
   * borde superior lleva el color del curso (variables de estiloCurso).
   */
  import { Link } from '@inertiajs/svelte';
  import { tick } from 'svelte';
  import { Award, ChevronLeft, Sprout, TableProperties, User, Users } from 'lucide-svelte';
  import Reticula from '@/components/student/dm/Reticula.svelte';
  import { etiqueta, tituloCond } from '@/lib/dm';

  interface Props {
    idCurso: number;
    codigo: string;
    nombreCurso: string;
    nombre: string;
    esSumativa: boolean;
    esGrupal: boolean;
    /** «Grupal · 3 integrantes · Taller», en texto corrido: en pastillas se leía como ítems del enunciado (OBS-37). */
    modalidad: string;
    /** Lo más urgente, en amarillo: el plazo si falta entregar, la nota si ya está. */
    destacado?: { texto: string; icono: IconoLucide } | null;
    pestanas: PestanaActividad[];
    seleccion: string;
    /** id del panel que controlan las pestañas. */
    panel: string;
    onCambiar: (id: string) => void;
    /** Abre la rúbrica en una ventana; sin ella no hay ícono al lado del nombre. */
    onVerRubrica?: () => void;
  }

  let { idCurso, codigo, nombreCurso, nombre, esSumativa, esGrupal, modalidad, destacado = null, pestanas, seleccion, panel, onCambiar, onVerRubrica }: Props = $props();

  // Flechas, Inicio y Fin recorren las pestañas y las activan (patrón ARIA de
  // pestañas con activación automática); el foco va con la elegida.
  async function alPulsarTecla(e: KeyboardEvent) {
    const actual = pestanas.findIndex((p) => p.id === seleccion);
    const ultima = pestanas.length - 1;
    const destino =
      e.key === 'ArrowRight'
        ? (actual + 1) % pestanas.length
        : e.key === 'ArrowLeft'
          ? (actual - 1 + pestanas.length) % pestanas.length
          : e.key === 'Home'
            ? 0
            : e.key === 'End'
              ? ultima
              : null;
    if (destino === null) return;
    e.preventDefault();
    const id = pestanas[destino].id;
    onCambiar(id);
    await tick();
    document.getElementById(`tab-${id}`)?.focus();
  }

  const Modalidad = $derived(esGrupal ? Users : User);

  const columnas = $derived(
    ({ 2: 'max-lg:grid-cols-2', 3: 'max-lg:grid-cols-3', 4: 'max-lg:grid-cols-4' } as Record<number, string>)[pestanas.length] ?? '',
  );
</script>

<header class="sobre-noche relative overflow-hidden border-t-6 border-(--curso) bg-dm-noche">
  <Reticula class="left-[45%] lg:left-1/2" />
  <div class="relative flex flex-col gap-2.5 px-4 pt-2 pb-5 lg:px-10 lg:pt-8 lg:pb-7">
    <Link
      href={`/estudiante/cursos/${idCurso}`}
      class="inline-flex min-h-11 items-center gap-1 self-start text-sm font-semibold text-dm-noche-texto-2 no-underline hover:text-dm-noche-texto lg:hidden"
    >
      <ChevronLeft size={18} aria-hidden="true" />{nombreCurso}
    </Link>
    <span class="{etiqueta} text-dm-amarillo">
      {codigo}<span class="max-lg:hidden">{codigo ? ' · ' : ''}{nombreCurso}</span>
    </span>
    <!-- En línea: el ícono sigue a la última palabra del nombre. -->
    <div class="text-[34px] leading-[38px] lg:text-5xl lg:leading-12">
      <h1 id="t-actividad" class="{tituloCond} inline text-dm-noche-texto">{nombre}</h1>
      {#if onVerRubrica}{' '}
        <button
          type="button"
          aria-haspopup="dialog"
          aria-label="Ver la rúbrica"
          title="Ver la rúbrica"
          onclick={onVerRubrica}
          class="ml-1 inline-flex size-11 cursor-pointer items-center justify-center rounded-[10px] border border-dm-noche-chip align-middle text-dm-noche-texto hover:bg-dm-noche-activo lg:-mt-2"
        >
          <TableProperties size={20} aria-hidden="true" />
        </button>
      {/if}
    </div>
    <span class="flex flex-wrap items-center gap-x-3 gap-y-2 lg:mt-1">
      {#if destacado}
        {@const Icono = destacado.icono}
        <span
          class="inline-flex items-center gap-1.5 rounded-full bg-dm-amarillo px-2.5 py-0.5 text-[13px] leading-[18px] font-extrabold text-dm-sobre-amarillo"
        >
          <Icono size={14} aria-hidden="true" />{destacado.texto}
        </span>
      {/if}
      {#if esSumativa}
        <span class="inline-flex items-center gap-1.5 rounded-md bg-dm-noche-texto px-2.5 py-1 text-[15px] leading-5 font-extrabold text-dm-noche lg:text-base">
          <Award size={18} aria-hidden="true" class="shrink-0" />Actividad sumativa
        </span>
      {:else}
        <span class="inline-flex items-center gap-1.5 text-[15px] leading-[22px] font-semibold text-dm-noche-texto lg:text-base">
          <Sprout size={18} aria-hidden="true" class="shrink-0" />Actividad formativa
        </span>
      {/if}
      <span class="inline-flex items-center gap-1.5 text-[15px] leading-[22px] font-semibold text-dm-noche-texto lg:text-base">
        <Modalidad size={18} aria-hidden="true" class="shrink-0" />{modalidad}
      </span>
    </span>
  </div>

  <div
    role="tablist"
    aria-label="Secciones de la actividad"
    tabindex="-1"
    onkeydown={alPulsarTecla}
    class="relative grid gap-1 border-b border-dm-noche-borde px-2 lg:flex lg:flex-wrap lg:gap-2 lg:px-10 {columnas}"
  >
    {#each pestanas as p (p.id)}
      {@const elegida = p.id === seleccion}
      {@const Icono = p.icono}
      <button
        type="button"
        role="tab"
        id="tab-{p.id}"
        aria-selected={elegida}
        aria-controls={panel}
        tabindex={elegida ? 0 : -1}
        onclick={() => onCambiar(p.id)}
        class="relative -mb-px flex min-h-16 min-w-0 cursor-pointer flex-col items-center justify-center gap-1 rounded-t-[10px] border border-b-0 px-0.5 pt-2 pb-2.5 font-dm transition-colors focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-dm-amarillo lg:min-h-18 lg:min-w-57.5 lg:flex-row lg:justify-start lg:gap-3 lg:rounded-t-xl lg:px-5 lg:py-3 lg:text-left {elegida
          ? 'border-dm-linea bg-dm-tarjeta text-dm-ink shadow-[inset_0_4px_0_var(--color-dm-amarillo)]'
          : 'border-white/24 bg-white/8 text-dm-noche-texto hover:bg-white/16'}"
      >
        <span
          aria-hidden="true"
          class="relative flex shrink-0 items-center justify-center lg:size-10 lg:rounded-[10px] {elegida
            ? 'text-dm-accion-texto lg:bg-dm-accion-suave'
            : 'text-dm-noche-texto lg:bg-white/12'}"
        >
          <Icono size={20} aria-hidden="true" />
          {#if p.nuevos}
            <!-- Móvil: el número sobre el ícono. -->
            <span
              class="absolute -top-1.5 -right-3 min-w-4.5 rounded-full px-1.25 text-center text-xs leading-[18px] font-extrabold lg:hidden {elegida
                ? 'bg-dm-accion text-dm-sobre-accion'
                : 'bg-dm-amarillo text-dm-sobre-amarillo'}">{p.nuevos}</span
            >
          {/if}
        </span>
        <span class="flex min-w-0 flex-col lg:flex-1 lg:gap-0.5">
          <span class="text-[13px] leading-[18px] font-extrabold lg:text-[17px] lg:leading-[22px]">{p.etiqueta}</span>
          {#if p.detalle}
            <span
              class="text-[13px] leading-[18px] font-medium max-lg:hidden {elegida ? 'text-dm-ink-2' : 'text-dm-noche-texto-2'}"
              >{p.detalle}</span
            >
          {/if}
        </span>
        {#if p.nuevos}
          <span
            class="shrink-0 rounded-full px-2 text-xs leading-[18px] font-extrabold max-lg:sr-only {elegida
              ? 'bg-dm-accion text-dm-sobre-accion'
              : 'bg-dm-amarillo text-dm-sobre-amarillo'}">{p.nuevos} {p.nuevos === 1 ? 'nuevo' : 'nuevos'}</span
          >
        {/if}
      </button>
    {/each}
  </div>
</header>
