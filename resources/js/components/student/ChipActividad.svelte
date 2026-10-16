<script lang="ts">
  /**
   * Etiqueta en píldora de una actividad (vista del alumno): Formativa,
   * Sumativa, Entregada, Vencida y los metadatos neutros («Individual»,
   * «Con entrega»). Comparten forma y tipografía; sólo cambian el color y, en
   * Entregada y Vencida, el ícono (el estado no depende sólo del color).
   */
  import { AlertCircle, CheckCircle2 } from 'lucide-svelte';

  interface Props {
    tipo: 'formativa' | 'sumativa' | 'entregada' | 'vencida' | 'metadato';
    /** Sólo para `metadato`: el texto de la etiqueta. */
    texto?: string;
  }

  let { tipo, texto = '' }: Props = $props();

  const ESTILOS = {
    formativa: { texto: 'Formativa', clases: 'bg-sky-50 text-sky-900 font-bold' },
    sumativa: { texto: 'Sumativa', clases: 'bg-indigo-50 text-uta-blue font-bold' },
    entregada: { texto: 'Entregada', clases: 'bg-emerald-50 text-emerald-800 font-bold' },
    vencida: { texto: 'Vencida', clases: 'bg-red-50 text-red-800 font-bold' },
    metadato: { texto: '', clases: 'bg-gray-100 text-slate-600 font-medium' },
  } as const;

  const estilo = $derived(ESTILOS[tipo]);
</script>

<span
  class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs leading-4 {estilo.clases}"
>
  {#if tipo === 'entregada'}
    <CheckCircle2 class="h-3.5 w-3.5" aria-hidden="true" />
  {:else if tipo === 'vencida'}
    <AlertCircle class="h-3.5 w-3.5" aria-hidden="true" />
  {/if}
  {tipo === 'metadato' ? texto : estilo.texto}
</span>
