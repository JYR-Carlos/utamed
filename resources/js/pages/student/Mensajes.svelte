<script lang="ts">
  /**
   * Buzón del alumno (OBS-48, OBS-54): todo lo que le escribieron, de todos
   * sus ramos, en una lista, para llegar a cada conversación en un clic.
   *
   * Cada fila dice de dónde viene, porque son dos mensajerías distintas:
   *  - Mensajes: la mensajería del ramo, de tema libre (conversación privada
   *    con alguien del equipo, o los avisos del curso).
   *  - Actividades: la agenda de una actividad (consultas, feedback,
   *    evaluaciones y apelaciones).
   *
   * Es sólo un índice: la fila abre la conversación en su lugar, donde se lee
   * y se responde. Abrir esta página no marca nada como leído.
   */
  import { Link, page } from '@inertiajs/svelte';
  import { Check, ChevronRight, ClipboardList, Megaphone, MessageSquare } from 'lucide-svelte';
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import Reticula from '@/components/student/dm/Reticula.svelte';
  import { cursosDelSemestreVigente, estiloCurso, numeroColorCurso } from '@/lib/colorCurso';
  import { etiqueta, pildora, tarjeta, tituloCond, tono } from '@/lib/dm';
  import type { BreadcrumbItem, SidebarCourse } from '@/types';
  import { formatDiaCorto } from '@/utils/formatters';

  type Origen = 'mensaje' | 'aviso' | 'actividad';

  interface Hilo {
    clave: string;
    origen: Origen;
    curso: { id_curso: number; nombre: string; codigo: string | null };
    componente: string;
    titulo: string;
    /** Papel de la persona (Titular, Docente, Ayudante). */
    detalle: string | null;
    /** Tipo del último mensaje de la agenda. */
    tipo: string | null;
    tema: string | null;
    texto: string | null;
    emisor: string | null;
    es_mio: boolean;
    fecha: string;
    no_leidos: number;
    href: string;
  }

  let { hilos = [] }: { hilos: Hilo[] } = $props();

  const ORIGEN = {
    mensaje: { etiqueta: 'Mensaje privado', icono: MessageSquare, tono: tono.accion },
    aviso: { etiqueta: 'Aviso del curso', icono: Megaphone, tono: tono.neutro },
    actividad: { etiqueta: 'Actividad', icono: ClipboardList, tono: tono.info },
  } as const;

  /** Cómo se llama cada tipo de la agenda para el alumno. */
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

  const delFiltro = (f: Filtro) => hilos.filter((h) => FILTROS.find((x) => x.id === f)!.origenes.includes(h.origen));
  const sinLeerDe = (f: Filtro) => delFiltro(f).reduce((total, h) => total + h.no_leidos, 0);
  const visibles = $derived(delFiltro(filtro));
  const sinLeer = $derived(sinLeerDe('todo'));

  const cursosMenu = $derived(($page.props.auth?.estudiante_courses as SidebarCourse[]) ?? []);
  const vigentes = $derived(cursosDelSemestreVigente(cursosMenu));
  const colorDe = (idCurso: number) => estiloCurso(numeroColorCurso(idCurso, cursosMenu));

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mensajes', href: '/estudiante/mensajes' },
  ];

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
    const persona = h.es_mio ? 'Tú' : (h.emisor ?? '');
    const tipo = h.tipo ? TIPO_AGENDA[h.tipo] ?? h.tipo : null;
    return tipo ? `${persona} · ${tipo}` : persona;
  }

  function texto(h: Hilo): string {
    const cuerpo = (h.texto ?? '').replace(/\s+/g, ' ').trim();
    if (!cuerpo && h.tipo === 'Evaluación') return 'Publicó tu evaluación.';
    return (h.tema?.trim() ? `${h.tema.trim()} · ` : '') + cuerpo;
  }
</script>

<svelte:head>
  <title>Mensajes | UTAMED</title>
</svelte:head>

<StudentLayout {breadcrumbs}>
  <div class="mx-auto flex w-full max-w-280 flex-col gap-6 pb-10 lg:gap-8 lg:px-10 lg:pt-8 lg:pb-12">
    <section
      aria-labelledby="t-mensajes"
      class="sobre-noche relative overflow-hidden bg-dm-noche lg:rounded-xl lg:border lg:border-dm-noche-borde"
    >
      <Reticula class="left-[45%]" />
      <div class="relative flex flex-col gap-2 px-4 py-6 lg:px-10 lg:py-8">
        <span class="{etiqueta} text-dm-amarillo">Buzón de tus ramos</span>
        <h1 id="t-mensajes" class="{tituloCond} text-[40px] leading-10 text-dm-noche-texto lg:text-[56px] lg:leading-[52px]">
          Mensajes
        </h1>
        <p class="text-base leading-6 text-dm-noche-texto-2">
          {#if sinLeer > 0}
            {sinLeer} sin leer: {sinLeerDe('mensajes')} en mensajes y {sinLeerDe('actividades')} en actividades.
          {:else}
            Los mensajes de tus ramos y las consultas de tus actividades, en un solo lugar.
          {/if}
        </p>
      </div>
    </section>

    <div class="flex flex-col gap-5 px-4 lg:px-0">
      <div class="flex flex-col gap-3">
        <div role="group" aria-label="Mostrar" class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap">
          {#each FILTROS as f (f.id)}
            {@const activo = filtro === f.id}
            {@const n = sinLeerDe(f.id)}
            <button
              type="button"
              aria-pressed={activo}
              onclick={() => (filtro = f.id)}
              class="inline-flex min-h-11 cursor-pointer items-center justify-center gap-1.5 rounded-full border px-2 py-1.5 text-sm font-semibold sm:px-3.5 lg:min-h-10 {activo
                ? 'border-dm-amarillo bg-dm-activo-suave text-dm-activo-texto'
                : 'border-dm-control bg-dm-tarjeta text-dm-ink hover:bg-dm-sutil'}"
            >
              {#if activo}<Check size={16} aria-hidden="true" class="hidden sm:block" />{/if}{f.etiqueta}
              {#if n > 0}<span class="rounded-full bg-dm-accion px-1.5 text-xs leading-5 text-dm-sobre-accion"
                  >{n}<span class="sr-only">{' '}sin leer</span></span
                >{/if}
            </button>
          {/each}
        </div>
        <p class="m-0 max-w-200 text-sm leading-5 text-dm-ink-2">
          <span class="font-semibold text-dm-ink">Mensajes</span>: tus conversaciones privadas con el equipo docente y los
          avisos de cada ramo. <span class="font-semibold text-dm-ink">Actividades</span>: las consultas, el feedback y las
          evaluaciones de cada actividad.
        </p>
      </div>

      {#if visibles.length === 0}
        <EstadoVacio
          icono={MessageSquare}
          titulo={hilos.length === 0 ? 'Aún no tienes mensajes' : 'Nada por aquí'}
          texto={hilos.length === 0
            ? 'Cuando tu equipo docente te escriba o respondas una consulta, aparecerá aquí.'
            : filtro === 'actividades'
              ? 'Todavía no hay consultas ni feedback en tus actividades.'
              : 'Todavía no hay mensajes ni avisos en tus ramos.'}
        />
      {:else}
        <ul class="m-0 flex list-none flex-col gap-3 p-0" aria-label="Conversaciones">
          {#each visibles as h (h.clave)}
            {@const o = ORIGEN[h.origen]}
            {@const Icono = o.icono}
            <li class="{tarjeta} flex flex-col overflow-hidden border-l-4 border-l-(--curso) sm:flex-row" style={colorDe(h.curso.id_curso)}>
              <Link
                href={h.href}
                class="flex min-w-0 flex-1 items-start gap-3 px-4 py-3.5 text-dm-ink no-underline hover:bg-dm-sutil lg:px-5"
              >
                <!-- En móvil el ícono va chico junto al origen: el texto necesita el ancho. -->
                <span class="mt-0.5 hidden size-10 shrink-0 items-center justify-center rounded-full sm:flex {o.tono}">
                  <Icono size={18} aria-hidden="true" />
                </span>
                <span class="flex min-w-0 flex-1 flex-col gap-1">
                  <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="{pildora} {tono.curso}">
                      <span aria-hidden="true" class="size-2 rounded-full bg-(--curso)"></span>{h.curso.codigo ?? h.curso.nombre} · {h.componente}
                    </span>
                    <span class="inline-flex items-center gap-1 text-[13px] leading-[18px] font-semibold text-dm-ink-2">
                      <Icono size={14} aria-hidden="true" class="sm:hidden" />{o.etiqueta}
                    </span>
                    <span class="ml-auto text-[13px] leading-[18px] text-dm-ink-3 tabular-nums">{fecha(h.fecha)}</span>
                  </span>
                  <span class="flex items-start gap-2">
                    <span class="min-w-0 flex-1 text-base leading-6 font-semibold text-pretty">
                      {h.titulo}{#if h.detalle}<span class="font-normal text-dm-ink-3">{' '}· {h.detalle}</span>{/if}
                    </span>
                    {#if h.no_leidos > 0}
                      <span class="shrink-0 rounded-full bg-dm-accion px-2 text-[13px] leading-6 font-bold text-dm-sobre-accion">
                        {h.no_leidos === 1 ? '1 nuevo' : `${h.no_leidos} nuevos`}
                      </span>
                    {/if}
                  </span>
                  <span class="line-clamp-2 text-[15px] leading-[22px] {h.no_leidos > 0 ? 'text-dm-ink' : 'text-dm-ink-2'}">
                    <span class="font-semibold">{quien(h)}:</span>{' '}{texto(h)}
                  </span>
                </span>
                <ChevronRight size={18} aria-hidden="true" class="hidden shrink-0 self-center text-dm-ink-3 sm:block" />
              </Link>
              <Link
                href={`/estudiante/cursos/${h.curso.id_curso}`}
                class="flex min-h-11 items-center gap-1 border-t border-dm-linea px-4 text-sm font-semibold text-dm-enlace no-underline hover:bg-dm-sutil hover:text-dm-enlace-hover hover:underline sm:border-t-0 sm:border-l"
              >
                Ver ramo<span class="sr-only">{' '}{h.curso.nombre}</span>
              </Link>
            </li>
          {/each}
        </ul>
      {/if}

      {#if vigentes.length > 0}
        <section aria-labelledby="t-escribir" class="flex flex-col gap-2">
          <h2 id="t-escribir" class="text-base leading-6 font-semibold">¿Quieres escribir un mensaje nuevo?</h2>
          <p class="m-0 text-sm leading-5 text-dm-ink-2">Entra a la Mensajería del ramo y elige con quién hablar.</p>
          <div class="flex flex-wrap gap-2">
            {#each vigentes as c (c.id_curso)}
              <Link
                href={`/estudiante/cursos/${c.id_curso}?seccion=mensajeria`}
                style={colorDe(c.id_curso)}
                class="{pildora} {tono.curso} min-h-11 px-3 no-underline hover:underline lg:min-h-9"
              >
                <span aria-hidden="true" class="size-2 rounded-full bg-(--curso)"></span>{c.cod_asignatura || c.cod_curso}
                <span class="font-normal">{c.nombre}</span>
              </Link>
            {/each}
          </div>
        </section>
      {/if}
    </div>
  </div>
</StudentLayout>
