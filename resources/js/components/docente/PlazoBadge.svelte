<script lang="ts">
  /**
   * Cuánto falta para un plazo («en 10 días», «mañana», «hoy»). Neutro en gris;
   * «mañana» en ámbar y «hoy» en rojo, siempre con el texto además del color.
   */
  import { plazoRelativo, type TonoPlazo } from '@/utils/plazo';

  interface Props {
    fecha: string;
  }

  let { fecha }: Props = $props();

  const plazo = $derived(plazoRelativo(fecha));

  const tonos: Record<TonoPlazo, string> = {
    neutro: 'bg-[#EEF2F7] text-[#33384A]',
    manana: 'bg-[#FEF3C7] text-[#92400E]',
    hoy: 'bg-[#FEE2E2] text-[#B91C1C]',
  };
</script>

<span class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap {tonos[plazo.tono]}">
  {plazo.texto}
</span>
