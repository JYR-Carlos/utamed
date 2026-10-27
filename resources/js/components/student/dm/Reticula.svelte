<script lang="ts">
  /**
   * Retícula de la guía dm sobre las cabeceras noche: líneas blancas cada
   * 24 px que aparecen desde la izquierda. Va dentro de un contenedor
   * `relative overflow-hidden`; el contenido de encima lleva `relative`.
   */
  interface Props {
    /** Dónde empieza (clase `left-*`). */
    class?: string;
  }

  let { class: clase = 'left-[40%]' }: Props = $props();
</script>

<div
  aria-hidden="true"
  class="pointer-events-none absolute inset-y-0 right-0 bg-[linear-gradient(rgb(255_255_255/0.09)_1px,transparent_1px),linear-gradient(90deg,rgb(255_255_255/0.09)_1px,transparent_1px)] bg-[length:24px_24px] [mask-image:linear-gradient(90deg,transparent,#000_55%)] {clase}"
></div>
