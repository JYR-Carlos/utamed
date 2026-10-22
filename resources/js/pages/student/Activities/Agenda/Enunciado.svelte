<script lang="ts">
  /**
   * Cuerpo de la ventana «Enunciado» del alumno (guía dm). Un PDF se lee ahí
   * mismo; cualquier otro archivo, el navegador no lo muestra y se descarga.
   */
  import { Download, ExternalLink, FileText } from 'lucide-svelte';
  import { btnPrimario, btnSecundario } from '@/lib/dm';

  interface Props {
    url_archivo: string;
    nombre_archivo: string;
    /** «PDF · 1,2 MB». */
    detalle?: string;
    esPdf: boolean;
  }

  let { url_archivo, nombre_archivo, detalle = '', esPdf }: Props = $props();
</script>

{#if esPdf}
  <iframe src={url_archivo} title={nombre_archivo} class="min-h-0 w-full flex-1 border-0 bg-dm-pagina"></iframe>
  <div class="flex shrink-0 flex-wrap items-center gap-3 border-t border-dm-linea bg-dm-elevada px-4 py-3 lg:px-7">
    <span class="flex min-w-0 flex-1 flex-col">
      <span class="truncate text-sm leading-5 font-semibold">{nombre_archivo}</span>
      {#if detalle}<span class="text-[13px] leading-[18px] text-dm-ink-3">{detalle}</span>{/if}
    </span>
    <a href={url_archivo} target="_blank" rel="noopener noreferrer" class={btnSecundario}>
      <ExternalLink size={16} aria-hidden="true" /><span class="max-sm:sr-only">Abrir en otra pestaña</span>
    </a>
    <a href={url_archivo} download={nombre_archivo} class={btnPrimario}><Download size={16} aria-hidden="true" />Descargar</a>
  </div>
{:else}
  <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-4 overflow-y-auto bg-dm-pagina px-4 py-10 text-center">
    <span class="flex size-16 items-center justify-center rounded-2xl bg-dm-accion-suave text-dm-accion-texto">
      <FileText size={30} aria-hidden="true" />
    </span>
    <span class="flex max-w-100 flex-col gap-1">
      <span class="text-base leading-[22px] font-bold break-all">{nombre_archivo}</span>
      {#if detalle}<span class="text-[13px] leading-[18px] text-dm-ink-3">{detalle}</span>{/if}
      <span class="mt-1 text-sm leading-5 text-dm-ink-2">Este tipo de archivo no se puede ver en el navegador: descárgalo para abrirlo en tu equipo.</span>
    </span>
    <a href={url_archivo} download={nombre_archivo} class={btnPrimario}><Download size={16} aria-hidden="true" />Descargar</a>
  </div>
{/if}
