<script lang="ts">
  /**
   * Tarjeta de curso de la guía dm: franja y código con el color del curso,
   * nombre, docente y sólo datos que existen: las vencidas sin entregar, la
   * próxima entrega pendiente (de proximasAVencer) y los mensajes sin leer.
   *
   * Toda la tarjeta lleva al curso (el enlace del nombre se estira sobre ella);
   * el aviso de vencidas es un enlace aparte, a las atrasadas del curso.
   */
  import { Link } from '@inertiajs/svelte';
  import { CalendarClock, CircleAlert, MessageSquare } from 'lucide-svelte';
  import { useFormatName } from '@/hooks';
  import { pildora, tono } from '@/lib/dm';
  import { hrefAtrasadas, plazoDe, textoVencidas, type ActividadPorVencer } from '@/lib/plazoAlumno';

  interface Props {
    id_curso: number;
    codigo: string;
    nombre: string;
    profesor: string;
    /** Primera entrega pendiente del curso, si hay una en los próximos 7 días. */
    proxima?: ActividadPorVencer | null;
    /** Actividades del curso vencidas sin entrega en el semestre. */
    vencidas?: number;
    no_leidos?: number;
    estilo: string;
  }

  let { id_curso, codigo, nombre, profesor, proxima = null, vencidas = 0, no_leidos = 0, estilo }: Props = $props();

  const { formatName } = useFormatName();
  const sinDocente = $derived(!profesor || profesor === '(sin docente asignado)');
  const plazo = $derived(proxima ? plazoDe(proxima) : null);
</script>

<article
  style={estilo}
  class="relative flex flex-col gap-1.5 rounded-xl border border-t-6 border-dm-linea border-t-(--curso) bg-dm-tarjeta p-4 text-dm-ink transition-shadow hover:shadow-[0_6px_18px_rgb(22_25_73/0.12)] lg:gap-2 lg:p-5"
>
  <span class="font-dm-cond text-2xl leading-7 font-extrabold tracking-[0.02em] text-(--curso-t)">{codigo}</span>
  <Link
    href={`/estudiante/cursos/${id_curso}`}
    class="text-lg leading-6 font-semibold text-dm-ink no-underline after:absolute after:inset-0 after:rounded-xl after:content-['']"
  >
    {nombre}
  </Link>
  <span class="text-[13px] leading-[18px] text-dm-ink-2">{sinDocente ? 'Sin docente asignado' : formatName(profesor)}</span>
  <span class="mt-1.5 flex flex-wrap items-center gap-2 lg:mt-2">
    {#if vencidas > 0}
      <Link href={hrefAtrasadas(id_curso)} class="{pildora} {tono.error} relative z-10 no-underline hover:underline">
        <CircleAlert size={14} aria-hidden="true" />{textoVencidas(vencidas)}
      </Link>
    {/if}
    {#if proxima && plazo}
      <span class="{pildora} {plazo.urgente ? tono.warn : tono.neutro}">
        <CalendarClock size={14} aria-hidden="true" />{plazo.texto}
      </span>
    {:else if vencidas === 0}
      <span class="{pildora} {tono.neutro}">Nada por entregar esta semana</span>
    {/if}
    {#if no_leidos > 0}
      <span class="{pildora} {tono.accion}">
        <MessageSquare size={14} aria-hidden="true" />{no_leidos} sin leer
      </span>
    {/if}
  </span>
</article>
