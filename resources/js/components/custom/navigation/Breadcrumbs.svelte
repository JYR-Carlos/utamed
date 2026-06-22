<script lang="ts">
    import { Breadcrumb, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator, Item } from '@/components/ui/breadcrumb';
    import { Link } from '@inertiajs/svelte';

    interface BreadcrumbItem {
        title: string;
        href?: string;
    }

    interface Props {
        breadcrumbs: BreadcrumbItem[];
        maxChars?: number;
    }

    let { breadcrumbs, maxChars = 20 }: Props = $props();

    function truncate(text: string, max: number): string {
        return text.length > max ? text.slice(0, max).trimEnd() + '…' : text;
    }
</script>

<Breadcrumb>
    <!-- direction:rtl en el wrapper externo ancla el scroll al lado derecho
         (muestra el último item / página actual primero) -->
    <div class="overflow-x-auto scrollbar-none" style="direction: rtl;">
        <BreadcrumbList class="flex-nowrap w-max" style="direction: ltr;">
            {#each breadcrumbs as item, index (index)}
                <Item class="shrink-0">
                    {#if index === breadcrumbs.length - 1}
                        <BreadcrumbPage title={item.title}>
                            {truncate(item.title, maxChars)}
                        </BreadcrumbPage>
                    {:else}
                        <BreadcrumbLink>
                            {#snippet child({ props }: { props: Record<string, unknown> })}
                                <Link {...props} href={item.href ?? '#'} title={item.title}>
                                    {truncate(item.title, maxChars)}
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
