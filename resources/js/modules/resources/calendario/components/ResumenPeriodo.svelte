<script lang="ts">
  /**
   * Recuento del período mostrado (el mes o la semana según la vista activa).
   * Sólo cuenta lo que el filtro deja ver, salvo los días con sobrecarga, que
   * se calculan sobre todas las entregas del docente.
   */
  import { ETIQUETA } from '../utils/estilos';

  interface Props {
    /** Rótulo del período: "Este mes", "Esta semana", … */
    titulo: string;
    entregas: number;
    sesiones: number;
    hitos: number;
    diasConSobrecarga: number;
  }

  let { titulo, entregas, sesiones, hitos, diasConSobrecarga }: Props = $props();
</script>

<div class="flex flex-col gap-2 border-t border-[#E5E7EB] px-3.5 py-3.5">
  <span class={ETIQUETA}>{titulo}</span>
  <dl class="m-0 flex flex-col gap-1.5 text-[12px] text-[#4A4E5C]">
    <div class="flex">
      <dt>Fechas límite</dt>
      <dd class="m-0 ml-auto font-semibold tabular-nums">{entregas}</dd>
    </div>
    <div class="flex">
      <dt>Sesiones de clase</dt>
      <dd class="m-0 ml-auto font-semibold tabular-nums">{sesiones}</dd>
    </div>
    <div class="flex">
      <dt>Hitos de syllabus</dt>
      <dd class="m-0 ml-auto font-semibold tabular-nums">{hitos}</dd>
    </div>
    <div class="flex" style="color:{diasConSobrecarga > 0 ? '#B91C1C' : '#4A4E5C'}">
      <dt>Días con sobrecarga</dt>
      <dd class="m-0 ml-auto font-semibold tabular-nums">{diasConSobrecarga}</dd>
    </div>
  </dl>
</div>
