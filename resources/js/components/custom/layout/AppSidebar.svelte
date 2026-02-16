<script lang="ts">
    import NavFooter from '@/components/custom/navigation/NavFooter.svelte';
    import NavMain from '@/components/custom/navigation/NavMain.svelte';
    import NavUser from '@/components/custom/navigation/NavUser.svelte';
    import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
    import { type NavItem } from '@/types';
    import { Link, page } from '@inertiajs/svelte';
    import { BookOpen, Folder, LayoutGrid, Users, GraduationCap, Building2, ClipboardList } from 'lucide-svelte';
    import AppLogo from './AppLogo.svelte';
    import { onMount } from 'svelte';
    import { router } from '@inertiajs/svelte';

    // Get roles from page props with Svelte 5 runes
    let authRoles = $derived(($page.props.auth?.roles as string[]) || []);
    let userDocente = $derived(($page.props.auth?.user?.docente) || null);
    let isAdminOnly = $derived(authRoles.length === 0 || (authRoles.length === 1 && (authRoles.includes('Administrador') || authRoles.includes('SuperAdmin') || authRoles.includes('Super Admin'))));



    // Helper to check for roles
    const hasRole = (requiredRoles: string[]) => {
        if (requiredRoles.includes('*')) return true;
        if (authRoles.includes('SuperAdmin') || authRoles.includes('Super Admin')) return true;
        return authRoles.some(r => requiredRoles.includes(r));
    };

    // Redirigir docentes al dashboard de docentes si están en la admin
    onMount(() => {
        if (userDocente && $page.url.startsWith('/admin')) {
            router.visit('/docente/dashboard');
        }
    });

    // Define all possible nav items
    let allNavItems = $derived([
        {
            title: 'Dashboard',
            href: '/dashboard',
            icon: LayoutGrid,
            show: true,
        },
        {
            title: 'Usuarios',
            href: '/admin/usuarios',
            icon: Users,
            show: hasRole(['Administrador', 'Coordinador de Carrera', 'Secretaria']),
        },
        {
            title: 'Facultades',
            href: '/admin/facultades',
            icon: Building2,
            show: hasRole(['Administrador']),
        },
        {
            title: 'Departamentos',
            href: '/admin/departamentos',
            icon: Folder,
            show: hasRole(['Administrador']),
        },
        {
            title: 'Carreras',
            href: '/admin/carreras',
            icon: GraduationCap,
            show: hasRole(['Administrador']),
        },
        {
            title: 'Asignaturas',
            href: '/admin/asignaturas',
            icon: BookOpen,
            show: hasRole(['Administrador']),
        },
        {
            title: 'Planes de Estudio',
            href: '/admin/planes',
            icon: ClipboardList,
            show: hasRole(['Administrador']),
        },
        {
            title: 'Cursos Ofertados',
            href: '/admin/cursos',
            icon: BookOpen,
            show: hasRole(['Administrador']),
        },
        {
            title: 'Inscripciones',
            href: '/admin/inscripciones_cursos',
            icon: Users,
            show: hasRole(['Administrador']),
        },
        {
            title: 'Mis Cursos',
            href: '/docente/cursos',
            icon: BookOpen,
            show: userDocente && !isAdminOnly,
        },
        // Ayudante Items - STRICT check (Admins shouldn't see this unless they are also Ayudantes)
        {
            title: 'Dashboard Ayudante',
            href: '/ayudante/dashboard',
            icon: LayoutGrid,
            show: authRoles.includes('Ayudante') || authRoles.includes('ayudante'),
        },
        {
            title: 'Cursos Asignados',
            href: '/ayudante/cursos',
            icon: BookOpen,
            show: authRoles.includes('Ayudante') || authRoles.includes('ayudante'),
        }
    ]);

    // Filter items based on active roles using $derived
    let mainNavItems = $derived(allNavItems.filter(item => {
        if (typeof item.show === 'boolean') return item.show;
        return item.show; // In this case show is the result of hasRole
    })) as NavItem[];

    const footerNavItems: NavItem[] = [];
</script>

<Sidebar collapsible="icon" variant="inset" class="soft-sidebar">
    <SidebarHeader class="border-b border-sidebar-border/50 py-3 px-4 md:px-5 lg:px-6">
        <SidebarMenu>
            <SidebarMenuItem>
                <div class="flex items-center gap-2 min-w-0">
                    <AppLogo />
                    <div class="logo-text group-data-[state=collapsed]:hidden flex flex-col min-w-0">
                        <span class="font-bold text-slate-900 text-base md:text-lg lg:text-xl leading-none truncate">UTAMED</span>
                        <span class="text-[9px] md:text-[10px] lg:text-xs text-slate-500 font-bold tracking-wider uppercase mt-0.5 truncate">Sistema de Gestión</span>
                    </div>
                </div>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent class="px-2 pt-4">
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter class="p-4 mt-auto">
        <div class="footer-card group-data-[collapsible=icon]:hidden">
            <p class="text-xs md:text-sm lg:text-sm font-bold text-slate-700">UTAMED Support</p>
            <p class="text-[10px] md:text-xs lg:text-xs text-slate-500 mt-1 leading-tight">Accede a nuestra documentación centralizada.</p>
            <div class="mt-3 flex justify-end">
                <span class="text-xl md:text-2xl lg:text-2xl">🚀</span>
            </div>
        </div>
        <div class="mt-4">
            <NavUser />
        </div>
    </SidebarFooter>
</Sidebar>

<style>
    :global(.soft-sidebar) {
        box-shadow: 4px 0 24px -12px rgba(0, 0, 0, 0.08);
        background: linear-gradient(to bottom, #ffffff, #fafbfc);
    }

    .logo-text {
        display: flex;
        flex-direction: column;
    }

    .footer-card {
        background: linear-gradient(135deg, #f0f9ff 0%, #f8fafc 100%);
        border: 1.5px solid #e0f2fe;
        border-radius: 14px;
        padding: 1rem;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .footer-card:hover {
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        border-color: #bae6fd;
    }

    .footer-card::after {
        content: '';
        position: absolute;
        top: -10px;
        right: -10px;
        width: 40px;
        height: 40px;
        background: rgba(59, 130, 246, 0.05);
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .footer-card:hover::after {
        background: rgba(59, 130, 246, 0.1);
    }

    .footer-card p {
        transition: color 0.3s ease;
    }

    .footer-card:hover p {
        color: #0369a1;
    }
</style>
