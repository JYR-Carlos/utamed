<script lang="ts">
  /**
   * Índice del documento de syllabus — 240 px, pegado al scroll.
   *
   * Marca con ✓ las secciones que ya tienen contenido y lleva el progreso justo
   * debajo, no en la cabecera: es información que el docente consulta mientras
   * redacta. Cuando el documento ya está sellado o en manos del revisor, los ✓ y
   * el progreso desaparecen — no hay nada que completar.
   *
   * El índice lista lo que el documento trae, en su orden; las secciones que el
   * tipo no exige no se dibujan deshabilitadas ni cuentan para el progreso, y
   * las exigidas que faltan aparecen sin ✓ en vez de desaparecer.
   */
  import { Check, FileText, File, HelpCircle } from 'lucide-svelte';

  interface EntradaIndice {
    numeral: string;
    nombre: string;
    completa: boolean;
  }

  interface Props {
    entradas: EntradaIndice[];
    /** Numeral de la sección visible en la columna de lectura. */
    activa?: string | null;
    /** 'BASICO' | 'COMPLETO' — null cuando el documento aún no declara tipo. */
    tipo?: string | null;
    /** Numerales que el tipo exige completar antes de poder entregar. */
    requeridas?: string[];
    /** Muestra ✓ y barra de progreso (sólo mientras el documento se redacta). */
    mostrarProgreso?: boolean;
    onNavegar?: (numeral: string) => void;
  }

  let {
    entradas,
    activa = null,
    tipo = null,
    requeridas = [],
    mostrarProgreso = true,
    onNavegar,
  }: Props = $props();

  /**
   * El progreso mide lo exigido, no todo lo que trae el documento: hay
   * programas antiguos con secciones que su tipo no pide.
   */
  const exigidas = $derived(
    requeridas.length > 0 ? entradas.filter((e) => requeridas.includes(e.numeral)) : entradas,
  );
  const completas = $derived(exigidas.filter((e) => e.completa).length);
  const porcentaje = $derived(
    exigidas.length > 0 ? Math.round((completas / exigidas.length) * 100) : 0,
  );

  let ayudaVisible = $state(false);

  const nombresExigidos = $derived(exigidas.map((e) => `${e.numeral}. ${e.nombre}`).join(', '));
</script>

<aside class="flex w-full flex-col gap-3 lg:sticky lg:top-6 lg:w-[240px] lg:flex-none">
  <nav
    class="flex flex-col gap-px rounded-[10px] border border-gray-200 bg-white px-2 py-2.5"
    aria-label="Contenido del syllabus"
  >
    <span
      class="px-2.5 pt-1 pb-2 text-[11px] font-semibold tracking-[0.08em] text-ink-2 uppercase"
    >
      Contenido
    </span>
    {#each entradas as entrada (entrada.numeral)}
      {@const esActiva = activa === entrada.numeral}
      <a
        href="#seccion-{entrada.numeral}"
        onclick={() => onNavegar?.(entrada.numeral)}
        aria-current={esActiva ? 'true' : undefined}
        class="flex items-center gap-2.5 rounded-[7px] px-2.5 py-[7px] text-[13px] no-underline transition-colors {esActiva
          ? 'bg-uta-blue-light font-semibold text-navy shadow-[inset_2px_0_0_var(--color-navy)]'
          : entrada.completa || !mostrarProgreso
            ? 'text-ink hover:bg-sand'
            : 'text-ink-2 hover:bg-sand'}"
      >
        <span
          class="w-[22px] shrink-0 font-mono text-[11.5px] {esActiva
            ? 'text-navy'
            : 'text-gray-400'}">{entrada.numeral}.</span
        >
        <span class="min-w-0 flex-1 truncate">{entrada.nombre}</span>
        {#if mostrarProgreso && entrada.completa}
          <Check size={14} class="shrink-0 text-emerald-600" aria-label="Con contenido" />
        {/if}
      </a>
    {/each}
  </nav>

  {#if tipo}
    <div class="relative flex flex-col gap-2">
      <button
        type="button"
        onclick={() => (ayudaVisible = !ayudaVisible)}
        aria-expanded={ayudaVisible}
        class="inline-flex w-fit items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-[12px] font-semibold text-ink transition-colors hover:bg-sand"
      >
        {#if tipo === 'BASICO'}
          <File size={13} class="text-ink-2" aria-hidden="true" />
        {:else}
          <FileText size={13} class="text-ink-2" aria-hidden="true" />
        {/if}
        Tipo {tipo}
        <HelpCircle size={13} class="text-gray-400" aria-hidden="true" />
      </button>
      {#if ayudaVisible}
        <p
          class="m-0 rounded-lg bg-ink px-2.5 py-2 text-[12px] leading-[1.5] text-white shadow-[0_6px_16px_rgba(0,0,0,.2)]"
          role="status"
        >
          El tipo {tipo} exige {exigidas.length}
          {exigidas.length === 1 ? 'sección' : 'secciones'}: {nombresExigidos}.
          {#if entradas.length > exigidas.length}
            Las demás se leen, pero no bloquean la entrega.
          {/if}
        </p>
      {/if}
    </div>
  {/if}

  {#if mostrarProgreso}
    <div class="flex flex-col gap-1.5 px-1">
      <p class="m-0 text-[11.5px] text-ink-2">
        <strong class="font-semibold text-ink">{completas} de {exigidas.length}</strong>
        {exigidas.length === 1 ? 'sección completa' : 'secciones completas'}
      </p>
      <div
        class="h-[5px] overflow-hidden rounded-full bg-gray-200"
        role="progressbar"
        aria-valuenow={porcentaje}
        aria-valuemin="0"
        aria-valuemax="100"
        aria-label="Progreso del syllabus"
      >
        <div class="h-full bg-navy transition-all" style="width: {porcentaje}%"></div>
      </div>
    </div>
  {/if}
</aside>
