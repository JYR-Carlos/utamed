<script lang="ts">
    import AppLayout from '@/layouts/app/AppSidebarLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import type { Snippet } from 'svelte';

    interface Props {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    }

    let { breadcrumbs = [], children }: Props = $props();
</script>

<AppLayout {breadcrumbs}>
    {@render children?.()}
</AppLayout>
