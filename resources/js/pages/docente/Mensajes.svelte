<script lang="ts">
  /**
   * Mensajes de actividades del docente — bandeja transversal a todos sus cursos
   * (dirección institucional UTA).
   *
   * Es la mensajería de NIVEL ACTIVIDAD (agenda.agenda). La de nivel curso vive
   * dentro de cada curso, en /docente/cursos/{id}/mensajeria (curso.mensaje).
   *
   * La bandeja no muestra las conversaciones: es un índice de actividades en
   * dos pestañas, «Nuevos» (con mensajes sin ver) y «Vistos». Cada fila lleva
   * a la página de la actividad y, si hay algo sin ver, abre la agenda del
   * grupo con el mensaje más reciente (?grupo=…). Así la agenda se ve de una
   * sola forma, la del modal de la actividad.
   *
   * Las actividades que ya no están activas (cerradas, no visibles o
   * planificadas) no tienen conversación que atender: se esconden salvo que se
   * pidan al pie, y entonces llevan su estado. Una con mensajes sin ver se
   * muestra igual, para que nada quede sin leer.
   *
   * Visibilidad: el backend sólo entrega cursos donde el docente es titular.
   */
  import { Link } from '@inertiajs/svelte';
  import { ChevronRight, EyeOff, MessageSquare, Search } from 'lucide-svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { btnFantasma, campo, chico, chip, codigo, cuerpo, tarjeta, tono } from '@/lib/inst';
  import type { BreadcrumbItem } from '@/types';

  interface ActividadItem {
    id_actividad: number;
    nombre: string;
    estado: 'ACTIVA' | 'CERRADA' | 'PLANIFICADA' | 'NO VISIBLE' | string;
    fecha_limite: string | null;
    no_leidos: number;
    grupos_con_no_leidos: number;
    grupo_a_abrir: number | null;
    ultima_fecha: string | null;
    pendientes: number;
    con_conversacion: boolean;
    curso: {
      id_curso: number;
      nombre: string;
      letra_grupo: string | null;
      cod_asignatura: string | null;
      cod_curso: string;
      agno_real: number;
      semestre_real: number;
    };
  }

  interface Props {
    actividades: ActividadItem[];
  }

  let { actividades = [] }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Mensajes', href: '/docente/mensajes' },
  ];

  const CLAVE_VER_TODAS = 'docente.mensajes.verTodas';

  let query = $state('');
  let verTodas = $state(leerVerTodas());

  function leerVerTodas(): boolean {
    try {
      return localStorage.getItem(CLAVE_VER_TODAS) === '1';
    } catch {
      return false;
    }
  }

  $effect(() => {
    try {
      localStorage.setItem(CLAVE_VER_TODAS, verTodas ? '1' : '0');
    } catch {
      /* sin almacenamiento: la elección sólo dura la visita */
    }
  });

  const esActiva = (a: ActividadItem) => a.estado === 'ACTIVA';

  const filtradas = $derived.by(() => {
    const q = query.trim().toLowerCase();
    if (!q) return actividades;
    return actividades.filter(
      (a) =>
        a.nombre.toLowerCase().includes(q) ||
        a.curso.nombre.toLowerCase().includes(q) ||
        (a.curso.cod_asignatura ?? '').toLowerCase().includes(q) ||
        a.curso.cod_curso.toLowerCase().includes(q),
    );
  });

  // Nuevos: lo más reciente arriba.
  const nuevas = $derived(
    filtradas
      .filter((a) => a.no_leidos > 0)
      .sort((a, b) => (b.ultima_fecha ?? '').localeCompare(a.ultima_fecha ?? '')),
  );

  // Vistos: primero las activas por fecha límite más próxima; las demás al
  // final (y sólo si se piden), de la más reciente a la más antigua.
  const vistas = $derived.by(() => {
    const sinNuevos = filtradas.filter((a) => a.no_leidos === 0);
    const porFecha = (x: ActividadItem, y: ActividadItem) =>
      (x.fecha_limite ?? '9999').localeCompare(y.fecha_limite ?? '9999') || x.nombre.localeCompare(y.nombre);
    const activas = sinNuevos.filter(esActiva).sort(porFecha);
    const otras = verTodas ? sinNuevos.filter((a) => !esActiva(a)).sort((x, y) => porFecha(y, x)) : [];
    return [...activas, ...otras];
  });

  const ocultas = $derived(filtradas.filter((a) => a.no_leidos === 0 && !esActiva(a)).length);

  // Abre en «Nuevos» si hay algo sin ver; si no, en «Vistos».
  type Pestana = 'nuevos' | 'vistos';
  let pestanaElegida = $state<Pestana | null>(null);
  const pestana = $derived<Pestana>(pestanaElegida ?? (nuevas.length > 0 ? 'nuevos' : 'vistos'));
  const lista = $derived(pestana === 'nuevos' ? nuevas : vistas);

  const PESTANAS: { id: Pestana; label: string }[] = [
    { id: 'nuevos', label: 'Nuevos' },
    { id: 'vistos', label: 'Vistos' },
  ];

  // Flechas izquierda/derecha entre pestañas (patrón tablist de ARIA).
  function teclaPestana(e: KeyboardEvent) {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    e.preventDefault();
    const siguiente: Pestana = pestana === 'nuevos' ? 'vistos' : 'nuevos';
    pestanaElegida = siguiente;
    document.getElementById(`tab-${siguiente}`)?.focus();
  }

  function href(a: ActividadItem) {
    const base = `/docente/cursos/${a.curso.id_curso}/actividades/${a.id_actividad}/evaluacion`;
    return a.grupo_a_abrir ? `${base}?grupo=${a.grupo_a_abrir}` : base;
  }

  function etiquetaEstado(estado: string) {
    return ({ CERRADA: 'Cerrada', 'NO VISIBLE': 'No visible', PLANIFICADA: 'Planificada' } as Record<string, string>)[estado] ?? estado;
  }

  function codigoCurso(a: ActividadItem) {
    const cod = a.curso.cod_asignatura || a.curso.cod_curso;
    return a.curso.letra_grupo ? `${cod} · Grupo ${a.curso.letra_grupo}` : cod;
  }

  /** «hace 2 h», «ayer», «hace 3 días» o la fecha. */
  function hace(iso: string): string {
    const min = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (min < 1) return 'ahora';
    if (min < 60) return `hace ${min} min`;
    const h = Math.round(min / 60);
    if (h < 24) return `hace ${h} h`;
    const d = Math.round(h / 24);
    if (d === 1) return 'ayer';
    if (d < 7) return `hace ${d} días`;
    return new Date(iso).toLocaleDateString('es-CL', { day: 'numeric', month: 'short' });
  }
</script>

<DocenteLayout {breadcrumbs} ceja="Agenda de las actividades" titulo="Mensajes">
  {#snippet acciones()}
    <p class="m-0 max-w-[360px] text-[13px] text-inst-ink-2">
      Lo que estudiantes y grupos te escriben dentro de cada actividad. Al abrir una vas a la actividad con su agenda
      abierta.
    </p>
  {/snippet}

  <div class={cuerpo}>
    <section aria-label="Bandeja" class="{tarjeta} overflow-hidden">
      <div class="flex flex-wrap items-end gap-x-4 gap-y-3 border-b border-inst-linea px-4 pt-2">
        <div role="tablist" aria-label="Bandeja" class="flex gap-1">
          {#each PESTANAS as t (t.id)}
            {@const sel = pestana === t.id}
            {@const cuenta = t.id === 'nuevos' ? nuevas.length : vistas.length}
            <button
              type="button"
              role="tab"
              id="tab-{t.id}"
              aria-selected={sel}
              aria-controls="panel-bandeja"
              tabindex={sel ? 0 : -1}
              onclick={() => (pestanaElegida = t.id)}
              onkeydown={teclaPestana}
              class="-mb-px inline-flex min-h-11 cursor-pointer items-center gap-2 border-0 border-b-[3px] bg-transparent px-3 text-sm {sel
                ? 'border-inst-oro font-extrabold text-inst-ink'
                : 'border-transparent font-semibold text-inst-ink-2 hover:text-inst-ink'}"
            >
              {t.label}
              <span class="{chip} {t.id === 'nuevos' && cuenta > 0 ? tono.solido : tono.off}">{cuenta}</span>
            </button>
          {/each}
        </div>
        <label class="relative mb-2 ml-auto flex w-full items-center max-sm:order-first max-sm:mt-2 sm:w-72">
          <Search size={16} aria-hidden="true" class="pointer-events-none absolute left-3 text-inst-ink-3" />
          <span class="sr-only">Buscar curso o actividad</span>
          <input type="search" bind:value={query} placeholder="Buscar curso o actividad" class="{campo} pl-9.5" />
        </label>
      </div>

      <div id="panel-bandeja" role="tabpanel" aria-labelledby="tab-{pestana}">
        {#if lista.length === 0}
          <p class="m-0 px-5 py-8 text-center text-inst-ink-2">
            {#if query.trim()}
              Sin resultados para «{query.trim()}».
            {:else if pestana === 'nuevos'}
              Estás al día: no hay mensajes sin ver.
            {:else}
              No hay actividades activas sin mensajes nuevos.
            {/if}
          </p>
        {:else}
          <ul class="m-0 list-none p-0">
            {#each lista as a (a.id_actividad)}
              {@const activa = esActiva(a)}
              {@const nuevo = a.no_leidos > 0}
              <li class="border-b border-inst-linea last:border-b-0">
                <Link
                  href={href(a)}
                  class="group flex items-center gap-3.5 px-4 py-3.5 text-inst-ink no-underline transition-colors hover:text-inst-ink {nuevo
                    ? 'bg-inst-info-s hover:bg-inst-accion-s'
                    : 'hover:bg-inst-sutil'}"
                >
                  <span
                    aria-hidden="true"
                    class="flex size-10 shrink-0 items-center justify-center rounded-full {nuevo
                      ? 'bg-inst-accion text-inst-sobre-accion'
                      : 'bg-inst-off-s text-inst-ink-2'}"
                  >
                    {#if activa}<MessageSquare size={18} />{:else}<EyeOff size={18} />{/if}
                  </span>
                  <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <!-- Dos líneas: con truncate, dos actividades de títulos parecidos se leían iguales. -->
                    <span class="line-clamp-2 leading-snug {nuevo ? 'font-extrabold' : 'font-semibold'} {activa ? '' : 'text-inst-ink-2'}">
                      {a.nombre}
                    </span>
                    <span class={codigo}>
                      {codigoCurso(a)}{a.curso.agno_real && a.curso.semestre_real
                        ? ` · ${a.curso.agno_real}-${a.curso.semestre_real}`
                        : ''}
                    </span>
                    {#if nuevo && a.grupos_con_no_leidos > 1}
                      <span class="text-[13px] text-inst-ink-2">En {a.grupos_con_no_leidos} grupos</span>
                    {/if}
                  </span>
                  <span class="flex shrink-0 flex-col items-end gap-1.5">
                    {#if nuevo && a.ultima_fecha}
                      <span class="text-[13px] text-inst-ink-2">{hace(a.ultima_fecha)}</span>
                    {/if}
                    {#if nuevo}
                      <span class="{chip} {tono.solido}">{a.no_leidos} {a.no_leidos === 1 ? 'nuevo' : 'nuevos'}</span>
                    {:else if !activa}
                      <span class="{chip} {tono.off}">{etiquetaEstado(a.estado)}</span>
                    {:else if a.pendientes > 0}
                      <span class="{chip} {tono.warn}">{a.pendientes} por responder</span>
                    {:else if a.con_conversacion}
                      <span class="{chip} {tono.ok}">Respondido</span>
                    {/if}
                  </span>
                  <ChevronRight size={18} aria-hidden="true" class="shrink-0 text-inst-ink-3 group-hover:text-inst-ink" />
                </Link>
              </li>
            {/each}
          </ul>
        {/if}
      </div>

      {#if pestana === 'vistos' && (ocultas > 0 || verTodas)}
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-inst-linea bg-inst-sutil px-4 py-2.5 text-[13px] text-inst-ink-2">
          <span class="flex min-w-0 flex-1 items-start gap-2.5">
            <EyeOff size={16} aria-hidden="true" class="mt-0.5 shrink-0" />
            <span>
            {#if verTodas}
              Se muestran también las actividades cerradas, no visibles o planificadas.
            {:else}
              {ocultas}
              {ocultas === 1 ? 'actividad cerrada, no visible o planificada no aparece' : 'actividades cerradas, no visibles o planificadas no aparecen'}
              aquí.
            {/if}
            </span>
          </span>
          <button type="button" onclick={() => (verTodas = !verTodas)} class="{btnFantasma} {chico} ml-auto">
            {verTodas ? 'Ocultarlas' : 'Mostrar'}
          </button>
        </div>
      {/if}
    </section>
  </div>
</DocenteLayout>
