<script lang="ts">
  import { CheckCircle2, FileText, Download, Upload } from 'lucide-svelte';
  import { formatBytes, formatFechaHora } from '@/utils/formatters';

  interface Props {
    esGrupal: boolean;
    fecha_entrega?: string | null;
    archivo?: {
      nombre_original: string | null;
      peso_bytes: number | null;
    } | null;
    urlDescarga?: string | null;
    puedeReemplazar: boolean;
    onReemplazarClick: () => void;
  }

  let { esGrupal, fecha_entrega, archivo, urlDescarga, puedeReemplazar, onReemplazarClick }: Props = $props();

  const titulo = $derived(esGrupal ? 'Entrega del grupo' : 'Tu entrega');
</script>

<section class="flex flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
  <div class="flex items-center gap-2">
    <CheckCircle2 class="h-[15px] w-[15px] text-emerald-600" />
    <span class="text-[13px] font-semibold text-[#1A1A24]">{titulo}</span>
    {#if fecha_entrega}
      <span class="ml-auto text-[11.5px] text-[#5A5E6E]">Enviada {formatFechaHora(fecha_entrega)}</span>
    {/if}
  </div>

  {#if archivo?.nombre_original}
    <div class="flex items-center gap-3 rounded-lg border border-[#E5E7EB] p-3">
      <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-[#F5F1EA]">
        <FileText class="h-4 w-4 text-[#5A5E6E]" />
      </div>
      <div class="flex min-w-0 flex-col">
        <span class="truncate text-[12.5px] font-semibold text-[#1A1A24]">{archivo.nombre_original}</span>
        {#if archivo.peso_bytes}
          <span class="font-mono text-[11px] text-[#5A5E6E]">{formatBytes(archivo.peso_bytes)}</span>
        {/if}
      </div>
      {#if urlDescarga}
        <a
          href={urlDescarga}
          class="ml-auto shrink-0 text-[#22213F] hover:text-[#15142e]"
          aria-label="Descargar entrega"
        >
          <Download class="h-4 w-4" />
        </a>
      {/if}
    </div>
  {/if}

  {#if puedeReemplazar}
    <button
      class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-[#D6D9E0] bg-white px-3 py-2 text-xs font-semibold text-[#1A1A24] transition-colors hover:bg-[#F8FAFC]"
      onclick={onReemplazarClick}
    >
      <Upload class="h-3.5 w-3.5 text-[#5A5E6E]" />
      Reemplazar
    </button>
  {/if}
</section>
