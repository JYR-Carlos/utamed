<script lang="ts">
  /**
   * Isologotipo UTA y cuadro dm, en ese orden y separados por el área de
   * protección de la UTA (guía dm, cap. 02). El isologotipo trae el texto en
   * blanco: va siempre sobre noche, en claro y en oscuro.
   */
  let { class: clase = '' }: { class?: string } = $props();
</script>

<div class="flex items-center gap-8 lg:gap-12 {clase}">
  <img
    src="/img/uta-isologotipo.png"
    alt="Universidad de Tarapacá, Universidad del Estado"
    width="4756"
    height="1336"
    class="h-12 w-auto shrink-0 lg:h-14"
  />
  <img
    src="/img/dm-logotipo.jpeg"
    alt="Diseño Multimedia"
    width="447"
    height="447"
    class="size-14 shrink-0 lg:size-16"
  />
</div>
