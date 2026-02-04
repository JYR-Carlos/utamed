<script lang="ts">
    import AppContent from '@/components/custom/layout/AppContent.svelte';
    import AppShell from '@/components/custom/layout/AppShell.svelte';
    import AppSidebar from '@/components/custom/layout/AppSidebar.svelte';
    import AppSidebarHeader from '@/components/custom/layout/AppSidebarHeader.svelte';
    import type { BreadcrumbItemType } from '@/types';
    import type { Snippet } from 'svelte';

    interface Props {
        breadcrumbs?: BreadcrumbItemType[];
        children?: Snippet;
    }

    let { breadcrumbs = [], children }: Props = $props();
</script>

<AppShell variant="sidebar">
    <AppSidebar />
    <AppContent variant="sidebar" class="overflow-x-hidden">
        <AppSidebarHeader {breadcrumbs} />
        {@render children?.()}
    </AppContent>
</AppShell>
