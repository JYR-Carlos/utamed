<script lang="ts">
    import AppLayout from '@/layouts/AppLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import { page } from '@inertiajs/svelte';

    interface Props {
        children?: import('svelte').Snippet;
        breadcrumbs?: BreadcrumbItem[];
    }

    let { children, breadcrumbs = [] }: Props = $props();
</script>

<AppLayout {breadcrumbs}>
    <div class="ayudante-layout min-h-screen bg-slate-50">
        {@render children?.()}
    </div>
</AppLayout>
