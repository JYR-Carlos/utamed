<script lang="ts">
  /**
   * Interruptor de tema de dos estados, con sol y luna a la vista: el que está
   * activo va resaltado y un clic pasa al otro. Lo usan el ingreso y las barras
   * del alumno y del docente; el color, el borde y el alto los pone quien lo usa.
   *
   * Si la persona eligió «Sistema» en Ajustes, muestra lo que se ve en ese
   * momento y el clic fija claro u oscuro.
   */
  import { Moon, Sun } from 'lucide-svelte';
  import { useAppearance } from '@/hooks/useAppearance.svelte';

  let { class: clase = '' }: { class?: string } = $props();

  const apariencia = useAppearance();
  const oscuro = $derived(apariencia.oscuro);

  const opcion = 'flex size-8 items-center justify-center rounded-full transition-colors';
  const activa = 'bg-current/15';
  const inactiva = 'opacity-60';
</script>

<button
  type="button"
  role="switch"
  aria-checked={oscuro}
  aria-label="Modo oscuro"
  title={oscuro ? 'Modo oscuro activado' : 'Modo claro activado'}
  onclick={() => apariencia.updateAppearance(oscuro ? 'light' : 'dark')}
  class="flex h-11 shrink-0 cursor-pointer items-center gap-0.5 rounded-full px-1 {clase}"
>
  <span aria-hidden="true" class="{opcion} {oscuro ? inactiva : activa}"><Sun size={16} /></span>
  <span aria-hidden="true" class="{opcion} {oscuro ? activa : inactiva}"><Moon size={16} /></span>
</button>
