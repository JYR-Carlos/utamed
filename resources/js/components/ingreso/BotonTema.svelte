<script lang="ts">
  /**
   * Botón único de tema del ingreso, igual que en la barra del alumno: cada
   * clic pasa a la siguiente opción (Claro → Oscuro → Sistema → Claro) y el
   * ícono muestra la actual. El color y el borde los pone quien lo usa.
   */
  import { OPCIONES_APARIENCIA, useAppearance } from '@/hooks/useAppearance.svelte';

  let { class: clase = '' }: { class?: string } = $props();

  const apariencia = useAppearance();
  const temaIndice = $derived(Math.max(0, OPCIONES_APARIENCIA.findIndex((o) => o.value === apariencia.appearance)));
  const temaActual = $derived(OPCIONES_APARIENCIA[temaIndice]);
  const temaSiguiente = $derived(OPCIONES_APARIENCIA[(temaIndice + 1) % OPCIONES_APARIENCIA.length]);
  const temaEtiqueta = $derived(`Tema: ${temaActual.label}. Cambiar a ${temaSiguiente.label.toLowerCase()}`);
</script>

<button
  type="button"
  onclick={() => apariencia.updateAppearance(temaSiguiente.value)}
  aria-label={temaEtiqueta}
  title={temaEtiqueta}
  class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-[10px] {clase}"
>
  <temaActual.Icon size={18} aria-hidden="true" />
</button>
