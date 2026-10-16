<script lang="ts">
    import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator, Item } from '@/components/ui/breadcrumb';
    import { Link } from '@inertiajs/svelte';
    import { ChevronLeft } from 'lucide-svelte';

    interface BreadcrumbItem {
        title: string;
        href?: string;
    }

    interface Props {
        breadcrumbs: BreadcrumbItem[];
        /** «volver»: sólo el nivel anterior, para la fila móvil bajo la barra superior. */
        variante?: 'completa' | 'volver';
    }

    let { breadcrumbs, variante = 'completa' }: Props = $props();

    // La máscara sólo se aplica si la ruta no cabe: antes difuminaba siempre
    // el final, que por el anclaje a la derecha es la página actual.
    let contenedor = $state<HTMLElement | null>(null);
    let desborda = $state(false);

    $effect(() => {
        const el = contenedor;
        if (!el) return;
        const medir = () => (desborda = el.scrollWidth > el.clientWidth + 1);
        medir();
        const ro = new ResizeObserver(medir);
        ro.observe(el);
        if (el.firstElementChild) ro.observe(el.firstElementChild);
        return () => ro.disconnect();
    });

    function onWheel(e: WheelEvent) {
        const el = e.currentTarget as HTMLElement;
        if (e.deltaY === 0) return;
        e.preventDefault();
        el.scrollLeft += e.deltaY;
    }
</script>

<!-- En móvil la ruta completa no cabe: sólo se ofrece volver al nivel anterior
     («‹ Mis cursos»), con un área táctil de 44 px. El nombre de la página
     actual ya es el título del encabezado. -->
{#if variante === 'volver'}
    {#if breadcrumbs.length > 1}
    {@const padre = breadcrumbs[breadcrumbs.length - 2]}
    <nav aria-label="Volver">
        <Link
            href={padre.href ?? '#'}
            class="inline-flex min-h-11 items-center gap-1 rounded-md pr-3 text-sm font-semibold text-uta-blue no-underline"
        >
            <ChevronLeft class="size-5 shrink-0" aria-hidden="true" />
            <span class="truncate">{padre.title}</span>
        </Link>
    </nav>
    {/if}
{:else}
<Breadcrumb>
    <!-- Si no cabe, direction:rtl ancla el scroll a la derecha (se ve la página actual).
         Si no cabe, un degradado en el borde izquierdo indica que hay más. -->
    <div
        class="breadcrumb-scroll"
        class:desborda
        style="direction: {desborda ? 'rtl' : 'ltr'};"
        onwheel={onWheel}
        bind:this={contenedor}
    >
        <BreadcrumbList class="flex-nowrap w-max" style="direction: ltr;">
            {#each breadcrumbs as item, index (index)}
                <Item class="shrink-0">
                    {#if index === breadcrumbs.length - 1}
                        <BreadcrumbPage class="font-semibold text-slate-900">
                            {item.title}
                        </BreadcrumbPage>
                    {:else}
                        <BreadcrumbLink>
                            {#snippet child({ props })}
                                <Link {...props} href={item.href ?? '#'} class="{props.class} inline-block py-2 font-medium text-slate-600 underline underline-offset-2">
                                    {item.title}
                                </Link>
                            {/snippet}
                        </BreadcrumbLink>
                    {/if}
                </Item>
                {#if index !== breadcrumbs.length - 1}
                    <BreadcrumbSeparator class="shrink-0" />
                {/if}
            {/each}
        </BreadcrumbList>
    </div>
</Breadcrumb>
{/if}

<style>
    .breadcrumb-scroll {
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .breadcrumb-scroll.desborda {
        -webkit-mask-image: linear-gradient(to right, transparent 0%, black 2rem);
        mask-image: linear-gradient(to right, transparent 0%, black 2rem);
    }
    .breadcrumb-scroll::-webkit-scrollbar {
        display: none;
    }
</style>
