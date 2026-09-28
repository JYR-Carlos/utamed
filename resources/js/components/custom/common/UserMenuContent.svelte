<script lang="ts">
    import UserInfo from '@/components/custom/common/UserInfo.svelte';
    import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
    import { edit } from '@/routes/profile/index';
    import type { User } from '@/types';
    import { logout } from '@/routes';
    import { router } from '@inertiajs/svelte';
    import { LogOut } from 'lucide-svelte';

    interface Props {
        user: User;
    }

    let { user }: Props = $props();

    /*
     * El POST se despacha desde onSelect del ítem. Antes el ítem envolvía un
     * <Link as="button">: el primer clic lo consumía el menú (que se cerraba
     * y desmontaba el Link) y hacía falta un segundo clic para salir.
     */
    const handleLogout = () => {
        router.flushAll();
        router.post(logout.url());
    };
</script>

<DropdownMenuLabel class="p-0 font-normal">
    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
        <UserInfo {user} showEmail={true} />
    </div>
</DropdownMenuLabel>
<DropdownMenuSeparator />
<DropdownMenuItem onSelect={handleLogout} class="cursor-pointer">
    <LogOut class="mr-2 h-4 w-4" />
    <span>Cerrar Sesión</span>
</DropdownMenuItem>
