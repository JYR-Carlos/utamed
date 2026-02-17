<script lang="ts">
    import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
    import { useInitials } from '@/hooks/useInitials';
    import type { User } from '@/types';

    interface Props {
        user: User;
        showEmail?: boolean;
    }

    let { user, showEmail = false }: Props = $props();

    const { getInitials } = useInitials();

    let displayName = $derived(user.nombre1 && user.apellido1 ? `${user.nombre1} ${user.apellido1}` : (user.nombre1 || user.username || 'Usuario'));
    let showAvatar = $derived(user.avatar && user.avatar !== '');
</script>

<Avatar class="h-8 w-8 overflow-hidden rounded-full">
    {#if showAvatar}
        <AvatarImage src={user.avatar} alt={displayName} />
    {:else}
        <AvatarFallback class="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
            {getInitials(displayName)}
        </AvatarFallback>
    {/if}
</Avatar>

<div class="grid flex-1 text-left text-sm leading-tight">
    <span class="truncate font-bold text-slate-900">{displayName}</span>
    <span class="truncate text-xs text-slate-500">{user.email}</span>
</div>
