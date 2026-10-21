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
    neutro: 'bg-slate-100 text-gray-700',
    manana: 'bg-amber-100 text-amber-800',
    hoy: 'bg-red-100 text-red-700',
  };
</script>

<span class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap {tonos[plazo.tono]}">
  {plazo.texto}
</span>
