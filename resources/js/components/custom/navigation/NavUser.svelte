<script lang="ts">
    import UserInfo from '@/components/custom/common/UserInfo.svelte';
    import UserMenuContent from '@/components/custom/common/UserMenuContent.svelte';
    import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
    import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
    import { page } from '@inertiajs/svelte';
    import { ChevronsUpDown } from 'lucide-svelte';

    const user = $derived($page.props.auth.user);
</script>

<div class="w-full">
    <DropdownMenu>
        <DropdownMenuTrigger class="w-full">
            <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer w-full text-left group">
                <UserInfo {user} />
                <ChevronsUpDown class="ml-auto size-4 text-slate-400 group-hover:text-slate-600" />
            </div>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-64 rounded-xl shadow-lg border-slate-100 p-1" side="bottom" align="end" sideOffset={8}>
            <UserMenuContent {user} />
        </DropdownMenuContent>
    </DropdownMenu>
</div>
