<script lang="ts">
  /**
   * Puente hacia el área de estudiante: se muestra sólo cuando el usuario
   * tiene perfil usuario.estudiante además del rol Ayudante (doble rol, ver
   * docs_ui_mockups/rol_ayudante.md). Cuando la ayudantía no tiene cursos
   * todavía, es el único destino útil del dashboard, así que pasa a ser la
   * acción primaria (botón relleno) en vez de la secundaria.
   */
  import { Link } from '@inertiajs/svelte';
  import { GraduationCap, ArrowUpRight } from 'lucide-svelte';

  interface Props {
    carrera?: string | null;
    primary?: boolean;
  }

  let { carrera = null, primary = false }: Props = $props();
</script>

<section class="flex flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,.08)]">
  <div class="flex items-center gap-2">
    <GraduationCap class="h-4 w-4 text-[#5A5E6E]" />
    <h3 class="text-base font-semibold text-[#1A1A24]">También eres estudiante</h3>
  </div>
  <p class="text-[13px] text-[#5A5E6E]">
    {#if primary}
      Mientras tanto, tus cursos, entregas y calificaciones{carrera ? ` de ${carrera}` : ''} siguen en tu área de estudiante.
    {:else}
      Tus cursos, entregas y calificaciones{carrera ? ` de ${carrera}` : ''} viven en tu área de estudiante. Aquí sólo ves lo que te toca
      como ayudante.
    {/if}
  </p>
  <Link
    href="/estudiante/dashboard"
    class="flex w-fit items-center gap-[7px] rounded-lg px-3.5 py-2 text-[13.5px] font-semibold no-underline transition-colors {primary
      ? 'bg-[#002F6C] text-white hover:bg-[#00214d]'
      : 'border border-[#D6D9E0] bg-white font-medium text-[#1A1A24] hover:bg-[#F8FAFC]'}"
  >
    Ir a mi área de estudiante
    <ArrowUpRight class="h-[15px] w-[15px] {primary ? 'text-white' : 'text-[#5A5E6E]'}" />
  </Link>
</section>
