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
    Search,
    Settings,
    Calendar,
    GraduationCap,
    ClipboardList,
    MessageSquare,
    BookOpenCheck,
    LayoutGrid,
    Users,
    Building2,
    ScrollText,
  } from 'lucide-svelte';
  import type { SidebarCourse } from '@/types';
  import { hasPermission } from '@/services/permissionValidator';

  // ── Shared props ──────────────────────────────────────────────
  let authRoles = $derived(($page.props.auth?.roles as string[]) || []);
  let docenteCourses = $derived(($page.props.auth?.docente_courses as SidebarCourse[]) || []);
  let estudianteCourses = $derived(($page.props.auth?.estudiante_courses as SidebarCourse[]) || []);
  let ayudanteCourses = $derived(($page.props.auth?.ayudante_courses as SidebarCourse[]) || []);

  // ── Role flags ────────────────────────────────────────────────
  let isSuperAdmin = $derived(($page.props.auth?.is_super_admin as boolean) || false);
  let isDocente = $derived(authRoles.some((r) => ['Docente', 'docente'].includes(r)));
  let isEstudiante = $derived(authRoles.some((r) => ['Estudiante', 'estudiante'].includes(r)));
  let isAyudante = $derived(authRoles.some((r) => ['Ayudante', 'ayudante'].includes(r)));
  let isAdmin = $derived(
    isSuperAdmin ||
      authRoles.some((r) => ['Administrador', 'SuperAdmin', 'Super Admin'].includes(r)) ||
      authRoles.length === 0,
  );

  // ── Período académico ─────────────────────────────────────────
  const now = new Date();
  const semestre = now.getMonth() < 6 ? 1 : 2;
  const periodoActual = `${now.getFullYear()} – Semestre ${semestre}`;

  // ── Search & tree state ───────────────────────────────────────
  let searchQuery = $state('');
  let expandedCursos: Record<number, boolean> = $state({});

  function toggleCurso(id: number) {
    expandedCursos[id] = !expandedCursos[id];
  }
  // ── Active route detection ─────────────────────────────────
  const currentPath = $derived.by(() => {
    if (!$page.url) return '';
    if (typeof $page.url === 'string') return $page.url;
    if (typeof $page.url === 'object' && 'pathname' in $page.url) {
      return $page.url.pathname;
    }
    return '';
  });

  function isActive(href: string): boolean {
    if (!currentPath) return false;
    return currentPath === href || currentPath.startsWith(href + '/');
  }
  let filteredDocente = $derived(
    docenteCourses.filter(
      (c) => !searchQuery || c.nombre.toLowerCase().includes(searchQuery.toLowerCase()),
    ),
  );
  let filteredEstudiante = $derived(
    estudianteCourses.filter(
      (c) => !searchQuery || c.nombre.toLowerCase().includes(searchQuery.toLowerCase()),
    ),
  );
  let filteredAyudante = $derived(
    ayudanteCourses.filter(
      (c) => !searchQuery || c.nombre.toLowerCase().includes(searchQuery.toLowerCase()),
    ),
  );

  // ── Admin menu items ──────────────────────────────────────
  const adminMenuItems: Array<{ href: string; icon: any; label: string }> = [
    { href: '/admin/usuarios', icon: Users, label: 'Usuarios' },
    { href: '/admin/facultades', icon: Building2, label: 'Facultades' },
    { href: '/admin/departamentos', icon: Folder, label: 'Departamentos' },
    { href: '/admin/carreras', icon: GraduationCap, label: 'Carreras' },
    { href: '/admin/asignaturas', icon: BookOpen, label: 'Asignaturas' },
    { href: '/admin/planes', icon: ClipboardList, label: 'Planes de Estudio' },
    { href: '/admin/cursos', icon: BookOpen, label: 'Cursos Ofertados' },
    { href: '/admin/inscripciones_cursos', icon: Users, label: 'Inscripciones' },
    { href: '/admin/syllabus', icon: ScrollText, label: 'Syllabus' },
  ];
</script>

<div class="h-full flex flex-col bg-white text-slate-700">
  <!-- ── Header: Logo ─────────────────────────────────────── -->
  <div class="border-b border-slate-100 py-4 px-6">
    <div class="flex items-center gap-3">
      <AppLogo clx|ass="h-8 w-8" />
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
  <div class="flex-1 overflow-y-auto py-6">
    <!-- Período Académico -->
    <div class="px-6 mb-6">
      <span class="text-[11px] font-extrabold tracking-widest uppercase text-slate-400 block mb-2"
        >Período Académico</span
      >
      <div
        class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-semibold text-slate-600"
      >
        <ChevronDown size={14} class="text-slate-400" />
        {periodoActual}
      </div>
    </div>

    <!-- Búsqueda -->
    {#if isDocente || isEstudiante || isAyudante}
      <div class="px-6 mb-6">
        <div class="relative">
          <Search size={16} class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            placeholder="Buscar cursos..."
            bind:value={searchQuery}
            class="w-full pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-lg text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all font-medium"
          />
        </div>
      </div>
    {/if}

    <!-- ══ DOCENTE ══════════════════════════════════════════ -->
    {#if isDocente}
      <div class="px-6 mb-2">
        <p class="text-[11px] font-extrabold tracking-widest uppercase text-slate-400 mb-2">
          Árbol de Gestión
        </p>
      </div>

      <!-- Root link -->
      <div class="px-4 mb-2">
        <Link
          href="/docente/cursos"
          class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[15px] font-bold text-slate-800 hover:bg-slate-50 hover:text-indigo-600 transition-all group"
        >
          <Folder size={20} class="text-slate-400 group-hover:text-indigo-500 transition-colors" />
          Mis Cursos Asignados
        </Link>
      </div>

      <!-- Course list -->
      <div class="px-4 flex flex-col gap-1">
        {#if filteredDocente.length === 0}
          <p class="px-4 py-2 text-sm text-slate-400 italic font-medium">
            {searchQuery ? 'Sin resultados' : 'Sin cursos asignados'}
          </p>
        {:else}
          {#each filteredDocente as curso (curso.id_curso)}
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
                      href="/docente/cursos/{curso.id_curso}/programa"
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
                  {:else}
                    <button
                      class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-slate-400 cursor-not-allowed w-full text-left bg-slate-50/50"
                      disabled
                    >
                      <div class="flex items-center gap-2">
                        <BookOpenCheck size={16} class="shrink-0 opacity-50" />
                        <span>Programa</span>
                      </div>
                      <span
                        class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-400"
                        >Pendiente</span
                      >
                    </button>
                  {/if}
                  <!-- Actividades -->
                  <Link
                    href="/docente/cursos/{curso.id_curso}/actividades"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-white hover:shadow-sm hover:text-indigo-600 transition-all"
                  >
                    <Calendar size={16} class="shrink-0 text-slate-400" /> Actividades
                  </Link>
                </div>
              {/if}
            </div>
          {/each}
        {/if}

        <div class="h-px bg-slate-100 my-4 mx-4"></div>

        <!-- Footer style links -->
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
          href="/docente/inscripciones"
          class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-[14px] font-semibold text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition-all group"
        >
          <Users size={18} class="text-slate-400 group-hover:text-indigo-500 transition-colors" />
          Inscripciones
        </Link>
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

    <!-- ══ ESTUDIANTE ═══════════════════════════════════════ -->
    {#if isEstudiante && !isDocente}
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
          <p class="px-4 py-2 text-sm text-slate-400 italic font-medium">
            {searchQuery ? 'Sin resultados' : 'Sin cursos inscritos'}
          </p>
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

        <div class="h-px bg-slate-100 my-4 mx-4"></div>

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
    {#if isAyudante && !isDocente}
      <div class="px-6 mb-2">
        <p class="text-[11px] font-extrabold tracking-widest uppercase text-slate-400 mb-2">
          Ayudantías
        </p>
      </div>
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
            {searchQuery ? 'Sin resultados' : 'Sin ayudantías asignadas'}
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
                  {:else if hasPermission(curso.userPermissions, 'cursos/programas:crear')}
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
                  {:else}
                    <button
                      class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-slate-400 cursor-not-allowed w-full text-left bg-slate-50/50"
                      disabled
                    >
                      <div class="flex items-center gap-2">
                        <BookOpenCheck size={16} class="shrink-0 opacity-50" />
                        <span>Programa</span>
                      </div>
                      <span
                        class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-400"
                        >Pendiente</span
                      >
                    </button>
                  {/if}
                </div>
              {/if}
            </div>
          {/each}
        {/if}
        <div class="h-px bg-slate-100 my-4 mx-4"></div>
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
    {#if isAdmin}
      <div class="px-6 mb-2">
        <p class="text-[11px] font-extrabold tracking-widest uppercase text-slate-400 mb-2">
          Administración
        </p>
      </div>
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
          {#if isActive('/dashboard')}
            <div class="ml-auto h-2 w-2 rounded-full bg-indigo-500 shrink-0"></div>
          {/if}
        </Link>
      </div>
      <div class="px-4 flex flex-col gap-1">
        {#each adminMenuItems as item}
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
            {#if isActive(item.href)}
              <div class="h-2 w-2 rounded-full bg-indigo-500 shrink-0"></div>
            {/if}
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
