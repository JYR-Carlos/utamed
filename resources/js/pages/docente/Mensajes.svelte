<script lang="ts">
  /**
   * Buzón del docente — /docente/mensajes (dirección institucional UTA).
   *
   * Lo mismo que el buzón del alumno (OBS-48), visto desde el otro lado: todo
   * lo que le escribieron, de todos sus cursos, en una lista, para llegar a
   * cada conversación en un clic. Cada fila dice de dónde viene, porque son
   * dos mensajerías distintas:
   *  - Mensajes: la mensajería del curso, de tema libre (la conversación
   *    privada con cada estudiante, o los avisos del curso).
   *  - Actividades: la agenda de una actividad (consultas, feedback,
   *    evaluaciones y apelaciones), que abre la agenda del grupo.
   *
   * Es sólo un índice: la fila abre la conversación en su lugar, donde se lee
   * y se responde. Abrir esta página no marca nada como leído.
   *
   * Al pie, los cursos del semestre vigente para escribir un mensaje nuevo
   * (lo que antes era la página «Mensajes para»).
   */
  import { Link } from '@inertiajs/svelte';
  import { ChevronRight, ClipboardList, Megaphone, MessageSquare, Search } from 'lucide-svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { nombreSemestre } from '@/lib/cursoDocente';
  import { campo, chip, codigo, cuerpo, seccionTitulo, tarjeta, titulo, tono } from '@/lib/inst';
  import { titleCase } from '@/lib/utils';
  import type { BreadcrumbItem } from '@/types';
  import { formatDiaCorto } from '@/utils/formatters';

  type Origen = 'mensaje' | 'aviso' | 'actividad';

  interface CursoHilo {
    id_curso: number;
    nombre: string;
    codigo: string | null;
    letra_grupo: string | null;
  }

  interface Hilo {
    clave: string;
    origen: Origen;
    curso: CursoHilo;
    componente: string;
    /** El estudiante, «Avisos del curso» o la actividad. */
    titulo: string;
    /** En una actividad: «En 3 grupos» cuando hay nuevos en más de uno. */
    detalle: string | null;
    /** Tipo del último mensaje de la agenda. */
    tipo: string | null;
    tema: string | null;
    texto: string | null;
    emisor: string | null;
    es_mio: boolean;
    fecha: string;
    no_leidos: number;
    /** Conversaciones (o grupos) cuyo último mensaje es del estudiante. */
    por_responder: number;
    href: string;
  }

  interface CursoVigente extends CursoHilo {
    no_leidos: number;
  }

  interface Props {
    periodo: { sem: number; ano: number } | null;
    hilos: Hilo[];
    cursos: CursoVigente[];
  }

  let { periodo, hilos = [], cursos = [] }: Props = $props();

  const ORIGEN = {
    mensaje: { etiqueta: 'Mensaje privado', icono: MessageSquare },
    aviso: { etiqueta: 'Aviso del curso', icono: Megaphone },
    actividad: { etiqueta: 'Actividad', icono: ClipboardList },
  } as const;

  /** Cómo se llama cada tipo de la agenda. */
  const TIPO_AGENDA: Record<string, string> = {
    'Mensaje al profesor': 'Consulta',
    Feedback: 'Feedback',
    Evaluación: 'Evaluación',
    'Solicitud de apelación': 'Apelación',
    'Resolución de apelación': 'Respuesta a la apelación',
  };

  type Filtro = 'todo' | 'mensajes' | 'actividades';
  const FILTROS: { id: Filtro; etiqueta: string; origenes: Origen[] }[] = [
    { id: 'todo', etiqueta: 'Todo', origenes: ['mensaje', 'aviso', 'actividad'] },
    { id: 'mensajes', etiqueta: 'Mensajes', origenes: ['mensaje', 'aviso'] },
    { id: 'actividades', etiqueta: 'Actividades', origenes: ['actividad'] },
  ];
  let filtro = $state<Filtro>('todo');
  let busqueda = $state('');

  const delFiltro = (f: Filtro) => hilos.filter((h) => FILTROS.find((x) => x.id === f)!.origenes.includes(h.origen));
  const sinLeerDe = (f: Filtro) => delFiltro(f).reduce((total, h) => total + h.no_leidos, 0);
  const sinLeer = $derived(sinLeerDe('todo'));

  const sinTildes = (t: string) => t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

  const visibles = $derived.by(() => {
    const q = sinTildes(busqueda.trim());
    const delOrigen = delFiltro(filtro);
    if (!q) return delOrigen;
    return delOrigen.filter((h) =>
      sinTildes([h.titulo, h.curso.nombre, h.curso.codigo ?? '', h.componente].join(' ')).includes(q),
    );
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Mensajes', href: '/docente/mensajes' },
  ];

  function codigoCurso(c: CursoHilo): string {
    const cod = c.codigo ?? c.nombre;
    return c.letra_grupo ? `${cod} · Grupo ${c.letra_grupo}` : cod;
  }

  /** «Mié 18 nov, 11:43». */
  function fecha(iso: string): string {
    const d = new Date(iso.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '';
    const dia = formatDiaCorto(d);
    const hora = d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
    return `${dia.charAt(0).toUpperCase()}${dia.slice(1)}, ${hora}`;
  }

  /** Quién escribió lo último y, en la agenda, de qué tipo es. */
  function quien(h: Hilo): string {
    const persona = h.es_mio ? 'Tú' : titleCase(h.emisor ?? '');
    const tipo = h.tipo ? (TIPO_AGENDA[h.tipo] ?? h.tipo) : null;
    return tipo ? `${persona} · ${tipo}` : persona;
  }

  function texto(h: Hilo): string {
    const cuerpo = (h.texto ?? '').replace(/\s+/g, ' ').trim();
    if (!cuerpo && h.tipo === 'Evaluación') return h.es_mio ? 'Publicaste la evaluación.' : 'Publicó la evaluación.';
    return (h.tema?.trim() ? `${h.tema.trim()} · ` : '') + cuerpo;
  }

  function porResponder(h: Hilo): string {
    return h.origen === 'actividad' && h.por_responder > 1 ? `${h.por_responder} por responder` : 'Por responder';
  }

  const botonFiltro =
    'inline-flex min-h-11 cursor-pointer items-center justify-center gap-1.5 border-0 border-r border-inst-linea px-3.5 text-[13px] font-bold last:border-r-0 lg:min-h-9';
</script>

<svelte:head>
  <title>Mensajes | UTAmed</title>
</svelte:head>

<DocenteLayout {breadcrumbs} ceja={periodo ? nombreSemestre(periodo.sem, periodo.ano) : undefined} titulo="Mensajes">
  {#snippet acciones()}
    <p class="m-0 max-w-[380px] text-[13px] text-inst-ink-2">
      {#if sinLeer > 0}
        {sinLeer} sin leer: {sinLeerDe('mensajes')} en mensajes y {sinLeerDe('actividades')} en actividades.
      {:else}
        La mensajería de tus cursos y la agenda de tus actividades, en un solo lugar.
      {/if}
    </p>
  {/snippet}

  <div class="{cuerpo} max-w-[1040px]">
    <section aria-label="Buzón" class="{tarjeta} overflow-hidden">
      <div class="flex flex-col gap-2.5 border-b border-inst-linea px-4 py-3">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
          <div role="group" aria-label="Mostrar" class="grid grid-cols-3 overflow-hidden rounded-md border border-inst-control sm:inline-flex">
            {#each FILTROS as f (f.id)}
              {@const activo = filtro === f.id}
              {@const n = sinLeerDe(f.id)}
              <button
                type="button"
                aria-pressed={activo}
                onclick={() => (filtro = f.id)}
                class="{botonFiltro} {activo ? 'bg-inst-accion text-inst-sobre-accion' : 'bg-inst-tarjeta text-inst-ink hover:bg-inst-sutil'}"
              >
                {f.etiqueta}
                {#if n > 0}
                  <span class="{chip} {activo ? 'bg-inst-tarjeta text-inst-accion-t' : tono.solido}"
                    >{n}<span class="sr-only">{' '}sin leer</span></span
                  >
                {/if}
              </button>
            {/each}
          </div>
          <label class="relative flex w-full items-center sm:ml-auto sm:w-72">
            <Search size={16} aria-hidden="true" class="pointer-events-none absolute left-3 text-inst-ink-3" />
            <span class="sr-only">Buscar estudiante, curso o actividad</span>
            <input type="search" bind:value={busqueda} placeholder="Estudiante, curso o actividad" class="{campo} pl-9.5" />
          </label>
        </div>
        <p class="m-0 text-[13px] text-inst-ink-2">
          <span class="font-bold text-inst-ink">Mensajes</span>: tu conversación privada con cada estudiante y los avisos de
          cada curso. <span class="font-bold text-inst-ink">Actividades</span>: las consultas, el feedback, las evaluaciones
          y las apelaciones de cada actividad.
        </p>
      </div>

      {#if visibles.length === 0}
        <div class="px-6 py-10 text-center">
          {#if hilos.length === 0}
            <p class="m-0 font-bold">Aún no tienes mensajes</p>
            <p class="m-0 mt-1 text-inst-ink-2">Cuando un estudiante te escriba o haya consultas en tus actividades, aparecerán aquí.</p>
          {:else if busqueda.trim()}
            <p class="m-0 font-bold">Sin resultados</p>
            <p class="m-0 mt-1 text-inst-ink-2">Nada coincide con «{busqueda.trim()}».</p>
          {:else}
            <p class="m-0 font-bold">Nada por aquí</p>
            <p class="m-0 mt-1 text-inst-ink-2">
              {filtro === 'actividades'
                ? 'Todavía no hay consultas ni feedback en tus actividades.'
                : 'Todavía no hay mensajes ni avisos en tus cursos.'}
            </p>
          {/if}
        </div>
      {:else}
        <ul class="m-0 list-none p-0" aria-label="Conversaciones">
          {#each visibles as h (h.clave)}
            {@const o = ORIGEN[h.origen]}
            {@const Icono = o.icono}
            {@const nuevo = h.no_leidos > 0}
            <li class="border-b border-inst-linea last:border-b-0">
              <Link
                href={h.href}
                class="group flex items-start gap-3.5 px-4 py-3.5 text-inst-ink no-underline transition-colors hover:text-inst-ink lg:px-5 {nuevo
                  ? 'bg-inst-info-s hover:bg-inst-accion-s'
                  : 'hover:bg-inst-sutil'}"
              >
                <!-- En el teléfono el ícono va chico junto al origen: el texto necesita el ancho. -->
                <span
                  aria-hidden="true"
                  class="mt-0.5 hidden size-10 shrink-0 items-center justify-center rounded-full sm:flex {nuevo
                    ? 'bg-inst-accion text-inst-sobre-accion'
                    : 'bg-inst-off-s text-inst-ink-2'}"
                >
                  <Icono size={18} />
                </span>
                <span class="flex min-w-0 flex-1 flex-col gap-1">
                  <span class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                    <span class={codigo}>{codigoCurso(h.curso)} · {h.componente}</span>
                    <span class="inline-flex items-center gap-1 text-[13px] font-semibold text-inst-ink-2">
                      <Icono size={14} aria-hidden="true" class="sm:hidden" />{o.etiqueta}
                    </span>
                    <span class="ml-auto text-[13px] text-inst-ink-2 tabular-nums">{fecha(h.fecha)}</span>
                  </span>
                  <span class="flex items-start gap-2">
                    <span class="min-w-0 flex-1 leading-snug text-pretty {nuevo ? 'font-extrabold' : 'font-semibold'}">
                      {h.origen === 'mensaje' ? titleCase(h.titulo) : h.titulo}{#if h.detalle}<span class="font-normal text-inst-ink-2">{' '}· {h.detalle}</span>{/if}
                    </span>
                    {#if nuevo}
                      <span class="{chip} {tono.solido} shrink-0">{h.no_leidos === 1 ? '1 nuevo' : `${h.no_leidos} nuevos`}</span>
                    {:else if h.por_responder > 0}
                      <span class="{chip} {tono.warn} shrink-0">{porResponder(h)}</span>
                    {/if}
                  </span>
                  <span class="line-clamp-2 text-sm leading-5 {nuevo ? 'text-inst-ink' : 'text-inst-ink-2'}">
                    <span class="font-semibold">{quien(h)}:</span>{' '}{texto(h)}
                  </span>
                </span>
                <ChevronRight size={18} aria-hidden="true" class="hidden shrink-0 self-center text-inst-ink-3 group-hover:text-inst-ink sm:block" />
              </Link>
            </li>
          {/each}
        </ul>
      {/if}
    </section>

    <section aria-labelledby="t-escribir" class="flex flex-col gap-3">
      <div class={seccionTitulo}>
        <h2 id="t-escribir" class="{titulo} m-0 text-[19px] leading-6">Escribir un mensaje nuevo</h2>
      </div>
      {#if cursos.length > 0}
        <p class="m-0 text-inst-ink-2">
          Elige el curso para abrir su mensajería: ahí envías un aviso a todo el curso o le escribes a un estudiante. A los grupos
          de una actividad se les escribe desde la actividad.
        </p>
        <ul class="{tarjeta} m-0 list-none overflow-hidden p-0">
          {#each cursos as c (c.id_curso)}
            <li class="border-b border-inst-linea last:border-b-0">
              <Link
                href="/docente/cursos/{c.id_curso}/mensajeria"
                class="flex min-h-16 items-center gap-4 px-5 py-3 text-inst-ink no-underline hover:bg-inst-sutil hover:text-inst-ink"
              >
                <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-inst-accion-s text-inst-accion-t">
                  <MessageSquare size={20} />
                </span>
                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                  <span class={codigo}>{codigoCurso(c)}</span>
                  <span class="{titulo} text-[17px] leading-[22px]">{c.nombre}</span>
                </span>
                {#if c.no_leidos > 0}
                  <span class="{chip} {tono.solido} shrink-0">{c.no_leidos} sin leer</span>
                {/if}
                <ChevronRight size={18} aria-hidden="true" class="shrink-0 text-inst-ink-3" />
              </Link>
            </li>
          {/each}
        </ul>
      {:else}
        <p class="m-0 text-inst-ink-2">Cuando tengas cursos asignados este semestre, su mensajería aparecerá aquí.</p>
      {/if}
    </section>
  </div>
</DocenteLayout>
