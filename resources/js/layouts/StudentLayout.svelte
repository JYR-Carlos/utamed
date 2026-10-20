<script lang="ts">
    import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import type { Snippet } from 'svelte';

    interface Props {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    }

    let { breadcrumbs = [], children }: Props = $props();

    // Salta a las pestañas del curso si la página las tiene; si no, al contenido.
    function saltarAlContenido(e: MouseEvent) {
        e.preventDefault();
        const pestanas = document.getElementById('secciones-curso');
        const destino =
            pestanas?.querySelector<HTMLElement>('button, a') ??
            document.querySelector<HTMLElement>('main');
        if (!destino) return;
        if (!destino.matches('button, a, input, select, textarea, [tabindex]')) destino.tabIndex = -1;
        destino.focus();
        destino.scrollIntoView({ block: 'start' });
    }
</script>

<a
    href="#secciones-curso"
    onclick={saltarAlContenido}
    class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-uta-blue focus:px-4 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-white focus:shadow-lg"
>
    Saltar al contenido
</a>

<div class="flex flex-col w-full mx-auto h-screen bg-white shadow-xl border-x border-slate-100 relative overflow-y-auto">
    <AppSidebarLayout {breadcrumbs}>
        {@render children?.()}
    </AppSidebarLayout>
</div>