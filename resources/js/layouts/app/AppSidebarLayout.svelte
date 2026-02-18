<script lang="ts">
    import AppContent from '@/components/custom/layout/AppContent.svelte';
    import AppShell from '@/components/custom/layout/AppShell.svelte';
    import RoleSidebar from '@/components/custom/layout/RoleSidebar.svelte';
    import AppSidebarHeader from '@/components/custom/layout/AppSidebarHeader.svelte';
    import { Sidebar, SidebarContent } from '@/components/ui/sidebar';
    import type { BreadcrumbItem } from '@/types';
    import type { Snippet } from 'svelte';

    interface Props {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    }

    let { breadcrumbs = [], children }: Props = $props();
</script>


<AppShell variant="sidebar">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <Sidebar collapsible="none" class="hidden lg:flex border-r border-slate-200">
            <SidebarContent>
                <RoleSidebar />
            </SidebarContent>
        </Sidebar>

        <!-- Mobile/Tablet Drawer (handled by the same Sidebar component if we use it correctly, 
             but here we are using RoleSidebar which is custom. 
             The UI Sidebar component handles the Sheet logic when isMobile is true.) -->
        <Sidebar class="lg:hidden">
            <SidebarContent>
                <RoleSidebar />
            </SidebarContent>
        </Sidebar>

        <!-- Main Content -->
        <AppContent variant="sidebar" class="flex-1 min-w-0 bg-white overflow-y-auto">
            <AppSidebarHeader {breadcrumbs} />
            <main class="flex-1 p-4 md:p-6 lg:p-8">
                {@render children?.()}
            </main>
        </AppContent>
    </div>
</AppShell>
