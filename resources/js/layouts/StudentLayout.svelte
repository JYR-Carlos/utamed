<script lang="ts">
    import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import type { Snippet } from 'svelte';

    interface Props {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    }

    let { breadcrumbs = [], children }: Props = $props();
</script>

<div class="flex flex-col w-full mx-auto h-screen bg-white shadow-xl border-x border-slate-100 relative overflow-y-auto">
    <AppSidebarLayout {breadcrumbs}>
        {@render children?.()}
    </AppSidebarLayout>
</div>