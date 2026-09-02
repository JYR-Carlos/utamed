<script lang="ts">
  import { Upload } from 'lucide-svelte';

  interface Props {
    disponible: boolean;
    esGrupal: boolean;
    onSubirClick: () => void;
  }

  let { disponible, esGrupal, onSubirClick }: Props = $props();

  const titulo = $derived(esGrupal ? 'Entrega del grupo' : 'Tu entrega');
  const subtitulo = $derived(
    disponible ? 'Arrastra tu archivo o selecciónalo' : 'Nueva entrega no disponible',
  );
</script>

<section class="rounded-xl border border-dashed border-[#D6D9E0] bg-[#F8FAFC] p-6">
  <div class="flex flex-col items-center gap-2.5 text-center">
    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#22213F]/10">
      <Upload class="h-[18px] w-[18px] text-[#22213F]" />
    </div>
    <h3 class="text-sm font-semibold text-[#1A1A24]">{titulo}</h3>
    <p class="text-xs text-[#5A5E6E]">{subtitulo}</p>
    {#if disponible}
      <button
        class="mt-1 rounded-lg bg-[#22213F] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#15142e]"
        onclick={onSubirClick}
      >
        Seleccionar archivo
      </button>
    {/if}
  </div>
</section>
