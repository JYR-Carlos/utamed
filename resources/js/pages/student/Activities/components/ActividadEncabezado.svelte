<script lang="ts">
  /**
   * Cabecera noche de la página de una actividad: curso, nombre, tipo y
   * modalidad, y los accesos al enunciado y a la rúbrica. El borde inferior
   * lleva el color del curso (variables de estiloCurso en el contenedor).
   */
  import { Link } from '@inertiajs/svelte';
  import { ChevronLeft, ClipboardList, FileText } from 'lucide-svelte';
  import Reticula from '@/components/student/dm/Reticula.svelte';
  import { btnNoche, etiqueta, pildoraNoche, tituloCond } from '@/lib/dm';

  interface Props {
    idCurso: number;
    codigo: string;
    nombreCurso: string;
    nombre: string;
    chips: string[];
    onVerEnunciado?: () => void;
    onVerRubrica?: () => void;
  }

  let { idCurso, codigo, nombreCurso, nombre, chips, onVerEnunciado, onVerRubrica }: Props = $props();

  // En el móvil van lado a lado con rótulo corto si son dos.
  const dosAccesos = $derived(!!onVerEnunciado && !!onVerRubrica);
</script>

<section
  aria-labelledby="t-actividad"
  class="sobre-noche relative overflow-hidden border-b-6 border-(--curso) bg-dm-noche lg:rounded-xl lg:border lg:border-b-6 lg:border-dm-noche-borde lg:border-b-(--curso)"
>
  <Reticula class="left-[45%] lg:left-1/2" />
  <div class="relative flex flex-col gap-2.5 px-4 pt-3 pb-6 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between lg:gap-6 lg:px-10 lg:py-8">
    <div class="flex flex-col gap-2.5 lg:flex-[1_1_420px]">
      <Link
        href={`/estudiante/cursos/${idCurso}`}
        class="inline-flex min-h-11 items-center gap-1 self-start text-sm font-semibold text-dm-noche-texto-2 no-underline hover:text-dm-noche-texto lg:hidden"
      >
        <ChevronLeft size={18} aria-hidden="true" />{nombreCurso}
      </Link>
      <span class="{etiqueta} text-dm-amarillo">
        {codigo}<span class="max-lg:hidden">{codigo ? ' · ' : ''}{nombreCurso}</span>
      </span>
      <h1 id="t-actividad" class="{tituloCond} text-[34px] leading-[34px] text-dm-noche-texto lg:text-5xl lg:leading-12">
        {nombre}
      </h1>
      {#if chips.length > 0}
        <span class="flex flex-wrap gap-1.5 lg:mt-1 lg:gap-2">
          {#each chips as chip (chip)}<span class={pildoraNoche}>{chip}</span>{/each}
        </span>
      {/if}
    </div>

    {#if onVerEnunciado || onVerRubrica}
      <div class="mt-1.5 grid gap-2 lg:mt-0 lg:flex lg:flex-wrap {dosAccesos ? 'grid-cols-2' : ''}">
        {#if onVerEnunciado}
          <button type="button" class="{btnNoche} px-3 lg:min-h-10 lg:px-3.5" onclick={onVerEnunciado}>
            <FileText size={18} aria-hidden="true" class={dosAccesos ? 'max-lg:hidden' : ''} />
            {#if dosAccesos}<span class="lg:hidden">Enunciado</span><span class="max-lg:hidden">Ver enunciado</span
              >{:else}Ver enunciado{/if}
          </button>
        {/if}
        {#if onVerRubrica}
          <button type="button" class="{btnNoche} px-3 lg:min-h-10 lg:px-3.5" onclick={onVerRubrica}>
            <ClipboardList size={18} aria-hidden="true" class={dosAccesos ? 'max-lg:hidden' : ''} />
            {#if dosAccesos}<span class="lg:hidden">Rúbrica</span><span class="max-lg:hidden">Ver rúbrica</span
              >{:else}Ver rúbrica{/if}
          </button>
        {/if}
      </div>
    {/if}
  </div>
</section>
