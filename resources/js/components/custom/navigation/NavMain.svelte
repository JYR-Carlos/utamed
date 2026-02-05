<script lang="ts">
    import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
    import type { NavItem } from '@/types';
    import { Link, page } from '@inertiajs/svelte';

    interface Props {
        items: NavItem[];
    }

    let { items = [] }: Props = $props();
</script>

<SidebarGroup class="px-0 py-0 pt-2">
    <SidebarMenu class="space-y-2">
        {#each items as item (item.title)}
            <SidebarMenuItem class="w-full">
                <Link href={item.href} class="block w-full">
                    <div 
                        class="relative px-3 py-2.5 mx-2 rounded-lg transition-all duration-200 flex items-center gap-3 group-data-[state=collapsed]:px-2 group-data-[state=collapsed]:mx-0 {item.href === $page.url 
                            ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30 group-data-[state=collapsed]:bg-blue-600/0 group-data-[state=collapsed]:shadow-none group-data-[state=collapsed]:text-slate-600' 
                            : 'text-slate-600 hover:bg-slate-100 group-data-[state=collapsed]:hover:bg-transparent'}"
                    >
                        {#if item.icon}
                            {@const Icon = item.icon}
                            <div class="flex-shrink-0">
                                <Icon class="h-5 w-5" />
                            </div>
                        {/if}
                        <span class="font-medium text-sm md:text-base lg:text-base flex-1 group-data-[state=collapsed]:hidden">{item.title}</span>
                    </div>
                </Link>
            </SidebarMenuItem>
        {/each}
    </SidebarMenu>
</SidebarGroup>
