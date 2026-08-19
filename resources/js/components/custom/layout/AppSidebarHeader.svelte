<script lang="ts">
  import Breadcrumbs from '@/components/custom/navigation/Breadcrumbs.svelte';
  import type { BreadcrumbItem } from '@/types';
  import {
    Search,
    Bell,
    ChevronDown,
    LayoutGrid,
    Users,
    Building2,
    BookOpen,
    Settings,
    LogOut,
    Folder,
    GraduationCap,
    ClipboardList,
    ChevronUp,
    ChevronLeft,
  } from 'lucide-svelte';
  import { page, router } from '@inertiajs/svelte';
  import * as Command from '@/components/ui/command';
  import { onMount } from 'svelte';
  import { useSidebar } from '@/components/ui/sidebar/context.svelte';
  import ChevronRight from '@lucide/svelte/icons/chevron-right';
  import UserMenuContent from '../common/UserMenuContent.svelte';
  import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
  import { Button } from '@/components/ui/button';
  import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
  import { getInitials } from '@/hooks/useInitials';

  const sidebar = useSidebar();

  interface Props {
    breadcrumbs?: BreadcrumbItem[];
    showHeader?: boolean;
    showSidebar?: boolean;
  }

  let {
    breadcrumbs = [],
    showHeader = $bindable(true),
    showSidebar = $bindable(true),
  }: Props = $props();
  let user = $derived($page.props.auth.user);
  const authRoles = $derived(($page.props.auth.roles as string[]) || []);

  let openSearch = $state(false);

  onMount(() => {
    const handleKeydown = (e: KeyboardEvent) => {
      if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        openSearch = !openSearch;
      }
    };

    document.addEventListener('keydown', handleKeydown);
    return () => document.removeEventListener('keydown', handleKeydown);
  });

  const searchItems = $derived.by(() => {
    // Permission and profile-based checks — not role name strings
    const isSuperAdmin = ($page.props.auth?.is_super_admin as boolean) || false;
    const authPermissions = ($page.props.auth?.permissions as string[]) || [];
    const hasPerm = (slug: string) =>
      isSuperAdmin ||
      authPermissions.includes('*') ||
      authPermissions.includes(slug);
    const isAdmin = isSuperAdmin || hasPerm('facultades:ver') || hasPerm('departamentos:ver') || hasPerm('usuarios/permisos/roles:ver');
    const isDocente = ($page.props.auth?.docente as any) !== null && ($page.props.auth?.docente as any) !== undefined;
    const isEstudiante = ($page.props.auth?.estudiante as any) !== null && ($page.props.auth?.estudiante as any) !== undefined;

    const items = [];

    // Admin navigation
    if (isAdmin) {
      items.push({
        group: 'Administración',
        items: [
          { icon: LayoutGrid, label: 'Dashboard', href: '/dashboard' },
          { icon: Users, label: 'Usuarios', href: '/admin/usuarios' },
          { icon: Building2, label: 'Facultades', href: '/admin/facultades' },
          { icon: Folder, label: 'Departamentos', href: '/admin/departamentos' },
          { icon: GraduationCap, label: 'Carreras', href: '/admin/carreras' },
          { icon: BookOpen, label: 'Asignaturas', href: '/admin/asignaturas' },
          { icon: ClipboardList, label: 'Planes de Estudio', href: '/admin/planes' },
          { icon: BookOpen, label: 'Cursos Ofertados', href: '/admin/cursos' },
          { icon: Users, label: 'Inscripciones', href: '/admin/inscripciones_cursos' },
        ],
      });
    }

    // Docente navigation
    if (isDocente) {
      items.push({
        group: 'Docente',
        items: [
          { icon: LayoutGrid, label: 'Dashboard', href: '/docente/dashboard' },
          { icon: BookOpen, label: 'Mis Cursos', href: '/docente/cursos' },
        ],
      });
    }

    // Estudiante navigation
    if (isEstudiante) {
      items.push({
        group: 'Estudiante',
        items: [
          { icon: LayoutGrid, label: 'Dashboard', href: '/estudiante/dashboard' },
          { icon: BookOpen, label: 'Mis Cursos', href: '/estudiante/cursos' },
        ],
      });
    }

    // Common options for all users
    items.push({
      group: 'Configuración',
      items: [
        { icon: Settings, label: 'Perfil', href: '/settings' },
        { icon: LogOut, label: 'Cerrar Sesión', action: () => router.post('/logout') },
      ],
    });

    return items;
  });

  function handleSelect(item: any) {
    openSearch = false;
    if (item.href) {
      router.visit(item.href);
    } else if (item.action) {
      item.action();
    }
  }
</script>

<header class="global-header">
  <div class="header-left">
    <!-- Mobile trigger -->
    <button
      class="lg:hidden p-2 -ml-2 text-slate-500 hover:bg-slate-100 rounded-md transition-colors"
      onclick={() => sidebar.toggle()}
      aria-label="Toggle Sidebar"
    >
      <LayoutGrid size={20} />
    </button>

    {#if breadcrumbs.length > 0}
      <div class="breadcrumb-wrapper">
        <Breadcrumbs {breadcrumbs} />
      </div>
    {/if}
  </div>

  <div class="header-center hidden lg:flex lg:justify-center">
    <button class="search-box cursor-text text-left" onclick={() => (openSearch = true)}>
      <Search size={18} class="search-icon" />
      <span class="search-input text-slate-400">Buscar en el sistema...</span>
      <span
        class="ml-auto hidden xl:inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-400"
      >
      <span class="text-[12px] leading-none">⌘</span> K
      </span>
    </button>
  </div>

  <!-- Search Dialog -->
  <Command.Dialog bind:open={openSearch}>
    <Command.Input placeholder="Escribe para buscar..." />
    <Command.List>
      <Command.Empty>No se encontraron resultados.</Command.Empty>
      {#each searchItems as group (group.group)}
        <Command.Group heading={group.group}>
          {#each group.items as item (item.label)}
            <Command.Item onSelect={() => handleSelect(item)} class="cursor-pointer">
              <item.icon class="mr-2 h-4 w-4" />
              <span>{item.label}</span>
            </Command.Item>
          {/each}
        </Command.Group>
      {/each}
    </Command.List>
  </Command.Dialog>

  <div class="header-right">
    <button
      class="lg:hidden mobile-search-trigger flex items-center justify-center gap-2 px-3 py-1.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-full font-bold shadow-sm"
      onclick={() => (openSearch = true)}
    >
      <Search size={18} />
      <span class="text-xs">Buscar</span>
    </button>

    <!-- Estos dos botones no decían qué hacían: sólo una flecha, sin
         etiqueta accesible y con un título que no cambiaba al alternar. -->
    <button
      class="icon-btn hidden lg:flex items-center justify-center group relative"
      onclick={() => (showSidebar = !showSidebar)}
      aria-label={showSidebar ? 'Ocultar el menú lateral' : 'Mostrar el menú lateral'}
      title={showSidebar ? 'Ocultar el menú lateral' : 'Mostrar el menú lateral'}
    >
      {#if showSidebar}
        <ChevronLeft size={20} class="group-hover:text-slate-900" />
      {/if}
      {#if !showSidebar}
        <ChevronRight size={20} class="group-hover:text-slate-900" />
      {/if}
    </button>

    <button
      class="icon-btn flex items-center justify-center group relative"
      onclick={() => (showHeader = !showHeader)}
      aria-label="Ocultar esta barra superior"
      title="Ocultar esta barra superior"
    >
      <ChevronUp size={20} class="group-hover:text-slate-900" />
    </button>

    <!--
    <div class="user-pill group">
      <div class="avatar-sm">
        {user.nombre1?.[0] || user.username?.[0] || 'U'}
      </div>
      <div class="user-meta hidden md:flex">
        <span class="user-name">{user.nombre1 || user.username}</span>
        <span class="user-role">{authRoles[0] || 'Usuario'}</span>
      </div>
      <ChevronDown
        size={14}
        class="ml-1 text-slate-400 group-hover:text-slate-600 transition-colors"
      />
    </div>
    -->
    <DropdownMenu>
      <DropdownMenuTrigger>
        <Button
          variant="ghost"
          size="icon"
          class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
        >
          <Avatar class="size-8 overflow-hidden rounded-full">
            {#if user.avatar}
              <AvatarImage src={user.avatar} alt={user.nombre1} />
            {:else}
              <AvatarFallback
                class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white"
              >
                {getInitials(user.nombre1 || '')}
              </AvatarFallback>
            {/if}
          </Avatar>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" class="w-56">
        <UserMenuContent {user} />
      </DropdownMenuContent>
    </DropdownMenu>
  </div>
</header>

<style>
  /*
   * Tres zonas con anchos propios y una separación que nunca se cierra.
   *
   * Antes `.header-left` llevaba `overflow: hidden` sin separación con la
   * zona central, así que la ruta de navegación se cortaba a media palabra
   * justo donde empieza el buscador —«Dashboar», «Cursos Ofertado»— y
   * parecía que la caja de búsqueda la tapaba. El único indicador de
   * «dónde estoy» estaba roto en todas las pantallas.
   */
  .global-header {
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 0 1.5rem;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    position: sticky;
    top: 0;
    z-index: 30;
  }

  .header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1 1 0;
    min-width: 0;
  }

  /* Si no cabe, la ruta se abrevia con puntos suspensivos en vez de
     cortarse a mitad de letra. */
  .breadcrumb-wrapper {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  .header-center {
    flex: 0 1 400px;
    min-width: 0;
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    justify-content: flex-end;
    min-width: 0;
  }

  @media (min-width: 768px) {
    .header-center {
      min-width: 300px;
    }

    .header-right {
      gap: 1.25rem;
      min-width: 200px;
    }
  }

  .search-box {
    width: 100%;
    max-width: 400px;
    position: relative;
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    color: #94a3b8;
    transition: all 0.2s;
  }

  .search-box:hover {
    background: #f1f5f9;
    border-color: #e2e8f0;
  }

  .search-input {
    margin-left: 0.75rem;
    font-size: 0.875rem;
  }

  @media (min-width: 1024px) {
    .search-input {
      font-size: 0.95rem;
    }
  }

  .icon-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    position: relative;
    cursor: pointer;
    transition: all 0.2s;
  }

  .icon-btn:hover {
    background: #f1f5f9;
    color: #1e293b;
  }
</style>
