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
  import type { BreadcrumbItem } from '@/types';
  import {
    CalendarAgenda,
    CalendarMonth,
    CalendarWeek,
    CalendarioVacio,
    CourseFilter,
    DayEventsDialog,
    ResumenPeriodo,
    TipoEventoFilter,
    accentFor,
    buildWeekDays,
    calcularSobrecargas,
    formatRangoSemana,
    inicioSemana,
    minutosDeHora,
    sumarDias,
    todayKey,
    toKey,
    MESES,
    BTN_OUTLINE,
    PILL_ROJA,
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
    CalendarRange,
    ChevronLeft,
    ChevronRight,
    Columns3,
    Info,
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

  const periodos = $derived.by(() => {
    const vistos = new Set<string>();
    const lista: string[] = [];
    for (const c of cursos) {
      const clave = clavePeriodo(c);
      if (!vistos.has(clave)) {
        vistos.add(clave);
        lista.push(clave);
      }
    }
    return lista;
  });

  let periodo = $state<string>('todos');

  const cursosDelPeriodo = $derived(
    periodo === 'todos' ? cursos : cursos.filter((c) => clavePeriodo(c) === periodo),
  );

  const idsDelPeriodo = $derived(new Set(cursosDelPeriodo.map((c) => c.id_curso)));

  // ── Navegación ──────────────────────────────────────────────────────────────
  const hoyIso = todayKey();
  let ancla = $state(todayKey());
  let vista = $state<Vista>('mes');
  let selectedDay = $state<string | null>(null);
  let filtrosAbiertos = $state(false);

  // En pantallas estrechas la agenda es la vista útil: la grilla mensual a
  // 390px no se lee. Sólo se aplica al montar, no pisa la elección del docente.
  onMount(() => {
    if (window.matchMedia('(max-width: 767px)').matches) vista = 'agenda';
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
  function soloEste(id: number) {
    ocultos = new Set(cursos.filter((c) => c.id_curso !== id).map((c) => c.id_curso));
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

  const hayDatos = $derived(eventos.length + sesiones.length + hitos.length > 0);

  // ── Lenguaje visual del selector de vista ───────────────────────────────────
  const SEG_BASE =
    'inline-flex items-center gap-1.5 rounded-[7px] border px-2.5 py-1.5 text-[12.5px] transition-colors';
  const SEG_ON = 'border-[#D6D9E0] bg-white font-semibold text-[#002F6C] shadow-[0_1px_2px_rgba(0,0,0,.06)]';
  const SEG_OFF = 'border-transparent bg-transparent font-medium text-[#4A4E5C] hover:text-[#1A1A24]';

  const NAV_BTN =
    'flex h-8 w-8 items-center justify-center transition-colors hover:bg-[#F5F1EA]';
</script>

<DocenteLayout {breadcrumbs}>
  <div class="min-h-screen bg-white pb-16">
    <div class="mx-auto flex max-w-[1440px] flex-col gap-4 px-6 py-6 max-md:px-4">
      <!-- ── Encabezado ─────────────────────────────────────────────────────── -->
      <header class="flex flex-wrap items-start gap-4">
        <div class="flex min-w-0 flex-col gap-0.5">
          <h1 class="m-0 text-[24px] font-semibold tracking-[-0.01em] text-[#1A1A24]">Calendario</h1>
          <span class="text-[12.5px] text-[#5A5E6E]">
            Fecha nominal de cada actividad, sesiones de asistencia e hitos de syllabus
          </span>
        </div>

        <div class="ml-auto flex flex-wrap items-center gap-2">
          <!-- Selector de vista -->
          <div
            class="flex gap-0.5 rounded-[9px] border border-[#DDE2E9] bg-[#EDF0F4] p-0.5"
            role="group"
            aria-label="Vista del calendario"
          >
            <button
              type="button"
              onclick={() => cambiarVista('mes')}
              class="{SEG_BASE} {vista === 'mes' ? SEG_ON : SEG_OFF}"
              aria-pressed={vista === 'mes'}
            >
              <CalendarDays size={14} color={vista === 'mes' ? '#002F6C' : '#5A5E6E'} />
              Mes
            </button>
            <button
              type="button"
              onclick={() => cambiarVista('semana')}
              class="{SEG_BASE} {vista === 'semana' ? SEG_ON : SEG_OFF}"
              aria-pressed={vista === 'semana'}
            >
              <Columns3 size={14} color={vista === 'semana' ? '#002F6C' : '#5A5E6E'} />
              Semana
            </button>
            <button
              type="button"
              onclick={() => cambiarVista('agenda')}
              class="{SEG_BASE} {vista === 'agenda' ? SEG_ON : SEG_OFF}"
              aria-pressed={vista === 'agenda'}
            >
              <LayoutList size={14} color={vista === 'agenda' ? '#002F6C' : '#5A5E6E'} />
              Agenda
            </button>
          </div>

          <!-- Navegación temporal -->
          <div
            class="flex items-center overflow-hidden rounded-lg border border-[#D6D9E0] bg-white"
          >
            <button
              type="button"
              onclick={() => paso(-1)}
              class="{NAV_BTN} border-r border-[#E5E7EB]"
              aria-label={vista === 'mes' ? 'Mes anterior' : 'Semana anterior'}
            >
              <ChevronLeft size={15} color="#4A4E5C" />
            </button>
            <button
              type="button"
              onclick={irHoy}
              class="h-8 border-r border-[#E5E7EB] bg-[#E8EDF5] px-3 text-[12.5px] font-semibold text-[#002F6C] transition-colors hover:bg-[#DCE5F1]"
            >
              Hoy
            </button>
            <button
              type="button"
              onclick={() => paso(1)}
              class={NAV_BTN}
              aria-label={vista === 'mes' ? 'Mes siguiente' : 'Semana siguiente'}
            >
              <ChevronRight size={15} color="#4A4E5C" />
            </button>
          </div>

          <!-- Período académico -->
          {#if periodos.length > 1}
            <label
              class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-[#D6D9E0] bg-white px-2.5 text-[12.5px] font-medium text-[#1A1A24]"
            >
              <CalendarRange size={14} color="#5A5E6E" />
              <span class="sr-only">Período académico</span>
              <select
                bind:value={periodo}
                class="cursor-pointer border-none bg-transparent text-[12.5px] font-medium text-[#1A1A24] outline-none"
              >
                <option value="todos">Todos los períodos</option>
                {#each periodos as p (p)}
                  <option value={p}>Semestre {p}</option>
                {/each}
              </select>
            </label>
          {/if}

          <!-- Filtros en pantallas estrechas -->
          <button
            type="button"
            onclick={() => (filtrosAbiertos = !filtrosAbiertos)}
            class="{BTN_OUTLINE} h-8 py-0 lg:hidden"
            aria-expanded={filtrosAbiertos}
          >
            <SlidersHorizontal size={13} color="#5A5E6E" />
            Filtros
            <span
              class="rounded-full bg-[#E8EDF5] px-1.5 text-[10px] font-bold text-[#002F6C] tabular-nums"
            >
              {activos.size}
            </span>
          </button>
        </div>
      </header>

      {#if !hayDatos}
        <CalendarioVacio {cursos} {accentPorCurso} />
      {:else}
        <div class="flex gap-5 max-lg:flex-col">
          <!-- ── Filtros ────────────────────────────────────────────────────── -->
          <aside
            class="w-[236px] shrink-0 self-start overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-[0_1px_3px_rgba(0,0,0,.06)] max-lg:w-full {filtrosAbiertos
              ? ''
              : 'max-lg:hidden'}"
          >
            <div class="flex items-center gap-2 border-b border-[#E5E7EB] px-3.5 py-3">
              <SlidersHorizontal size={15} color="#5A5E6E" />
              <span class="text-[13.5px] font-semibold text-[#1A1A24]">Filtros</span>
              <span
                class="ml-auto rounded-full border border-[#C9D6E6] bg-[#E8EDF5] px-2 py-px text-[10.5px] font-bold text-[#002F6C] tabular-nums"
              >
                {activos.size} de {cursosDelPeriodo.length}
              </span>
            </div>

            <CourseFilter
              cursos={cursosDelPeriodo}
              {accentPorCurso}
              {activos}
              conteoPorCurso={enRangoPorCurso}
              onToggle={toggleCurso}
              onSoloEste={soloEste}
              onTodos={todosCursos}
            />

            <TipoEventoFilter
              familias={familiasActivas}
              conteos={conteosFamilia}
              onToggle={toggleFamilia}
            />

            <ResumenPeriodo
              titulo={rango.rotulo}
              entregas={conteosFamilia.ENTREGA}
              sesiones={conteosFamilia.SESION}
              hitos={conteosFamilia.HITO}
              {diasConSobrecarga}
            />
          </aside>

          <!-- ── Vista ──────────────────────────────────────────────────────── -->
          <main class="flex min-w-0 flex-1 flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
              <h2 class="m-0 text-[16px] font-semibold capitalize text-[#1A1A24]">
                {rango.titulo}
              </h2>
              <span class="font-mono text-[11.5px] text-[#5A5E6E]">
                {rango.desde.split('-').reverse().slice(0, 2).join('-')} → {rango.hasta
                  .split('-')
                  .reverse()
                  .slice(0, 2)
                  .join('-')}
              </span>
              {#if diasConSobrecarga > 0}
                <span class="{PILL_ROJA} ml-auto">
                  <TriangleAlert size={12} color="#B91C1C" />
                  {diasConSobrecarga}
                  {diasConSobrecarga === 1 ? 'día con sobrecarga' : 'días con sobrecarga'}
                </span>
              {/if}
            </div>

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
              />
            {/if}

            <div class="flex items-start gap-1.5 px-0.5">
              <Info size={13} class="mt-0.5 shrink-0" color="#8A8E9C" />
              <p class="m-0 max-w-[880px] text-[11.5px] leading-[1.45] text-[#5A5E6E]">
                Este calendario muestra la fecha
                <span class="font-semibold text-[#1A1A24]">nominal</span> de cada actividad. La
                fecha efectiva de cada alumno
                <span class="font-mono">(fecha_limite + días de holgura)</span> es personal y se
                consulta en el detalle de la actividad. Las sesiones de clase que aparecen son las
                que ya tienen asistencia registrada.
                {#if ancla !== hoyIso}
                  <span class="text-[#8A8E9C]">Hoy es el {hoyIso.split('-').reverse().join('-')}.</span>
                {/if}
              </p>
            </div>
          </main>
        </div>
      {/if}
    </div>
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
