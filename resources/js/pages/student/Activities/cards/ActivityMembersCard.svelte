<script lang="ts">
  import { Users } from 'lucide-svelte';
  import { initials } from '@/utils/formatters';

  interface Props {
    usuarios: Array<{
      id_estudiante: number;
      nombre1: string;
      nombre2: string;
      apellido1: string;
      apellido2: string;
    }>;
  }

  let { usuarios }: Props = $props();

  function nombreCompleto(u: Props['usuarios'][number]): string {
    return [u.nombre1, u.nombre2, u.apellido1, u.apellido2].filter(Boolean).join(' ');
  }
</script>

{#if usuarios.length > 0}
  <section class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    <div class="flex items-center gap-2">
      <Users class="h-[15px] w-[15px] text-ink-2" />
      <span class="text-[13px] font-semibold text-ink">Integrantes</span>
    </div>
    <div class="flex flex-col gap-2">
      {#each usuarios as u (u.id_estudiante)}
        <div class="flex items-center gap-2.5">
          <div
            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-sand text-[11px] font-semibold text-ink-2"
          >
            {initials(nombreCompleto(u))}
          </div>
          <span class="min-w-0 truncate text-[12.5px] font-medium text-ink">{nombreCompleto(u)}</span>
        </div>
      {/each}
    </div>
    <span class="border-t border-gray-200 pt-2.5 text-[11px] text-ink-2">
      Tú no apareces en la lista: se muestra al resto del grupo.
    </span>
  </section>
{/if}
