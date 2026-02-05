<script lang="ts">
    import NavFooter from '@/components/custom/navigation/NavFooter.svelte';
    import NavMain from '@/components/custom/navigation/NavMain.svelte';
    import NavUser from '@/components/custom/navigation/NavUser.svelte';
    import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
    import { type NavItem } from '@/types';
    import { Link } from '@inertiajs/svelte';
    import { BookOpen, LayoutGrid } from 'lucide-svelte';
    import AppLogo from '@/components/custom/layout/AppLogo.svelte';
    import AppShell from '@/components/custom/layout/AppShell.svelte';
    import AppContent from '@/components/custom/layout/AppContent.svelte';
    import AppSidebarHeader from '@/components/custom/layout/AppSidebarHeader.svelte';
    import type { BreadcrumbItem } from '@/types';
    import type { Snippet } from 'svelte';

    interface Props {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    }

    let { breadcrumbs = [], children }: Props = $props();

    // Define nav items for docentes - limited options
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: '/docente/dashboard',
            icon: LayoutGrid,
        },
        {
            title: 'Mis Cursos',
            href: '/docente/cursos',
            icon: BookOpen,
        }
    ];

    const footerNavItems: NavItem[] = [];
</script>

<AppShell variant="sidebar">
    <Sidebar collapsible="icon" variant="inset" class="soft-sidebar">
        <SidebarHeader class="border-b border-sidebar-border/50 py-4 px-6">
            <SidebarMenu>
                <SidebarMenuItem>
                    <div class="flex items-center gap-3">
                        <AppLogo />
                        <div class="logo-text group-data-[state=collapsed]:hidden flex flex-col">
                            <span class="font-bold text-slate-900 text-lg md:text-xl lg:text-2xl leading-none">UTAMED</span>
                            <span class="text-[10px] md:text-xs lg:text-xs text-slate-500 font-bold tracking-wider uppercase mt-1">Sistema de Gestión</span>
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
                <p class="text-xs font-bold text-slate-700">UTAMED Support</p>
                <p class="text-[10px] text-slate-500 mt-1 leading-tight">Accede a nuestra documentación centralizada.</p>
                <div class="mt-3 flex justify-end">
                    <span class="text-xl">🚀</span>
                </div>
            </div>
            <div class="mt-4">
                <NavUser />
            </div>
        </SidebarFooter>
    </Sidebar>

    <AppContent variant="sidebar" class="overflow-x-hidden">
        <AppSidebarHeader {breadcrumbs} />
        {@render children?.()}
    </AppContent>
</AppShell>

<style>
    :global(.soft-sidebar) {
        box-shadow: 4px 0 24px -12px rgba(0, 0, 0, 0.08);
    }

    .logo-text {
        display: flex;
        flex-direction: column;
    }

    .footer-card {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        padding: 1rem;
        position: relative;
        overflow: hidden;
    }

    .footer-card::after {
        content: '';
        position: absolute;
        top: -10px;
        right: -10px;
        width: 40px;
        height: 40px;
        background: rgba(59, 130, 246, 0.03);
        border-radius: 50%;
    }
</style>
