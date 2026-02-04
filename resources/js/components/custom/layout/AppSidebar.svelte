<script lang="ts">
    import NavFooter from '@/components/custom/navigation/NavFooter.svelte';
    import NavMain from '@/components/custom/navigation/NavMain.svelte';
    import NavUser from '@/components/custom/navigation/NavUser.svelte';
    import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
    import { dashboard } from '@/routes';
    import { type NavItem } from '@/types';
    import { Link, page } from '@inertiajs/svelte';
    import { BookOpen, Folder, LayoutGrid, Users, GraduationCap } from 'lucide-svelte';
    import AppLogo from './AppLogo.svelte';

    // Get roles from page props (provided by HandleInertiaRequests)
    $: roles = $page.props.auth.roles || [];

    // Helper to check for roles
    const hasRole = (requiredRoles: string[]) => {
        if (requiredRoles.includes('*')) return true;
        if (roles.includes('Super Admin')) return true; // Super Admin sees everything
        return roles.some(r => requiredRoles.includes(r));
    };

    // Define all possible nav items
    const allNavItems = [
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
            show: hasRole(['Coordinador de Carrera', 'Secretaria']),
        },
        {
            title: 'Cursos',
            href: '/cursos', // Assuming /cursos route exists
            icon: BookOpen,
            show: hasRole(['Docente', 'Ayudante', 'Estudiante']),
        }
    ];

    // Filter items based on active roles
    $: mainNavItems = allNavItems.filter(item => item.show) as NavItem[];

    const footerNavItems: NavItem[] = [];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg">
                    <Link href={dashboard()}>
                        <AppLogo />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavFooter items={footerNavItems} class="mt-auto" />
        <NavUser />
    </SidebarFooter>
</Sidebar>
