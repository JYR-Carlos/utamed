<script lang="ts">
  /**
   * Próximas fechas límite de actividades en todos los cursos del docente
   * (titular o componente), desde agenda.actividad.fecha_limite — mismo
   * criterio que el calendario académico (/docente/calendario).
   *
   * Una fila por actividad y un enlace por grupo con su fecha y cuánto falta:
   * la misma actividad existe en cada grupo, así que sin el grupo no se sabría
   * cuál fecha es de cuál.
   */
  import { Link } from '@inertiajs/svelte';
  import { Clock, ChevronRight } from 'lucide-svelte';
  import BloqueVacio from '@/components/docente/BloqueVacio.svelte';
  import PlazoBadge from '@/components/docente/PlazoBadge.svelte';
  import { fechaCorta } from '@/utils/plazo';
  import { tonoGrupo } from '@/utils/grupoColor';

  interface Grupo {
    id_curso: number;
    letra_grupo: string | null;
    fecha_limite: string;
  }

  interface Item {
    id_actividad: number;
    nombre: string;
    nombre_curso: string | null;
    grupos: Grupo[];
  }

  interface Props {
    items?: Item[];
  }

  let { items = [] }: Props = $props();
</script>

<section class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
  <div class="flex items-center gap-2">
    <Clock class="h-5 w-5 text-uta-blue" />
    <h3 class="text-base font-semibold text-ink">Próximas fechas límite</h3>
    {#if items.length > 0}
      <span class="ml-auto hidden text-[13px] text-ink-2 sm:inline">Ordenadas por fecha</span>
    {/if}
  </div>

  {#if items.length > 0}
    <div class="flex flex-col gap-4">
      {#each items as item, i (item.id_actividad)}
        {#if i > 0}
          <div class="h-px bg-gray-200"></div>
        {/if}
        <div class="flex flex-col gap-1">
          <p class="line-clamp-2 text-sm leading-5 font-medium text-ink">{item.nombre}</p>
          {#if item.nombre_curso}
            <p class="text-[13px] text-ink-2">{item.nombre_curso}</p>
          {/if}
          <div class="flex flex-wrap gap-2 pt-2">
            {#each item.grupos as grupo (grupo.id_curso)}
              <Link
                href={`/docente/cursos/${grupo.id_curso}/actividades`}
                class="flex min-h-11 items-center gap-2 rounded-[10px] border border-l-4 py-1 pr-2.5 pl-3 text-[13px] no-underline transition-colors {tonoGrupo(grupo.letra_grupo).enlace}"
              >
                {#if grupo.letra_grupo}
                  <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {tonoGrupo(grupo.letra_grupo).chip}">Grupo {grupo.letra_grupo}</span>
                {/if}
                <span class="font-semibold text-zinc-600">{fechaCorta(grupo.fecha_limite)}</span>
                <PlazoBadge fecha={grupo.fecha_limite} />
                <ChevronRight class="h-4 w-4 shrink-0 text-ink-2" />
              </Link>
            {/each}
          </div>
        </div>
      {/each}
    </div>
  {:else}
    <BloqueVacio title="Sin fechas próximas" description="Aparecerán cuando tus cursos tengan actividades con fecha límite." />
  {/if}
</section>
