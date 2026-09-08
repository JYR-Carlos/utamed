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
    ArrowLeft,
    ChevronRight,
    FileText,
    Users,
    UsersRound,
    KeyRound,
    MessageSquare,
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
    userPermissions?: Permission[];
  }

  interface Props {
    curso: Curso;
    mis_componentes: Componente[];
    mis_estudiantes: EstudianteComponente[];
    todos_componentes: ComponenteCurso[];
    actividades?: Actividad[];
  }

  let {
    curso,
    mis_componentes,
    mis_estudiantes,
    todos_componentes = [],
    actividades = [],
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
      componenteActivo = mis_componentes[0].id_componente;
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

  const actividadesFiltradas = $derived(
    filtroComponente === 'todos'
      ? actividadesDelRol
      : actividadesDelRol.filter((a) => a.id_componente === filtroComponente),
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
  const CARD =
    'bg-white border border-[#E5E7EB] rounded-xl shadow-[0_1px_3px_rgba(0,0,0,.08)]';
  const BTN_GHOST =
    'inline-flex items-center gap-[7px] rounded-lg px-3 py-2 text-[13.5px] font-medium text-[#002F6C] transition-colors hover:bg-[#F5F1EA]';
  const BTN_OUTLINE =
    'inline-flex items-center gap-[7px] rounded-lg border border-[#D6D9E0] bg-white px-3 py-2 text-[13.5px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F5F1EA]';
  const BTN_PRIMARY =
    'inline-flex items-center gap-[7px] rounded-lg border border-[#002F6C] bg-[#002F6C] px-3.5 py-2 text-[13px] font-semibold text-white transition-colors hover:bg-[#1B4789]';
  const PILL_AZUL =
    'inline-flex items-center gap-1.5 rounded-full border border-[#C9D6E6] bg-[#E8EDF5] px-2.5 py-0.5 text-[12px] font-semibold text-[#002F6C]';
  const PILL_NEUTRA =
    'inline-flex items-center gap-1.5 rounded-full border border-[#E5E7EB] bg-[#F5F1EA] px-2.5 py-0.5 text-[12px] font-medium text-[#5A5E6E]';
  const TAB_BASE =
    'inline-flex items-center gap-2 -mb-px border-b-2 px-3.5 pt-3.5 pb-3 text-sm transition-colors duration-150';
  const CONTADOR_ACTIVO =
    'rounded-full border border-[#C9D6E6] bg-[#E8EDF5] px-2 py-px font-mono text-[11px] font-semibold tabular-nums text-[#002F6C]';
  const CONTADOR_INACTIVO =
    'rounded-full border border-[#E5E7EB] bg-[#F5F1EA] px-2 py-px font-mono text-[11px] font-semibold tabular-nums text-[#5A5E6E]';
</script>

<DocenteLayout>
  <div class="min-h-screen bg-white pb-16">
    <div class="mx-auto flex max-w-[1440px] flex-col gap-5 px-6 py-6 sm:px-10">
      <!-- ── Ruta ── -->
      <nav class="flex items-center gap-2 text-[13px] text-[#5A5E6E]" aria-label="Ruta de navegación">
        <Link
          href="/docente/cursos"
          class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 font-medium text-[#1A1A24] no-underline transition-colors hover:bg-[#F5F1EA] hover:text-[#002F6C]"
        >
          <ArrowLeft size={15} />
          Mis Cursos
        </Link>
        <ChevronRight size={13} class="text-[#98A0AE]" aria-hidden="true" />
        <span
          class="rounded-md border border-[#E5E7EB] bg-[#F5F1EA] px-2 py-[3px] font-mono text-[12px] tracking-[0.02em] text-[#5A5E6E]"
          aria-current="page">{curso.cod_curso}</span
        >
      </nav>

      <!-- ── Cabecera del curso ── -->
      <section class="{CARD} flex flex-col gap-4 p-5">
        <div class="flex flex-wrap items-start gap-6">
          <div class="flex min-w-0 flex-col gap-1.5">
            <span class="font-mono text-[12px] text-[#5A5E6E]">{curso.asignatura.cod_asignatura}</span>
            <div class="flex flex-wrap items-center gap-2.5">
              <h1 class="m-0 text-[22px] font-semibold tracking-[-0.01em] text-[#1A1A24]">
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
            <span class="text-[12.5px] text-[#5A5E6E]">
              Actúas como
              <strong class="font-semibold text-[#1A1A24]">
                {curso.es_titular_curso ? 'docente titular' : 'docente de componente'}
              </strong>
              · {curso.asignatura.nombre} · {curso.plan.carrera} · {formatDate(curso.fecha_inicio)} a
              {formatDate(curso.fecha_fin)}
            </span>
          </div>

          <div class="ml-auto flex flex-none flex-col items-end gap-2.5">
            <div class="flex flex-wrap items-center justify-end gap-2">
              {#if curso.tiene_programa}
                <button
                  onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/programa`)}
                  class={BTN_GHOST}
                >
                  <FileText size={15} />
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
                <MessageSquare size={15} class="text-[#5A5E6E]" />
                Mensajería
              </button>
              {#if curso.es_titular_curso}
                <button onclick={() => (showEquipoSlideOver = true)} class={BTN_OUTLINE}>
                  <Users size={15} class="text-[#5A5E6E]" />
                  Equipo docente
                </button>
                <button
                  onclick={() =>
                    router.visit(`/docente/cursos/${curso.id_curso}/delegacion-permisos`)}
                  class={BTN_OUTLINE}
                >
                  <KeyRound size={15} class="text-[#5A5E6E]" />
                  Delegación de permisos
                </button>
              {/if}
            </div>

            {#if !curso.es_titular_curso && componenteActual}
              <div class="flex flex-col items-end gap-0.5">
                <span
                  class="inline-flex items-center gap-[7px] rounded-full border border-[#E5E7EB] bg-[#F5F1EA] px-2.5 py-1 text-[12px] font-semibold text-[#1A1A24]"
                >
                  <span
                    class="rounded border border-[#D6D9E0] bg-white px-1.5 py-px font-mono text-[10.5px] font-bold tracking-[0.06em] text-[#5A5E6E]"
                    >{sigla(componenteActual.tipo_componente)}</span
                  >
                  Tu componente
                </span>
                <span class="text-[11.5px] text-[#5A5E6E]">
                  {componenteActual.total_estudiantes}
                  {componenteActual.total_estudiantes === 1 ? 'estudiante' : 'estudiantes'} ·
                  {componenteActual.total_sesiones}
                  {componenteActual.total_sesiones === 1 ? 'sesión' : 'sesiones'}
                </span>
              </div>
            {/if}
          </div>
        </div>

        <!-- KPIs: sólo el titular responde por el curso completo -->
        {#if curso.es_titular_curso}
          <div class="grid grid-cols-1 gap-3 border-t border-[#E5E7EB] pt-4 sm:grid-cols-3">
            <div class="flex flex-col gap-0.5 rounded-xl border border-[#E5E7EB] px-4 py-3.5">
              <span class="text-[12px] text-[#5A5E6E]">Componentes</span>
              <span class="text-[26px] font-semibold leading-[1.2] tracking-[-0.01em] tabular-nums"
                >{todos_componentes.length}</span
              >
              <span class="truncate text-[12px] text-[#5A5E6E]">
                {todos_componentes.map((c) => c.tipo_componente).join(' · ') || 'Sin componentes'}
              </span>
            </div>
            <div class="flex flex-col gap-0.5 rounded-xl border border-[#E5E7EB] px-4 py-3.5">
              <span class="text-[12px] text-[#5A5E6E]">Docentes</span>
              <span class="text-[26px] font-semibold leading-[1.2] tracking-[-0.01em] tabular-nums"
                >{totalDocentesCurso}</span
              >
              <span class="text-[12px] text-[#5A5E6E]">
                {totalTitularesComponente} con componente a cargo · {Math.max(
                  0,
                  totalDocentesCurso - totalTitularesComponente,
                )} de componente
              </span>
            </div>
            <div class="flex flex-col gap-0.5 rounded-xl border border-[#E5E7EB] px-4 py-3.5">
              <span class="text-[12px] text-[#5A5E6E]">Estudiantes</span>
              <span class="text-[26px] font-semibold leading-[1.2] tracking-[-0.01em] tabular-nums"
                >{curso.total_estudiantes}</span
              >
              {#if componenteActual && sinNota > 0}
                <span class="text-[12px] text-[#B45309]"
                  >{sinNota} sin nota en {tipoComponenteActivo}</span
                >
              {:else if componenteActual}
                <span class="text-[12px] text-[#5A5E6E]"
                  >Todas las notas puestas en {tipoComponenteActivo}</span
                >
              {:else}
                <span class="text-[12px] text-[#5A5E6E]">Organizados por componente</span>
              {/if}
            </div>
          </div>
        {/if}
      </section>

      <!-- ── Panel de pestañas ── -->
      <section class="{CARD} flex flex-col">
        <div
          class="flex items-stretch gap-1 overflow-x-auto border-b border-[#E5E7EB] px-5"
          role="tablist"
          aria-label="Secciones del curso"
        >
          <button
            role="tab"
            aria-selected={mainTab === 'grupo'}
            onclick={() => (mainTab = 'grupo')}
            class="{TAB_BASE} {mainTab === 'grupo'
              ? 'border-[#002F6C] font-semibold text-[#002F6C]'
              : 'border-transparent font-medium text-[#5A5E6E] hover:text-[#1A1A24]'}"
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
                ? 'border-[#002F6C] font-semibold text-[#002F6C]'
                : 'border-transparent font-medium text-[#5A5E6E] hover:text-[#1A1A24]'}"
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
                ? 'border-[#002F6C] font-semibold text-[#002F6C]'
                : 'border-transparent font-medium text-[#5A5E6E] hover:text-[#1A1A24]'}"
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
          <div class="flex flex-col gap-3.5 px-5 pb-5 pt-[18px]">
            {#if mis_componentes.length === 0}
              <div
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-[#D0CBC1] bg-[#F5F1EA] py-14 text-center"
              >
                <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-[#E8E4DC]">
                  <Crown size={26} class="text-[#8A5F00]" />
                </div>
                <p class="mb-1 text-sm font-semibold text-[#1A1A24]">Titular administrativo</p>
                <p class="max-w-[320px] text-xs leading-relaxed text-[#5A5E6E]">
                  No tienes un componente asignado para impartir clases directamente. Los
                  <strong class="text-[#1A1A24]">{curso.total_estudiantes}</strong>
                  {curso.total_estudiantes === 1 ? 'estudiante' : 'estudiantes'} del curso están
                  organizados por componente en <strong class="text-[#1A1A24]">Todos los componentes</strong>.
                </p>
              </div>
            {:else}
              <!-- Conmutador de componente + búsqueda -->
              <div class="flex flex-wrap items-center gap-2.5">
                <span
                  class="mr-0.5 text-[12px] font-semibold uppercase tracking-[0.06em] text-[#5A5E6E]"
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
                <span class="hidden text-[12px] text-[#5A5E6E] lg:inline">
                  La nota que ves es la del componente activo, no la del curso.
                </span>
                {#if estudiantesActivos.length > 0}
                  <div class="relative ml-auto w-full sm:w-[240px]">
                    <Search
                      size={13}
                      class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[#98A0AE]"
                    />
                    <input
                      type="text"
                      bind:value={estudianteQuery}
                      placeholder="Buscar estudiante…"
                      class="w-full rounded-lg border border-[#D6D9E0] bg-white py-2 pl-8 pr-8 text-[13px] placeholder:text-[#98A0AE] focus:border-[#002F6C] focus:outline-none"
                    />
                    {#if estudianteQuery}
                      <button
                        onclick={() => (estudianteQuery = '')}
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#98A0AE] hover:text-[#1A1A24]"
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
                  class="flex flex-col items-center justify-center rounded-xl border border-dashed border-[#D0CBC1] bg-[#F5F1EA] py-16 text-center"
                >
                  <div
                    class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-[#E8E4DC]"
                  >
                    <GraduationCap size={26} class="text-[#8A8E9C]" />
                  </div>
                  <p class="text-sm font-medium text-[#5A5E6E]">Sin estudiantes inscritos</p>
                  <p class="mt-1 text-xs text-[#98A0AE]">
                    Los estudiantes aparecerán aquí cuando se inscriban.
                  </p>
                </div>
              {:else if estudiantesActivosFiltrados.length === 0}
                <div class="flex flex-col items-center gap-2 py-10 text-center text-[#98A0AE]">
                  <Search size={28} class="opacity-30" />
                  <p class="text-sm">Sin resultados para «{estudianteQuery}»</p>
                  <button
                    onclick={() => (estudianteQuery = '')}
                    class="text-xs font-medium text-[#002F6C] hover:underline">Limpiar búsqueda</button
                  >
                </div>
              {:else}
                <EstudiantesTable
                  estudiantes={estudiantesActivosFiltrados}
                  tipoComponente={tipoComponenteActivo}
                  mostrarDetalle
                  onDetalle={(item) => abrirModalEstudiante(item as EstudianteComponente)}
                >
                  {#snippet pie()}
                    <div class="flex flex-wrap items-center gap-3.5 text-[12px] text-[#5A5E6E]">
                      <span class="mr-auto">
                        Mostrando {estudiantesActivosFiltrados.length} de {estudiantesActivos.length}
                        {estudiantesActivos.length === 1 ? 'estudiante' : 'estudiantes'} de {tipoComponenteActivo}
                      </span>
                      {#if sinNota > 0}
                        <span class="inline-flex items-center gap-[7px]">
                          <span
                            class="inline-block h-3.5 w-3.5 rounded-full border border-dashed border-[#D6D9E0]"
                            aria-hidden="true"
                          ></span>
                          {sinNota} sin nota
                        </span>
                      {/if}
                      {#if promedioComponente !== null}
                        <span>
                          Promedio del componente
                          <strong class="font-mono font-semibold text-[#1A1A24]"
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
                <span class="inline-flex items-center gap-[7px] text-[12px] text-[#5A5E6E]">
                  <EyeOff size={14} class="text-[#B45309]" />
                  {actividadesOcultas} de {actividadesDelRol.length}
                  {actividadesDelRol.length === 1 ? 'actividad no es visible' : 'actividades no son visibles'}
                  para los alumnos
                </span>
              {/if}
              <div class="ml-auto flex flex-wrap items-center gap-2">
                {#if componentesDeActividades.length > 1}
                  <label class="inline-flex items-center gap-2 text-[12px] text-[#5A5E6E]">
                    <span class="sr-only">Filtrar por componente</span>
                    <select
                      bind:value={filtroComponente}
                      class="rounded-lg border border-[#D6D9E0] bg-white px-3 py-2 text-[13px] font-medium text-[#1A1A24] focus:border-[#002F6C] focus:outline-none"
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
                class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-[#D0CBC1] bg-[#F5F1EA] py-16 text-center text-[#8A8E9C]"
              >
                <ClipboardList size={34} class="opacity-30" />
                <p class="m-0 text-sm">
                  {actividadesDelRol.length === 0
                    ? 'No hay actividades en este curso.'
                    : 'Ninguna actividad en el componente elegido.'}
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
                  class="mr-0.5 text-[12px] font-semibold uppercase tracking-[0.06em] text-[#5A5E6E]"
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
            <h2 class="m-0 text-base font-semibold text-[#1A1A24]">Todos los componentes</h2>
            <span class="text-[12px] text-[#5A5E6E]">
              Sólo el titular ve el curso completo y quién responde por cada componente.
            </span>
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
            <p class="py-6 text-center text-xs text-[#98A0AE]">
              Este curso todavía no tiene componentes.
            </p>
          {:else}
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
              {#each todos_componentes as comp (comp.id_componente)}
                {@const esMio = misComponentesIds.includes(comp.id_componente)}
                <article
                  class="flex flex-col gap-2.5 rounded-xl border px-4 py-3.5 {esMio
                    ? 'border-[#C9D6E6] bg-[#F8FAFC]'
                    : 'border-[#E5E7EB] bg-white'}"
                >
                  <div class="flex items-center gap-2">
                    <span
                      class="rounded-[5px] border px-1.5 py-0.5 font-mono text-[10.5px] font-bold tracking-[0.06em] {esMio
                        ? 'border-[#C9D6E6] bg-white text-[#002F6C]'
                        : 'border-[#E5E7EB] bg-[#F5F1EA] text-[#5A5E6E]'}"
                      >{sigla(comp.tipo_componente)}</span
                    >
                    <span class="text-sm font-semibold text-[#1A1A24]">{comp.tipo_componente}</span>
                    <span class="ml-auto font-mono text-[11.5px] tabular-nums text-[#5A5E6E]"
                      >{comp.total_estudiantes} est.</span
                    >
                    {#if comp.docentes.length > 1}
                      <div class="flex items-center gap-0.5">
                        <button
                          onclick={() => {
                            cambiarTitularComponente = comp;
                            showCambiarTitular = true;
                          }}
                          class="rounded-lg p-1.5 text-[#98A0AE] transition-colors hover:bg-[#FFF3D1] hover:text-[#8A5F00]"
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
                          class="rounded-lg p-1.5 text-[#98A0AE] transition-colors hover:bg-[#E6ECF5] hover:text-[#002F6C]"
                          aria-label="Gestionar permisos de {comp.tipo_componente}"
                        >
                          <Shield size={13} aria-hidden="true" />
                        </button>
                      </div>
                    {/if}
                  </div>

                  {#if comp.docentes.length === 0}
                    <p class="m-0 border-t border-[#E5E7EB] pt-2.5 text-[12px] text-[#98A0AE]">
                      Sin docente asignado.
                    </p>
                  {:else}
                    <div class="flex flex-col gap-[7px] border-t border-[#E5E7EB] pt-2.5">
                      {#each comp.docentes as doc (doc.id_docente_componente)}
                        {@const esDtCurso = doc.id_docente === curso.id_docente_titular}
                        {@const soyYo = doc.id_docente === curso.id_docente_actual}
                        <div class="flex items-center gap-2.5">
                          <div
                            class="flex h-7 w-7 flex-none items-center justify-center rounded-full text-[11px] font-semibold {esDtCurso
                              ? 'bg-[#E8EDF5] text-[#002F6C]'
                              : 'bg-[#F5F1EA] text-[#5A5E6E]'}"
                          >
                            {initials(doc.nombre)}
                          </div>
                          <div class="flex min-w-0 flex-col">
                            <span class="truncate text-[12.5px] font-semibold text-[#1A1A24]"
                              >{doc.nombre}</span
                            >
                            <span
                              class="text-[11.5px] {soyYo ? 'text-[#002F6C]' : 'text-[#5A5E6E]'}"
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
      onCerrar={cerrarModalEstudiante}
    />
  {/if}
</DocenteLayout>
