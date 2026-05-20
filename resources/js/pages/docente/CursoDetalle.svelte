<script lang="ts">
  /**
   * Página de detalles de curso para docentes.
   *
   * Vista bifurcada:
   * - Titular: layout 2 columnas (65/35). Header compacto blanco, Mi Grupo a la izquierda,
   *   Equipo Docente + Detalles a la derecha.
   * - Colegiado: vista centrada en su componente y sus alumnos.
   */
  import { router, Link } from '@inertiajs/svelte';
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
    ArrowLeft,
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
  type MainTab = 'grupo' | 'actividades';
  let mainTab = $state<MainTab>('grupo');

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

  function formatDate(dateString: string) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('es-CL', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  }

  /** Iniciales para avatar */
  function initials(name: string): string {
    return name
      .split(' ')
      .slice(0, 2)
      .map((w) => w[0] ?? '')
      .join('')
      .toUpperCase();
  }
</script>

<DocenteLayout>
  <div style="background:#FFF; min-height:100vh; padding-bottom:5rem;">
    <!-- ── Header ── -->
    <div class="px-6 sm:px-12 py-6" style="border-bottom:1px solid #E8E4DC;">
      <!-- Breadcrumb -->
      <nav
        class="flex items-center gap-2 mb-6"
        style="font-size:13px; color:#5A5E6E;"
        aria-label="Ruta de navegación"
      >
        <Link
          href="/docente/cursos"
          class="back-link inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg font-medium transition-all"
          style="color:#2D2F3A;"
        >
          <ArrowLeft size={15} />
          Mis Cursos
        </Link>
        <ChevronRight size={13} style="color:#8A8E9C;" aria-hidden="true" />
        <span
          class="bread-code"
          style="font-family:ui-monospace,monospace; font-size:12px; background:white; color:#5A5E6E; padding:3px 8px; border-radius:6px; border:1px solid #E8E4DC; letter-spacing:0.02em;"
          aria-current="page">{curso.cod_curso}</span
        >
      </nav>

      <!-- Title + actions row -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-3 flex-wrap mb-2">
            <h1
              class="font-semibold tracking-tight"
              style="font-size:clamp(28px,3.5vw,44px); line-height:1.05; color:#1A1A24; margin:0;"
            >
              {curso.nombre || curso.asignatura.nombre}
            </h1>
            {#if curso.es_titular_curso}
              <span
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium"
                style="background:#FFF3D1; color:#8A5F00; border:1px solid rgba(255,184,28,0.35);"
              >
                <Crown size={12} />
                Titular
              </span>
            {:else}
              <span
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium"
                style="background:#F5F1EA; color:#5A5E6E; border:1px solid #E8E4DC;"
              >
                <UserCheck size={12} />
                Colaborador
              </span>
            {/if}
            <span
              class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium"
              style={curso.es_plantilla
                ? 'background:#F5F1EA; color:#5A5E6E; border:1px solid #E8E4DC;'
                : 'background:#E0F5EA; color:#0E7C4A; border:1px solid rgba(14,124,74,0.25);'}
            >
              {#if !curso.es_plantilla}
                <span class="active-dot" aria-hidden="true"></span>
              {/if}
              {curso.es_plantilla ? 'Plantilla' : 'Activo'}
            </span>
          </div>
          <div class="flex items-center gap-2.5 flex-wrap">
            <span
              style="font-family:ui-monospace,monospace; font-size:12px; background:#E6ECF5; color:#002F6C; padding:4px 10px; border-radius:7px; font-weight:600; letter-spacing:0.02em; border:1px solid rgba(0,47,108,0.18);"
              >{curso.cod_curso}</span
            >
            <span style="font-size:13px; color:#5A5E6E;"
              >{curso.asignatura.nombre} · {curso.asignatura.cod_asignatura}</span
            >
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0 flex-wrap">
          {#if curso.tiene_programa}
            <button
              onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/programa`)}
              class="btn-design inline-flex items-center gap-2 px-4 text-sm font-medium rounded-[10px] border transition-all"
              style="height:40px; background:transparent; color:#2D2F3A; border-color:#E8E4DC;"
            >
              <BookOpenCheck size={16} />
              Ver Programa
            </button>
          {/if}
          {#if curso.es_titular_curso}
            <button
              onclick={() => (showSyllabusPermisos = true)}
              class="inline-flex items-center gap-2 px-4 text-sm font-medium rounded-[10px] border transition-all"
              style="height:40px; background:white; color:#002F6C; border-color:#002F6C;"
            >
              <Shield size={16} />
              Permisos Syllabus
            </button>
          {/if}
          {#if canVerActividades}
            <button
              onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/actividades`)}
              class="btn-primary-design inline-flex items-center gap-2 px-4 text-sm font-semibold text-white rounded-[10px] transition-all"
              style="height:40px; background:#002F6C;"
            >
              <FileText size={16} />
              Gestionar Actividades
            </button>
          {/if}
        </div>
      </div>

      <!-- Meta strip (datos críticos del curso — antes en "Detalles del Curso") -->
      <div
        class="meta-strip flex flex-wrap rounded-2xl border mb-4"
        style="background:white; border-color:#E8E4DC; padding:16px 20px; row-gap:12px;"
      >
        <div class="meta-item">
          <div class="meta-key">Asignatura</div>
          <div class="meta-val">{curso.asignatura.nombre}</div>
        </div>
        <div class="meta-divider" aria-hidden="true"></div>
        <div class="meta-item">
          <div class="meta-key">Carrera</div>
          <div class="meta-val">{curso.plan.carrera}</div>
        </div>
        <div class="meta-divider" aria-hidden="true"></div>
        <div class="meta-item">
          <div class="meta-key">Semestre</div>
          <div class="meta-val">
            {curso.semestre_real === 1 ? '1er' : '2do'} Sem. · {curso.agno_real}
          </div>
        </div>
        <div class="meta-divider" aria-hidden="true"></div>
        <div class="meta-item">
          <div class="meta-key">Período</div>
          <div class="meta-val" style="display:inline-flex; align-items:center; gap:6px;">
            <Calendar size={13} style="color:#E11D74;" />
            {formatDate(curso.fecha_inicio)} — {formatDate(curso.fecha_fin)}
          </div>
        </div>
        {#if curso.plan.nombre && curso.plan.nombre !== 'undefined'}
          <div class="meta-divider" aria-hidden="true"></div>
          <div class="meta-item">
            <div class="meta-key">Plan</div>
            <div class="meta-val" style="color:#8A8E9C; font-weight:400;">{curso.plan.nombre}</div>
          </div>
        {/if}
        {#if curso.asignatura.descripcion}
          <div class="meta-divider" aria-hidden="true"></div>
          <div class="meta-item" style="flex:2 1 200px;">
            <div class="meta-key">Descripción</div>
            <div
              class="meta-val"
              style="color:#5A5E6E; font-weight:400; line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;"
            >
              {curso.asignatura.descripcion}
            </div>
          </div>
        {/if}
      </div>

      <!-- Stat chips -->
      <div class="flex items-center gap-2 flex-wrap">
        {#if todos_componentes.length > 0 || curso.es_titular_curso}
          <span class="stat-chip">
            <Layers size={15} />
            <strong>{todos_componentes.length}</strong>
            <span>Componente{todos_componentes.length !== 1 ? 's' : ''}</span>
          </span>
          <span class="stat-chip">
            <UsersRound size={15} />
            <strong>{totalDocentesCurso}</strong>
            <span>Docente{totalDocentesCurso !== 1 ? 's' : ''}</span>
          </span>
        {/if}
        <span class="stat-chip stat-chip-active">
          <Users size={15} />
          <strong>{curso.total_estudiantes}</strong>
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
          <div class="panel flex flex-col">
            <!-- Panel header + tabs -->
            <div style="border-bottom:1px solid #E8E4DC;">
              <div style="padding:22px 24px 0; display:flex; align-items:center; gap:12px;">
                <div class="panel-icon">
                  <GraduationCap size={18} style="color:#002F6C;" />
                </div>
                <h2 class="panel-title">
                  {mainTab === 'grupo' ? 'Mi Grupo' : 'Actividades'}
                </h2>
              </div>
              <div
                style="display:flex; padding:0 24px; margin-bottom:-1px;"
                role="tablist"
                aria-label="Secciones del curso"
              >
                <button
                  role="tab"
                  aria-selected={mainTab === 'grupo'}
                  onclick={() => (mainTab = 'grupo')}
                  class="tab-btn"
                  style="border-bottom-color:{mainTab === 'grupo'
                    ? '#002F6C'
                    : 'transparent'}; color:{mainTab === 'grupo' ? '#002F6C' : '#5A5E6E'};"
                >
                  <GraduationCap size={14} />
                  Mi Grupo
                  <span
                    class="tab-count"
                    style="background:{mainTab === 'grupo'
                      ? '#002F6C'
                      : '#F5F1EA'}; color:{mainTab === 'grupo' ? 'white' : '#5A5E6E'};"
                    >{estudiantesActivos.length}</span
                  >
                </button>
                {#if canVerActividades}
                  <button
                    role="tab"
                    aria-selected={mainTab === 'actividades'}
                    onclick={() => (mainTab = 'actividades')}
                    class="tab-btn"
                    style="border-bottom-color:{mainTab === 'actividades'
                      ? '#002F6C'
                      : 'transparent'}; color:{mainTab === 'actividades' ? '#002F6C' : '#5A5E6E'};"
                  >
                    <ClipboardList size={14} />
                    Actividades
                    <span
                      class="tab-count"
                      style="background:{mainTab === 'actividades'
                        ? '#002F6C'
                        : '#F5F1EA'}; color:{mainTab === 'actividades' ? 'white' : '#5A5E6E'};"
                      >{actividades.length}</span
                    >
                  </button>
                {/if}
              </div>
            </div>

            <!-- TAB: Mi Grupo -->
            {#if mainTab === 'grupo'}
              <div style="padding:22px 24px; flex:1;" class="space-y-4">
                {#if estudiantesActivos.length > 3}
                  <div class="relative">
                    <Search
                      size={13}
                      class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
                      style="color:#8A8E9C;"
                    />
                    <input
                      type="text"
                      bind:value={estudianteQuery}
                      placeholder="Buscar estudiante…"
                      class="w-full pl-8 pr-8 py-2 text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-[#002F6C]/20 focus:border-[#002F6C] placeholder:text-gray-400"
                      style="border:1px solid #E8E4DC; background:white;"
                    />
                    {#if estudianteQuery}
                      <button
                        onclick={() => (estudianteQuery = '')}
                        class="absolute right-2.5 top-1/2 -translate-y-1/2"
                        style="color:#8A8E9C;"
                        aria-label="Limpiar búsqueda"
                      >
                        <X size={13} />
                      </button>
                    {/if}
                  </div>
                {/if}

                <!-- Component pills -->
                {#if mis_componentes.length > 1}
                  <div class="flex gap-2 flex-wrap">
                    {#each mis_componentes as comp}
                      <button
                        onclick={() => {
                          componenteActivo = comp.id_componente;
                          estudianteQuery = '';
                        }}
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium transition-all"
                        style={componenteActivo === comp.id_componente
                          ? 'background:#002F6C; color:white;'
                          : 'background:#F5F1EA; color:#5A5E6E;'}
                      >
                        {comp.tipo_componente}
                        {#if comp.es_titular}
                          <Crown
                            size={11}
                            style="color:{componenteActivo === comp.id_componente
                              ? '#FFB81C'
                              : '#8A5F00'};"
                          />
                        {/if}
                        <span
                          class="inline-flex items-center justify-center h-4 min-w-4 rounded-full px-1 text-[11px] font-bold"
                          style={componenteActivo === comp.id_componente
                            ? 'background:rgba(255,255,255,0.25); color:white;'
                            : 'background:#D0CBC1; color:#5A5E6E;'}>{comp.total_estudiantes}</span
                        >
                      </button>
                    {/each}
                  </div>
                {:else if mis_componentes.length === 1}
                  <span
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold text-white"
                    style="background:#002F6C;"
                  >
                    {mis_componentes[0].tipo_componente}
                    {#if mis_componentes[0].es_titular}
                      <Crown size={11} style="color:#FFB81C;" />
                    {/if}
                  </span>
                {/if}

                <!-- Student table -->
                {#if estudiantesActivos.length === 0}
                  <div
                    class="flex flex-col items-center justify-center py-16 text-center rounded-xl border-2 border-dashed"
                    style="background:#F5F1EA; border-color:#D0CBC1;"
                  >
                    <div
                      class="w-14 h-14 rounded-full flex items-center justify-center mb-3"
                      style="background:#E8E4DC;"
                    >
                      <GraduationCap size={26} style="color:#8A8E9C;" />
                    </div>
                    <p class="text-sm font-medium" style="color:#5A5E6E;">
                      Sin estudiantes inscritos
                    </p>
                    <p class="text-xs mt-1" style="color:#8A8E9C;">
                      Los estudiantes aparecerán aquí cuando se inscriban.
                    </p>
                  </div>
                {:else if estudiantesActivosFiltrados.length === 0}
                  <div
                    class="flex flex-col items-center gap-2 py-10 text-center"
                    style="color:#8A8E9C;"
                  >
                    <Search size={28} class="opacity-30" />
                    <p class="text-sm">Sin resultados para «{estudianteQuery}»</p>
                    <button
                      onclick={() => (estudianteQuery = '')}
                      class="text-xs font-medium hover:underline"
                      style="color:#002F6C;">Limpiar búsqueda</button
                    >
                  </div>
                {:else}
                  <div style="border:1px solid #E8E4DC; border-radius:12px; overflow:hidden;">
                    <table class="w-full text-sm">
                      <caption class="sr-only"
                        >Estudiantes inscritos — {tipoComponenteActivo}</caption
                      >
                      <thead style="background:#F5F1EA; border-bottom:1px solid #E8E4DC;">
                        <tr>
                          <th
                            class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider"
                            style="color:#8A8E9C; letter-spacing:0.06em;">#</th
                          >
                          <th
                            class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider"
                            style="color:#8A8E9C; letter-spacing:0.06em;">Estudiante</th
                          >
                          <th
                            class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider hidden sm:table-cell"
                            style="color:#8A8E9C; letter-spacing:0.06em;">Usuario</th
                          >
                          <th
                            class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider"
                            style="color:#8A8E9C; letter-spacing:0.06em;">Nota</th
                          >
                          <th
                            class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider"
                            style="color:#8A8E9C; letter-spacing:0.06em;">Detalle</th
                          >
                        </tr>
                      </thead>
                      <tbody>
                        {#each estudiantesActivosFiltrados as item, i}
                          <tr
                            class="stu-row group"
                            style={i < estudiantesActivosFiltrados.length - 1
                              ? 'border-bottom:1px solid #E8E4DC;'
                              : ''}
                          >
                            <td class="px-4 py-3.5 tabular-nums text-xs" style="color:#8A8E9C;"
                              >{i + 1}</td
                            >
                            <td class="px-4 py-3.5">
                              <div class="flex items-center gap-3">
                                <div
                                  class="flex items-center justify-center h-8 w-8 rounded-full text-xs font-semibold text-white shrink-0"
                                  style="background:#002F6C;"
                                >
                                  {item.estudiante.nombre.charAt(0).toUpperCase()}
                                </div>
                                <span class="font-medium text-sm" style="color:#1A1A24;"
                                  >{item.estudiante.nombre}</span
                                >
                              </div>
                            </td>
                            <td class="px-4 py-3.5 hidden sm:table-cell">
                              <span
                                style="font-family:ui-monospace,monospace; font-size:12px; color:#5A5E6E;"
                                >{item.estudiante.username}</span
                              >
                            </td>
                            <td class="px-4 py-3.5 text-right">
                              {#if item.nota_componente !== null}
                                <span
                                  class="inline-flex items-center justify-center h-7 min-w-[2.5rem] rounded-lg text-xs font-bold"
                                  style={item.nota_componente >= 4
                                    ? 'background:#E0F5EA; color:#0E7C4A; outline:1px solid rgba(14,124,74,0.25);'
                                    : 'background:#FEE2E2; color:#DC2626; outline:1px solid rgba(220,38,38,0.25);'}
                                >
                                  {item.nota_componente}
                                  <span class="sr-only"
                                    >{item.nota_componente >= 4
                                      ? '— Aprobado'
                                      : '— Reprobado'}</span
                                  >
                                </span>
                              {:else}
                                <span style="color:#D0CBC1;">—</span>
                              {/if}
                            </td>
                            <td class="px-4 py-3.5 text-right">
                              <button
                                onclick={() => abrirModalEstudiante(item)}
                                title="Ver evaluaciones, mensajes y asistencia"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg border transition-all opacity-0 group-hover:opacity-100"
                                style="background:#E6ECF5; color:#002F6C; border-color:rgba(0,47,108,0.18);"
                              >
                                <BookOpenCheck size={12} />
                                Detalle
                              </button>
                            </td>
                          </tr>
                        {/each}
                      </tbody>
                    </table>
                  </div>
                  <p class="text-xs text-right" style="color:#8A8E9C;">
                    {estudiantesActivosFiltrados.length}{estudiantesActivosFiltrados.length !==
                    estudiantesActivos.length
                      ? ` de ${estudiantesActivos.length}`
                      : ''} estudiante{estudiantesActivos.length !== 1 ? 's' : ''}
                  </p>
                {/if}
              </div>

              <!-- TAB: Actividades -->
            {:else if mainTab === 'actividades'}
              <div style="padding:22px 24px;">
                {#if actividades.length === 0}
                  <div
                    class="flex flex-col items-center gap-3 py-16 text-center rounded-xl border border-dashed"
                    style="background:#F5F1EA; border-color:#D0CBC1; color:#8A8E9C;"
                  >
                    <ClipboardList size={36} class="opacity-30" />
                    <p class="text-sm">No hay actividades en este curso.</p>
                    {#if curso.es_titular_curso}
                      <button
                        onclick={() =>
                          router.visit(`/docente/cursos/${curso.id_curso}/actividades`)}
                        class="text-xs font-medium hover:underline"
                        style="color:#002F6C;"
                      >
                        Crear primera actividad →
                      </button>
                    {/if}
                  </div>
                {:else}
                  <ActividadesPorEstado {actividades} idCurso={curso.id_curso} />
                {/if}
              </div>
            {/if}
          </div>

          <!-- Columna derecha: Equipo Docente únicamente -->
          <div class="flex flex-col gap-5">
            <div class="panel">
              <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                  <div class="panel-icon">
                    <UsersRound size={16} style="color:#002F6C;" />
                  </div>
                  <h3 class="panel-title">Equipo Docente</h3>
                </div>
                <button
                  onclick={() => (showEquipoSlideOver = true)}
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition-all"
                  style="background:transparent; color:#002F6C; border-color:#002F6C;"
                  onmouseenter={(e) =>
                    ((e.currentTarget as HTMLElement).style.background = '#E6ECF5')}
                  onmouseleave={(e) =>
                    ((e.currentTarget as HTMLElement).style.background = 'transparent')}
                >
                  <Settings size={11} />
                  Gestionar
                </button>
              </div>

              {#if todos_componentes.length === 0}
                <p class="text-xs text-center py-4" style="color:#8A8E9C;">
                  Sin docentes asignados.
                </p>
              {:else}
                <div class="space-y-5">
                  {#each todos_componentes as comp}
                    <div>
                      <!-- Component label + divider line -->
                      <div class="flex items-center gap-2.5 mb-3">
                        <span
                          style="font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:#8A8E9C; white-space:nowrap;"
                          >{comp.tipo_componente}</span
                        >
                        <div style="flex:1; height:1px; background:#E8E4DC;"></div>
                        {#if comp.docentes.length > 1}
                          <div class="flex items-center gap-1">
                            <button
                              onclick={() => {
                                cambiarTitularComponente = comp;
                                showCambiarTitular = true;
                              }}
                              class="p-1.5 rounded-lg transition-colors"
                              style="color:#8A8E9C;"
                              aria-label="Cambiar titular de {comp.tipo_componente}"
                              onmouseenter={(e) => {
                                (e.currentTarget as HTMLElement).style.color = '#8A5F00';
                                (e.currentTarget as HTMLElement).style.background = '#FFF3D1';
                              }}
                              onmouseleave={(e) => {
                                (e.currentTarget as HTMLElement).style.color = '#8A8E9C';
                                (e.currentTarget as HTMLElement).style.background = '';
                              }}
                            >
                              <Crown size={13} aria-hidden="true" />
                            </button>
                            <button
                              onclick={() => {
                                componentePermisoId = comp.id_componente;
                                componentePermisoTipo = comp.tipo_componente;
                                showComponentePermisos = true;
                              }}
                              class="p-1.5 rounded-lg transition-colors"
                              style="color:#8A8E9C;"
                              aria-label="Gestionar permisos de {comp.tipo_componente}"
                              onmouseenter={(e) => {
                                (e.currentTarget as HTMLElement).style.color = '#002F6C';
                                (e.currentTarget as HTMLElement).style.background = '#E6ECF5';
                              }}
                              onmouseleave={(e) => {
                                (e.currentTarget as HTMLElement).style.color = '#8A8E9C';
                                (e.currentTarget as HTMLElement).style.background = '';
                              }}
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
                          class="flex items-center gap-3 py-2.5 {di > 0 ? 'border-t' : ''}"
                          style={di > 0 ? 'border-color:#E8E4DC;' : ''}
                        >
                          <div
                            class="flex items-center justify-center w-9 h-9 rounded-full text-xs font-semibold shrink-0"
                            style="background:{avBg}; color:{avColor};"
                          >
                            {initials(doc.nombre)}
                          </div>
                          <p
                            class="text-sm font-medium flex-1 min-w-0 truncate"
                            style="color:#1A1A24;"
                          >
                            {doc.nombre}
                          </p>
                          {#if esDtCurso}
                            <span
                              class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full shrink-0"
                              style="background:#E6ECF5; color:#002F6C; border:1px solid rgba(0,47,108,0.2);"
                            >
                              <Crown size={9} />DT
                            </span>
                          {:else if doc.es_titular}
                            <span
                              class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full shrink-0"
                              style="background:#FFF3D1; color:#8A5F00; border:1px solid rgba(255,184,28,0.35);"
                            >
                              <Crown size={9} />Titular
                            </span>
                          {:else}
                            <span class="text-xs shrink-0" style="color:#8A8E9C;">Colegiado</span>
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
          <div class="panel">
            <div style="border-bottom:1px solid #E8E4DC; padding-bottom:18px; margin-bottom:22px;">
              <div class="flex items-center gap-3 mb-1">
                <div class="panel-icon">
                  <GraduationCap size={16} style="color:#002F6C;" />
                </div>
                <h2 class="panel-title">Mi Componente</h2>
              </div>
              <p class="text-xs ml-12" style="color:#8A8E9C;">Tu grupo de estudiantes</p>
            </div>

            <div class="space-y-4">
              <!-- Component pills -->
              {#if mis_componentes.length > 1}
                <div class="flex gap-2 flex-wrap">
                  {#each mis_componentes as comp}
                    <button
                      onclick={() => (componenteActivo = comp.id_componente)}
                      class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium transition-all"
                      style={componenteActivo === comp.id_componente
                        ? 'background:#002F6C; color:white;'
                        : 'background:#F5F1EA; color:#5A5E6E;'}
                    >
                      {comp.tipo_componente}
                      {#if comp.es_titular}
                        <Crown
                          size={11}
                          style="color:{componenteActivo === comp.id_componente
                            ? '#FFB81C'
                            : '#8A5F00'};"
                        />
                      {/if}
                    </button>
                  {/each}
                </div>
              {:else}
                <span
                  class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold text-white"
                  style="background:#002F6C;"
                >
                  {mis_componentes[0].tipo_componente}
                  {#if mis_componentes[0].es_titular}
                    <Crown size={11} style="color:#FFB81C;" />
                  {/if}
                </span>
              {/if}

              <!-- KPI -->
              <div
                class="flex items-center justify-center gap-4 p-5 rounded-xl"
                style="background:#E6ECF5;"
              >
                <div
                  class="flex items-center justify-center h-12 w-12 rounded-full bg-white shadow-sm"
                >
                  <Users size={20} style="color:#002F6C;" />
                </div>
                <div>
                  <p class="text-3xl font-bold" style="color:#002F6C;">
                    {estudiantesActivos.length}
                  </p>
                  <p class="text-xs font-medium" style="color:#8A8E9C;">Estudiantes</p>
                </div>
              </div>

              <!-- Table -->
              {#if estudiantesActivos.length === 0}
                <div
                  class="flex flex-col items-center justify-center py-16 rounded-xl border-2 border-dashed"
                  style="background:#F5F1EA; border-color:#D0CBC1;"
                >
                  <div
                    class="w-14 h-14 rounded-full flex items-center justify-center mb-3"
                    style="background:#E8E4DC;"
                  >
                    <Users size={26} style="color:#8A8E9C;" />
                  </div>
                  <p class="text-sm font-medium" style="color:#5A5E6E;">
                    Sin estudiantes inscritos aún
                  </p>
                </div>
              {:else}
                <div style="border:1px solid #E8E4DC; border-radius:12px; overflow:hidden;">
                  <table class="w-full text-sm">
                    <caption class="sr-only">Estudiantes inscritos — {tipoComponenteActivo}</caption
                    >
                    <thead style="background:#F5F1EA; border-bottom:1px solid #E8E4DC;">
                      <tr>
                        <th
                          class="px-4 py-3 text-left text-xs font-medium uppercase"
                          style="color:#8A8E9C; letter-spacing:0.06em;">#</th
                        >
                        <th
                          class="px-4 py-3 text-left text-xs font-medium uppercase"
                          style="color:#8A8E9C; letter-spacing:0.06em;">Estudiante</th
                        >
                        <th
                          class="px-4 py-3 text-left text-xs font-medium uppercase hidden sm:table-cell"
                          style="color:#8A8E9C; letter-spacing:0.06em;">Usuario</th
                        >
                        <th
                          class="px-4 py-3 text-right text-xs font-medium uppercase"
                          style="color:#8A8E9C; letter-spacing:0.06em;">Nota</th
                        >
                      </tr>
                    </thead>
                    <tbody>
                      {#each estudiantesActivos as item, i}
                        <tr
                          class="stu-row"
                          style={i < estudiantesActivos.length - 1
                            ? 'border-bottom:1px solid #E8E4DC;'
                            : ''}
                        >
                          <td class="px-4 py-3.5 tabular-nums text-xs" style="color:#8A8E9C;"
                            >{i + 1}</td
                          >
                          <td class="px-4 py-3.5">
                            <div class="flex items-center gap-3">
                              <div
                                class="flex items-center justify-center h-8 w-8 rounded-full text-xs font-semibold text-white shrink-0"
                                style="background:#002F6C;"
                              >
                                {item.estudiante.nombre.charAt(0).toUpperCase()}
                              </div>
                              <span class="font-medium" style="color:#1A1A24;"
                                >{item.estudiante.nombre}</span
                              >
                            </div>
                          </td>
                          <td class="px-4 py-3.5 hidden sm:table-cell">
                            <span
                              style="font-family:ui-monospace,monospace; font-size:12px; color:#5A5E6E;"
                              >{item.estudiante.username}</span
                            >
                          </td>
                          <td class="px-4 py-3.5 text-right">
                            {#if item.nota_componente !== null}
                              <span
                                class="inline-flex items-center justify-center h-7 min-w-[2.5rem] rounded-lg text-xs font-bold"
                                style={item.nota_componente >= 4
                                  ? 'background:#E0F5EA; color:#0E7C4A; outline:1px solid rgba(14,124,74,0.25);'
                                  : 'background:#FEE2E2; color:#DC2626; outline:1px solid rgba(220,38,38,0.25);'}
                              >
                                {item.nota_componente}
                                <span class="sr-only"
                                  >{item.nota_componente >= 4 ? '— Aprobado' : '— Reprobado'}</span
                                >
                              </span>
                            {:else}
                              <span style="color:#D0CBC1;">—</span>
                            {/if}
                          </td>
                        </tr>
                      {/each}
                    </tbody>
                  </table>
                </div>
                <p class="text-xs text-right" style="color:#8A8E9C;">
                  {estudiantesActivos.length} estudiante{estudiantesActivos.length !== 1 ? 's' : ''}
                </p>
              {/if}
            </div>
          </div>
        {:else}
          <div
            class="flex flex-col items-center justify-center py-14 rounded-xl border-2 border-dashed"
            style="background:white; border-color:#D0CBC1; color:#8A8E9C;"
          >
            <GraduationCap size={32} class="mb-2" style="color:#D0CBC1;" />
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

<style>
  /* ── Panels ── */
  .panel {
    background: white;
    border-radius: 18px;
    border: 1px solid #e8e4dc;
    padding: 22px 24px;
  }
  .panel-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 11px;
    background: #e6ecf5;
    flex-shrink: 0;
  }
  .panel-title {
    font-size: 18px;
    font-weight: 600;
    letter-spacing: -0.015em;
    margin: 0;
    color: #1a1a24;
  }

  /* ── Meta strip ── */
  .meta-strip .meta-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1 1 auto;
    min-width: 130px;
    padding: 0 20px;
  }
  .meta-strip .meta-item:first-child {
    padding-left: 0;
  }
  .meta-strip .meta-item:last-child {
    padding-right: 0;
  }
  .meta-key {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #8a8e9c;
  }
  .meta-val {
    font-size: 14px;
    font-weight: 500;
    color: #1a1a24;
    line-height: 1.3;
  }
  .meta-divider {
    width: 1px;
    background: #e8e4dc;
    margin: 2px 0;
    align-self: stretch;
  }

  /* ── Stat chips ── */
  .stat-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 14px 7px 12px;
    background: white;
    border: 1px solid #e8e4dc;
    border-radius: 999px;
    font-size: 13px;
    color: #5a5e6e;
  }
  .stat-chip strong {
    color: #1a1a24;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
  }
  .stat-chip-active {
    background: #002f6c;
    border-color: #002f6c;
    color: white;
  }
  .stat-chip-active strong {
    color: white;
  }

  /* ── Tab buttons ── */
  .tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 12px 4px;
    margin-right: 20px;
    background: transparent;
    border: none;
    border-bottom: 2px solid;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    margin-bottom: -1px;
    font-family: inherit;
    transition:
      color 0.15s,
      border-color 0.15s;
  }
  .tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 6px;
    border-radius: 9px;
    font-size: 11px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
  }

  /* ── Student table row hover ── */
  .stu-row {
    transition: background 0.12s;
  }
  .stu-row:hover {
    background: #f5f1ea;
  }

  /* ── Active badge dot ── */
  .active-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #0e7c4a;
    box-shadow: 0 0 0 3px rgba(14, 124, 74, 0.18);
  }

  /* ── Breadcrumb back-link hover ── */
  .back-link:hover {
    background: white;
    color: #002f6c;
  }

  /* ── Button hovers ── */
  .btn-design:hover {
    background: white !important;
    border-color: #d0cbc1 !important;
  }
  .btn-primary-design:hover {
    background: #1b4789 !important;
  }

  /* ── Responsive meta strip ── */
  @media (max-width: 768px) {
    .meta-strip {
      flex-direction: column;
    }
    .meta-strip .meta-item {
      padding: 0;
      min-width: unset;
    }
    .meta-divider {
      display: none;
    }
  }
</style>
