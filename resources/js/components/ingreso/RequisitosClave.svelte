<script lang="ts">
  /**
   * Caja «La nueva contraseña necesita» con la lista reactiva de requisitos.
   * Lo que falta queda neutro mientras se escribe; el rojo es sólo para el
   * error al guardar. Los campos la nombran con aria-describedby="requisitos".
   */
  import RequisitosPassword from '@/components/custom/common/RequisitosPassword.svelte';

  interface Props {
    password: string;
    confirmacion: string;
  }

  let { password, confirmacion }: Props = $props();
</script>

<div id="requisitos" class="flex flex-col gap-2.5 rounded-[10px] bg-dm-sutil p-4">
  <span class="text-xs leading-4 font-semibold tracking-[0.06em] text-dm-ink-3 uppercase"
    >La nueva contraseña necesita</span
  >
  <RequisitosPassword {password} {confirmacion} tono="ingreso" />
</div>
