<script lang="ts">
  import { page, Link } from '@inertiajs/svelte';
  import NavUser from '@/components/custom/navigation/NavUser.svelte';
  import CourseListNav from '@/components/custom/navigation/CourseListNav.svelte';
  import AppLogo from '@/components/custom/layout/AppLogo.svelte';
  import {
    BookOpen,
    Settings,
    Calendar,
    GraduationCap,
    ClipboardList,
    LayoutGrid,
    Users,
    UserCheck,
    Building2,
    Landmark,
    CalendarRange,
    ScrollText,
    BarChart2,
  } from 'lucide-svelte';
  import type { SidebarCourse } from '@/types';
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
  let isAdminSection = $derived(openSection === 'admin');

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
        <span class="font-extrabold text-[#22213F] text-lg leading-none tracking-tight">UTAMED</span
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

    <!-- Un solo patrón de fila para todo destino de navegación simple
         (Dashboard, ítems de Jefe de Carrera, destinos de /admin). El único
         eje de variación es el acento: azul marino en toda la app, indigo
         sólo dentro de /admin — nunca al revés. Las clases de cada rama van
         literales (no interpoladas) para que Tailwind pueda extraerlas. -->
    {#snippet navLink(href: string, label: string, Icon: any, admin: boolean = false, big: boolean = false)}
      <Link
        {href}
        class="flex items-center gap-3 rounded-xl transition-all group {big
          ? 'px-4 py-2.5 text-[15px] font-bold'
          : 'px-4 py-2.5 text-[14px] font-semibold'} {isActive(href)
          ? admin
            ? 'bg-indigo-50 text-indigo-700'
            : 'bg-[#22213F]/8 text-[#22213F]'
          : admin
            ? 'text-slate-800 hover:bg-slate-50 hover:text-indigo-600'
            : 'text-slate-600 hover:bg-slate-50 hover:text-[#22213F]'}"
      >
        <Icon
          size={big ? 20 : 18}
          class="{isActive(href)
            ? admin
              ? 'text-indigo-500'
              : 'text-[#22213F]'
            : admin
              ? 'text-slate-400 group-hover:text-indigo-500'
              : 'text-slate-400 group-hover:text-[#22213F]'} shrink-0 transition-colors"
        />
        {label}
      </Link>
    {/snippet}

    <!-- Título de sección + acceso opcional a la lista completa. -->
    {#snippet sectionTitle(label: string, viewAllHref?: string)}
      <div class="px-6 mb-1 flex items-center justify-between gap-2">
        <span class="text-[14px] font-extrabold text-slate-800">{label}</span>
        {#if viewAllHref}
          <Link
            href={viewAllHref}
            class="text-[11px] font-bold text-slate-400 hover:text-[#22213F] transition-colors shrink-0"
          >
            Ver todos →
          </Link>
        {/if}
      </div>
    {/snippet}

    <!-- ══ DOCENTE ══════════════════════════════════════════ -->
    {#if isDocente && openSection === 'docente'}
      {@render navLink('/docente/dashboard', 'Dashboard', LayoutGrid)}
      {@render navLink('/docente/calendario', 'Calendario', Calendar)}
      <!-- La mensajería no está en el menú: los dos niveles se entran por su
           contexto. La del curso, desde el curso; la de agenda, desde la
           actividad. Un enlace global obligaría a reelegir lo que ya elegiste. -->

      <div class="h-px bg-slate-100 my-4 mx-4"></div>

      {@render sectionTitle('Mis Cursos', '/docente/cursos')}
      <CourseListNav
        courses={filteredDocente}
        basePath="/docente/cursos"
        showPrograma
        showActividades
        emptyLabel="Sin cursos asignados"
      />
    {/if}

    <!-- ══ JEFE DE CARRERA ══════════════════════════════════ -->
    {#if isJefeCarrera && openSection === 'jefe'}
      {@render navLink('/docente/jefe-carrera/dashboard', 'Dashboard Carrera', LayoutGrid)}
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
    {#if isEstudiante && !isDocente && openSection === 'estudiante'}
      {@render navLink('/estudiante/dashboard', 'Dashboard', LayoutGrid)}

      <!-- La mensajería se entra desde la ficha del curso, no desde el menú:
           el hilo pertenece a un curso y elegirlo dos veces sobra. -->

      <div class="h-px bg-slate-100 my-4 mx-4"></div>

      {@render sectionTitle('Mis Cursos', '/estudiante/cursos')}
      <CourseListNav
        courses={filteredEstudiante}
        basePath="/estudiante/cursos"
        emptyLabel="Sin cursos inscritos"
      />
    {/if}

    <!-- ══ AYUDANTE ══════════════════════════════════════════ -->
    {#if isAyudante && !isDocente && openSection === 'ayudante'}
      {@render navLink('/ayudante/dashboard', 'Dashboard', LayoutGrid)}

      <!-- Igual que el docente: la mensajería se entra desde el curso. -->

      <div class="h-px bg-slate-100 my-4 mx-4"></div>

      {@render sectionTitle('Cursos Asignados', '/ayudante/cursos')}
      <CourseListNav
        courses={filteredAyudante}
        basePath="/ayudante/cursos"
        showPrograma
        emptyLabel="Sin ayudantías asignadas"
      />
    {/if}

    <!-- ══ ADMIN ═════════════════════════════════════════════ -->
    {#if isAdmin && openSection === 'admin'}
      {@render navLink('/dashboard', 'Dashboard', LayoutGrid, true, true)}
      <div class="px-6 mb-2 mt-3">
        <p class="text-[10px] font-bold tracking-widest uppercase text-slate-300">Administración</p>
      </div>
      {#each adminMenuItems as item (item.href)}
        {@render navLink(item.href, item.label, item.icon, true)}
      {/each}
    {/if}

    <!-- ── Footer: común a toda sección activa, mismo patrón de fila ── -->
    {#if openSection !== null}
      <div class="h-px bg-slate-100 my-4 mx-4"></div>
      {@render navLink('/settings', 'Configuración', Settings, isAdminSection)}
    {/if}
  </div>

  <!-- ── Footer: NavUser ──────────────────────────────────── -->
  <div class="p-6 border-t border-slate-100 bg-white z-10">
    <NavUser />
  </div>
</div>
