<script lang="ts">
  /**
   * Entrada a SGEQ (préstamo de equipos) desde UTAmed.
   *
   * Abre en otra pestaña porque SGEQ es otro sistema con su propia sesión: quien
   * pide un equipo normalmente vuelve a lo que estaba haciendo acá.
   *
   * Apunta a /sso/sgeq y no directo a SGEQ. Esa ruta firma la identidad del
   * usuario en el momento del clic y redirige; ver App\Services\Sso\SgeqSsoService.
   *
   * Sólo se muestra si el servidor dijo que esta persona puede entrar (`visible`):
   * el acceso depende del rol y de la carrera, y un botón que siempre rebota es
   * peor que ningún botón.
   */
  import ExternalLink from '@lucide/svelte/icons/external-link';

  interface Props {
    visible?: boolean;
    class?: string;
  }

  let { visible = false, class: className = '' }: Props = $props();
</script>

{#if visible}
  <a
    href="/sso/sgeq"
    target="_blank"
    rel="noopener noreferrer"
    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 {className}"
  >
    <ExternalLink class="h-4 w-4" aria-hidden="true" />
    Préstamo de equipos
  </a>
{/if}
