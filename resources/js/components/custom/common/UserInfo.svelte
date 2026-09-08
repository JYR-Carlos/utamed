<script lang="ts">
    import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
    import { useInitials } from '@/hooks/useInitials';
    import { page } from '@inertiajs/svelte';
    import type { User } from '@/types';

    interface Props {
        user: User;
        showEmail?: boolean;
    }

    let { user, showEmail = false }: Props = $props();

    const { getInitials } = useInitials();

    let displayName = $derived(user.nombre1 && user.apellido1 ? `${user.nombre1} ${user.apellido1}` : (user.nombre1 || user.username || 'Usuario'));
    let showAvatar = $derived(user.avatar && user.avatar !== '');
    let isSuperAdmin = $derived(($page.props.auth?.is_super_admin as boolean) || false);
</script>

<Avatar class="h-8 w-8 overflow-hidden rounded-full">
    {#if showAvatar}
        <AvatarImage src={user.avatar} alt={displayName} />
    {:else}
        <AvatarFallback class="rounded-full bg-[#22213F]/10 text-[#22213F]">
            {getInitials(displayName)}
        </AvatarFallback>
    {/if}
</Avatar>

<div class="grid flex-1 text-left text-sm leading-tight min-w-0">
    <span class="flex items-center gap-1.5 min-w-0">
        <span class="truncate font-bold text-slate-900">{displayName}</span>
        {#if isSuperAdmin}
            <span
                class="shrink-0 rounded-full bg-red-50 border border-red-200 text-red-700 text-[9px] font-bold tracking-wide px-1.5 py-0.5 uppercase"
                >Super Admin</span
            >
        {/if}
    </span>
    {#if showEmail}
        <span class="truncate text-xs text-slate-500">{user.email}</span>
    {:else}
        <span class="truncate text-xs font-mono text-slate-500">{user.rut}</span>
    {/if}
</div>
