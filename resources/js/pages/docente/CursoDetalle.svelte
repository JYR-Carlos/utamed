<script lang="ts">
  /**
   * Página de detalles de curso para docentes.
   *
   * Vista bifurcada:
   * - Titular: layout 2 columnas (65/35). Header compacto blanco, Mi Grupo a la izquierda,
   *   Equipo Docente + Detalles a la derecha.
   * - Colegiado: vista centrada en su componente y sus alumnos.
   */
  import { router, Link, page } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import {
    Calendar,
    BookOpen,
    Users,
    GraduationCap,
    Building2,
    FileText,
    BookOpenCheck,
    Crown,
    UserCheck,
    UsersRound,
    Settings,
    ChevronRight,
    Layers,
    Shield,
    Search,
    X,
    ClipboardList,
    ClipboardCheck,
    ArrowLeft,
    MessageSquare,
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
  import { initials, formatFechaCorta } from '@/utils/formatters';
  import type { Actividad } from '@/types/actividad';

  interface Componente {
    id_componente: number;
    tipo_componente: string;
    es_titular: boolean;
    total_docentes: number;
    total_estudiantes: number;
  }

  interface EstudianteComponente {
    id_inscripcion_componente: number;
    id_componente: number;
    tipo_componente: string;
    nota_componente: number | null;
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
    fecha_inicio: string;
    fecha_fin: string;
    agno_real: number;
    semestre_real: number;
    estado_interno: string;
    es_plantilla: boolean;
    tiene_programa: boolean;
    es_titular_curso: boolean;
    id_docente_titular: number;
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

  // Pestaña activa en la sección "Mi Grupo"
  let componenteActivo = $state<number | null>(null);

  // ─── Modales de permisos ───
  let showSyllabusPermisos = $state(false);
  let showComponentePermisos = $state(false);
  let componentePermisoId = $state<number>(0);
  let componentePermisoTipo = $state('');

  // ─── Slide-over Equipo ───
  let showEquipoSlideOver = $state(false);

  // ─── Modal Cambiar Titular de Componente ───
  let showCambiarTitular = $state(false);
  let cambiarTitularComponente = $state<ComponenteCurso | null>(null);

  // ─── Vista principal (tabs) ───
  type MainTab = 'grupo' | 'actividades' | 'asistencia';

  // Permite abrir directo en un tab vía deep-link (?tab=asistencia), p.ej. desde
  // el dashboard. Sólo se honra si el tab es accesible en este curso.
  const initialTab: MainTab = (() => {
    const query = ($page.url.split('?')[1] ?? '');
    const tab = new URLSearchParams(query).get('tab');
    if (tab === 'asistencia' && mis_componentes.length > 0) return 'asistencia';
    if (tab === 'actividades' && canVerActividades) return 'actividades';
    return 'grupo';
  })();
  let mainTab = $state<MainTab>(initialTab);

  // ─── Vista colegiado: alternar entre estudiantes y asistencia ───
  type ColegiadoTab = 'estudiantes' | 'asistencia';
  let colegiadoTab = $state<ColegiadoTab>('estudiantes');

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

  const estudiantesActivos = $derived(
    mis_estudiantes.filter((e) => e.id_componente === componenteActivo),
  );

  const tipoComponenteActivo = $derived(
    mis_componentes.find((c) => c.id_componente === componenteActivo)?.tipo_componente ??
      'Componente',
  );

  const totalDocentesCurso = $derived(
    new Set(todos_componentes.flatMap((c) => c.docentes.map((d) => d.id_docente))).size,
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

  function abrirModalEstudiante(est: EstudianteComponente) {
    estudianteSeleccionado = est;
    modalEstudiante = true;
  }

  function cerrarModalEstudiante() {
    modalEstudiante = false;
    estudianteSeleccionado = null;
  }

  /** Formato de fecha-solo-día para el período del curso. D-04: helper compartido. */
  function formatDate(dateString: string) {
    if (!dateString) return '—';
    return formatFechaCorta(dateString);
  }
</script>

<DocenteLayout>
  <div class="bg-white min-h-screen pb-20">
    <!-- ── Header ── -->
    <div class="px-6 sm:px-12 py-6 border-b border-[#E8E4DC]">
      <!-- Breadcrumb -->
      <nav
        class="flex items-center gap-2 mb-6 text-[13px] text-[#5A5E6E]"
        aria-label="Ruta de navegación"
      >
        <Link
          href="/docente/cursos"
          class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg font-medium transition-all text-[#2D2F3A] hover:bg-white hover:text-[#002F6C]"
        >
          <ArrowLeft size={15} />
          Mis Cursos
        </Link>
        <ChevronRight size={13} class="text-[#8A8E9C]" aria-hidden="true" />
        <span
          class="font-mono text-[12px] bg-white text-[#5A5E6E] px-2 py-[3px] rounded-md border border-[#E8E4DC] tracking-[0.02em]"
          aria-current="page">{curso.cod_curso}</span
        >
      </nav>

      <!-- Title + actions row -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-3 flex-wrap mb-2">
            <h1
              class="font-semibold tracking-tight m-0 text-[#1A1A24] text-[clamp(28px,3.5vw,44px)] leading-[1.05]"
            >
              {curso.nombre || curso.asignatura.nombre}
            </h1>
            {#if curso.es_titular_curso}
              <span
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-[#FFF3D1] text-[#8A5F00] border border-[rgba(255,184,28,0.35)]"
              >
                <Crown size={12} />
                Titular
              </span>
            {:else}
              <span
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-[#F5F1EA] text-[#5A5E6E] border border-[#E8E4DC]"
              >
                <UserCheck size={12} />
                Colaborador
              </span>
            {/if}
            <span
              class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium {curso.es_plantilla ? 'bg-[#F5F1EA] text-[#5A5E6E] border border-[#E8E4DC]' : 'bg-[#E0F5EA] text-[#0E7C4A] border border-[rgba(14,124,74,0.25)]'}"
            >
              {#if !curso.es_plantilla}
                <span class="w-[7px] h-[7px] rounded-full bg-[#0E7C4A] shadow-[0_0_0_3px_rgba(14,124,74,0.18)]" aria-hidden="true"></span>
              {/if}
              {curso.es_plantilla ? 'Plantilla' : 'Activo'}
            </span>
          </div>
          <div class="flex items-center gap-2.5 flex-wrap">
            <span
              class="font-mono text-[12px] bg-[#E6ECF5] text-[#002F6C] px-[10px] py-1 rounded-[7px] font-semibold tracking-[0.02em] border border-[rgba(0,47,108,0.18)]"
              >{curso.cod_curso}</span
            >
            <span class="text-[13px] text-[#5A5E6E]"
              >{curso.asignatura.nombre} · {curso.asignatura.cod_asignatura}</span
            >
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0 flex-wrap">
          {#if curso.tiene_programa}
            <button
              onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/programa`)}
              class="inline-flex items-center gap-2 px-4 text-sm font-medium rounded-[10px] border transition-all h-10 bg-transparent text-[#2D2F3A] border-[#E8E4DC] hover:!bg-white hover:!border-[#D0CBC1]"
            >
              <BookOpenCheck size={16} />
              Ver Programa
            </button>
          {/if}
          {#if curso.es_titular_curso}
            <button
              onclick={() => (showSyllabusPermisos = true)}
              class="inline-flex items-center gap-2 px-4 text-sm font-medium rounded-[10px] border transition-all h-10 bg-white text-[#002F6C] border-[#002F6C]"
            >
              <Shield size={16} />
              Permisos Syllabus
            </button>
          {/if}
          <!-- Mensajería de nivel curso (curso.mensaje): avisos al componente y
               canal por alumno. Se entra desde aquí porque el hilo pertenece a
               este curso; las consultas sobre una entrega van en su actividad. -->
          <button
            onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/mensajeria`)}
            class="inline-flex items-center gap-2 px-4 text-sm font-medium rounded-[10px] border transition-all h-10 bg-transparent text-[#2D2F3A] border-[#E8E4DC] hover:!bg-white hover:!border-[#D0CBC1]"
          >
            <MessageSquare size={16} />
            Mensajería
          </button>
          {#if canVerActividades}
            <button
              onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/actividades`)}
              class="inline-flex items-center gap-2 px-4 text-sm font-semibold text-white rounded-[10px] transition-all h-10 bg-[#002F6C] hover:!bg-[#1B4789]"
            >
              <FileText size={16} />
              Gestionar Actividades
            </button>
          {/if}
        </div>
      </div>

      <!-- Meta strip (datos críticos del curso — antes en "Detalles del Curso") -->
      <div
        class="flex flex-col md:flex-row flex-wrap rounded-2xl border mb-4 bg-white border-[#E8E4DC] py-4 md:px-5 px-0 gap-y-3"
      >
        <div class="flex flex-col gap-1 flex-auto md:min-w-[130px] px-5 md:pl-0">
          <div class="text-[10px] font-semibold tracking-[0.08em] uppercase text-[#8A8E9C]">Asignatura</div>
          <div class="text-sm font-medium text-[#1A1A24] leading-[1.3]">{curso.asignatura.nombre}</div>
        </div>
        <div class="w-px bg-[#E8E4DC] my-0.5 self-stretch hidden md:block" aria-hidden="true"></div>
        <div class="flex flex-col gap-1 flex-auto md:min-w-[130px] px-5">
          <div class="text-[10px] font-semibold tracking-[0.08em] uppercase text-[#8A8E9C]">Carrera</div>
          <div class="text-sm font-medium text-[#1A1A24] leading-[1.3]">{curso.plan.carrera}</div>
        </div>
        <div class="w-px bg-[#E8E4DC] my-0.5 self-stretch hidden md:block" aria-hidden="true"></div>
        <div class="flex flex-col gap-1 flex-auto md:min-w-[130px] px-5">
          <div class="text-[10px] font-semibold tracking-[0.08em] uppercase text-[#8A8E9C]">Semestre</div>
          <div class="text-sm font-medium text-[#1A1A24] leading-[1.3]">
            {curso.semestre_real === 1 ? '1er' : '2do'} Sem. · {curso.agno_real}
          </div>
        </div>
        <div class="w-px bg-[#E8E4DC] my-0.5 self-stretch hidden md:block" aria-hidden="true"></div>
        <div class="flex flex-col gap-1 flex-auto md:min-w-[130px] px-5 md:pr-0">
          <div class="text-[10px] font-semibold tracking-[0.08em] uppercase text-[#8A8E9C]">Período</div>
          <div class="text-sm font-medium text-[#1A1A24] leading-[1.3] inline-flex items-center gap-1.5">
            <Calendar size={13} class="text-[#E11D74]" />
            {formatDate(curso.fecha_inicio)} — {formatDate(curso.fecha_fin)}
          </div>
        </div>
        {#if curso.plan.nombre && curso.plan.nombre !== 'undefined'}
          <div class="w-px bg-[#E8E4DC] my-0.5 self-stretch hidden md:block" aria-hidden="true"></div>
          <div class="flex flex-col gap-1 flex-auto md:min-w-[130px] px-5">
            <div class="text-[10px] font-semibold tracking-[0.08em] uppercase text-[#8A8E9C]">Plan</div>
            <div class="text-sm font-medium text-[#8A8E9C] font-normal leading-[1.3]">{curso.plan.nombre}</div>
          </div>
        {/if}
        {#if curso.asignatura.descripcion}
          <div class="w-px bg-[#E8E4DC] my-0.5 self-stretch hidden md:block" aria-hidden="true"></div>
          <div class="flex flex-col gap-1 flex-[2_1_200px] px-5 md:pr-0">
            <div class="text-[10px] font-semibold tracking-[0.08em] uppercase text-[#8A8E9C]">Descripción</div>
            <div
              class="text-sm text-[#5A5E6E] font-normal leading-[1.4] line-clamp-2 overflow-hidden"
            >
              {curso.asignatura.descripcion}
            </div>
          </div>
        {/if}
      </div>

      <!-- Stat chips -->
      <div class="flex items-center gap-2 flex-wrap">
        {#if todos_componentes.length > 0 || curso.es_titular_curso}
          <span class="inline-flex items-center gap-[7px] py-[7px] pl-[12px] pr-[14px] bg-white border border-[#E8E4DC] rounded-full text-[13px] text-[#5A5E6E]">
            <Layers size={15} />
            <strong class="text-[#1A1A24] font-semibold tabular-nums">{todos_componentes.length}</strong>
            <span>Componente{todos_componentes.length !== 1 ? 's' : ''}</span>
          </span>
          <span class="inline-flex items-center gap-[7px] py-[7px] pl-[12px] pr-[14px] bg-white border border-[#E8E4DC] rounded-full text-[13px] text-[#5A5E6E]">
            <UsersRound size={15} />
            <strong class="text-[#1A1A24] font-semibold tabular-nums">{totalDocentesCurso}</strong>
            <span>Docente{totalDocentesCurso !== 1 ? 's' : ''}</span>
          </span>
        {/if}
        <span class="inline-flex items-center gap-[7px] py-[7px] pl-[12px] pr-[14px] !bg-[#002F6C] !border-[#002F6C] !text-white rounded-full text-[13px]">
          <Users size={15} />
          <strong class="!text-white font-semibold tabular-nums">{curso.total_estudiantes}</strong>
          <span>Estudiante{curso.total_estudiantes !== 1 ? 's' : ''}</span>
        </span>
      </div>
    </div>

    <!-- ── Content ── -->
    <div class="px-6 sm:px-12 pt-6">
      {#if curso.es_titular_curso}
        <!-- VISTA TITULAR: asimétrico 1fr / 340px -->
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6 items-start">
          <!-- Columna principal: Mi Grupo -->
          <div class="bg-white rounded-[18px] border border-[#E8E4DC] flex flex-col">
            <!-- Panel header + tabs -->
            <div class="border-b border-[#E8E4DC]">
              <div class="pt-[22px] px-6 flex items-center gap-3">
                <div class="flex items-center justify-center w-9 h-9 rounded-[11px] bg-[#E6ECF5] shrink-0">
                  <GraduationCap size={18} class="text-[#002F6C]" />
                </div>
                <h2 class="text-lg font-semibold tracking-[-0.015em] m-0 text-[#1A1A24]">
                  {mainTab === 'grupo' ? 'Mi Grupo' : mainTab === 'actividades' ? 'Actividades' : 'Asistencia'}
                </h2>
              </div>
              <div
                class="flex px-6 -mb-px"
                role="tablist"
                aria-label="Secciones del curso"
              >
                <button
                  role="tab"
                  aria-selected={mainTab === 'grupo'}
                  onclick={() => (mainTab = 'grupo')}
                  class="inline-flex items-center gap-[7px] py-3 px-1 mr-5 bg-transparent border-none border-b-2 text-sm font-medium cursor-pointer transition-colors duration-150 {mainTab === 'grupo' ? 'border-[#002F6C] text-[#002F6C]' : 'border-transparent text-[#5A5E6E]'}"
                >
                  <GraduationCap size={14} />
                  Mi Grupo
                  <span
                    class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1.5 rounded-full text-[11px] font-semibold tabular-nums {mainTab === 'grupo' ? 'bg-[#002F6C] text-white' : 'bg-[#F5F1EA] text-[#5A5E6E]'}"
                    >{estudiantesActivos.length}</span
                  >
                </button>
                {#if canVerActividades}
                  <button
                    role="tab"
                    aria-selected={mainTab === 'actividades'}
                    onclick={() => (mainTab = 'actividades')}
                    class="inline-flex items-center gap-[7px] py-3 px-1 mr-5 bg-transparent border-none border-b-2 text-sm font-medium cursor-pointer transition-colors duration-150 {mainTab === 'actividades' ? 'border-[#002F6C] text-[#002F6C]' : 'border-transparent text-[#5A5E6E]'}"
                  >
                    <ClipboardList size={14} />
                    Actividades
                    <span
                      class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1.5 rounded-full text-[11px] font-semibold tabular-nums {mainTab === 'actividades' ? 'bg-[#002F6C] text-white' : 'bg-[#F5F1EA] text-[#5A5E6E]'}"
                      >{actividades.length}</span
                    >
                  </button>
                {/if}
                {#if mis_componentes.length > 0}
                  <button
                    role="tab"
                    aria-selected={mainTab === 'asistencia'}
                    onclick={() => (mainTab = 'asistencia')}
                    class="inline-flex items-center gap-[7px] py-3 px-1 mr-5 bg-transparent border-none border-b-2 text-sm font-medium cursor-pointer transition-colors duration-150 {mainTab === 'asistencia' ? 'border-[#002F6C] text-[#002F6C]' : 'border-transparent text-[#5A5E6E]'}"
                  >
                    <ClipboardCheck size={14} />
                    Asistencia
                  </button>
                {/if}
              </div>
            </div>

            <!-- TAB: Mi Grupo -->
            {#if mainTab === 'grupo'}
              <div class="p-[22px_24px] flex-1 space-y-4">
                {#if mis_componentes.length === 0}
                  <div class="flex flex-col items-center justify-center py-14 text-center rounded-xl border-2 border-dashed bg-[#F5F1EA] border-[#D0CBC1]">
                    <div class="w-14 h-14 rounded-full flex items-center justify-center mb-3 bg-[#E8E4DC]">
                      <Crown size={26} class="text-[#8A5F00]" />
                    </div>
                    <p class="text-sm font-semibold text-[#1A1A24] mb-1">Titular Administrativo</p>
                    <p class="text-xs text-[#5A5E6E] max-w-[280px] leading-relaxed">
                      No tienes un componente asignado para impartir clases directamente. Los
                      <strong class="text-[#1A1A24]">{curso.total_estudiantes}</strong>
                      {curso.total_estudiantes === 1 ? 'estudiante' : 'estudiantes'} del curso están organizados por componente en el panel <strong class="text-[#1A1A24]">Equipo Docente</strong>.
                    </p>
                  </div>
                {:else}
                {#if estudiantesActivos.length > 0}
                  <div class="relative">
                    <Search
                      size={13}
                      class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-[#8A8E9C]"
                    />
                    <input
                      type="text"
                      bind:value={estudianteQuery}
                      placeholder="Buscar estudiante…"
                      class="w-full pl-8 pr-8 py-2 text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-[#002F6C]/20 focus:border-[#002F6C] placeholder:text-gray-400 bg-white border border-[#E8E4DC]"
                    />
                    {#if estudianteQuery}
                      <button
                        onclick={() => (estudianteQuery = '')}
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#8A8E9C]"
                        aria-label="Limpiar búsqueda"
                      >
                        <X size={13} />
                      </button>
                    {/if}
                  </div>
                {/if}

                <!-- Component pills -->
                <ComponentePills
                  componentes={mis_componentes}
                  {componenteActivo}
                  mostrarContador
                  onSelect={(id) => {
                    componenteActivo = id;
                    estudianteQuery = '';
                  }}
                />

                <!-- Student table -->
                {#if estudiantesActivos.length === 0}
                  <div
                    class="flex flex-col items-center justify-center py-16 text-center rounded-xl border-2 border-dashed bg-[#F5F1EA] border-[#D0CBC1]"
                  >
                    <div
                      class="w-14 h-14 rounded-full flex items-center justify-center mb-3 bg-[#E8E4DC]"
                    >
                      <GraduationCap size={26} class="text-[#8A8E9C]" />
                    </div>
                    <p class="text-sm font-medium text-[#5A5E6E]">
                      Sin estudiantes inscritos
                    </p>
                    <p class="text-xs mt-1 text-[#8A8E9C]">
                      Los estudiantes aparecerán aquí cuando se inscriban.
                    </p>
                  </div>
                {:else if estudiantesActivosFiltrados.length === 0}
                  <div
                    class="flex flex-col items-center gap-2 py-10 text-center text-[#8A8E9C]"
                  >
                    <Search size={28} class="opacity-30" />
                    <p class="text-sm">Sin resultados para «{estudianteQuery}»</p>
                    <button
                      onclick={() => (estudianteQuery = '')}
                      class="text-xs font-medium hover:underline text-[#002F6C]"
                      >Limpiar búsqueda</button
                    >
                  </div>
                {:else}
                  <EstudiantesTable
                    estudiantes={estudiantesActivosFiltrados}
                    tipoComponente={tipoComponenteActivo}
                    mostrarDetalle
                    onDetalle={(item) => abrirModalEstudiante(item as EstudianteComponente)}
                  />
                  <p class="text-xs text-right text-[#8A8E9C]">
                    {estudiantesActivosFiltrados.length}{estudiantesActivosFiltrados.length !==
                    estudiantesActivos.length
                      ? ` de ${estudiantesActivos.length}`
                      : ''} estudiante{estudiantesActivos.length !== 1 ? 's' : ''}
                  </p>
                {/if}
                {/if}
              </div>

              <!-- TAB: Actividades -->
            {:else if mainTab === 'actividades'}
              <div class="p-[22px_24px]">
                {#if actividades.length === 0}
                  <div
                    class="flex flex-col items-center gap-3 py-16 text-center rounded-xl border border-dashed bg-[#F5F1EA] border-[#D0CBC1] text-[#8A8E9C]"
                  >
                    <ClipboardList size={36} class="opacity-30" />
                    <p class="text-sm">No hay actividades en este curso.</p>
                    {#if curso.es_titular_curso}
                      <button
                        onclick={() =>
                          router.visit(`/docente/cursos/${curso.id_curso}/actividades`)}
                        class="text-xs font-medium hover:underline text-[#002F6C]"
                      >
                        Crear primera actividad →
                      </button>
                    {/if}
                  </div>
                {:else}
                  <ActividadesPorEstado {actividades} idCurso={curso.id_curso} />
                {/if}
              </div>

              <!-- TAB: Asistencia -->
            {:else if mainTab === 'asistencia'}
              <div class="p-[22px_24px] flex-1 space-y-4">
                <!-- Selector de componente -->
                <ComponentePills
                  componentes={mis_componentes}
                  {componenteActivo}
                  mostrarSingle={false}
                  onSelect={(id) => (componenteActivo = id)}
                />

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
          </div>

          <!-- Columna derecha: Equipo Docente únicamente -->
          <div class="flex flex-col gap-5">
            <div class="bg-white rounded-[18px] border border-[#E8E4DC] p-[22px_24px]">
              <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                  <div class="flex items-center justify-center w-9 h-9 rounded-[11px] bg-[#E6ECF5] shrink-0">
                    <UsersRound size={16} class="text-[#002F6C]" />
                  </div>
                  <h3 class="text-lg font-semibold tracking-[-0.015em] m-0 text-[#1A1A24]">Equipo Docente</h3>
                </div>
                <button
                  onclick={() => (showEquipoSlideOver = true)}
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition-all bg-transparent text-[#002F6C] border-[#002F6C] hover:bg-[#E6ECF5]"
                >
                  <Settings size={11} />
                  Gestionar
                </button>
              </div>

              {#if todos_componentes.length === 0}
                <p class="text-xs text-center py-4 text-[#8A8E9C]">
                  Sin docentes asignados.
                </p>
              {:else}
                <div class="space-y-5">
                  {#each todos_componentes as comp}
                    <div>
                      <!-- Component label + divider line -->
                      <div class="flex items-center gap-2.5 mb-3">
                        <span
                          class="text-[11px] font-semibold tracking-[0.08em] uppercase text-[#8A8E9C] whitespace-nowrap"
                          >{comp.tipo_componente}</span
                        >
                        <div class="flex-1 h-px bg-[#E8E4DC]"></div>
                        {#if comp.docentes.length > 1}
                          <div class="flex items-center gap-1">
                            <button
                              onclick={() => {
                                cambiarTitularComponente = comp;
                                showCambiarTitular = true;
                              }}
                              class="p-1.5 rounded-lg transition-colors text-[#8A8E9C] hover:text-[#8A5F00] hover:bg-[#FFF3D1]"
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
                              class="p-1.5 rounded-lg transition-colors text-[#8A8E9C] hover:text-[#002F6C] hover:bg-[#E6ECF5]"
                              aria-label="Gestionar permisos de {comp.tipo_componente}"
                            >
                              <Shield size={13} aria-hidden="true" />
                            </button>
                          </div>
                        {/if}
                      </div>
                      <!-- Docente rows -->
                      {#each comp.docentes as doc, di}
                        {@const esDtCurso = doc.id_docente === curso.id_docente_titular}
                        {@const avColors = ['#002F6C', '#E11D74', '#FFB81C', '#FF5A5F', '#6E4AC6']}
                        {@const avBg = avColors[di % avColors.length]}
                        {@const avColor = avBg === '#FFB81C' ? '#8A5F00' : 'white'}
                        <div
                          class="flex items-center gap-3 py-2.5 {di > 0 ? 'border-t border-[#E8E4DC]' : ''}"
                        >
                          <div
                            class="flex items-center justify-center w-9 h-9 rounded-full text-xs font-semibold shrink-0"
                            style="background:{avBg}; color:{avColor};"
                          >
                            {initials(doc.nombre)}
                          </div>
                          <p
                            class="text-sm font-medium flex-1 min-w-0 truncate text-[#1A1A24]"
                          >
                            {doc.nombre}
                          </p>
                          {#if esDtCurso}
                            <span
                              class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full shrink-0 bg-[#E6ECF5] text-[#002F6C] border border-[rgba(0,47,108,0.2)]"
                            >
                              <Crown size={9} />DT
                            </span>
                          {:else if doc.es_titular}
                            <span
                              class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full shrink-0 bg-[#FFF3D1] text-[#8A5F00] border border-[rgba(255,184,28,0.35)]"
                            >
                              <Crown size={9} />Titular
                            </span>
                          {:else}
                            <span class="text-xs shrink-0 text-[#8A8E9C]">Colegiado</span>
                          {/if}
                        </div>
                      {/each}
                    </div>
                  {/each}
                </div>
              {/if}
            </div>
          </div>
        </div>
      {:else}
        <!-- ── VISTA COLEGIADO ── -->
        {#if mis_componentes.length > 0}
          <div class="bg-white rounded-[18px] border border-[#E8E4DC] p-[22px_24px]">
            <div class="border-b border-[#E8E4DC] pb-[18px] mb-[22px]">
              <div class="flex items-center gap-3 mb-1">
                <div class="flex items-center justify-center w-9 h-9 rounded-[11px] bg-[#E6ECF5] shrink-0">
                  <GraduationCap size={16} class="text-[#002F6C]" />
                </div>
                <h2 class="text-lg font-semibold tracking-[-0.015em] m-0 text-[#1A1A24]">Mi Componente</h2>
              </div>
              <p class="text-xs ml-12 text-[#8A8E9C]">Tu grupo de estudiantes</p>
            </div>

            <div class="space-y-4">
              <!-- Component pills -->
              <ComponentePills
                componentes={mis_componentes}
                {componenteActivo}
                onSelect={(id) => (componenteActivo = id)}
              />

              <!-- Sub-tabs: Estudiantes / Asistencia -->
              <div class="flex gap-1 p-1 rounded-xl bg-[#F5F1EA] w-fit" role="tablist" aria-label="Vista del componente">
                <button
                  role="tab"
                  aria-selected={colegiadoTab === 'estudiantes'}
                  onclick={() => (colegiadoTab = 'estudiantes')}
                  class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-sm font-medium transition-all {colegiadoTab === 'estudiantes' ? 'bg-white text-[#002F6C] shadow-sm' : 'text-[#5A5E6E]'}"
                >
                  <Users size={14} />
                  Estudiantes
                </button>
                <button
                  role="tab"
                  aria-selected={colegiadoTab === 'asistencia'}
                  onclick={() => (colegiadoTab = 'asistencia')}
                  class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-sm font-medium transition-all {colegiadoTab === 'asistencia' ? 'bg-white text-[#002F6C] shadow-sm' : 'text-[#5A5E6E]'}"
                >
                  <ClipboardCheck size={14} />
                  Asistencia
                </button>
              </div>

              {#if colegiadoTab === 'estudiantes'}
              <!-- KPI -->
              <div
                class="flex items-center justify-center gap-4 p-5 rounded-xl bg-[#E6ECF5]"
              >
                <div
                  class="flex items-center justify-center h-12 w-12 rounded-full bg-white shadow-sm"
                >
                  <Users size={20} class="text-[#002F6C]" />
                </div>
                <div>
                  <p class="text-3xl font-bold text-[#002F6C]">
                    {estudiantesActivos.length}
                  </p>
                  <p class="text-xs font-medium text-[#8A8E9C]">Estudiantes</p>
                </div>
              </div>

              <!-- Table -->
              {#if estudiantesActivos.length === 0}
                <div
                  class="flex flex-col items-center justify-center py-16 rounded-xl border-2 border-dashed bg-[#F5F1EA] border-[#D0CBC1]"
                >
                  <div
                    class="w-14 h-14 rounded-full flex items-center justify-center mb-3 bg-[#E8E4DC]"
                  >
                    <Users size={26} class="text-[#8A8E9C]" />
                  </div>
                  <p class="text-sm font-medium text-[#5A5E6E]">
                    Sin estudiantes inscritos aún
                  </p>
                </div>
              {:else}
                <EstudiantesTable
                  estudiantes={estudiantesActivos}
                  tipoComponente={tipoComponenteActivo}
                />
                <p class="text-xs text-right text-[#8A8E9C]">
                  {estudiantesActivos.length} estudiante{estudiantesActivos.length !== 1 ? 's' : ''}
                </p>
              {/if}
              {:else}
                {#if componenteActivo !== null}
                  {#key componenteActivo}
                    <AsistenciaPanel
                      idCurso={curso.id_curso}
                      idComponente={componenteActivo}
                      tipoComponente={tipoComponenteActivo}
                    />
                  {/key}
                {/if}
              {/if}
            </div>
          </div>
        {:else}
          <div
            class="flex flex-col items-center justify-center py-14 rounded-xl border-2 border-dashed bg-white border-[#D0CBC1] text-[#8A8E9C]"
          >
            <GraduationCap size={32} class="mb-2 text-[#D0CBC1]" />
            <p class="text-sm">No estás asignado a ningún componente de este curso.</p>
          </div>
        {/if}
      {/if}
    </div>
  </div>

  <!-- ─── Modales de Permisos ─── -->
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

  <!-- Modal detalle de estudiante (disponible para titular) -->
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
