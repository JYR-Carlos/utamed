<script lang="ts">
    /**
     * Pie del menú lateral (ayudante y administrador): la persona abre su menú
     * (perfil, contraseña, salir) y, al lado, la puerta cierra la sesión con un
     * clic, como en el menú del estudiante y del docente.
     */
    import UserInfo from '@/components/custom/common/UserInfo.svelte';
    import UserMenuContent from '@/components/custom/common/UserMenuContent.svelte';
    import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
    import { cerrarSesion } from '@/lib/sesion';
    import { page } from '@inertiajs/svelte';
    import { ChevronsUpDown, LogOut } from 'lucide-svelte';

    const user = $derived($page.props.auth.user);
</script>

<div class="flex w-full items-center gap-1">
    <DropdownMenu>
        <DropdownMenuTrigger class="min-w-0 flex-1">
            <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer w-full text-left group">
                <UserInfo {user} />
                <ChevronsUpDown class="ml-auto size-4 shrink-0 text-slate-400 group-hover:text-slate-600" />
            </div>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-64 rounded-xl shadow-lg border-slate-100 p-1" side="bottom" align="end" sideOffset={8}>
            <UserMenuContent {user} />
        </DropdownMenuContent>
    </DropdownMenu>
    <button
        type="button"
        onclick={cerrarSesion}
        aria-label="Cerrar sesión"
        title="Cerrar sesión"
        class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 lg:size-10"
    >
        <LogOut class="size-[18px]" aria-hidden="true" />
    </button>
</div>
