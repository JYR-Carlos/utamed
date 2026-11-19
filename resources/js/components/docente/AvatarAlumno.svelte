<script lang="ts">
  interface Props {
    src?: string | null;
    nombre?: string;
    class?: string;
  }

  let { src = null, class: clase = '' }: Props = $props();
  let fallo = $state(false);

  $effect(() => {
    void src;
    fallo = false;
  });
</script>

<span aria-hidden="true" class="flex shrink-0 items-center justify-center overflow-hidden rounded-full {clase}">
  {#if src && !fallo}
    <img {src} alt="" class="size-full object-cover object-top" onerror={() => (fallo = true)} />
  {:else}
    <img src="/img/usuarioutamed.png" alt="" class="size-full object-contain p-[14%]" />
  {/if}
</span>
