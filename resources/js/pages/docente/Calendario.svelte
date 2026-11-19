<script lang="ts">
  /**
   * Calendario del docente.
   *
   * De almanaque a herramienta de la semana. El color pertenece al CURSO, no al
   * tipo de evento: con varios cursos abiertos la primera pregunta es "de qué
   * curso es esta fecha". Se pintan tres familias sobre el mismo eje temporal:
   *
   * - Fechas límite de actividades (fecha NOMINAL; la efectiva de cada alumno
   *   suma su holgura y se ve en el detalle de la actividad).
   * - Sesiones de asistencia ya tomadas, con su contador de presentes.
   * - Hitos de syllabus: fechas límite de entrega y aprobación/rechazo.
   *
   * Además detecta cuándo un mismo grupo acumula tres o más entregas en un día,
   * contando siempre todas las entregas: ocultar un curso con el filtro no
   * reduce la carga real del grupo.
   *
   * Tres vistas —mes, semana y agenda— sobre los mismos datos. En pantallas
   * estrechas la vista por defecto es la agenda: escanear, no navegar.
   *
   * No crea, edita ni elimina nada: desde el detalle del día se sale al curso.
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { btnFantasma, btnSecundario, campo, chico, cuerpo, tarjeta, titulo as tituloInst } from '@/lib/inst';
  import type { BreadcrumbItem } from '@/types';
  import {
    CalendarAgenda,
    CalendarMonth,
    CalendarWeek,
    CalendarioVacio,
    DayEventsDialog,
    accentFor,
    buildWeekDays,
    calcularSobrecargas,
    codigoCurso,
    formatRangoSemana,
    inicioSemana,
    minutosDeHora,
    sumarDias,
    todayKey,
    toKey,
    MESES,
    PILL_SOBRECARGA,
    type CalendarCurso,
    type CalendarEvento,
    type CalendarHito,
    type CalendarItem,
    type CalendarSesion,
    type CursoAccent,
    type FamiliaItem,
  } from '@/modules/resources/calendario';
  import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    ClipboardList,
    Clock,
    Columns3,
    LayoutList,
    SlidersHorizontal,
    TriangleAlert,
  } from 'lucide-svelte';
  import { onMount } from 'svelte';

  interface Props {
    cursos: CalendarCurso[];
    eventos: CalendarEvento[];
    sesiones: CalendarSesion[];
    hitos: CalendarHito[];
  }

  let { cursos = [], eventos = [], sesiones = [], hitos = [] }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Calendario', href: '/docente/calendario' },
  ];

  type Vista = 'mes' | 'semana' | 'agenda';

  // ── Paleta estable por curso ────────────────────────────────────────────────
  // El acento se asigna sobre TODOS los cursos, no sobre los filtrados: así el
  // mismo curso conserva su tono al cambiar de filtro o de período.
  const accentPorCurso = $derived.by(() => {
    const map: Record<number, CursoAccent> = {};
    cursos.forEach((c, i) => (map[c.id_curso] = accentFor(i)));
    return map;
  });

  const cursoPorId = $derived.by(() => {
    const map: Record<number, CalendarCurso> = {};
    cursos.forEach((c) => (map[c.id_curso] = c));
    return map;
  });

  // ── Filtro de período académico ─────────────────────────────────────────────
  function clavePeriodo(curso: CalendarCurso): string {
    return `${curso.agno_real ?? '?'}-${curso.semestre_real ?? '?'}`;
  }

  // Del más reciente al más antiguo; «?-?» (curso sin período) al final.
  const periodos = $derived(
    [...new Set(cursos.map(clavePeriodo))].sort((a, b) => {
      const [aa, as] = a.split('-').map((n) => Number(n) || 0);
      const [ba, bs] = b.split('-').map((n) => Number(n) || 0);
      return ba - aa || bs - as;
    }),
  );

  // Por defecto el período más reciente, como en el resto de la vista del
  // docente; «Todos los períodos» sigue disponible en el selector.
  let periodoElegido = $state<string | null>(null);
  const periodo = $derived(periodoElegido ?? periodos[0] ?? 'todos');

  const ceja = $derived.by(() => {
    const [agno, sem] = (periodo === 'todos' ? (periodos[0] ?? '') : periodo).split('-');
    return Number(agno) && Number(sem) ? `Semestre ${sem} · ${agno}` : undefined;
  });

  const cursosDelPeriodo = $derived(
    periodo === 'todos' ? cursos : cursos.filter((c) => clavePeriodo(c) === periodo),
  );

  const idsDelPeriodo = $derived(new Set(cursosDelPeriodo.map((c) => c.id_curso)));

  // ── Navegación ──────────────────────────────────────────────────────────────
  const hoyIso = todayKey();
  let ancla = $state(todayKey());
  let vista = $state<Vista>('mes');
  let selectedDay = $state<string | null>(null);
  let filtrosAbiertos = $state(true);

  // En pantallas estrechas la agenda es la vista útil: la grilla mensual a
  // 390px no se lee, y los filtros empiezan plegados. Sólo se aplica al
  // montar, no pisa la elección del docente.
  onMount(() => {
    if (window.matchMedia('(max-width: 767px)').matches) {
      vista = 'agenda';
      filtrosAbiertos = false;
    }
  });

  const anclaFecha = $derived.by(() => {
    const [y, m, d] = ancla.split('-').map(Number);
    return { year: y, month: m - 1, day: d };
  });

  const diasSemana = $derived(buildWeekDays(ancla));

  function paso(direccion: 1 | -1) {
    if (vista === 'mes') {
      const { year, month } = anclaFecha;
      ancla = toKey(new Date(year, month + direccion, 1));
    } else {
      ancla = sumarDias(inicioSemana(ancla), direccion * 7);
    }
  }

  function irHoy() {
    ancla = todayKey();
  }

  function cambiarVista(siguiente: Vista) {
    // Al pasar de mes a semana se ancla en la semana que contiene el día 1 del
    // mes visible, salvo que el mes visible sea el actual: entonces, en hoy.
    if (vista === 'mes' && siguiente !== 'mes') {
      const { year, month } = anclaFecha;
      const hoy = new Date();
      ancla =
        hoy.getFullYear() === year && hoy.getMonth() === month
          ? todayKey()
          : toKey(new Date(year, month, 1));
    }
    vista = siguiente;
  }

  // ── Filtros de curso y de familia ───────────────────────────────────────────
  // Se modela con el conjunto de cursos *ocultos* (vacío = todos visibles), lo
  // que evita capturar el valor inicial de los props en el estado.
  let ocultos = $state<Set<number>>(new Set());
  let familiasOcultas = $state<Set<FamiliaItem>>(new Set());

  const activos = $derived.by(() => {
    const s = new Set<number>();
    for (const c of cursosDelPeriodo) if (!ocultos.has(c.id_curso)) s.add(c.id_curso);
    return s;
  });

  const familiasActivas = $derived.by(() => {
    const s = new Set<FamiliaItem>();
    for (const f of ['ENTREGA', 'SESION', 'HITO'] as FamiliaItem[]) {
      if (!familiasOcultas.has(f)) s.add(f);
    }
    return s;
  });

  function toggleCurso(id: number) {
    const next = new Set(ocultos);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    ocultos = next;
  }
  function todosCursos() {
    ocultos = new Set();
  }
  function toggleFamilia(f: FamiliaItem) {
    const next = new Set(familiasOcultas);
    if (next.has(f)) next.delete(f);
    else next.add(f);
    familiasOcultas = next;
  }

  // ── Marcas unificadas ───────────────────────────────────────────────────────
  const entregasDelPeriodo = $derived(eventos.filter((e) => idsDelPeriodo.has(e.id_curso)));

  const items = $derived.by(() => {
    const lista: CalendarItem[] = [];

    for (const e of entregasDelPeriodo) {
      lista.push({
        familia: 'ENTREGA',
        key: `e-${e.id_actividad}-${e.id_componente}`,
        id_curso: e.id_curso,
        fecha: e.fecha,
        entrega: e,
      });
    }
    for (const s of sesiones) {
      if (!idsDelPeriodo.has(s.id_curso)) continue;
      lista.push({
        familia: 'SESION',
        key: `s-${s.id_componente}-${s.fecha}-${s.hora_inicio}`,
        id_curso: s.id_curso,
        fecha: s.fecha,
        sesion: s,
      });
    }
    for (const h of hitos) {
      if (!idsDelPeriodo.has(h.id_curso)) continue;
      lista.push({
        familia: 'HITO',
        key: `h-${h.id}`,
        id_curso: h.id_curso,
        fecha: h.fecha,
        hito: h,
      });
    }

    return lista;
  });

  /** Orden estable dentro de un día: entregas, luego clases, luego hitos. */
  const ORDEN_FAMILIA: Record<FamiliaItem, number> = { ENTREGA: 0, SESION: 1, HITO: 2 };

  function comparar(a: CalendarItem, b: CalendarItem): number {
    const porFamilia = ORDEN_FAMILIA[a.familia] - ORDEN_FAMILIA[b.familia];
    if (porFamilia !== 0) return porFamilia;

    if (a.familia === 'ENTREGA' && b.familia === 'ENTREGA') {
      return (
        Number(b.entrega.tipo_actividad === 'SUMATIVA') -
          Number(a.entrega.tipo_actividad === 'SUMATIVA') ||
        a.entrega.titulo.localeCompare(b.entrega.titulo)
      );
    }
    if (a.familia === 'SESION' && b.familia === 'SESION') {
      return minutosDeHora(a.sesion.hora_inicio) - minutosDeHora(b.sesion.hora_inicio);
    }
    return 0;
  }

  const itemsVisibles = $derived(
    items.filter((i) => activos.has(i.id_curso) && familiasActivas.has(i.familia)),
  );

  const itemsPorDia = $derived.by(() => {
    const map: Record<string, CalendarItem[]> = {};
    for (const item of itemsVisibles) (map[item.fecha] ??= []).push(item);
    for (const k in map) map[k].sort(comparar);
    return map;
  });

  const itemsDiaSeleccionado = $derived(selectedDay ? (itemsPorDia[selectedDay] ?? []) : []);

  // ── Sobrecarga por grupo ────────────────────────────────────────────────────
  // Siempre sobre todas las entregas del período: ocultar un curso no reduce la
  // carga real del grupo, sólo cambia cuántas de esas entregas se ven.
  const sobrecargas = $derived(calcularSobrecargas(entregasDelPeriodo, cursoPorId, activos));

  const cargasDiaSeleccionado = $derived(selectedDay ? (sobrecargas[selectedDay] ?? []) : []);

  // ── Rango mostrado y recuentos ──────────────────────────────────────────────
  const rango = $derived.by(() => {
    if (vista === 'mes') {
      const { year, month } = anclaFecha;
      return {
        desde: toKey(new Date(year, month, 1)),
        hasta: toKey(new Date(year, month + 1, 0)),
        titulo: `${MESES[month]} ${year}`,
        rotulo: 'Este mes',
      };
    }
    const desde = diasSemana[0].iso;
    const hasta = diasSemana[diasSemana.length - 1].iso;
    return {
      desde,
      hasta,
      titulo: formatRangoSemana(diasSemana),
      rotulo: 'Esta semana',
    };
  });

  function enRango(iso: string): boolean {
    return iso >= rango.desde && iso <= rango.hasta;
  }

  /** Marcas del rango, con el filtro de familia pero sin el de curso. */
  const enRangoPorCurso = $derived.by(() => {
    const map: Record<number, number> = {};
    for (const item of items) {
      if (!enRango(item.fecha) || !familiasActivas.has(item.familia)) continue;
      map[item.id_curso] = (map[item.id_curso] ?? 0) + 1;
    }
    return map;
  });

  /** Marcas del rango por familia, con el filtro de curso pero sin el de familia. */
  const conteosFamilia = $derived.by(() => {
    const map: Record<FamiliaItem, number> = { ENTREGA: 0, SESION: 0, HITO: 0 };
    for (const item of items) {
      if (!enRango(item.fecha) || !activos.has(item.id_curso)) continue;
      map[item.familia] += 1;
    }
    return map;
  });

  const diasConSobrecarga = $derived(Object.keys(sobrecargas).filter(enRango).length);

  /** Primera marca visible después del rango, para el salto desde la agenda vacía. */
  const proximaMarca = $derived(
    itemsVisibles
      .filter((i) => i.fecha > rango.hasta)
      .sort((a, b) => a.fecha.localeCompare(b.fecha) || comparar(a, b))[0] ?? null,
  );

  // Muestras de la leyenda: el mismo trazo que EventPill, con el azul de acción.
  const MUESTRA = {
    sumativa: 'background:var(--color-inst-accion-s);border-left:3px solid var(--color-inst-accion)',
    formativa:
      'background:var(--color-inst-tarjeta);border:1px dashed var(--color-inst-accion);border-left:3px solid var(--color-inst-accion)',
    oculta:
      'background:var(--color-inst-off-s);border:1px dashed var(--color-inst-control);border-left:3px solid var(--color-inst-accion)',
  };

  const hayDatos = $derived(eventos.length + sesiones.length + hitos.length > 0);
  const todosVisibles = $derived(activos.size === cursosDelPeriodo.length);

  const VISTAS: { id: Vista; label: string; icon: typeof CalendarDays }[] = [
    { id: 'mes', label: 'Mes', icon: CalendarDays },
    { id: 'semana', label: 'Semana', icon: Columns3 },
    { id: 'agenda', label: 'Agenda', icon: LayoutList },
  ];

  const FAMILIAS: { id: FamiliaItem; label: string; icon: typeof CalendarDays }[] = [
    { id: 'ENTREGA', label: 'Fechas límite', icon: ClipboardCheck },
    { id: 'SESION', label: 'Clases con asistencia', icon: Clock },
    { id: 'HITO', label: 'Hitos de syllabus', icon: ClipboardList },
  ];

  const botonSeg =
    'inline-flex min-h-11 cursor-pointer items-center gap-1.5 border-0 border-r border-inst-linea px-3.5 text-[13px] font-bold last:border-r-0 lg:min-h-9';
  const filtro =
    'inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-md border px-3 text-[13px] font-bold transition-colors lg:min-h-9';
  const filtroOn = 'border-inst-control bg-inst-tarjeta text-inst-ink hover:bg-inst-sutil';
  const filtroOff = 'border-dashed border-inst-linea bg-transparent text-inst-ink-3 hover:bg-inst-sutil';
</script>

<DocenteLayout {breadcrumbs} {ceja} titulo="Calendario">
  {#snippet acciones()}
    <div role="group" aria-label="Vista del calendario" class="inline-flex overflow-hidden rounded-md border border-inst-control">
      {#each VISTAS as v (v.id)}
        <button
          type="button"
          aria-pressed={vista === v.id}
          onclick={() => cambiarVista(v.id)}
          class="{botonSeg} {vista === v.id ? 'bg-inst-accion text-inst-sobre-accion' : 'bg-inst-tarjeta text-inst-ink'}"
        >
          <v.icon size={15} aria-hidden="true" />
          {v.label}
        </button>
      {/each}
    </div>
  {/snippet}

  <div class={cuerpo}>
    {#if !hayDatos}
      <CalendarioVacio {cursos} {accentPorCurso} />
    {:else}
      <!-- Barra: navegación, período y filtros -->
      <div class="{tarjeta} flex flex-wrap items-center gap-x-3 gap-y-2.5 px-4 py-3">
        <button type="button" onclick={irHoy} class="{btnSecundario} {chico}">Hoy</button>
        <span class="flex gap-1">
          <button
            type="button"
            onclick={() => paso(-1)}
            class="{btnSecundario} w-11 px-0! lg:min-h-9 lg:w-9"
            aria-label={vista === 'mes' ? 'Mes anterior' : 'Semana anterior'}
          >
            <ChevronLeft size={17} aria-hidden="true" />
          </button>
          <button
            type="button"
            onclick={() => paso(1)}
            class="{btnSecundario} w-11 px-0! lg:min-h-9 lg:w-9"
            aria-label={vista === 'mes' ? 'Mes siguiente' : 'Semana siguiente'}
          >
            <ChevronRight size={17} aria-hidden="true" />
          </button>
        </span>
        <h2 class="{tituloInst} m-0 text-lg leading-6 first-letter:uppercase" aria-live="polite">{rango.titulo}</h2>
        {#if diasConSobrecarga > 0}
          <span class={PILL_SOBRECARGA}>
            <TriangleAlert size={13} aria-hidden="true" />
            {diasConSobrecarga}
            {diasConSobrecarga === 1 ? 'día con sobrecarga' : 'días con sobrecarga'}
          </span>
        {/if}

        <span class="ml-auto flex flex-wrap items-center gap-3">
          {#if periodos.length > 1}
            <label class="flex items-center gap-2">
              <span class="text-[13px] font-bold">Período</span>
              <select value={periodo} onchange={(e) => (periodoElegido = e.currentTarget.value)} class="{campo} w-auto">
                {#each periodos as p (p)}
                  <option value={p}>{p === '?-?' ? 'Sin período' : p}</option>
                {/each}
                <option value="todos">Todos los períodos</option>
              </select>
            </label>
          {/if}
          <button
            type="button"
            onclick={() => (filtrosAbiertos = !filtrosAbiertos)}
            class="{btnSecundario} {chico}"
            aria-expanded={filtrosAbiertos}
            aria-controls="filtros-calendario"
          >
            <SlidersHorizontal size={15} aria-hidden="true" />
            Filtros · {todosVisibles ? cursosDelPeriodo.length : `${activos.size} de ${cursosDelPeriodo.length}`}
            {cursosDelPeriodo.length === 1 ? 'curso' : 'cursos'}
          </button>
        </span>
      </div>

      {#if filtrosAbiertos}
        <div id="filtros-calendario" class="flex flex-col gap-2.5">
          <!-- El color es del curso: estos botones son también la leyenda de colores. -->
          <div role="group" aria-label="Cursos visibles" class="flex flex-wrap items-center gap-2">
            {#each cursosDelPeriodo as curso (curso.id_curso)}
              {@const activo = activos.has(curso.id_curso)}
              <button
                type="button"
                aria-pressed={activo}
                onclick={() => toggleCurso(curso.id_curso)}
                title={curso.asignatura}
                class="{filtro} {activo ? filtroOn : filtroOff}"
              >
                <span
                  aria-hidden="true"
                  class="size-3 shrink-0 rounded-[3px] {activo ? '' : 'opacity-35'}"
                  style="background:{accentPorCurso[curso.id_curso].base}"
                ></span>
                {codigoCurso(curso)}
                <span class="font-semibold text-inst-ink-3 tabular-nums">{enRangoPorCurso[curso.id_curso] ?? 0}</span>
              </button>
            {/each}
            {#if !todosVisibles}
              <button type="button" onclick={todosCursos} class="{btnFantasma} {chico}">Mostrar todos</button>
            {/if}
          </div>
          <div role="group" aria-label="Tipos de evento" class="flex flex-wrap items-center gap-2">
            {#each FAMILIAS as f (f.id)}
              {@const activo = familiasActivas.has(f.id)}
              <button
                type="button"
                aria-pressed={activo}
                onclick={() => toggleFamilia(f.id)}
                class="{filtro} {activo ? filtroOn : filtroOff}"
              >
                <f.icon size={15} aria-hidden="true" />
                {f.label}
                <span class="font-semibold text-inst-ink-3 tabular-nums">{conteosFamilia[f.id]}</span>
              </button>
            {/each}
          </div>
        </div>
      {/if}

      {#if vista === 'mes'}
        <CalendarMonth
          year={anclaFecha.year}
          month={anclaFecha.month}
          {itemsPorDia}
          {accentPorCurso}
          {cursoPorId}
          {sobrecargas}
          onSelectDay={(iso) => (selectedDay = iso)}
          onSelectItem={(item) => (selectedDay = item.fecha)}
        />
      {:else if vista === 'semana'}
        <CalendarWeek
          dias={diasSemana}
          {itemsPorDia}
          {accentPorCurso}
          {cursoPorId}
          {sobrecargas}
          onSelectDay={(iso) => (selectedDay = iso)}
          onSelectItem={(item) => (selectedDay = item.fecha)}
        />
      {:else}
        <CalendarAgenda
          dias={diasSemana}
          {itemsPorDia}
          {accentPorCurso}
          {cursoPorId}
          {sobrecargas}
          proxima={proximaMarca}
          onIrA={(iso) => (ancla = iso)}
        />
      {/if}

      <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[13px] text-inst-ink-2">
        <span class="font-bold text-inst-ink">Leyenda</span>
        <span class="inline-flex items-center gap-1.5">
          <span class="h-3 w-5 rounded-[3px]" style={MUESTRA.sumativa} aria-hidden="true"></span>
          Sumativa
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span class="h-3 w-5 rounded-[3px]" style={MUESTRA.formativa} aria-hidden="true"></span>
          Formativa (borde punteado)
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span class="h-3 w-5 rounded-[3px]" style={MUESTRA.oculta} aria-hidden="true"></span>
          Oculta al estudiante
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span class={PILL_SOBRECARGA}><TriangleAlert size={12} aria-hidden="true" />3</span>
          Sobrecarga: 3 o más entregas de un mismo grupo el mismo día
        </span>
      </div>
      <p class="m-0 max-w-[880px] text-[13px] text-inst-ink-2">
        Se dibuja la fecha nominal de cierre de cada actividad; los plazos adicionales de grupos o estudiantes se ven
        en el detalle de la actividad. Las clases que aparecen son las que ya tienen asistencia registrada.
        {#if ancla !== hoyIso}
          Hoy es el {hoyIso.split('-').reverse().join('-')}.
        {/if}
      </p>
    {/if}
  </div>

  <!-- Detalle del día -->
  <DayEventsDialog
    iso={selectedDay}
    items={itemsDiaSeleccionado}
    {cursoPorId}
    {accentPorCurso}
    cargas={cargasDiaSeleccionado}
    onClose={() => (selectedDay = null)}
  />
</DocenteLayout>
