<script lang="ts">
    /**
     * AdminLayout — contenedor común de todas las pantallas del panel.
     *
     * Antes no definía ni ancho ni relleno, así que cada página inventaba el
     * suyo (`max-w-7xl mx-auto` en unas, `max-w-2xl px-4` en otras, nada en
     * el resto) y el título saltaba de sitio al navegar entre módulos.
     *
     * Ahora el ancho lo decide el layout: `wide` para listados y tableros,
     * `narrow` para formularios de una columna, donde una línea de 1400 px
     * sería ilegible.
     */
    import AppLayout from '@/layouts/AppLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import type { Snippet } from 'svelte';

    interface Props {
        breadcrumbs?: BreadcrumbItem[];
        /** `wide` para listados; `narrow` para formularios de una columna. */
        width?: 'wide' | 'narrow';
        children?: Snippet;
    }

    let { breadcrumbs = [], width = 'wide', children }: Props = $props();
</script>

<AppLayout {breadcrumbs}>
    <div class="page-shell" class:page-shell-narrow={width === 'narrow'}>
        {@render children?.()}
    </div>
</AppLayout>

<style>
    .page-shell-narrow {
        max-width: 42rem;
    }
</style>
