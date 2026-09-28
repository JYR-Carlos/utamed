<script lang="ts">
    import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator, Item } from '@/components/ui/breadcrumb';
    import { Link } from '@inertiajs/svelte';

    interface BreadcrumbItem {
        title: string;
        href?: string;
    }

    interface Props {
        breadcrumbs: BreadcrumbItem[];
    }

    let { breadcrumbs }: Props = $props();

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
                        <BreadcrumbPage>
                            {item.title}
                        </BreadcrumbPage>
                    {:else}
                        <BreadcrumbLink>
                            {#snippet child({ props })}
                                <Link {...props} href={item.href ?? '#'}>
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
