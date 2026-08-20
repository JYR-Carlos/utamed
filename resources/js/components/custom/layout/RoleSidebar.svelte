<script lang="ts">
  import { page, Link } from '@inertiajs/svelte';
  import NavUser from '@/components/custom/navigation/NavUser.svelte';
  import AppLogo from '@/components/custom/layout/AppLogo.svelte';
  import {
    BookOpen,
    ChevronDown,
    ChevronRight,
    Folder,
    FolderOpen,
    Settings,
    Calendar,
    GraduationCap,
    ClipboardList,
    BookOpenCheck,
    LayoutGrid,
    Users,
    UserCheck,
    Building2,
    Landmark,
    CalendarRange,
    ScrollText,
    BarChart2,
    Search,
  } from 'lucide-svelte';
  import type { SidebarCourse } from '@/types';
  import { hasPermission } from '@/services/permissionValidator';
  import { usePermissions } from '@/lib/composables/usePermissions';

  const { can } = usePermissions();

  // ── Shared props ──────────────────────────────────────────────
  let authRoles = $derived(($page.props.auth?.roles as string[]) || []);
  let docenteCourses = $derived(($page.props.auth?.docente_courses as SidebarCourse[]) || []);
  let estudianteCourses = $derived(($page.props.auth?.estudiante_courses as SidebarCourse[]) || []);
  let ayudanteCourses = $derived(($page.props.auth?.ayudante_courses as SidebarCourse[]) || []);

  // ── Role flags ────────────────────────────────────────────────
  let isSuperAdmin = $derived(($page.props.auth?.is_super_admin as boolean) || false);
  // Permission-based flags — independent of role name strings
  let userDocente = $derived(($page.props.auth?.docente as any) ?? null);
  let userEstudiante = $derived(($page.props.auth?.estudiante as any) ?? null);
  let isDocente = $derived(userDocente !== null && can('cursos:ver'));
  let isEstudiante = $derived(userEstudiante !== null);
  let isAyudante = $derived(ayudanteCourses.length > 0);
  let isJefeCarrera = $derived(authRoles.includes('Jefe de Carrera'));
  let isAdmin = $derived(
    isSuperAdmin ||
      can('facultades:ver') ||
      can('departamentos:ver') ||
      can('usuarios/permisos/roles:ver'),
  );

  // ── Active route detection ─────────────────────────────────
  const currentPath = $derived($page.url ?? '');

  function isActive(href: string): boolean {
    if (!currentPath) return false;
    return currentPath === href || currentPath.startsWith(href + '/');
  }
  let filteredDocente = $derived(docenteCourses);
  let filteredEstudiante = $derived(estudianteCourses);
  let filteredAyudante = $derived(ayudanteCourses);

  // ── Tree state ────────────────────────────────────────────────
  let expandedCursos: Record<number, boolean> = $state({});

  function toggleCurso(id: number) {
    expandedCursos[id] = !expandedCursos[id];
  }

  // Agrupa por año, ordenado descendente (más reciente primero)
  let docenteByYear = $derived(
    filteredDocente.reduce(
      (acc, curso) => {
        const year = curso.agno_real;
        const sem = curso.semestre_real;
        if (year == null || sem == null) return acc;
        if (!acc[year]) acc[year] = {};
        if (!acc[year][sem]) acc[year][sem] = [];
        acc[year][sem].push(curso);
        return acc;
      },
      {} as Record<number, Record<number, SidebarCourse[]>>,
    ),
  );

  let sortedYears = $derived(
    Object.keys(docenteByYear)
      .map(Number)
      .filter((n) => !isNaN(n))
      .sort((a, b) => b - a),
  );

  let historicalOpen = $state(false);

  // Período vigente = el más reciente con cursos asignados
  let currentPeriod = $derived.by(() => {
    const year = sortedYears[0];
    if (year == null) return null;
    const sems = Object.keys(docenteByYear[year])
      .map(Number)
      .sort((a, b) => b - a);
    if (sems.length === 0) return null;
    return { year, sem: sems[0] };
  });

  let currentCourses = $derived(
    currentPeriod ? docenteByYear[currentPeriod.year][currentPeriod.sem] : [],
  );

  // Resto de años/semestres (histórico), respetando la búsqueda activa
  let historicalYears = $derived(
    sortedYears
      .map((year) => ({
        year,
        sems: Object.keys(docenteByYear[year])
          .map(Number)
          .sort((a, b) => b - a)
          .filter(
            (sem) => !(currentPeriod && year === currentPeriod.year && sem === currentPeriod.sem),
          ),
      }))
      .filter((y) => y.sems.length > 0),
  );

  let historicalCount = $derived(
    historicalYears.reduce(
      (acc, y) => acc + y.sems.reduce((a, sem) => a + docenteByYear[y.year][sem].length, 0),
      0,
    ),
  );

  // Historial aplanado a un nivel: "Año · Semestre" en vez de año > semestre > curso.
  let historicalGroups = $derived(
    historicalYears.flatMap(({ year, sems }) =>
      sems.map((sem) => ({
        key: `${year}-${sem}`,
        label: `Año ${year} · Semestre ${sem}`,
        courses: docenteByYear[year][sem],
      })),
    ),
  );

  // ── Buscador de cursos (reemplaza el selector de período decorativo) ──
  let courseSearch = $state('');

  let filteredCurrentCourses = $derived.by(() => {
    const q = courseSearch.trim().toLowerCase();
    if (!q) return currentCourses;
    return currentCourses.filter((c) => c.nombre.toLowerCase().includes(q));
  });

  let filteredHistoricalGroups = $derived.by(() => {
    const q = courseSearch.trim().toLowerCase();
    if (!q) return historicalGroups;
    return historicalGroups
      .map((g) => ({ ...g, courses: g.courses.filter((c) => c.nombre.toLowerCase().includes(q)) }))
      .filter((g) => g.courses.length > 0);
  });

  // Si la búsqueda encuentra algo en el histórico, lo despliega solo.
  $effect(() => {
    if (courseSearch.trim() && filteredHistoricalGroups.length > 0) {
      historicalOpen = true;
    }
  });

  // ── Admin menu items ──────────────────────────────────────
  /**
   * Un icono distinto por destino.
   *
   * Tres pares compartían el mismo: Administración y Facultades (edificio),
   * Asignaturas y Cursos Ofertados (libro), Usuarios e Inscripciones
   * (personas). Con el icono repetido, la barra sólo se podía leer palabra
   * por palabra.
   */
  const adminMenuItems: Array<{ href: string; icon: any; label: string }> = [
    { href: '/admin/usuarios', icon: Users, label: 'Usuarios' },
    { href: '/admin/facultades', icon: Landmark, label: 'Facultades' },
    { href: '/admin/departamentos', icon: Building2, label: 'Departamentos' },
    { href: '/admin/carreras', icon: GraduationCap, label: 'Carreras' },
    { href: '/admin/asignaturas', icon: BookOpen, label: 'Asignaturas' },
    { href: '/admin/planes', icon: ClipboardList, label: 'Planes de Estudio' },
    { href: '/admin/cursos', icon: CalendarRange, label: 'Cursos Ofertados' },
    { href: '/admin/inscripciones_cursos', icon: UserCheck, label: 'Inscripciones' },
    { href: '/admin/syllabus', icon: ScrollText, label: 'Syllabus' },
  ];

  // ── Pestañas de rol ────────────────────────────────────────
  // Un usuario puede tener varios roles; sólo uno se muestra a la vez,
  // elegido con una pestaña en vez de un acordeón que mueve el resto del menú.
  type SectionId = 'docente' | 'jefe' | 'estudiante' | 'ayudante' | 'admin';

  const sectionMeta: Record<SectionId, { label: string; icon: any }> = {
    docente: { label: 'Docente', icon: GraduationCap },
    jefe: { label: 'Jefe de Carrera', icon: ClipboardList },
    estudiante: { label: 'Estudiante', icon: GraduationCap },
    ayudante: { label: 'Ayudantía', icon: BookOpen },
    admin: { label: 'Administración', icon: Building2 },
  };

  // Secciones disponibles según los roles del usuario, en orden de prioridad.
  let availableSections = $derived(
    (
      [
        isDocente && 'docente',
        isJefeCarrera && 'jefe',
        isEstudiante && !isDocente && 'estudiante',
        isAyudante && !isDocente && 'ayudante',
        isAdmin && 'admin',
      ] as Array<SectionId | false>
    ).filter((s): s is SectionId => s !== false),
  );

  // Sección que corresponde a la URL actual (para abrirla automáticamente).
  let urlSection = $derived.by<SectionId | null>(() => {
    const p = currentPath;
    if (p.startsWith('/docente/jefe-carrera')) return isJefeCarrera ? 'jefe' : null;
    if (p.startsWith('/docente')) return isDocente ? 'docente' : null;
    if (p.startsWith('/estudiante'))
      return availableSections.includes('estudiante') ? 'estudiante' : null;
    if (p.startsWith('/ayudante'))
      return availableSections.includes('ayudante') ? 'ayudante' : null;
    if (p.startsWith('/admin') || p === '/dashboard') return isAdmin ? 'admin' : null;
    return null;
  });

  let openSection = $state<SectionId | null>(null);

  // Sigue la URL al navegar; si la ruta no mapea a un rol, abre la primera disponible.
  $effect(() => {
    if (urlSection) {
      openSection = urlSection;
    } else if (openSection === null && availableSections.length > 0) {
      openSection = availableSections[0];
    }
  });
</script>

<div class="h-full flex flex-col bg-white text-slate-700">
  <!-- ── Header: Logo ─────────────────────────────────────── -->
  <div class="border-b border-slate-100 py-4 px-6">
    <div class="flex items-center gap-3">
      <AppLogo />
      <div class="flex flex-col">
        <span class="font-extrabold text-slate-900 text-lg leading-none tracking-tight">UTAMED</span
        >
        <span class="text-[10px] text-slate-500 font-bold tracking-widest uppercase mt-1"
          >Sistema de Gestión</span
        >
      </div>
    </div>
  </div>

  <!-- ── Content ──────────────────────────────────────────── -->
  <div class="flex-1 overflow-y-scroll py-6">
    <!-- Selector de rol: sólo si el usuario tiene más de uno. Antes cada
         rol era un acordeón que empujaba el resto del menú hacia abajo al
         abrirse; con pestañas, cambiar de rol no mueve nada más. -->
    {#if availableSections.length > 1}
      <div class="px-4 mb-4">
        <div class="flex items-center gap-0.5 p-1 bg-slate-100 rounded-xl">
          {#each availableSections as id (id)}
            {@const meta = sectionMeta[id]}
            {@const Icon = meta.icon}
            <button
              onclick={() => (openSection = id)}
              class="flex-1 flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg text-[11px] font-bold tracking-wide transition-all {openSection ===
              id
                ? 'bg-white text-indigo-600 shadow-sm'
                : 'text-slate-500 hover:text-slate-700'}"
            >
              <Icon size={14} class="shrink-0" />
              {meta.label}
            </button>
          {/each}
        </div>
      </div>
    {/if}

    <!-- Tarjeta de curso reutilizada para período actual e histórico: antes
         eran dos componentes distintos (card siempre visible vs. fila que
         había que expandir para ver las mismas acciones). -->
    {#snippet courseRow(curso: SidebarCourse, current: boolean)}
      <div
        class="rounded-xl border px-3 py-2.5 transition-all {current
          ? 'bg-white border-slate-100 hover:border-slate-200 hover:shadow-sm'
          : 'bg-slate-50 border-transparent'}"
      >
        <div class="flex items-center gap-2 mb-2">
          <Folder
            size={current ? 18 : 16}
            class="{current ? 'text-indigo-400' : 'text-slate-400'} shrink-0"
          />
          <span
            class="flex-1 truncate text-[13.5px] font-semibold text-slate-700 border-b border-dotted border-slate-300"
            title={curso.nombre}>{curso.nombre}</span
          >
        </div>
        <div class="flex items-center gap-1.5 flex-wrap">
          {#if curso.tiene_programa}
            <Link
              href="/docente/cursos/{curso.id_curso}/programa"
              class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-all"
            >
              <BookOpenCheck size={14} class="shrink-0" /> Programa
            </Link>
          {/if}
          <Link
            href="/docente/cursos/{curso.id_curso}/actividades"
            class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-600 bg-slate-50 hover:bg-indigo-50 hover:text-indigo-600 transition-all"
          >
            <Calendar size={14} class="shrink-0" /> Actividades
          </Link>
        </div>
      </div>
    {/snippet}

    <!-- ══ DOCENTE ══════════════════════════════════════════ -->
    {#if isDocente && openSection === 'docente'}
      <!-- Header style links -->
      <Link
        href="/docente/dashboard"
        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition-all group"
      >
        <LayoutGrid
          size={18}
          class="text-slate-400 group-hover:text-indigo-500 transition-colors"
        />
        Dashboard
      </Link>
      <Link
        href="/docente/calendario"
        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold transition-all group {isActive(
          '/docente/calendario',
        )
          ? 'bg-indigo-50 text-indigo-600'
          : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600'}"
      >
        <Calendar
          size={18}
          class="{isActive('/docente/calendario')
            ? 'text-indigo-500'
            : 'text-slate-400 group-hover:text-indigo-500'} transition-colors"
        />
        Calendario
      </Link>
      <!-- La mensajería no está en el menú: los dos niveles se entran por su
           contexto. La del curso, desde el curso; la de agenda, desde la
           actividad. Un enlace global obligaría a reelegir lo que ya elegiste. -->

      <div class="h-px bg-slate-100 my-4 mx-4"></div>

      <!-- Buscador de cursos: reemplaza el selector de período académico,
           que no tenía ninguna acción asociada. -->
      <div class="px-4 mb-3">
        <div
          class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg transition-all focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-50"
        >
          <Search size={14} class="text-slate-400 shrink-0" />
          <input
            type="text"
            bind:value={courseSearch}
            placeholder="Buscar curso o período…"
            class="flex-1 bg-transparent border-0 outline-none text-[13px] text-slate-700 placeholder:text-slate-400"
          />
        </div>
      </div>

      <!-- "Mis Cursos": título fijo del árbol + link aparte a la lista
           completa. Antes el chevron de colapsar y el link a /docente/cursos
           compartían la misma fila. -->
      <div class="px-6 mb-1 flex items-center justify-between gap-2">
        <span class="text-[14px] font-extrabold text-slate-800">Mis Cursos</span>
        <Link
          href="/docente/cursos"
          class="text-[11px] font-bold text-slate-400 hover:text-indigo-600 transition-colors shrink-0"
        >
          Ver todos →
        </Link>
      </div>

      {#if filteredDocente.length === 0}
        <p class="px-8 py-2 text-sm text-slate-400 italic font-medium">Sin cursos asignados</p>
      {:else}
        <!-- ── Período actual (plano, sin desplegables) ──────── -->
        {#if currentPeriod}
          <div class="px-4 mt-2 mb-2">
            <div class="flex items-center gap-2 px-2 mb-2">
              <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 shrink-0"></span>
              <span class="text-[11px] font-bold uppercase tracking-widest text-indigo-600">
                Período actual · Año {currentPeriod.year} · Semestre {currentPeriod.sem}
              </span>
            </div>

            <div class="flex flex-col gap-1.5">
              {#each filteredCurrentCourses as curso (curso.id_curso)}
                {@render courseRow(curso, true)}
              {/each}
              {#if filteredCurrentCourses.length === 0}
                <p class="px-2 py-1 text-xs text-slate-400 italic">Sin coincidencias</p>
              {/if}
            </div>
          </div>
        {/if}

        <!-- ── Períodos anteriores: un solo nivel "Año · Semestre" en un
             único <details> nativo, en vez de tres acordeones anidados que
             igual se abrían todos juntos por defecto. -->
        {#if historicalYears.length > 0}
          <div class="px-4">
            <details bind:open={historicalOpen}>
              <summary
                class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wide text-slate-500 hover:bg-slate-50 transition-all cursor-pointer list-none [&::-webkit-details-marker]:hidden"
              >
                <ChevronRight
                  size={12}
                  class="shrink-0 text-slate-400 transition-transform {historicalOpen
                    ? 'rotate-90'
                    : ''}"
                />
                Períodos anteriores
                <span
                  class="ml-auto text-[10px] font-semibold text-slate-400 bg-slate-100 rounded-full px-2 py-0.5"
                >
                  {historicalCount}
                </span>
              </summary>

              <div class="flex flex-col gap-3 mt-2 mb-1">
                {#each filteredHistoricalGroups as group (group.key)}
                  <div>
                    <p
                      class="px-2 mb-1.5 text-[10.5px] font-bold uppercase tracking-wide text-slate-400"
                    >
                      {group.label}
                    </p>
                    <div class="flex flex-col gap-1.5">
                      {#each group.courses as curso (curso.id_curso)}
                        {@render courseRow(curso, false)}
                      {/each}
                    </div>
                  </div>
                {/each}
                {#if courseSearch.trim() && filteredHistoricalGroups.length === 0}
                  <p class="px-2 py-1 text-xs text-slate-400 italic">
                    Sin coincidencias en períodos anteriores
                  </p>
                {/if}
              </div>
            </details>
          </div>
        {/if}
      {/if}

      <!-- Footer style links -->
      <div class="h-px bg-slate-100 my-4 mx-4"></div>

      <Link
        href="/settings"
        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition-all group"
      >
        <Settings size={18} class="text-slate-400 group-hover:text-indigo-500 transition-colors" />
        Configuración
      </Link>
    {/if}

    <!-- ══ JEFE DE CARRERA ══════════════════════════════════ -->
    {#if isJefeCarrera && openSection === 'jefe'}
      <Link
        href="/docente/jefe-carrera/dashboard"
        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold transition-all group {isActive(
          '/docente/jefe-carrera/dashboard',
        )
          ? 'bg-indigo-50 text-indigo-600'
          : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600'}"
      >
        <LayoutGrid
          size={18}
          class="{isActive('/docente/jefe-carrera/dashboard')
            ? 'text-indigo-500'
            : 'text-slate-400 group-hover:text-indigo-500'} transition-colors"
        />
        Dashboard Carrera
      </Link>
      <Link
        href="/docente/jefe-carrera/seguimiento"
        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold transition-all group {isActive(
          '/docente/jefe-carrera/seguimiento',
        )
          ? 'bg-indigo-50 text-indigo-600'
          : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600'}"
      >
        <ClipboardList
          size={18}
          class="{isActive('/docente/jefe-carrera/seguimiento')
            ? 'text-indigo-500'
            : 'text-slate-400 group-hover:text-indigo-500'} transition-colors"
        />
        Seguimiento
      </Link>
      <Link
        href="/docente/jefe-carrera/metricas"
        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold transition-all group {isActive(
          '/docente/jefe-carrera/metricas',
        )
          ? 'bg-indigo-50 text-indigo-600'
          : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600'}"
      >
        <BarChart2
          size={18}
          class="{isActive('/docente/jefe-carrera/metricas')
            ? 'text-indigo-500'
            : 'text-slate-400 group-hover:text-indigo-500'} transition-colors"
        />
        Métricas
      </Link>

      <div class="px-6 mb-2 mt-3">
        <p class="text-[10px] font-bold tracking-widest uppercase text-slate-300">Gestión</p>
      </div>
      <Link
        href="/docente/jefe-carrera/planes"
        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold transition-all group {isActive(
          '/docente/jefe-carrera/planes',
        )
          ? 'bg-indigo-50 text-indigo-600'
          : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600'}"
      >
        <ClipboardList
          size={18}
          class="{isActive('/docente/jefe-carrera/planes')
            ? 'text-indigo-500'
            : 'text-slate-400 group-hover:text-indigo-500'} transition-colors"
        />
        Malla / Planes
      </Link>
      <Link
        href="/docente/jefe-carrera/asignaturas"
        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold transition-all group {isActive(
          '/docente/jefe-carrera/asignaturas',
        )
          ? 'bg-indigo-50 text-indigo-600'
          : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600'}"
      >
        <BookOpen
          size={18}
          class="{isActive('/docente/jefe-carrera/asignaturas')
            ? 'text-indigo-500'
            : 'text-slate-400 group-hover:text-indigo-500'} transition-colors"
        />
        Asignaturas
      </Link>
      <Link
        href="/docente/jefe-carrera/carrera"
        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold transition-all group {isActive(
          '/docente/jefe-carrera/carrera',
        )
          ? 'bg-indigo-50 text-indigo-600'
          : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600'}"
      >
        <GraduationCap
          size={18}
          class="{isActive('/docente/jefe-carrera/carrera')
            ? 'text-indigo-500'
            : 'text-slate-400 group-hover:text-indigo-500'} transition-colors"
        />
        Mi Carrera
      </Link>
    {/if}

    <!-- ══ ESTUDIANTE ═══════════════════════════════════════ -->
    {#if isEstudiante && !isDocente && openSection === 'estudiante'}
      <!-- Header style links -->
      <Link
        href="/estudiante/dashboard"
        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition-all group"
      >
        <LayoutGrid
          size={18}
          class="text-slate-400 group-hover:text-indigo-500 transition-colors"
        />
        Dashboard
      </Link>

      <!-- La mensajería se entra desde la ficha del curso, no desde el menú:
           el hilo pertenece a un curso y elegirlo dos veces sobra. -->

      <div class="px-6 mb-2">
        <p class="text-[11px] font-extrabold tracking-widest uppercase text-slate-400 mb-2">
          Mis Cursos
        </p>
      </div>

      <div class="px-4 mb-2">
        <Link
          href="/estudiante/cursos"
          class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[15px] font-bold text-slate-800 hover:bg-slate-50 hover:text-indigo-600 transition-all group"
        >
          <GraduationCap
            size={20}
            class="text-slate-400 group-hover:text-indigo-500 transition-colors"
          />
          Mis Cursos
        </Link>
      </div>

      <div class="px-4 flex flex-col gap-1">
        {#if filteredEstudiante.length === 0}
          <p class="px-4 py-2 text-sm text-slate-400 italic font-medium">Sin cursos inscritos</p>
        {:else}
          {#each filteredEstudiante as curso (curso.id_curso)}
            <Link
              href="/estudiante/cursos/{curso.id_curso}"
              class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition-all group"
            >
              <Folder size={18} class="text-slate-400 group-hover:text-indigo-500 shrink-0" />
              <span class="flex-1 truncate">{curso.nombre}</span>
            </Link>
          {/each}
        {/if}
        <!-- Footer style links -->
        <Link
          href="/settings"
          class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition-all group"
        >
          <Settings
            size={18}
            class="text-slate-400 group-hover:text-indigo-500 transition-colors"
          />
          Configuración
        </Link>
      </div>
    {/if}

    <!-- ══ AYUDANTE ══════════════════════════════════════════ -->
    {#if isAyudante && !isDocente && openSection === 'ayudante'}
      <Link
        href="/ayudante/dashboard"
        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition-all group"
      >
        <LayoutGrid
          size={18}
          class="text-slate-400 group-hover:text-indigo-500 transition-colors"
        />
        Dashboard
      </Link>

      <!-- Igual que el docente: la mensajería se entra desde el curso. -->

      <div class="px-4 mb-2">
        <Link
          href="/ayudante/dashboard"
          class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[15px] font-bold text-slate-800 hover:bg-slate-50 hover:text-indigo-600 transition-all group"
        >
          <BookOpen
            size={20}
            class="text-slate-400 group-hover:text-indigo-500 transition-colors"
          />
          Cursos Asignados
        </Link>
      </div>
      <div class="px-4 flex flex-col gap-1">
        {#if filteredAyudante.length === 0}
          <p class="px-4 py-2 text-sm text-slate-400 italic font-medium">
            Sin ayudantías asignadas
          </p>
        {:else}
          {#each filteredAyudante as curso (curso.id_curso)}
            {@const expanded = expandedCursos[curso.id_curso]}
            <div
              class="rounded-xl overflow-hidden transition-colors {expanded
                ? 'bg-slate-50/50'
                : ''}"
            >
              <button
                onclick={() => toggleCurso(curso.id_curso)}
                class="flex items-center gap-3 w-full px-4 py-2.5 rounded-xl text-[14px] text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-all text-left group"
              >
                <span class="text-slate-400 shrink-0 group-hover:text-slate-600">
                  {#if expanded}<ChevronDown size={14} />{:else}<ChevronRight size={14} />{/if}
                </span>
                {#if expanded}
                  <FolderOpen size={18} class="text-indigo-500 shrink-0" />
                {:else}
                  <Folder
                    size={18}
                    class="text-slate-400 group-hover:text-slate-600 shrink-0 transition-colors"
                  />
                {/if}
                <span class="flex-1 truncate font-semibold">{curso.nombre}</span>
              </button>

              {#if expanded}
                <div class="pl-11 pr-2 py-1 flex flex-col gap-1 mb-2">
                  <!-- Programa -->
                  {#if curso.tiene_programa}
                    <Link
                      href="/ayudante/cursos/{curso.id_curso}/programa"
                      class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-white hover:shadow-sm hover:text-indigo-600 transition-all"
                    >
                      <div class="flex items-center gap-2">
                        <BookOpenCheck size={16} class="shrink-0 text-slate-400" />
                        <span>Programa</span>
                      </div>
                      <span
                        class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700"
                        >Ver</span
                      >
                    </Link>
                  {:else if hasPermission(curso.permisos, 'cursos/programas:crear')}
                    <Link
                      href="/ayudante/cursos/{curso.id_curso}/programa/create"
                      class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-white hover:shadow-sm hover:text-indigo-600 transition-all"
                    >
                      <div class="flex items-center gap-2">
                        <BookOpenCheck size={16} class="shrink-0 text-slate-400" />
                        <span>Programa</span>
                      </div>
                      <span
                        class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700"
                        >Crear</span
                      >
                    </Link>
                  {/if}
                </div>
              {/if}
            </div>
          {/each}
        {/if}
        <!-- Footer style links -->
        <div class="h-px bg-slate-100 my-4 mx-4"></div>

        <Link
          href="/settings"
          class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition-all group"
        >
          <Settings
            size={18}
            class="text-slate-400 group-hover:text-indigo-500 transition-colors"
          />
          Configuración
        </Link>
      </div>
    {/if}

    <!-- ══ ADMIN ═════════════════════════════════════════════ -->
    {#if isAdmin && openSection === 'admin'}
      <div class="px-4 mb-2">
        <Link
          href="/dashboard"
          class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[15px] font-bold transition-all group {isActive(
            '/dashboard',
          )
            ? 'bg-indigo-50 text-indigo-700'
            : 'text-slate-800 hover:bg-slate-50 hover:text-indigo-600'}"
        >
          <LayoutGrid
            size={20}
            class="{isActive('/dashboard')
              ? 'text-indigo-500'
              : 'text-slate-400 group-hover:text-indigo-500'} shrink-0 transition-colors"
          />
          Dashboard
        </Link>
      </div>
      <div class="px-4 flex flex-col gap-1">
        {#each adminMenuItems as item (item.href)}
          <Link
            href={item.href}
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold transition-all group {isActive(
              item.href,
            )
              ? 'bg-indigo-50 text-indigo-700'
              : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600'}"
          >
            {@const Icon = item.icon}
            <Icon
              size={18}
              class="{isActive(item.href)
                ? 'text-indigo-500'
                : 'text-slate-400 group-hover:text-indigo-500'} shrink-0 transition-colors"
            />
            <span class="flex-1 truncate">{item.label}</span>
            <!-- Sin punto a la derecha: no tenía significado declarado y se
                 confundía con un indicador de novedades. El fondo y el color
                 ya marcan cuál es la sección activa. -->
          </Link>
        {/each}
        <div class="h-px bg-slate-100 my-4 mx-4"></div>
        <Link
          href="/settings"
          class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition-all group"
        >
          <Settings
            size={18}
            class="text-slate-400 group-hover:text-indigo-500 transition-colors"
          />
          Configuración
        </Link>
      </div>
    {/if}
  </div>

  <!-- ── Footer: NavUser ──────────────────────────────────── -->
  <div class="p-6 border-t border-slate-100 bg-white z-10">
    <NavUser />
  </div>
</div>
