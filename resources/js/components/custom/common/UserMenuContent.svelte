<script lang="ts">
    import UserInfo from '@/components/custom/common/UserInfo.svelte';
    import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
    import { edit } from '@/routes/profile/index';
    import type { User } from '@/types';
    import { logout } from '@/routes';
    import { router } from '@inertiajs/svelte';
    import { KeyRound, LogOut, User2 } from 'lucide-svelte';

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

    const profileUrl = $derived(user.docente ? '/docente/perfil' : '/estudiante/perfil');
</script>

<DropdownMenuLabel class="p-0 font-normal">
    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
        <UserInfo {user} showEmail={true} />
    </div>
</DropdownMenuLabel>
<DropdownMenuSeparator />
<DropdownMenuItem onSelect={() => router.visit(profileUrl)} class="cursor-pointer">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-2 h-4 w-4">
    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
    </svg>
    <span>Ver Perfil</span>
</DropdownMenuItem>
<DropdownMenuItem onSelect={() => router.visit('/settings/password')} class="cursor-pointer">
    <KeyRound class="mr-2 h-4 w-4" />
    <span>Cambiar contraseña</span>
</DropdownMenuItem>
<DropdownMenuSeparator />
<DropdownMenuItem onSelect={handleLogout} class="cursor-pointer">
    <LogOut class="mr-2 h-4 w-4" />
    <span>Cerrar Sesión</span>
</DropdownMenuItem>
