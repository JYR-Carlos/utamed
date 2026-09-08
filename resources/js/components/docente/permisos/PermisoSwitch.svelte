<script lang="ts">
  /**
   * Switch de una celda persona × permiso. Una escritura por toggle: el
   * switch se mueve de inmediato (optimista) y no espera respuesta del
   * servidor para reaccionar — el estado visual (reposo/guardando/
   * confirmado/error) lo controla el padre, que sabe cuándo llegó la
   * respuesta y si hubo que revertir.
   */
  import { Check, AlertTriangle } from 'lucide-svelte';

  type Estado = 'reposo' | 'guardando' | 'confirmado' | 'error';

  interface Props {
    checked: boolean;
    estado?: Estado;
    disabled?: boolean;
    onToggle: (next: boolean) => void;
    label?: string;
  }

  let { checked, estado = 'reposo', disabled = false, onToggle, label }: Props = $props();

  const trackBg = $derived(
    estado === 'guardando' ? '#C9D6E6' : estado === 'error' ? '#FEE2E2' : checked ? '#002F6C' : '#E5E7EB',
  );
</script>

<span class="inline-flex items-center gap-1.5">
  <button
    type="button"
    role="switch"
    aria-checked={checked}
    aria-label={label}
    disabled={disabled || estado === 'guardando'}
    onclick={() => onToggle(!checked)}
    class="inline-flex h-5 w-[34px] shrink-0 items-center rounded-full p-0.5 transition-colors disabled:cursor-wait"
    style="background:{trackBg}; justify-content:{checked ? 'flex-end' : 'flex-start'}"
  >
    {#if estado === 'guardando'}
      <span
        class="h-4 w-4 rounded-full border-2 border-[#C9D6E6] bg-white"
        style="border-top-color:#002F6C; animation: permiso-switch-spin 0.6s linear infinite"
      ></span>
    {:else}
      <span class="h-4 w-4 rounded-full bg-white shadow-[0_1px_2px_rgba(0,0,0,.2)]"></span>
    {/if}
  </button>
  {#if estado === 'confirmado'}
    <Check class="h-3.5 w-3.5 shrink-0 text-[#059669]" />
  {:else if estado === 'error'}
    <AlertTriangle class="h-3.5 w-3.5 shrink-0 text-[#DC2626]" />
  {/if}
</span>

<style>
  @keyframes permiso-switch-spin {
    to {
      transform: rotate(360deg);
    }
  }
</style>
