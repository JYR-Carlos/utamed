<script lang="ts">
  /**
   * Detalle de curso del docente — /docente/cursos/{curso}.
   *
   * Una sola vista para dos roles (lámina «Detalle de curso (docente)»). El
   * cuerpo —cabecera, tres pestañas, grupo, actividades y asistencia— es
   * idéntico; lo que cambia es el **gobierno del curso**: los KPIs, el mapa de
   * componentes y los accesos de gestión existen sólo para el docente titular.
   * Al retirarlos el layout cierra el hueco por `gap`: no se deshabilita nada,
   * lo que no se permite no se dibuja.
   */
  import { router, Link, page } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import {
    UsersRound,
    Shield,
    Crown,
    GraduationCap,
    ClipboardList,
    Search,
    X,
    EyeOff,
  } from 'lucide-svelte';
  import {
    SyllabusPermisosModal,
    ComponentePermisosModal,
    ComponenteTitularModal,
    EquipoDocenteSlideOver,
  } from '@/modules/resources/curso/components';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions/permissions';
  import ActividadesPorEstado from './components/ActividadesPorEstado.svelte';
  import EstudianteDetalleModal from './components/EstudianteDetalleModal.svelte';
  import AsistenciaPanel from './components/AsistenciaPanel.svelte';
  import EstudiantesTable from './components/EstudiantesTable.svelte';
  import ComponentePills from './components/ComponentePills.svelte';
  import { initials, formatFechaCorta, formatNota } from '@/utils/formatters';
  import type { Actividad } from '@/types/actividad';

  interface Componente {
    id_componente: number;
    tipo_componente: string;
    es_titular: boolean;
    total_docentes: number;
    total_estudiantes: number;
    /** Sesiones de asistencia registradas en el componente. */
    total_sesiones: number;
    /** Mínimo de asistencia del componente (0–100). */
    porcentaje_asistencia_obligatoria?: number | null;
  }

  interface EstudianteComponente {
    id_inscripcion_componente: number;
    id_componente: number;
    tipo_componente: string;
    nota_componente: number | null;
    asistencia?: { presentes: number; total: number };
    estudiante: {
      id_estudiante: number;
      nombre: string;
      username: string;
    };
  }

  interface DocenteComponenteCurso {
    id_docente_componente: number;
    id_docente: number;
    id_usuario: number;
    nombre: string;
    es_titular: boolean;
  }

  interface ComponenteCurso {
    id_componente: number;
    tipo_componente: string;
    total_estudiantes: number;
    docentes: DocenteComponenteCurso[];
  }

  interface Curso {
    etiqueta?: string;
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo: string | null;
    fecha_inicio: string;
    fecha_fin: string;
    agno_real: number;
    semestre_real: number;
    estado_interno: string;
    es_plantilla: boolean;
    tiene_programa: boolean;
    es_titular_curso: boolean;
    id_docente_titular: number;
    id_docente_actual: number;
    asignatura: {
      nombre: string;
      cod_asignatura: string;
      descripcion: string;
    };
    plan: {
      nombre: string;
      carrera: string;
    };
    secciones: any[];
    total_estudiantes: number;
    /** Mensajes de nivel curso sin leer en los componentes que dicta. */
    mensajes_sin_leer?: number;
    userPermissions?: Permission[];
  }

  interface Props {
    curso: Curso;
    mis_componentes: Componente[];
    mis_estudiantes: EstudianteComponente[];
    todos_componentes: ComponenteCurso[];
    actividades?: Actividad[];
    /** Prop diferida: sólo llega cuando la ficha del alumno la pide. */
    mensajesEstudiante?: any[];
  }

  let {
    curso,
    mis_componentes,
    mis_estudiantes,
    todos_componentes = [],
    actividades = [],
    mensajesEstudiante = [],
  }: Props = $props();

  const canVerActividades = $derived(
    curso.es_titular_curso || hasPermission(curso.userPermissions ?? [], 'actividades:ver'),
  );

  // ─── Componente activo (alcance de la tabla y de la asistencia) ───
  let componenteActivo = $state<number | null>(null);

  // ─── Modales ───
  let showSyllabusPermisos = $state(false);
  let showComponentePermisos = $state(false);
  let componentePermisoId = $state<number>(0);
  let componentePermisoTipo = $state('');
  let showEquipoSlideOver = $state(false);
  let showCambiarTitular = $state(false);
  let cambiarTitularComponente = $state<ComponenteCurso | null>(null);

  // ─── Pestañas ───
  type MainTab = 'grupo' | 'actividades' | 'asistencia';

  // Permite abrir directo en un tab vía deep-link (?tab=asistencia), p.ej. desde
  // el dashboard. Sólo se honra si el tab es accesible en este curso.
  const initialTab: MainTab = (() => {
    const query = $page.url.split('?')[1] ?? '';
    const tab = new URLSearchParams(query).get('tab');
    if (tab === 'asistencia' && mis_componentes.length > 0) return 'asistencia';
    if (tab === 'actividades' && canVerActividades) return 'actividades';
    return 'grupo';
  })();
  let mainTab = $state<MainTab>(initialTab);

  // ─── Búsqueda de estudiantes ───
  let estudianteQuery = $state('');

  // ─── Modal Detalle Estudiante ───
  let modalEstudiante = $state(false);
  let estudianteSeleccionado = $state<EstudianteComponente | null>(null);

  $effect.pre(() => {
    if (componenteActivo === null && mis_componentes.length > 0) {
      // Se abre en el primer componente con inscritos: abrir en uno vacío
      // (p. ej. Cátedra sin alumnos) escondía a los estudiantes (hallazgo #8).
      const conEstudiantes = mis_componentes.find((c) => c.total_estudiantes > 0);
      componenteActivo = (conEstudiantes ?? mis_componentes[0]).id_componente;
    }
  });

  const componenteActual = $derived(
    mis_componentes.find((c) => c.id_componente === componenteActivo) ?? null,
  );

  const tipoComponenteActivo = $derived(componenteActual?.tipo_componente ?? 'Componente');

  const estudiantesActivos = $derived(
    mis_estudiantes.filter((e) => e.id_componente === componenteActivo),
  );

  const estudiantesActivosFiltrados = $derived(
    estudiantesActivos.filter((e) => {
      const q = estudianteQuery.toLowerCase().trim();
      return (
        !q ||
        e.estudiante.nombre.toLowerCase().includes(q) ||
        e.estudiante.username.toLowerCase().includes(q)
      );
    }),
  );

  // ─── Notas del componente activo: vacío no es cero ───
  const notasPuestas = $derived(
    estudiantesActivos.filter((e) => e.nota_componente !== null).map((e) => e.nota_componente!),
  );
  const sinNota = $derived(estudiantesActivos.length - notasPuestas.length);

  // ─── Asistencia del componente activo bajo su mínimo (KPI y tabla) ───
  const minAsistencia = $derived(componenteActual?.porcentaje_asistencia_obligatoria ?? null);
  const bajoMinimo = $derived(
    minAsistencia && minAsistencia > 0
      ? estudiantesActivos.filter(
          (e) => e.asistencia && e.asistencia.total > 0 && (e.asistencia.presentes / e.asistencia.total) * 100 < minAsistencia,
        ).length
      : 0,
  );
  /** Promedio calculado SÓLO sobre las notas puestas: una celda vacía no lo arrastra. */
  const promedioComponente = $derived(
    notasPuestas.length === 0
      ? null
      : notasPuestas.reduce((acc, n) => acc + Number(n), 0) / notasPuestas.length,
  );

  // ─── Actividades ───
  const misComponentesIds = $derived(mis_componentes.map((c) => c.id_componente));

  /**
   * Alcance de los datos, no del diseño: el titular ve los tres componentes;
   * el docente de componente, sólo el suyo.
   */
  const actividadesDelRol = $derived(
    curso.es_titular_curso
      ? actividades
      : actividades.filter((a) => misComponentesIds.includes(a.id_componente ?? -1)),
  );

  let filtroComponente = $state<number | 'todos'>('todos');

  /**
   * Formativa vs sumativa. `tipo_actividad` ya viaja en cada actividad, así que
   * el filtro se resuelve sobre la lista ya cargada: cambiarlo no vuelve al
   * servidor ni recarga la página.
   */
  let filtroTipo = $state<Actividad['tipo_actividad'] | 'todas'>('todas');

  const actividadesFiltradas = $derived(
    actividadesDelRol.filter(
      (a) =>
        (filtroComponente === 'todos' || a.id_componente === filtroComponente) &&
        (filtroTipo === 'todas' || a.tipo_actividad === filtroTipo),
    ),
  );

  const actividadesOcultas = $derived(actividadesDelRol.filter((a) => !a.visible).length);

  /** Componentes por los que se puede filtrar el tablero de actividades. */
  const componentesDeActividades = $derived(
    curso.es_titular_curso
      ? todos_componentes.map((c) => ({ id: c.id_componente, tipo: c.tipo_componente }))
      : mis_componentes.map((c) => ({ id: c.id_componente, tipo: c.tipo_componente })),
  );

  // ─── Gobierno del curso (sólo titular) ───
  const docentesDelCurso = $derived(
    todos_componentes.flatMap((c) => c.docentes.map((d) => ({ ...d, id_componente: c.id_componente }))),
  );
  const totalDocentesCurso = $derived(new Set(docentesDelCurso.map((d) => d.id_docente)).size);
  const totalTitularesComponente = $derived(
    new Set(docentesDelCurso.filter((d) => d.es_titular).map((d) => d.id_docente)).size,
  );
  // Con un solo docente la tarjeta sólo repetiría «1»: no aporta nada.
  const mostrarKpiDocentes = $derived(totalDocentesCurso > 1);

  function abrirModalEstudiante(est: EstudianteComponente) {
    estudianteSeleccionado = est;
    modalEstudiante = true;
  }

  function cerrarModalEstudiante() {
    modalEstudiante = false;
    estudianteSeleccionado = null;
  }

  /** Sigla de 3 letras del componente ("Cátedra" → "CÁT"). */
  function sigla(tipo: string): string {
    return tipo.slice(0, 3).toUpperCase();
  }

  /** Rol del docente dentro del componente, tal como lo lee el titular. */
  function rolDocente(d: DocenteComponenteCurso): string {
    if (d.id_docente === curso.id_docente_titular) return 'Titular del curso';
    return d.es_titular ? 'Titular del componente' : 'Docente de componente';
  }

  function formatDate(dateString: string) {
    if (!dateString) return '—';
    return formatFechaCorta(dateString);
  }

  // ─── Lenguaje visual compartido ───
  const CARD = 'bg-white border border-slate-200 rounded-xl';
  const KPI = 'flex flex-col gap-1 rounded-xl border border-slate-200 bg-white px-4 py-3.5';
  const KPI_LABEL = 'text-[11px] font-bold uppercase tracking-[0.06em] text-slate-600';
  const KPI_NUM = 'text-[28px] font-bold leading-[34px] tabular-nums text-ink';
  const BTN_GHOST =
    'inline-flex items-center gap-[7px] rounded-lg px-3 py-2 text-[13.5px] font-medium text-navy transition-colors hover:bg-sand';
  const BTN_OUTLINE =
    'inline-flex h-11 items-center gap-2 rounded-lg border border-line-control bg-white px-4 text-sm font-semibold text-uta-blue transition-colors hover:bg-uta-blue-light sm:h-10';
  const BTN_PRIMARY =
    'inline-flex h-11 items-center gap-2 rounded-lg bg-uta-blue px-4 text-sm font-semibold text-white transition-colors hover:bg-uta-blue-hover sm:h-10';
  const PILL_AZUL =
    'inline-flex items-center gap-1.5 rounded-full bg-uta-blue-light px-2.5 py-0.5 text-xs font-semibold text-uta-blue';
  const PILL_NEUTRA =
    'inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600';
  // Pestañas del mockup: 15 px, subrayado navy de 3 px y contador gris en ambas.
  const TAB_BASE =
    'inline-flex shrink-0 items-center gap-2 -mb-px border-b-[3px] px-1 pt-4 pb-3 text-[15px] transition-colors duration-150';
  const CONTADOR_ACTIVO =
    'rounded-full bg-slate-100 px-2 py-px text-xs font-semibold tabular-nums text-slate-600';
  const CONTADOR_INACTIVO = CONTADOR_ACTIVO;
</script>

<DocenteLayout
  breadcrumbs={[
    { title: 'Mis Cursos', href: '/docente/cursos' },
    { title: curso.etiqueta ?? curso.nombre, href: `/docente/cursos/${curso.id_curso}` },
  ]}
>
  <div class="pb-16">
    <div class="mx-auto flex max-w-[1120px] flex-col gap-5 px-4 md:px-0">

      <!-- ── Cabecera del curso ── -->
      <section class="{CARD} flex flex-col gap-4 px-4 py-4 sm:px-6 sm:py-[22px]">
        <div class="flex flex-col gap-3.5">
          <div class="flex min-w-0 flex-col gap-1.5">
            <span class="font-mono text-xs font-medium text-slate-600">{curso.asignatura.cod_asignatura}</span>
            <div class="flex flex-wrap items-center gap-2.5">
              <h1 class="m-0 text-2xl font-semibold leading-[30px] text-ink sm:text-[28px] sm:leading-[34px]">
                {curso.nombre || curso.asignatura.nombre}
              </h1>
              {#if curso.letra_grupo}
                <span class={PILL_AZUL}>Grupo {curso.letra_grupo}</span>
              {/if}
              <span class={PILL_NEUTRA}>{curso.agno_real}-{curso.semestre_real}</span>
              {#if curso.es_plantilla}
                <span class={PILL_NEUTRA}>Plantilla</span>
              {/if}
            </div>
            <!-- El nombre de la asignatura sólo se repite si difiere del título. -->
            <span class="text-sm font-medium text-slate-600">
              Actúas como {curso.es_titular_curso ? 'docente titular' : 'docente de componente'}
              {#if curso.asignatura.nombre !== (curso.nombre || curso.asignatura.nombre)}
                · {curso.asignatura.nombre}
              {/if}
              · {curso.plan.carrera} · {formatDate(curso.fecha_inicio)} a {formatDate(curso.fecha_fin)}
            </span>
          </div>

          <div class="flex flex-col items-start gap-2.5">
            <div class="flex flex-wrap items-center gap-2">
              {#if curso.tiene_programa}
                <button
                  onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/programa`)}
                  class="{BTN_PRIMARY} max-sm:w-full max-sm:justify-center"
                >
                  Programa
                </button>
              {/if}
              <!-- Mensajería de nivel curso (curso.mensaje): avisos al componente y
                   canal por alumno. Se entra desde aquí porque el hilo pertenece a
                   este curso; las consultas sobre una entrega van en su actividad. -->
              <button
                onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/mensajeria`)}
                class={BTN_OUTLINE}
              >
                Mensajería{#if (curso.mensajes_sin_leer ?? 0) > 0}<span class="font-semibold"
                    >&nbsp;·&nbsp;{curso.mensajes_sin_leer} sin leer</span
                  >{/if}
              </button>
              {#if curso.es_titular_curso}
                <button onclick={() => (showEquipoSlideOver = true)} class={BTN_OUTLINE}>
                  Equipo docente
                </button>
                <button
                  onclick={() =>
                    router.visit(`/docente/cursos/${curso.id_curso}/delegacion-permisos`)}
                  class={BTN_OUTLINE}
                >
                  Delegación de permisos
                </button>
              {/if}
            </div>

            {#if !curso.es_titular_curso && componenteActual}
              <div class="flex flex-col items-start gap-0.5">
                <span
                  class="inline-flex items-center gap-[7px] rounded-full border border-gray-200 bg-sand px-2.5 py-1 text-[12px] font-semibold text-ink"
                >
                  <span
                    class="rounded border border-line bg-white px-1.5 py-px font-mono text-[10.5px] font-bold tracking-[0.06em] text-ink-2"
                    >{sigla(componenteActual.tipo_componente)}</span
                  >
                  Tu componente
                </span>
                <span class="text-[11.5px] text-ink-2">
                  {componenteActual.total_estudiantes}
                  {componenteActual.total_estudiantes === 1 ? 'estudiante' : 'estudiantes'} ·
                  {componenteActual.total_sesiones}
                  {componenteActual.total_sesiones === 1 ? 'sesión' : 'sesiones'}
                </span>
              </div>
            {/if}
          </div>
        </div>

      </section>

      <!-- KPIs: sólo el titular responde por el curso completo. Tarjetas aparte,
           como en el mockup; la de asistencia se resalta si alguien queda bajo el mínimo. -->
      {#if curso.es_titular_curso}
        <div class="grid grid-cols-2 gap-3 sm:gap-4 {mostrarKpiDocentes ? 'lg:grid-cols-4' : 'lg:grid-cols-3'}">
          <div class={KPI}>
            <span class={KPI_LABEL}>Componentes</span>
            <span class={KPI_NUM}>{todos_componentes.length}</span>
            <span class="text-[13px] text-slate-600">
              {todos_componentes.map((c) => c.tipo_componente).join(' · ') || 'Sin componentes'}
            </span>
          </div>
          {#if mostrarKpiDocentes}
            <div class={KPI}>
              <span class={KPI_LABEL}>Docentes</span>
              <span class={KPI_NUM}>{totalDocentesCurso}</span>
              <span class="text-[13px] text-slate-600">
                {totalTitularesComponente} con componente a cargo · {Math.max(
                  0,
                  totalDocentesCurso - totalTitularesComponente,
                )} de componente
              </span>
            </div>
          {/if}
          <div class={KPI}>
            <span class={KPI_LABEL}>Estudiantes</span>
            <span class={KPI_NUM}>{curso.total_estudiantes}</span>
            {#if componenteActual && estudiantesActivos.length === 0}
              <span class="text-[13px] text-slate-600">Sin estudiantes en {tipoComponenteActivo}</span>
            {:else if componenteActual && sinNota > 0}
              <span class="text-[13px] text-slate-600">{sinNota} sin nota final en {tipoComponenteActivo}</span>
            {:else if componenteActual}
              <span class="text-[13px] text-slate-600">Todas las notas puestas en {tipoComponenteActivo}</span>
            {:else}
              <span class="text-[13px] text-slate-600">Organizados por componente</span>
            {/if}
          </div>
          {#if componenteActual}
            {@const alerta = bajoMinimo > 0}
            <div
              class="col-span-2 flex flex-col gap-1 rounded-xl border px-4 py-3.5 lg:col-span-1 {alerta
                ? 'border-yellow-400 bg-amber-100'
                : 'border-slate-200 bg-white'}"
            >
              <span class="{KPI_LABEL} {alerta ? '!text-amber-800' : ''}">Asistencia</span>
              {#if componenteActual.total_sesiones === 0}
                <span class={KPI_NUM}>—</span>
                <span class="text-[13px] text-slate-600">Sin sesiones registradas en {tipoComponenteActivo}</span>
              {:else}
                <span class="{KPI_NUM} {alerta ? '!text-amber-800' : ''}">{bajoMinimo}</span>
                <span class="text-[13px] {alerta ? 'text-amber-800' : 'text-slate-600'}">
                  {bajoMinimo === 1 ? 'estudiante' : 'estudiantes'} bajo el mínimo{minAsistencia
                    ? ` de ${Math.round(minAsistencia)} %`
                    : ''} en {tipoComponenteActivo}
                </span>
              {/if}
            </div>
          {/if}
        </div>
      {/if}

      <!-- ── Panel de pestañas ── (overflow-hidden: el pie gris respeta las esquinas) -->
      <!-- En móvil el panel pierde la tarjeta: las filas ya son tarjetas (mockup 390). -->
      <section
        class="{CARD} flex flex-col overflow-hidden {mainTab === 'grupo'
          ? 'max-md:rounded-none max-md:border-0 max-md:bg-transparent'
          : ''}"
      >
        <div
          class="flex items-stretch gap-5 overflow-x-auto overflow-y-hidden [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:gap-6 border-b border-slate-200 {mainTab === 'grupo' ? 'md:px-6' : 'px-4 sm:px-6'}"
          role="tablist"
          aria-label="Secciones del curso"
        >
          <button
            role="tab"
            aria-selected={mainTab === 'grupo'}
            onclick={() => (mainTab = 'grupo')}
            class="{TAB_BASE} {mainTab === 'grupo'
              ? 'border-uta-blue font-semibold text-uta-blue'
              : 'border-transparent font-medium text-slate-600 hover:text-ink'}"
          >
            Mi Grupo
            <span class={mainTab === 'grupo' ? CONTADOR_ACTIVO : CONTADOR_INACTIVO}
              >{estudiantesActivos.length}</span
            >
          </button>
          {#if canVerActividades}
            <button
              role="tab"
              aria-selected={mainTab === 'actividades'}
              onclick={() => (mainTab = 'actividades')}
              class="{TAB_BASE} {mainTab === 'actividades'
                ? 'border-uta-blue font-semibold text-uta-blue'
                : 'border-transparent font-medium text-slate-600 hover:text-ink'}"
            >
              Actividades
              <span class={mainTab === 'actividades' ? CONTADOR_ACTIVO : CONTADOR_INACTIVO}
                >{actividadesDelRol.length}</span
              >
            </button>
          {/if}
          {#if mis_componentes.length > 0}
            <button
              role="tab"
              aria-selected={mainTab === 'asistencia'}
              onclick={() => (mainTab = 'asistencia')}
              class="{TAB_BASE} {mainTab === 'asistencia'
                ? 'border-uta-blue font-semibold text-uta-blue'
                : 'border-transparent font-medium text-slate-600 hover:text-ink'}"
            >
              Asistencia
              <span class={mainTab === 'asistencia' ? CONTADOR_ACTIVO : CONTADOR_INACTIVO}
                >{componenteActual?.total_sesiones ?? 0}</span
              >
            </button>
          {/if}
        </div>

        <!-- ── TAB: Mi Grupo ── -->
        {#if mainTab === 'grupo'}
          <!-- Mockup: barra de componentes con su padding y la tabla a sangre,
               pegada a los bordes de la tarjeta (sin doble marco). -->
          <div class="flex flex-col">
            {#if mis_componentes.length === 0}
              <div
                class="m-5 flex flex-col items-center justify-center rounded-xl border border-dashed border-sand-line bg-sand py-14 text-center"
              >
                <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-sand-2">
                  <Crown size={26} class="text-ocre" />
                </div>
                <p class="mb-1 text-sm font-semibold text-ink">Titular administrativo</p>
                <p class="max-w-[320px] text-xs leading-relaxed text-ink-2">
                  No tienes un componente asignado para impartir clases directamente. Los
                  <strong class="text-ink">{curso.total_estudiantes}</strong>
                  {curso.total_estudiantes === 1 ? 'estudiante' : 'estudiantes'} del curso están
                  organizados por componente en <strong class="text-ink">Todos los componentes</strong>.
                </p>
              </div>
            {:else}
              <!-- Conmutador de componente + búsqueda -->
              <div class="flex flex-wrap items-center gap-2.5 py-4 md:px-6">
                <span
                  class="mr-0.5 text-[11px] font-bold uppercase tracking-[0.06em] text-slate-600"
                >
                  {mis_componentes.length > 1 ? 'Tus componentes' : 'Tu componente'}
                </span>
                <ComponentePills
                  componentes={mis_componentes}
                  {componenteActivo}
                  mostrarContador
                  onSelect={(id) => {
                    componenteActivo = id;
                    estudianteQuery = '';
                  }}
                />
                
                {#if estudiantesActivos.length > 0}
                  <div class="relative ml-auto w-full sm:w-[280px]">
                    <Search
                      size={16}
                      class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-2"
                    />
                    <input
                      type="text"
                      bind:value={estudianteQuery}
                      placeholder="Buscar estudiante…"
                      class="h-11 w-full rounded-lg border border-line-control bg-white pl-9 pr-8 text-sm placeholder:text-muted-foreground focus:border-uta-blue focus:outline-none focus:ring-2 focus:ring-uta-blue sm:h-10"
                    />
                    {#if estudianteQuery}
                      <button
                        onclick={() => (estudianteQuery = '')}
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-ink"
                        aria-label="Limpiar búsqueda"
                      >
                        <X size={13} />
                      </button>
                    {/if}
                  </div>
                {/if}
              </div>

              <!-- Tabla del componente activo -->
              {#if estudiantesActivos.length === 0}
                <div
                  class="mx-4 mb-5 flex flex-col items-center justify-center rounded-xl border border-dashed border-line-control bg-slate-50 py-16 text-center sm:mx-6"
                >
                  <div
                    class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-sand-2"
                  >
                    <GraduationCap size={26} class="text-ink-3" />
                  </div>
                  <p class="text-sm font-medium text-ink-2">Sin estudiantes inscritos</p>
                  <p class="mt-1 text-xs text-muted-foreground">
                    Los estudiantes aparecerán aquí cuando se inscriban.
                  </p>
                </div>
              {:else if estudiantesActivosFiltrados.length === 0}
                <div class="flex flex-col items-center gap-2 py-10 text-center text-muted-foreground">
                  <Search size={28} class="opacity-30" />
                  <p class="text-sm">Sin resultados para «{estudianteQuery}»</p>
                  <button
                    onclick={() => (estudianteQuery = '')}
                    class="text-xs font-medium text-navy hover:underline">Limpiar búsqueda</button
                  >
                </div>
              {:else}
                <EstudiantesTable
                  estudiantes={estudiantesActivosFiltrados}
                  tipoComponente={tipoComponenteActivo}
                  {minAsistencia}
                  mostrarDetalle
                  plano
                  inicial={estudianteQuery ? null : 8}
                  onDetalle={(item) => abrirModalEstudiante(item as EstudianteComponente)}
                >
                  {#snippet pie(mostrados)}
                    <div class="flex flex-wrap items-center gap-x-3.5 gap-y-1 text-[13px] font-medium text-slate-600">
                      <span>
                        Mostrando {mostrados} de {estudiantesActivos.length}
                        {estudiantesActivos.length === 1 ? 'estudiante' : 'estudiantes'} de {tipoComponenteActivo}
                      </span>
                      {#if sinNota > 0}
                        <span class="inline-flex items-center gap-[7px]">
                          <span
                            class="inline-block h-3.5 w-3.5 rounded-full border border-dashed border-line"
                            aria-hidden="true"
                          ></span>
                          {sinNota} sin nota
                        </span>
                      {/if}
                      {#if promedioComponente !== null}
                        <span>
                          Promedio del componente
                          <strong class="font-mono font-semibold text-ink"
                            >{formatNota(promedioComponente)}</strong
                          >
                          · calculado sólo sobre las {notasPuestas.length}
                          {notasPuestas.length === 1 ? 'nota puesta' : 'notas puestas'}
                        </span>
                      {:else}
                        <span>Todavía no hay notas puestas en {tipoComponenteActivo}</span>
                      {/if}
                    </div>
                  {/snippet}
                </EstudiantesTable>
              {/if}
            {/if}
          </div>

          <!-- ── TAB: Actividades ── -->
        {:else if mainTab === 'actividades'}
          <div class="flex flex-col gap-3.5 px-5 pb-5 pt-[18px]">
            <div class="flex flex-wrap items-center gap-2.5">
              {#if actividadesOcultas > 0}
                <span class="inline-flex items-center gap-[7px] text-[12px] text-ink-2">
                  <EyeOff size={14} class="text-amber-700" />
                  {actividadesOcultas} de {actividadesDelRol.length}
                  {actividadesDelRol.length === 1 ? 'actividad no es visible' : 'actividades no son visibles'}
                  para los alumnos
                </span>
              {/if}
              <div class="ml-auto flex flex-wrap items-center gap-2">
                <label class="inline-flex items-center gap-2 text-[12px] text-ink-2">
                  <span class="sr-only">Filtrar por tipo de evaluación</span>
                  <select
                    bind:value={filtroTipo}
                    class="rounded-lg border border-line bg-white px-3 py-2 text-[13px] font-medium text-ink focus:border-navy focus:outline-none"
                  >
                    <option value="todas">Evaluación: todas</option>
                    <option value="FORMATIVA">Sólo formativas</option>
                    <option value="SUMATIVA">Sólo sumativas</option>
                  </select>
                </label>
                {#if componentesDeActividades.length > 1}
                  <label class="inline-flex items-center gap-2 text-[12px] text-ink-2">
                    <span class="sr-only">Filtrar por componente</span>
                    <select
                      bind:value={filtroComponente}
                      class="rounded-lg border border-line bg-white px-3 py-2 text-[13px] font-medium text-ink focus:border-navy focus:outline-none"
                    >
                      <option value="todos">Componente: todos</option>
                      {#each componentesDeActividades as c (c.id)}
                        <option value={c.id}>{c.tipo}</option>
                      {/each}
                    </select>
                  </label>
                {/if}
                <button
                  onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/actividades`)}
                  class={BTN_PRIMARY}
                >
                  <ClipboardList size={15} />
                  Gestionar actividades
                </button>
              </div>
            </div>

            {#if actividadesFiltradas.length === 0}
              <div
                class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-sand-line bg-sand py-16 text-center text-muted-foreground"
              >
                <ClipboardList size={34} class="opacity-30" />
                <p class="m-0 text-sm">
                  {actividadesDelRol.length === 0
                    ? 'No hay actividades en este curso.'
                    : 'Ninguna actividad cumple los filtros elegidos.'}
                </p>
              </div>
            {:else}
              <ActividadesPorEstado actividades={actividadesFiltradas} idCurso={curso.id_curso} />
            {/if}
          </div>

          <!-- ── TAB: Asistencia ── -->
        {:else if mainTab === 'asistencia'}
          <div class="flex flex-col gap-3.5 px-5 pb-5 pt-[18px]">
            {#if mis_componentes.length > 1}
              <div class="flex flex-wrap items-center gap-2.5">
                <span
                  class="mr-0.5 text-[11px] font-bold uppercase tracking-[0.06em] text-slate-600"
                  >Tus componentes</span
                >
                <ComponentePills
                  componentes={mis_componentes}
                  {componenteActivo}
                  mostrarSingle={false}
                  onSelect={(id) => (componenteActivo = id)}
                />
              </div>
            {/if}

            {#if componenteActivo !== null}
              {#key componenteActivo}
                <AsistenciaPanel
                  idCurso={curso.id_curso}
                  idComponente={componenteActivo}
                  tipoComponente={tipoComponenteActivo}
                />
              {/key}
            {/if}
          </div>
        {/if}
      </section>

      <!-- ── Todos los componentes: sólo el titular ve el curso completo ── -->
      {#if curso.es_titular_curso}
        <section class="{CARD} flex flex-col gap-3.5 p-5">
          <div class="flex flex-wrap items-baseline gap-3">
            <h2 class="m-0 text-base font-semibold text-ink">Todos los componentes</h2>
            <div class="ml-auto flex items-center gap-1">
              <button onclick={() => (showSyllabusPermisos = true)} class={BTN_GHOST}>
                <Shield size={14} />
                Permisos del syllabus
              </button>
              <button
                onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/docentes`)}
                class={BTN_GHOST}
              >
                <UsersRound size={14} />
                Gestionar equipo
              </button>
            </div>
          </div>

          {#if todos_componentes.length === 0}
            <p class="py-6 text-center text-xs text-muted-foreground">
              Este curso todavía no tiene componentes.
            </p>
          {:else}
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
              {#each todos_componentes as comp (comp.id_componente)}
                {@const esMio = misComponentesIds.includes(comp.id_componente)}
                <article
                  class="flex flex-col gap-2.5 rounded-xl border px-4 py-3.5 {esMio
                    ? 'border-slate-300 bg-slate-50'
                    : 'border-gray-200 bg-white'}"
                >
                  <div class="flex items-center gap-2">
                    <span
                      class="rounded-[5px] border px-1.5 py-0.5 font-mono text-[10.5px] font-bold tracking-[0.06em] {esMio
                        ? 'border-slate-300 bg-white text-navy'
                        : 'border-gray-200 bg-sand text-ink-2'}"
                      >{sigla(comp.tipo_componente)}</span
                    >
                    <span class="text-sm font-semibold text-ink">{comp.tipo_componente}</span>
                    <span class="ml-auto font-mono text-[11.5px] tabular-nums text-ink-2"
                      >{comp.total_estudiantes} est.</span
                    >
                    {#if comp.docentes.length > 1}
                      <div class="flex items-center gap-0.5">
                        <button
                          onclick={() => {
                            cambiarTitularComponente = comp;
                            showCambiarTitular = true;
                          }}
                          class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-amber-100 hover:text-ocre"
                          aria-label="Cambiar titular de {comp.tipo_componente}"
                        >
                          <Crown size={13} aria-hidden="true" />
                        </button>
                        <button
                          onclick={() => {
                            componentePermisoId = comp.id_componente;
                            componentePermisoTipo = comp.tipo_componente;
                            showComponentePermisos = true;
                          }}
                          class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-uta-blue-light hover:text-navy"
                          aria-label="Gestionar permisos de {comp.tipo_componente}"
                        >
                          <Shield size={13} aria-hidden="true" />
                        </button>
                      </div>
                    {/if}
                  </div>

                  {#if comp.docentes.length === 0}
                    <p class="m-0 border-t border-gray-200 pt-2.5 text-[12px] text-muted-foreground">
                      Sin docente asignado.
                    </p>
                  {:else}
                    <div class="flex flex-col gap-[7px] border-t border-gray-200 pt-2.5">
                      {#each comp.docentes as doc (doc.id_docente_componente)}
                        {@const esDtCurso = doc.id_docente === curso.id_docente_titular}
                        {@const soyYo = doc.id_docente === curso.id_docente_actual}
                        <div class="flex items-center gap-2.5">
                          <div
                            class="flex h-7 w-7 flex-none items-center justify-center rounded-full text-[11px] font-semibold {esDtCurso
                              ? 'bg-uta-blue-light text-navy'
                              : 'bg-sand text-ink-2'}"
                          >
                            {initials(doc.nombre)}
                          </div>
                          <div class="flex min-w-0 flex-col">
                            <span class="truncate text-[12.5px] font-semibold text-ink"
                              >{doc.nombre}</span
                            >
                            <span
                              class="text-[11.5px] {soyYo ? 'text-navy' : 'text-ink-2'}"
                            >
                              {rolDocente(doc)}{soyYo ? ' · tú' : ''}
                            </span>
                          </div>
                        </div>
                      {/each}
                    </div>
                  {/if}
                </article>
              {/each}
            </div>
          {/if}
        </section>
      {/if}
    </div>
  </div>

  <!-- ─── Modales ─── -->
  {#if curso.es_titular_curso}
    <SyllabusPermisosModal
      bind:isOpen={showSyllabusPermisos}
      onClose={() => (showSyllabusPermisos = false)}
      cursoId={curso.id_curso}
      cursoNombre={curso.nombre}
    />

    <EquipoDocenteSlideOver
      bind:isOpen={showEquipoSlideOver}
      onClose={() => (showEquipoSlideOver = false)}
      cursoId={curso.id_curso}
      cursoNombre={curso.nombre || curso.asignatura.nombre}
      cursoCodigo={curso.cod_curso}
    />
  {/if}

  {#if componentePermisoId}
    <ComponentePermisosModal
      bind:isOpen={showComponentePermisos}
      onClose={() => {
        showComponentePermisos = false;
        componentePermisoId = 0;
      }}
      cursoId={curso.id_curso}
      componenteId={componentePermisoId}
      tipoComponente={componentePermisoTipo}
    />
  {/if}

  <ComponenteTitularModal
    bind:isOpen={showCambiarTitular}
    onClose={() => {
      showCambiarTitular = false;
      cambiarTitularComponente = null;
    }}
    cursoId={curso.id_curso}
    idDocenteTitularCurso={curso.id_docente_titular}
    componente={cambiarTitularComponente}
  />

  <!-- Ficha del estudiante (evaluaciones, mensajes y asistencia) -->
  {#if estudianteSeleccionado}
    <EstudianteDetalleModal
      abierto={modalEstudiante}
      estudiante={estudianteSeleccionado}
      {actividades}
      idCurso={curso.id_curso}
      {mensajesEstudiante}
      onCerrar={cerrarModalEstudiante}
    />
  {/if}
</DocenteLayout>
