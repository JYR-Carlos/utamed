<script lang="ts">
  import { page, Link, router } from '@inertiajs/svelte';
  import NavUser from '@/components/custom/navigation/NavUser.svelte';
  import CourseListNav from '@/components/custom/navigation/CourseListNav.svelte';
  import AppLogo from '@/components/custom/layout/AppLogo.svelte';
  import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
  } from '@/components/ui/dropdown-menu';
  import {
    BookOpen,
    Bug,
    Calendar,
    GraduationCap,
    ClipboardList,
    House,
    Users,
    UserCheck,
    Building2,
    Landmark,
    CalendarRange,
    ScrollText,
    BarChart2,
    ChevronsUpDown,
    Check,
    ArrowRight,
  } from 'lucide-svelte';
  import type { SidebarCourse } from '@/types';
  import { usePermissions } from '@/lib/composables/usePermissions';
  import { AMBIENTES, ambientesDisponibles, type Ambiente } from '@/lib/ambientes';

  const { can } = usePermissions();

  // ── Shared props ──────────────────────────────────────────────
  let docenteCourses = $derived(($page.props.auth?.docente_courses as SidebarCourse[]) || []);
  let estudianteCourses = $derived(($page.props.auth?.estudiante_courses as SidebarCourse[]) || []);
  let ayudanteCourses = $derived(($page.props.auth?.ayudante_courses as SidebarCourse[]) || []);

  // ── Role flags ────────────────────────────────────────────────
  // Secciones disponibles según los roles del usuario, en orden de prioridad.
  let availableSections = $derived(ambientesDisponibles($page.props.auth, can));
  let isDocente = $derived(availableSections.includes('docente'));
  let isEstudiante = $derived(availableSections.includes('estudiante'));
  let isAyudante = $derived(availableSections.includes('ayudante'));
  let isJefeCarrera = $derived(availableSections.includes('jefe'));
  let isAdmin = $derived(availableSections.includes('admin'));

  // ── Active route detection ─────────────────────────────────
  const currentPath = $derived($page.url ?? '');

  function isActive(href: string): boolean {
    if (!currentPath) return false;
    return currentPath === href || currentPath.startsWith(href + '/');
  }
  let filteredDocente = $derived(docenteCourses);
  let filteredEstudiante = $derived(estudianteCourses);
  let filteredAyudante = $derived(ayudanteCourses);

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
    { href: '/admin/reportes', icon: Bug, label: 'Reportes de Bugs' },
  ];

  // ── Selector de ambiente ──────────────────────────────────
  // Un usuario puede tener varios roles; sólo uno se muestra a la vez,
  // elegido con un selector tipo combobox que se despliega hacia abajo.
  type SectionId = Ambiente;
  const sectionMeta = AMBIENTES;

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

  let activeSection = $derived<SectionId>(
    openSection ?? urlSection ?? (availableSections[0] as SectionId) ?? 'docente',
  );
  let activeMeta = $derived(sectionMeta[activeSection] ?? sectionMeta.docente);
  let ActiveIcon = $derived(activeMeta.icon);

  function switchSection(id: SectionId) {
    const meta = sectionMeta[id];
    if (activeSection !== id) {
      openSection = id;
      if (meta?.href) {
        router.visit(meta.href);
      }
    } else if (meta?.href && !currentPath.startsWith(meta.href.replace('/dashboard', ''))) {
      router.visit(meta.href);
    }
  }
</script>

<div class="h-full flex flex-col bg-white text-slate-700">
  <!-- ── Header: Logo ─────────────────────────────────────── -->
  <div class="border-b border-slate-100 py-4 px-6">
    <div class="flex items-center gap-3">
      <AppLogo />
      <div class="flex flex-col">
        <span class="font-extrabold text-uta-blue text-lg leading-none tracking-tight">UTAMED</span
        >
        <span class="text-[10px] text-muted-foreground font-semibold tracking-widest uppercase mt-1"
          >Sistema de Gestión</span
        >
      </div>
    </div>
  </div>

  <!-- ── Content ──────────────────────────────────────────── -->
  <div class="flex-1 overflow-y-auto py-6">
    <!-- Selector de ambiente: desplegable tipo combobox hacia abajo sólo si el usuario tiene más de un rol. -->
    {#if availableSections.length > 1}
      <div class="px-4 mb-4">
        <DropdownMenu>
          <DropdownMenuTrigger
            class="w-full flex items-center justify-between p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100/80 border border-slate-200/80 transition-all cursor-pointer text-left group outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <div
                class="p-1.5 rounded-lg bg-indigo-50 text-indigo-600 shrink-0 group-hover:bg-indigo-100 transition-colors"
              >
                <ActiveIcon size={16} />
              </div>
              <div class="flex flex-col min-w-0">
                <span class="text-[9px] font-bold uppercase tracking-wider text-muted-foreground leading-tight"
                  >Ambiente</span
                >
                <span class="text-xs font-bold text-slate-800 truncate leading-tight mt-0.5"
                  >{activeMeta.label}</span
                >
              </div>
            </div>
            <ChevronsUpDown size={14} class="text-slate-400 group-hover:text-slate-600 shrink-0 ml-2" />
          </DropdownMenuTrigger>
          <DropdownMenuContent
            class="w-[var(--bits-dropdown-menu-anchor-width,224px)] min-w-[200px] rounded-xl shadow-lg border-slate-200/80 p-1.5 bg-white"
            side="bottom"
            align="start"
            sideOffset={4}
          >
            <DropdownMenuLabel
              class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-muted-foreground"
            >
              Cambiar de ambiente
            </DropdownMenuLabel>
            <DropdownMenuSeparator class="my-1 bg-slate-100" />
            <div class="flex flex-col gap-0.5">
              {#each availableSections as id (id)}
                {@const meta = sectionMeta[id]}
                {@const Icon = meta.icon}
                {@const isCurrent = activeSection === id}
                <DropdownMenuItem
                  onSelect={() => switchSection(id)}
                  class="flex items-center justify-between px-2.5 py-2 cursor-pointer rounded-lg text-xs font-semibold transition-colors {isCurrent
                    ? 'bg-indigo-50 text-indigo-700 font-bold'
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'}"
                >
                  <div class="flex items-center gap-2.5 min-w-0">
                    <div
                      class="p-1 rounded-md {isCurrent
                        ? 'bg-indigo-100/70 text-indigo-700'
                        : 'bg-slate-100 text-slate-500'}"
                    >
                      <Icon size={14} class="shrink-0" />
                    </div>
                    <span class="truncate">{meta.label}</span>
                  </div>
                  {#if isCurrent}
                    <Check size={14} class="text-indigo-600 shrink-0 ml-2" />
                  {/if}
                </DropdownMenuItem>
              {/each}
            </div>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    {/if}

    <!-- Un solo patrón de fila para todo destino de navegación simple
         (Dashboard, ítems de Jefe de Carrera, destinos de /admin). El único
         eje de variación es el acento: azul marino en toda la app, indigo
         sólo dentro de /admin — nunca al revés. Las clases de cada rama van
         literales (no interpoladas) para que Tailwind pueda extraerlas. -->
    {#snippet navLink(href: string, label: string, Icon: any, admin: boolean = false, big: boolean = false)}
      <Link
        {href}
        class="nav-link mx-3 flex min-h-11 items-center gap-3 rounded-lg transition-all group {big
          ? 'px-3 py-2.5 text-[15px] font-bold'
          : admin
            ? 'px-3 py-2.5 text-[14px] font-semibold'
            : 'px-3 py-2.5 text-[15px] font-medium'} {isActive(href)
          ? admin
            ? 'bg-indigo-50 text-indigo-700'
            : 'bg-uta-blue-light text-uta-blue'
          : admin
            ? 'text-slate-800 hover:bg-slate-50 hover:text-indigo-600'
            : 'text-ink hover:bg-slate-50 hover:text-uta-blue'}"
      >
        <Icon
          size={big ? 20 : 18}
          class="{isActive(href)
            ? admin
              ? 'text-indigo-500'
              : 'text-uta-blue'
            : admin
              ? 'text-muted-foreground group-hover:text-indigo-500'
              : 'text-slate-600 group-hover:text-uta-blue'} shrink-0 transition-colors"
        />
        {label}
      </Link>
    {/snippet}

    <!-- Título de sección. -->
    {#snippet sectionTitle(label: string)}
      <div class="px-4 mb-2.5">
        <span class="text-[11px] font-bold uppercase tracking-[0.06em] text-slate-600 whitespace-nowrap"
          >{label}</span
        >
      </div>
    {/snippet}

    <!-- Acceso a la lista completa, al pie de la lista de cursos. -->
    {#snippet verTodos(href: string)}
      <Link
        {href}
        class="mx-4 mt-1 flex min-h-11 items-center gap-1.5 rounded-lg px-3 text-[13px] font-semibold text-slate-600 underline underline-offset-2 transition-colors hover:text-ink"
      >
        Ver todos los cursos
        <ArrowRight size={16} class="shrink-0" aria-hidden="true" />
      </Link>
    {/snippet}

    <!-- ══ DOCENTE ══════════════════════════════════════════ -->
    {#if isDocente && openSection === 'docente'}
      {@render navLink('/docente/dashboard', 'Inicio', House)}
      {@render navLink('/docente/calendario', 'Calendario', Calendar)}
      <!-- La mensajería no está en el menú: los dos niveles se entran por su
           contexto. La del curso, desde el curso; la de agenda, desde la
           actividad. Un enlace global obligaría a reelegir lo que ya elegiste. -->

      <div class="h-px bg-slate-100 my-4 mx-4"></div>

      {@render sectionTitle('Mis Cursos')}
      <CourseListNav
        courses={filteredDocente}
        basePath="/docente/cursos"
        showPrograma
        showActividades
        emptyLabel="Sin cursos asignados"
      />
      {@render verTodos('/docente/cursos')}
    {/if}

    <!-- ══ JEFE DE CARRERA ══════════════════════════════════ -->
    {#if isJefeCarrera && openSection === 'jefe'}
      {@render navLink('/docente/jefe-carrera/dashboard', 'Inicio Carrera', House)}
      {@render navLink('/docente/jefe-carrera/seguimiento', 'Seguimiento', ClipboardList)}
      {@render navLink('/docente/jefe-carrera/metricas', 'Métricas', BarChart2)}

      <div class="px-6 mb-2 mt-3">
        <p class="text-[10px] font-bold tracking-widest uppercase text-slate-300">Gestión</p>
      </div>
      {@render navLink('/docente/jefe-carrera/planes', 'Malla / Planes', ClipboardList)}
      {@render navLink('/docente/jefe-carrera/asignaturas', 'Asignaturas', BookOpen)}
      {@render navLink('/docente/jefe-carrera/carrera', 'Mi Carrera', GraduationCap)}
    {/if}

    <!-- ══ ESTUDIANTE ═══════════════════════════════════════ -->
    {#if isEstudiante && openSection === 'estudiante'}
      {@render navLink('/estudiante/dashboard', 'Inicio', House)}

      <!-- La mensajería se entra desde la ficha del curso, no desde el menú:
           el hilo pertenece a un curso y elegirlo dos veces sobra. -->

      <div class="h-px bg-slate-100 my-4 mx-4"></div>

      {@render sectionTitle('Mis Cursos')}
      <CourseListNav
        courses={filteredEstudiante}
        basePath="/estudiante/cursos"
        emptyLabel="Sin cursos inscritos"
      />
      {@render verTodos('/estudiante/cursos')}
    {/if}

    <!-- ══ AYUDANTE ══════════════════════════════════════════ -->
    {#if isAyudante && openSection === 'ayudante'}
      {@render navLink('/ayudante/dashboard', 'Inicio', House)}

      <!-- Igual que el docente: la mensajería se entra desde el curso. -->

      <div class="h-px bg-slate-100 my-4 mx-4"></div>

      {@render sectionTitle('Cursos Asignados')}
      <CourseListNav
        courses={filteredAyudante}
        basePath="/ayudante/cursos"
        showPrograma
        emptyLabel="Sin ayudantías asignadas"
      />
      {@render verTodos('/ayudante/cursos')}
    {/if}

    <!-- ══ ADMIN ═════════════════════════════════════════════ -->
    {#if isAdmin && openSection === 'admin'}
      {@render navLink('/admin/dashboard', 'Inicio', House, true, true)}
      <div class="px-6 mb-2 mt-3">
        <p class="text-[10px] font-bold tracking-widest uppercase text-slate-300">Administración</p>
      </div>
      {#each adminMenuItems as item (item.href)}
        {@render navLink(item.href, item.label, item.icon, true)}
      {/each}
    {/if}
  </div>

  <!-- ── Footer: NavUser ──────────────────────────────────── -->
  <div class="p-6 border-t border-slate-100 bg-white z-10">
    <NavUser />
  </div>
</div>
