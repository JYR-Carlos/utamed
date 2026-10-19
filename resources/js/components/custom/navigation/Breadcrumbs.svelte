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
    <!-- Sin scroll lateral: la ruta se recorta con puntos suspensivos al final del
         último tramo (hallazgo #19). Antes se anclaba a la derecha y se cortaba
         el comienzo («…iseño de un Sitio Web»). El título completo va en `title`. -->
    <div class="breadcrumb-recorte min-w-0 overflow-hidden">
        <BreadcrumbList class="flex-nowrap min-w-0">
            {#each breadcrumbs as item, index (index)}
                {#if index === breadcrumbs.length - 1}
                    <Item class="min-w-0 flex-1">
                        <BreadcrumbPage class="block truncate text-sm font-medium text-[#1A1A24]" title={item.title}>
                            {item.title}
                        </BreadcrumbPage>
                    </Item>
                {:else}
                    <Item class="min-w-0 shrink-0 max-w-[16rem]">
                        <BreadcrumbLink>
                            {#snippet child({ props })}
                                <Link {...props} href={item.href ?? '#'} title={item.title} class="{props.class} block truncate py-2 text-sm font-medium text-slate-600 no-underline hover:text-uta-blue hover:underline underline-offset-2">
                                    {item.title}
                                </Link>
                            {/snippet}
                        </BreadcrumbLink>
                    </Item>
                    <BreadcrumbSeparator class="shrink-0" />
                {/if}
            {/each}
        </BreadcrumbList>
    </div>
</Breadcrumb>
{/if}
