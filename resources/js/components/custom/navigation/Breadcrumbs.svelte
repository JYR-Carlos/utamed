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

    function onWheel(e: WheelEvent) {
        const el = e.currentTarget as HTMLElement;
        if (e.deltaY === 0) return;
        e.preventDefault();
        el.scrollLeft += e.deltaY;
    }
</script>

<Breadcrumb>
    <!-- direction:rtl ancla el scroll al lado derecho (muestra el último item primero).
         La máscara aplica degradado en ambos bordes para indicar scroll. -->
    <div class="breadcrumb-scroll" style="direction: rtl;" onwheel={onWheel}>
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
        -webkit-mask-image: linear-gradient(
            to right,
            transparent 0%,
            black 2rem,
            black calc(100% - 2rem),
            transparent 100%
        );
        mask-image: linear-gradient(
            to right,
            transparent 0%,
            black 2rem,
            black calc(100% - 2rem),
            transparent 100%
        );
    }
    .breadcrumb-scroll::-webkit-scrollbar {
        display: none;
    }
</style>
