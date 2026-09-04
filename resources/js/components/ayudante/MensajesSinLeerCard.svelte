<script lang="ts">
  /**
   * Mensajería de nivel curso (curso.mensaje) sin leer, uno por cada curso de
   * ayudantía del período vigente — incluidos los que no tienen mensajes
   * nuevos, para que la bandeja muestre el panorama completo en vez de sólo
   * lo pendiente (ver Ayudante\DashboardController::mensajeriaPorCurso).
   */
  import { Link } from '@inertiajs/svelte';
  import { Mail, ChevronRight } from 'lucide-svelte';

  interface CursoConMensajes {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    no_leidos: number;
    /** Instante del mensaje sin leer más reciente (curso.mensaje.fecha_creacion). */
    ultimo_no_leido?: string | null;
  }

  interface Props {
    total?: number;
    cursos?: CursoConMensajes[];
  }

  let { total = 0, cursos = [] }: Props = $props();

  /**
   * Los timestamps de Postgres llegan como 'YYYY-MM-DD HH:MM:SS'. `new Date()`
   * sólo garantiza la lectura en hora local si el separador es 'T', y la
   * columna guarda hora de pared chilena, así que la normalizamos antes.
   * No sirve `formatFechaHora` de @/utils/formatters porque aquí interesa
   * destacar «hoy», no repetir la fecha del día en curso.
   */
  function recencia(val: string | null | undefined): string {
    if (!val) return '';
    const d = new Date(val.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '';

    const hora = d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
    const hoy = new Date();
    const esHoy =
      d.getFullYear() === hoy.getFullYear() && d.getMonth() === hoy.getMonth() && d.getDate() === hoy.getDate();

    if (esHoy) return `hoy · ${hora}`;
    return `${d.toLocaleDateString('es-CL', { day: '2-digit', month: 'short', year: 'numeric' })} · ${hora}`;
  }
</script>

<section class="flex flex-col gap-3.5 rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,.08)]">
  <div class="flex items-center gap-2">
    <Mail class="h-4 w-4 text-[#5A5E6E]" />
    <h3 class="text-base font-semibold text-[#1A1A24]">Mensajes sin leer</h3>
    <span
      class="ml-auto inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {total > 0
        ? 'border-[#FECACA] bg-[#FEF2F2] text-[#B91C1C]'
        : 'border-[#E2E8F0] bg-[#F1F5F9] text-[#475569]'}"
    >
      {total}
    </span>
  </div>

  {#if cursos.length > 0}
    <div class="flex flex-col gap-2">
      {#each cursos as curso (curso.id_curso)}
        {#if curso.no_leidos > 0}
          <Link
            href={`/ayudante/cursos/${curso.id_curso}/mensajeria`}
            class="flex items-center gap-2.5 rounded-[10px] border border-[#E5E7EB] p-3 no-underline transition-colors hover:border-[#C9D6E6] hover:bg-[#FAFBFC]"
          >
            <div class="flex min-w-0 flex-col gap-0.5">
              <span class="font-mono text-[11px] tracking-wide text-[#5A5E6E]"
                >{curso.cod_curso}{#if curso.letra_grupo} · Grupo {curso.letra_grupo}{/if}</span
              >
              <span class="truncate text-[13.5px] font-semibold leading-snug text-[#1A1A24]">{curso.nombre}</span>
              {#if recencia(curso.ultimo_no_leido)}
                <span class="text-[12px] text-[#5A5E6E]">Más reciente {recencia(curso.ultimo_no_leido)}</span>
              {/if}
            </div>
            <span class="ml-auto flex-none rounded-full border border-[#FECACA] bg-[#FEF2F2] px-2.5 py-0.5 text-[11.5px] font-semibold text-[#B91C1C]">
              {curso.no_leidos}
            </span>
            <ChevronRight class="h-4 w-4 flex-none text-[#5A5E6E]" />
          </Link>
        {:else}
          <div class="flex items-center gap-2.5 rounded-[10px] border border-dashed border-[#E5E7EB] p-3">
            <div class="flex min-w-0 flex-col gap-0.5">
              <span class="font-mono text-[11px] tracking-wide text-[#5A5E6E]"
                >{curso.cod_curso}{#if curso.letra_grupo} · Grupo {curso.letra_grupo}{/if}</span
              >
              <span class="truncate text-[13.5px] font-medium leading-snug text-[#5A5E6E]">{curso.nombre}</span>
            </div>
            <span class="ml-auto flex-none text-[12px] text-[#5A5E6E]">Sin mensajes nuevos</span>
          </div>
        {/if}
      {/each}
    </div>
  {:else}
    <div class="flex flex-col items-center gap-1 rounded-[10px] border border-dashed border-[#E5E7EB] p-[18px] text-center">
      <span class="text-[13.5px] font-semibold text-[#1A1A24]">Sin bandejas todavía</span>
      <p class="text-[12.5px] text-[#5A5E6E]">La mensajería se abre con el primer curso asignado.</p>
    </div>
  {/if}
</section>
