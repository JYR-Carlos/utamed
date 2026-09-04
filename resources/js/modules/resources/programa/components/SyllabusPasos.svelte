<script lang="ts">
  /**
   * Barra de pasos del asistente de syllabus.
   *
   * La barra codifica dos cosas a la vez, progreso y permisos:
   *
   *   activo      azul sólido
   *   completo    blanco con ✓ verde — la sección ya tiene contenido
   *   pendiente   gris — aún no se ha escrito
   *   bloqueado   borde punteado y candado — no tienes el permiso de módulo
   *               (`cursos/programas/modificar:modulo_N`); se puede LEER
   *   con errores borde rojo y contador de campos obligatorios que faltan
   *
   * Ningún paso se deshabilita para navegar: todos se pueden abrir, porque leer
   * el documento entero es parte de escribir tu sección.
   */
  import { Check, Lock } from 'lucide-svelte';
  import type { IdPaso, PasoSyllabus } from '../utils/syllabusPasos';

  interface Props {
    pasos: PasoSyllabus[];
    actual: IdPaso;
    /** Numerales con contenido escrito. */
    completos: string[];
    /** Numerales que este usuario puede escribir. */
    editables: string[];
    /** Numeral → nº de campos obligatorios que faltan. */
    errores: Record<string, number>;
    onIr: (id: IdPaso) => void;
  }

  let { pasos, actual, completos, editables, errores, onIr }: Props = $props();

  /** Con pocos pasos la barra respira en horizontal; con nueve, en vertical. */
  const compacta = $derived(pasos.length >= 7);
</script>

<nav
  class="flex gap-1.5 overflow-x-auto border-b border-[#E5E7EB] px-5 py-4 sm:px-7"
  aria-label="Secciones del syllabus"
>
  {#each pasos as paso (paso.id)}
    {@const Icono = paso.icono}
    {@const esActual = actual === paso.id}
    {@const numeral = paso.numeral ?? ''}
    {@const bloqueado = !!paso.numeral && !editables.includes(numeral)}
    {@const completo = !!paso.numeral && completos.includes(numeral)}
    {@const faltan = errores[numeral] ?? 0}
    <button
      type="button"
      onclick={() => onIr(paso.id)}
      aria-current={esActual ? 'step' : undefined}
      title={bloqueado ? `${numeral}. ${paso.titulo} — sección de otro responsable` : `${numeral ? numeral + '. ' : ''}${paso.titulo}`}
      class="relative flex min-w-[104px] flex-1 items-center gap-2 rounded-[9px] border px-2.5 py-2.5 transition-colors {compacta
        ? 'flex-col justify-start gap-1.5 text-center'
        : 'justify-center'} {esActual
        ? 'border-[#002F6C] bg-[#002F6C]'
        : faltan > 0
          ? 'border-[1.5px] border-[#DC2626] bg-white'
          : bloqueado
            ? 'border-dashed border-[#D6D9E0] bg-[#F7F8FA] hover:bg-[#EDEFF3]'
            : completo
              ? 'border-[#D6D9E0] bg-white hover:bg-[#F5F1EA]'
              : 'border-[#EDEFF3] bg-[#F7F8FA] hover:bg-[#EDEFF3]'}"
    >
      {#if bloqueado && !esActual}
        <Lock size={16} class="shrink-0 text-[#9AA0AE]" aria-hidden="true" />
      {:else}
        <Icono
          size={16}
          class="shrink-0 {esActual
            ? 'text-white'
            : faltan > 0
              ? 'text-[#DC2626]'
              : 'text-[#9AA0AE]'}"
          aria-hidden="true"
        />
      {/if}

      <span
        class="inline-flex items-center gap-1 text-[11.5px] leading-[1.25] {esActual
          ? 'text-white'
          : faltan > 0
            ? 'font-semibold text-[#B91C1C]'
            : completo && !bloqueado
              ? 'text-[#1A1A24]'
              : 'text-[#9AA0AE]'}"
      >
        {#if completo && !esActual && faltan === 0}
          <Check size={12} class="shrink-0 text-[#059669]" aria-hidden="true" />
        {/if}
        {#if numeral}
          <b class={esActual ? 'font-bold' : 'font-semibold'}>{numeral}.</b>
        {/if}
        {paso.corto}
      </span>

      {#if faltan > 0 && !esActual}
        <span
          class="absolute -top-[7px] -right-[7px] flex h-[19px] min-w-[19px] items-center justify-center rounded-full bg-[#DC2626] px-1.5 text-[11px] font-bold text-white"
          aria-label="{faltan} campos obligatorios pendientes"
        >
          {faltan}
        </span>
      {/if}
    </button>
  {/each}
</nav>
