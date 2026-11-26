<script lang="ts">
  /**
   * La entrega que viene, en la tarjeta del encabezado del Inicio. Si ese mismo
   * día cierran varias, se alternan solas y «Ver las N…» las lista todas.
   *
   * La rotación se detiene con el mouse o el foco encima, con la lista abierta
   * y cuando el sistema pide reducir el movimiento; las flechas permiten
   * recorrerlas a mano.
   */
  import { Link } from '@inertiajs/svelte';
  import { fade } from 'svelte/transition';
  import { MediaQuery } from 'svelte/reactivity';
  import { CalendarClock, ChevronLeft, ChevronRight } from 'lucide-svelte';
  import TipoActividad from '@/components/student/dm/TipoActividad.svelte';
  import { btnPrimario, enlace, etiqueta, pildora, tono } from '@/lib/dm';
  import { plazoDe, type ActividadPorVencer } from '@/lib/plazoAlumno';

  interface Props {
    /** Las que cierran el mismo día que la más próxima, en orden. */
    items: ActividadPorVencer[];
    /** «Lo de hoy» o «Lo próximo». */
    titulo: string;
    cierraHoy: boolean;
    hoy: string;
    colorDe: (idCurso: number) => string;
    nombreCurso: (idCurso: number) => string;
  }

  let { items, titulo, cierraHoy, hoy, colorDe, nombreCurso }: Props = $props();

  const INTERVALO_MS = 6000;
  const reducirMovimiento = new MediaQuery('prefers-reduced-motion: reduce');

  let indice = $state(0);
  let pausada = $state(false);
  let verDia = $state(false);

  const varias = $derived(items.length > 1);
  const actual = $derived(items[Math.min(indice, items.length - 1)]);
  const plazo = $derived(plazoDe(actual, hoy));

  $effect(() => {
    if (!varias || pausada || verDia || reducirMovimiento.current) return;
    const reloj = setInterval(() => (indice = (indice + 1) % items.length), INTERVALO_MS);
    return () => clearInterval(reloj);
  });

  const mover = (paso: number) => (indice = (indice + paso + items.length) % items.length);
  const hrefDe = (a: ActividadPorVencer) => `/estudiante/cursos/${a.id_curso}/actividad/${a.id_actividad}`;
  const rotuloCurso = (a: ActividadPorVencer) => (nombreCurso(a.id_curso) ? `${a.curso} · ${nombreCurso(a.id_curso)}` : a.curso);
  const flecha =
    'flex size-9 cursor-pointer items-center justify-center rounded-full text-dm-ink-2 hover:bg-dm-sutil hover:text-dm-ink';
</script>

{#snippet curso(a: ActividadPorVencer)}
  <span class="{pildora} {tono.curso} self-start font-bold" style={colorDe(a.id_curso)}>
    <span aria-hidden="true" class="size-2 rounded-full bg-(--curso)"></span>{rotuloCurso(a)}
  </span>
{/snippet}

<div
  role="group"
  aria-label={titulo}
  aria-roledescription={varias ? 'carrusel' : undefined}
  class="flex flex-col gap-3"
  onmouseenter={() => (pausada = true)}
  onmouseleave={() => (pausada = false)}
  onfocusin={() => (pausada = true)}
  onfocusout={() => (pausada = false)}
>
  <div class="flex min-h-9 items-center justify-between gap-2">
    <span class="{etiqueta} text-dm-ink-3">{titulo}</span>
    {#if varias && !verDia}
      <span class="flex items-center gap-0.5 text-[13px] leading-[18px] text-dm-ink-2">
        <button type="button" class={flecha} aria-label="Entrega anterior" onclick={() => mover(-1)}>
          <ChevronLeft size={16} aria-hidden="true" />
        </button>
        <!-- Mientras rota sola no se anuncia; detenida (foco o mouse encima), sí. -->
        <span aria-live={pausada ? 'polite' : 'off'}>{indice + 1} de {items.length}</span>
        <button type="button" class={flecha} aria-label="Entrega siguiente" onclick={() => mover(1)}>
          <ChevronRight size={16} aria-hidden="true" />
        </button>
      </span>
    {/if}
  </div>

  {#if verDia}
    <ul class="m-0 flex list-none flex-col gap-2 p-0">
      {#each items as a (a.id_actividad)}
        <li>
          <Link
            href={hrefDe(a)}
            class="flex flex-col gap-1.5 rounded-lg border border-dm-linea p-3 text-dm-ink no-underline hover:bg-dm-sutil"
          >
            {@render curso(a)}
            <span class="text-[15px] leading-5 font-semibold">{a.nombre}</span>
            <TipoActividad esSumativa={a.es_sumativa} esGrupal={a.es_grupal} />
          </Link>
        </li>
      {/each}
    </ul>
  {:else}
    {#key actual.id_actividad}
      <div class="flex flex-col gap-3" in:fade={{ duration: reducirMovimiento.current ? 0 : 250 }}>
        {@render curso(actual)}
        <span class="text-lg leading-6 font-semibold">{actual.nombre}</span>
        <span class="{pildora} {plazo.urgente ? tono.warn : tono.neutro} self-start">
          <CalendarClock size={14} aria-hidden="true" />{plazo.texto}
        </span>
        <TipoActividad esSumativa={actual.es_sumativa} esGrupal={actual.es_grupal} />
        <Link href={hrefDe(actual)} class="{btnPrimario} mt-1 w-full">
          Ir a la actividad <ChevronRight size={16} aria-hidden="true" />
        </Link>
      </div>
    {/key}
  {/if}

  {#if varias}
    <button type="button" class="{enlace} cursor-pointer self-start" aria-expanded={verDia} onclick={() => (verDia = !verDia)}>
      {verDia ? 'Ver una a la vez' : `Ver las ${items.length} que cierran ${cierraHoy ? 'hoy' : 'ese día'}`}
    </button>
  {/if}
</div>
