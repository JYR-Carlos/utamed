<script lang="ts">
  /**
   * La foto del alumno: la de credencial de la Intranet o, si no hay o no
   * carga, la silueta genérica. La de credencial es vertical: se ancla arriba
   * para que la cabeza nunca quede cortada, sea cual sea el marco (redondo en
   * el menú, de credencial en el perfil). La silueta va entera.
   */
  interface Props {
    src?: string | null;
    /** Junto al nombre, la foto no agrega información: alt vacío. */
    decorativa?: boolean;
    /** Forma y tamaño del marco. */
    class?: string;
  }

  let { src = null, decorativa = false, class: clase = '' }: Props = $props();

  let fallo = $state(false);
</script>

<span class="block shrink-0 overflow-hidden {clase}">
  {#if src && !fallo}
    <img {src} alt={decorativa ? '' : 'Tu foto de perfil'} class="size-full object-cover object-top" onerror={() => (fallo = true)} />
  {:else}
    <img src="/img/usuarioutamed.png" alt={decorativa ? '' : 'Foto de perfil genérica'} class="size-full object-contain p-[14%]" />
  {/if}
</span>
