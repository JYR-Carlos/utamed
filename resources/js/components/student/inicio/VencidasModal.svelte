<script lang="ts">
  /**
   * Ventana «Vencidas sin entregar» del Inicio: todas las del semestre, de la
   * más reciente a la más antigua, con su curso y el día en que venció cada
   * una. Cada fila lleva a su actividad.
   */
  import { Link } from '@inertiajs/svelte';
  import { ChevronRight, CircleAlert } from 'lucide-svelte';
  import ModalAlumno from '@/components/student/dm/ModalAlumno.svelte';
  import TipoActividad from '@/components/student/dm/TipoActividad.svelte';
  import { pildora, tono } from '@/lib/dm';
  import { plazoDe, textoVencidas, type ActividadPorVencer } from '@/lib/plazoAlumno';

  interface Props {
    open: boolean;
    items: ActividadPorVencer[];
    hoy: string;
    colorDe: (idCurso: number) => string;
    nombreCurso: (idCurso: number) => string;
  }

  let { open = $bindable(), items, hoy, colorDe, nombreCurso }: Props = $props();

  const rotuloCurso = (a: ActividadPorVencer) => (nombreCurso(a.id_curso) ? `${a.curso} · ${nombreCurso(a.id_curso)}` : a.curso);
</script>

<ModalAlumno
  bind:open
  variante="centro"
  etiqueta="Tus cursos del semestre"
  titulo={textoVencidas(items.length)}
  estilo="--curso: var(--color-dm-error)"
  rotuloCerrar="Cerrar las actividades vencidas"
>
  <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-4 md:p-6">
    <p class="text-sm leading-5 text-dm-ink-2">
      Su plazo ya cerró y no hay entrega registrada. Si tuviste un motivo, escríbele a tu docente en Consultas, dentro de la actividad.
    </p>
    <ul class="m-0 flex list-none flex-col gap-2.5 p-0">
      {#each items as a (a.id_actividad)}
        <li>
          <Link
            href={`/estudiante/cursos/${a.id_curso}/actividad/${a.id_actividad}`}
            class="flex items-center gap-3 rounded-xl border border-dm-linea bg-dm-tarjeta p-3.5 text-dm-ink no-underline hover:border-dm-control hover:bg-dm-sutil hover:text-dm-ink"
          >
            <span class="flex min-w-0 flex-1 flex-col gap-1.5">
              <span class="{pildora} {tono.curso} self-start font-bold" style={colorDe(a.id_curso)}>
                <span aria-hidden="true" class="size-2 shrink-0 rounded-full bg-(--curso)"></span>{rotuloCurso(a)}
              </span>
              <span class="text-base leading-[22px] font-semibold">{a.nombre}</span>
              <TipoActividad esSumativa={a.es_sumativa} esGrupal={a.es_grupal} />
              <span class="inline-flex items-center gap-1.5 text-[13px] leading-[18px] font-semibold text-dm-error">
                <CircleAlert size={14} aria-hidden="true" class="shrink-0" />{plazoDe(a, hoy).texto}
              </span>
            </span>
            <ChevronRight size={18} aria-hidden="true" class="shrink-0 text-dm-ink-3" />
          </Link>
        </li>
      {/each}
    </ul>
  </div>
</ModalAlumno>
