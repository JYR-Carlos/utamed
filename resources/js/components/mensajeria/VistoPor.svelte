<script lang="ts">
  /**
   * «Visto por …» bajo el último mensaje de un hilo de agenda (T07).
   *
   * Como en Instagram: se nombran hasta dos lectores y el resto se resume
   * («y 3 más»). Al pasar el cursor se ve la lista completa con la hora. El
   * backend ya excluye a quien envió el mensaje y a quien está mirando.
   */
  import { CheckCheck } from 'lucide-svelte';
  import { formatFechaHora } from '@/utils/formatters';

  interface Lector {
    nombre: string;
    fecha_lectura: string;
  }

  interface Props {
    lectores?: Lector[] | null;
    /** Lado del hilo en que cae el mensaje. */
    alinear?: 'izquierda' | 'derecha';
    /** Espaciado del contenedor; el valor por defecto calza con un hilo de burbujas. */
    class?: string;
  }

  let { lectores = [], alinear = 'derecha', class: clase = 'mt-1.5 pb-2 px-1' }: Props = $props();

  const MAX_NOMBRADOS = 2;

  const texto = $derived.by(() => {
    const lista = lectores ?? [];
    const nombrados = lista.slice(0, MAX_NOMBRADOS).map((l) => l.nombre.split(' ')[0]);
    const resto = lista.length - nombrados.length;
    if (resto > 0) return `Visto por ${nombrados.join(', ')} y ${resto} más`;
    if (nombrados.length === 2) return `Visto por ${nombrados[0]} y ${nombrados[1]}`;
    return `Visto por ${nombrados[0]}`;
  });

  const detalle = $derived((lectores ?? []).map((l) => `${l.nombre} · ${formatFechaHora(l.fecha_lectura)}`).join('\n'));
</script>

{#if lectores && lectores.length > 0}
  <div class="flex {clase} {alinear === 'derecha' ? 'justify-end' : 'justify-start'}">
    <span class="flex items-center gap-1 text-[11px] text-[#5A5E6E]" title={detalle}>
      <CheckCheck class="h-3.5 w-3.5 text-[#002F6C]" />
      {texto}
    </span>
  </div>
{/if}
