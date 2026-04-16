<script lang="ts">
  /**
   * Página de detalles de curso para docentes.
   *
   * Vista bifurcada:
   * - Titular: layout 2 columnas (65/35). Header compacto blanco, Mi Grupo a la izquierda,
   *   Equipo Docente + Detalles a la derecha.
   * - Colegiado: vista centrada en su componente y sus alumnos.
   */
  import { router } from '@inertiajs/svelte';
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
  } from 'lucide-svelte';
  import {
    SyllabusPermisosModal,
    ComponentePermisosModal,
    EquipoDocenteSlideOver,
  } from '@/modules/resources/curso/components';

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
  }

  interface Props {
    curso: Curso;
    mis_componentes: Componente[];
    mis_estudiantes: EstudianteComponente[];
    todos_componentes: ComponenteCurso[];
  }

  let { curso, mis_componentes, mis_estudiantes, todos_componentes = [] }: Props = $props();

  // Pestaña activa en la sección "Mi Grupo"
  let componenteActivo = $state<number | null>(null);

  // ─── Modales de permisos ───
  let showSyllabusPermisos = $state(false);
  let showComponentePermisos = $state(false);
  let componentePermisoId = $state<number>(0);
  let componentePermisoTipo = $state('');

  // ─── Slide-over Equipo ───
  let showEquipoSlideOver = $state(false);

  $effect.pre(() => {
    if (componenteActivo === null && mis_componentes.length > 0) {
      componenteActivo = mis_componentes[0].id_componente;
    }
  });

  const estudiantesActivos = $derived(
    mis_estudiantes.filter((e) => e.id_componente === componenteActivo),
  );

  const totalDocentesCurso = $derived(
    new Set(todos_componentes.flatMap((c) => c.docentes.map((d) => d.id_docente))).size,
  );

  function goBack() {
    router.visit('/docente/cursos');
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
  <div class="min-h-screen pb-10">
    <!-- ══════════════════════════════════════════════════════════════════
         HEADER COMPACTO — fondo blanco, ancho completo
         ══════════════════════════════════════════════════════════════════ -->
    <div class="bg-white border-b border-gray-200 px-6 py-5">
      <!-- Breadcrumb -->
      <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-3">
        <button onclick={goBack} class="hover:text-[#2A66AC] transition-colors">Mis Cursos</button>
        <ChevronRight size={12} />
        <span class="text-gray-600 font-medium">{curso.cod_curso}</span>
      </nav>

      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <!-- Título + badges -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight truncate">
              {curso.nombre || curso.asignatura.nombre}
            </h1>
            <!-- Badge Titular -->
            {#if curso.es_titular_curso}
              <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                style="background:#FFF8EC; color:#F0AD4E; border:1px solid #F0AD4E44;"
              >
                <Crown size={11} />
                Titular
              </span>
            {:else}
              <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200"
              >
                <UserCheck size={11} />
                Colaborador
              </span>
            {/if}
            <!-- Badge Estado -->
            <span
              class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {curso.es_plantilla
                ? 'bg-gray-100 text-gray-500 border border-gray-200'
                : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}"
            >
              {curso.es_plantilla ? 'Plantilla' : 'Activo'}
            </span>
          </div>

          <!-- Sub-línea código + asignatura -->
          <p class="text-sm text-gray-500 mt-1">
            <span class="font-mono bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded text-xs mr-2"
              >{curso.cod_curso}</span
            >
            {curso.asignatura.nombre} · {curso.asignatura.cod_asignatura}
          </p>

          <!-- Contadores inline (barra sutil) -->
          <div class="flex items-center gap-0 mt-3 text-xs text-gray-500 divide-x divide-gray-200">
            {#if todos_componentes.length > 0 || curso.es_titular_curso}
              <span class="flex items-center gap-1.5 pr-3">
                <Layers size={12} class="text-gray-400" />
                {todos_componentes.length} Componente{todos_componentes.length !== 1 ? 's' : ''}
              </span>
              <span class="flex items-center gap-1.5 px-3">
                <UsersRound size={12} class="text-gray-400" />
                {totalDocentesCurso} Docente{totalDocentesCurso !== 1 ? 's' : ''}
              </span>
            {/if}
            <span class="flex items-center gap-1.5 {curso.es_titular_curso ? 'pl-3' : ''}">
              <Users size={12} class="text-gray-400" />
              {curso.total_estudiantes} Estudiante{curso.total_estudiantes !== 1 ? 's' : ''}
            </span>
          </div>
        </div>

        <!-- Botones de acción -->
        <div class="flex items-center gap-2 shrink-0 flex-wrap">
          {#if curso.tiene_programa}
            <button
              onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/programa`)}
              class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition"
            >
              <BookOpenCheck size={15} />
              Ver Programa
            </button>
          {/if}
          {#if curso.es_titular_curso}
            <button
              onclick={() => (showSyllabusPermisos = true)}
              class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium border border-[#2A66AC] text-[#2A66AC] rounded-lg hover:bg-blue-50 transition"
            >
              <Shield size={15} />
              Permisos Syllabus
            </button>
          {/if}
          <button
            onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/actividades`)}
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-lg transition shadow-sm hover:brightness-110"
            style="background:#2A66AC;"
          >
            <FileText size={15} />
            Ver Actividades
          </button>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
         ══════════════════════════════════════════════════════════════════ -->
    <div class="px-6 pt-6">
      {#if curso.es_titular_curso}
        <!-- ── VISTA TITULAR: layout asimétrico 65 / 35 ───────────────── -->
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-5 items-start">
          <!-- ╔═══════════════════════════════════╗
               ║  COLUMNA IZQUIERDA: Mi Grupo 65%  ║
               ╚═══════════════════════════════════╝ -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex flex-col">
            <!-- Card header -->
            <div class="px-5 pt-5 pb-4 border-b border-gray-100">
              <div class="flex items-center gap-2 mb-1">
                <div
                  class="flex items-center justify-center w-8 h-8 rounded-lg"
                  style="background:#EEF4FB;"
                >
                  <GraduationCap size={16} style="color:#2A66AC;" />
                </div>
                <h2 class="text-base font-semibold text-gray-900">Mi Grupo</h2>
              </div>
              <p class="text-xs text-gray-400 ml-10">Estudiantes inscritos en tus componentes</p>
            </div>

            <div class="p-5 space-y-4 flex-1">
              <!-- Pill tabs -->
              {#if mis_componentes.length > 1}
                <div class="flex gap-2 flex-wrap">
                  {#each mis_componentes as comp}
                    <button
                      onclick={() => (componenteActivo = comp.id_componente)}
                      class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-medium transition-all"
                      style={componenteActivo === comp.id_componente
                        ? 'background:#2A66AC; color:#fff;'
                        : 'background:#F1F5F9; color:#64748B;'}
                    >
                      {comp.tipo_componente}
                      {#if comp.es_titular}
                        <Crown
                          size={11}
                          style="color:{componenteActivo === comp.id_componente
                            ? '#FCD68A'
                            : '#F0AD4E'};"
                        />
                      {/if}
                      <span
                        class="inline-flex items-center justify-center h-4 min-w-4 rounded-full px-1 text-[10px] font-bold"
                        style={componenteActivo === comp.id_componente
                          ? 'background:rgba(255,255,255,0.25); color:#fff;'
                          : 'background:#CBD5E1; color:#475569;'}
                      >
                        {comp.total_estudiantes}
                      </span>
                    </button>
                  {/each}
                </div>
              {:else if mis_componentes.length === 1}
                <div class="flex items-center gap-2">
                  <span
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-semibold text-white"
                    style="background:#2A66AC;"
                  >
                    {mis_componentes[0].tipo_componente}
                    {#if mis_componentes[0].es_titular}
                      <Crown size={11} style="color:#FCD68A;" />
                    {/if}
                  </span>
                </div>
              {/if}

              <!-- Tabla de estudiantes -->
              {#if estudiantesActivos.length === 0}
                <div
                  class="flex flex-col items-center justify-center py-16 text-center bg-gray-50 rounded-xl border-2 border-dashed border-gray-200"
                >
                  <div
                    class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-3"
                  >
                    <GraduationCap size={26} class="text-gray-300" />
                  </div>
                  <p class="text-sm font-medium text-gray-500">Sin estudiantes inscritos</p>
                  <p class="text-xs text-gray-400 mt-1">
                    Los estudiantes aparecerán aquí cuando se inscriban.
                  </p>
                </div>
              {:else}
                <div class="rounded-xl border border-gray-200 overflow-hidden">
                  <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                      <tr>
                        <th
                          class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider"
                          >#</th
                        >
                        <th
                          class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider"
                          >Estudiante</th
                        >
                        <th
                          class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden sm:table-cell"
                          >Usuario</th
                        >
                        <th
                          class="px-4 py-2.5 text-right text-[11px] font-semibold text-gray-500 uppercase tracking-wider"
                          >Nota</th
                        >
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                      {#each estudiantesActivos as item, i}
                        <tr class="hover:bg-blue-50/30 transition-colors group">
                          <td class="px-4 py-3 text-gray-400 tabular-nums text-xs">{i + 1}</td>
                          <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                              <div
                                class="flex items-center justify-center h-8 w-8 rounded-full text-xs font-bold text-white shrink-0"
                                style="background:#2A66AC;"
                              >
                                {item.estudiante.nombre.charAt(0).toUpperCase()}
                              </div>
                              <span class="font-medium text-gray-900 text-sm"
                                >{item.estudiante.nombre}</span
                              >
                            </div>
                          </td>
                          <td class="px-4 py-3 hidden sm:table-cell">
                            <span class="font-mono text-xs text-gray-400"
                              >{item.estudiante.username}</span
                            >
                          </td>
                          <td class="px-4 py-3 text-right">
                            {#if item.nota_componente !== null}
                              <span
                                class="inline-flex items-center justify-center h-7 min-w-[2.5rem] rounded-lg text-xs font-bold {item.nota_componente >=
                                4
                                  ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                                  : 'bg-red-50 text-red-600 ring-1 ring-red-200'}"
                              >
                                {item.nota_componente}
                              </span>
                            {:else}
                              <span class="text-gray-300">—</span>
                            {/if}
                          </td>
                        </tr>
                      {/each}
                    </tbody>
                  </table>
                </div>
                <p class="text-xs text-gray-400 text-right">
                  {estudiantesActivos.length} estudiante{estudiantesActivos.length !== 1 ? 's' : ''}
                </p>
              {/if}
            </div>
          </div>

          <!-- ╔══════════════════════════════════════╗
               ║  COLUMNA DERECHA: Context Panel 35%  ║
               ╚══════════════════════════════════════╝ -->
          <div class="flex flex-col gap-4">
            <!-- ── Tarjeta 1: Equipo Docente ────────────────────────────── -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
              <div
                class="flex items-center justify-between px-5 pt-4 pb-3 border-b border-gray-100"
              >
                <div class="flex items-center gap-2">
                  <div
                    class="flex items-center justify-center w-7 h-7 rounded-lg"
                    style="background:#EEF4FB;"
                  >
                    <UsersRound size={14} style="color:#2A66AC;" />
                  </div>
                  <h3 class="text-sm font-semibold text-gray-900">Equipo Docente</h3>
                </div>
                <button
                  onclick={() => (showEquipoSlideOver = true)}
                  class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-lg border transition"
                  style="border-color:#2A66AC; color:#2A66AC;"
                  onmouseenter={(e) =>
                    ((e.currentTarget as HTMLElement).style.background = '#EEF4FB')}
                  onmouseleave={(e) =>
                    ((e.currentTarget as HTMLElement).style.background = 'transparent')}
                >
                  <Settings size={11} />
                  Gestionar
                </button>
              </div>

              <div class="p-4 space-y-2">
                {#if todos_componentes.length === 0}
                  <p class="text-xs text-gray-400 text-center py-4">Sin docentes asignados.</p>
                {:else}
                  {#each todos_componentes as comp}
                    {#each comp.docentes as doc}
                      <div
                        class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 transition-colors group"
                      >
                        <!-- Avatar -->
                        <div
                          class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold text-white shrink-0 flex-none"
                          style="background:#2A66AC;"
                        >
                          {initials(doc.nombre)}
                        </div>
                        <div class="min-w-0 flex-1">
                          <p class="text-sm font-medium text-gray-900 truncate">{doc.nombre}</p>
                          <p class="text-[11px] text-gray-400 flex items-center gap-1">
                            {comp.tipo_componente}
                            {#if doc.es_titular}
                              <Crown size={10} style="color:#F0AD4E;" />
                            {/if}
                          </p>
                        </div>
                        {#if comp.docentes.length > 1}
                          <button
                            onclick={() => {
                              componentePermisoId = comp.id_componente;
                              componentePermisoTipo = comp.tipo_componente;
                              showComponentePermisos = true;
                            }}
                            class="opacity-0 group-hover:opacity-100 p-1.5 rounded-lg transition text-gray-400 hover:text-[#2A66AC] hover:bg-blue-50"
                            title="Permisos del componente"
                          >
                            <Shield size={13} />
                          </button>
                        {/if}
                      </div>
                    {/each}
                  {/each}
                {/if}
              </div>
            </div>

            <!-- ── Tarjeta 2: Detalles del Curso ────────────────────────── -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
              <div class="flex items-center gap-2 px-5 pt-4 pb-3 border-b border-gray-100">
                <div
                  class="flex items-center justify-center w-7 h-7 rounded-lg"
                  style="background:#EEF4FB;"
                >
                  <BookOpen size={14} style="color:#2A66AC;" />
                </div>
                <h3 class="text-sm font-semibold text-gray-900">Detalles del Curso</h3>
              </div>

              <dl class="px-5 py-4 space-y-3 text-sm">
                {#each [{ label: 'Asignatura', value: curso.asignatura.nombre }, { label: 'Código', value: curso.asignatura.cod_asignatura }, { label: 'Carrera', value: curso.plan.carrera }, { label: 'Plan', value: curso.plan.nombre }, { label: 'Año', value: String(curso.agno_real) }, { label: 'Semestre', value: curso.semestre_real === 1 ? '1er Semestre' : '2do Semestre' }, { label: 'Inicio', value: formatDate(curso.fecha_inicio) }, { label: 'Término', value: formatDate(curso.fecha_fin) }] as row}
                  {#if row.value && row.value !== 'undefined' && row.value !== '—' && row.value !== 'NaN'}
                    <div class="flex items-start justify-between gap-3">
                      <dt class="text-xs text-gray-400 shrink-0 mt-0.5">{row.label}</dt>
                      <dd class="text-xs font-medium text-gray-800 text-right">{row.value}</dd>
                    </div>
                  {/if}
                {/each}
                {#if curso.asignatura.descripcion}
                  <div class="pt-2 border-t border-gray-100">
                    <dt class="text-xs text-gray-400 mb-1">Descripción</dt>
                    <dd class="text-xs text-gray-600 leading-relaxed line-clamp-4">
                      {curso.asignatura.descripcion}
                    </dd>
                  </div>
                {/if}
              </dl>
            </div>
          </div>
          <!-- fin grid -->
        </div>
      {:else}
        <!-- ══════════════════════════════════════════════════════════════════
             VISTA COLEGIADO
             ══════════════════════════════════════════════════════════════════ -->

        <!-- Info compacta del curso -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {#each [{ label: 'Asignatura', value: curso.asignatura.nombre }, { label: 'Plan', value: curso.plan.nombre }, { label: 'Carrera', value: curso.plan.carrera }, { label: 'Período', value: `${curso.agno_real} — ${curso.semestre_real === 1 ? '1er Sem.' : '2do Sem.'}` }] as item}
              <div>
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">
                  {item.label}
                </p>
                <p class="text-sm font-semibold text-gray-900">{item.value}</p>
              </div>
            {/each}
          </div>
        </div>

        <!-- Mi Componente (colegiado) -->
        {#if mis_componentes.length > 0}
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 pt-5 pb-4 border-b border-gray-100">
              <div class="flex items-center gap-2 mb-1">
                <div
                  class="flex items-center justify-center w-8 h-8 rounded-lg"
                  style="background:#EEF4FB;"
                >
                  <GraduationCap size={16} style="color:#2A66AC;" />
                </div>
                <h2 class="text-base font-semibold text-gray-900">Mi Componente</h2>
              </div>
              <p class="text-xs text-gray-400 ml-10">Tu grupo de estudiantes</p>
            </div>

            <div class="p-5 space-y-4">
              <!-- Pill tabs -->
              {#if mis_componentes.length > 1}
                <div class="flex gap-2 flex-wrap">
                  {#each mis_componentes as comp}
                    <button
                      onclick={() => (componenteActivo = comp.id_componente)}
                      class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-medium transition-all"
                      style={componenteActivo === comp.id_componente
                        ? 'background:#2A66AC; color:#fff;'
                        : 'background:#F1F5F9; color:#64748B;'}
                    >
                      {comp.tipo_componente}
                      {#if comp.es_titular}
                        <Crown
                          size={11}
                          style="color:{componenteActivo === comp.id_componente
                            ? '#FCD68A'
                            : '#F0AD4E'};"
                        />
                      {/if}
                    </button>
                  {/each}
                </div>
              {:else}
                <span
                  class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-semibold text-white"
                  style="background:#2A66AC;"
                >
                  {mis_componentes[0].tipo_componente}
                  {#if mis_componentes[0].es_titular}
                    <Crown size={11} style="color:#FCD68A;" />
                  {/if}
                </span>
              {/if}

              <!-- KPI Estudiantes -->
              <div
                class="flex items-center justify-center gap-4 p-5 rounded-xl"
                style="background:#EEF4FB;"
              >
                <div
                  class="flex items-center justify-center h-12 w-12 rounded-full bg-white shadow-sm"
                >
                  <Users size={20} style="color:#2A66AC;" />
                </div>
                <div>
                  <p class="text-3xl font-bold" style="color:#2A66AC;">
                    {estudiantesActivos.length}
                  </p>
                  <p class="text-xs font-medium text-gray-400">Estudiantes</p>
                </div>
              </div>

              <!-- Tabla -->
              {#if estudiantesActivos.length === 0}
                <div
                  class="flex flex-col items-center justify-center py-16 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200"
                >
                  <div
                    class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-3"
                  >
                    <Users size={26} class="text-gray-300" />
                  </div>
                  <p class="text-sm font-medium text-gray-500">Sin estudiantes inscritos aún</p>
                </div>
              {:else}
                <div class="rounded-xl border border-gray-200 overflow-hidden">
                  <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                      <tr>
                        <th
                          class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider"
                          >#</th
                        >
                        <th
                          class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider"
                          >Estudiante</th
                        >
                        <th
                          class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider hidden sm:table-cell"
                          >Usuario</th
                        >
                        <th
                          class="px-4 py-2.5 text-right text-[11px] font-semibold text-gray-500 uppercase tracking-wider"
                          >Nota</th
                        >
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                      {#each estudiantesActivos as item, i}
                        <tr class="hover:bg-blue-50/30 transition-colors">
                          <td class="px-4 py-3 text-gray-400 tabular-nums text-xs">{i + 1}</td>
                          <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                              <div
                                class="flex items-center justify-center h-8 w-8 rounded-full text-xs font-bold text-white shrink-0"
                                style="background:#2A66AC;"
                              >
                                {item.estudiante.nombre.charAt(0).toUpperCase()}
                              </div>
                              <span class="font-medium text-gray-900">{item.estudiante.nombre}</span
                              >
                            </div>
                          </td>
                          <td class="px-4 py-3 hidden sm:table-cell">
                            <span class="font-mono text-xs text-gray-400"
                              >{item.estudiante.username}</span
                            >
                          </td>
                          <td class="px-4 py-3 text-right">
                            {#if item.nota_componente !== null}
                              <span
                                class="inline-flex items-center justify-center h-7 min-w-[2.5rem] rounded-lg text-xs font-bold {item.nota_componente >=
                                4
                                  ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                                  : 'bg-red-50 text-red-600 ring-1 ring-red-200'}"
                              >
                                {item.nota_componente}
                              </span>
                            {:else}
                              <span class="text-gray-300">—</span>
                            {/if}
                          </td>
                        </tr>
                      {/each}
                    </tbody>
                  </table>
                </div>
                <p class="text-xs text-gray-400 text-right">
                  {estudiantesActivos.length} estudiante{estudiantesActivos.length !== 1 ? 's' : ''}
                </p>
              {/if}
            </div>
          </div>
        {:else}
          <div
            class="bg-white rounded-xl border-2 border-dashed border-gray-200 flex flex-col items-center justify-center py-14 text-gray-400"
          >
            <GraduationCap size={32} class="text-gray-300 mb-2" />
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
</DocenteLayout>
