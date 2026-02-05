<script lang="ts">
    import Breadcrumbs from '@/components/custom/navigation/Breadcrumbs.svelte';
    import { SidebarTrigger } from '@/components/ui/sidebar';
    import type { BreadcrumbItem } from '@/types';
    import { Search, Bell, ChevronDown, LayoutGrid, Users, Building2, BookOpen, Settings, LogOut } from 'lucide-svelte';
    import { page, router } from '@inertiajs/svelte';
    import * as Command from '@/components/ui/command';
    import { onMount } from 'svelte';

    interface Props {
        breadcrumbs?: BreadcrumbItem[];
    }

    let { breadcrumbs = [] }: Props = $props();
    let user = $derived($page.props.auth.user);
    let roles = $derived(($page.props.auth.roles as string[]) || []);

    let openSearch = $state(false);

    onMount(() => {
        const handleKeydown = (e: KeyboardEvent) => {
            if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                openSearch = !openSearch;
            }
        };

        document.addEventListener('keydown', handleKeydown);
        return () => document.removeEventListener('keydown', handleKeydown);
    });

    const searchItems = [
        { group: 'Navegación', items: [
            { icon: LayoutGrid, label: 'Dashboard', href: '/dashboard' },
            { icon: Users, label: 'Usuarios', href: '/admin/usuarios' },
            { icon: Building2, label: 'Facultades', href: '/admin/facultades' },
            { icon: BookOpen, label: 'Asignaturas', href: '/admin/asignaturas' },
        ]},
        { group: 'Configuración', items: [
            { icon: Settings, label: 'Perfil', href: '/settings' },
            { icon: LogOut, label: 'Cerrar Sesión', action: () => router.post('/logout') },
        ]}
    ];

    function handleSelect(item: any) {
        openSearch = false;
        if (item.href) {
            router.visit(item.href);
        } else if (item.action) {
            item.action();
        }
    }
</script>

<header class="global-header">
    <div class="header-left">
        <SidebarTrigger class="sidebar-toggle" />
        {#if breadcrumbs.length > 0}
            <div class="breadcrumb-wrapper">
                <Breadcrumbs {breadcrumbs} />
            </div>
        {/if}
    </div>

    <div class="header-center">
        <button class="search-box cursor-text text-left" onclick={() => openSearch = true}>
            <Search size={18} class="search-icon" />
            <span class="search-input text-slate-400">Buscar en el sistema...</span>
            <span class="ml-auto hidden md:inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-400">
                <span class="text-[12px] leading-none">⌘</span> K
            </span>
        </button>
    </div>

    <!-- Search Dialog -->
    <Command.Dialog bind:open={openSearch}>
        <Command.Input placeholder="Escribe para buscar..." />
        <Command.List>
            <Command.Empty>No se encontraron resultados.</Command.Empty>
            {#each searchItems as group}
                <Command.Group heading={group.group}>
                    {#each group.items as item}
                        <Command.Item onSelect={() => handleSelect(item)} class="cursor-pointer">
                            <item.icon class="mr-2 h-4 w-4" />
                            <span>{item.label}</span>
                        </Command.Item>
                    {/each}
                </Command.Group>
            {/each}
        </Command.List>
    </Command.Dialog>

    <div class="header-right">
        <button class="icon-btn">
            <Bell size={20} />
            <span class="pulse-dot"></span>
        </button>

        <div class="user-pill group">
            <div class="avatar-sm">
                {user.nombre1?.[0] || user.username?.[0] || 'U'}
            </div>
            <div class="user-meta hidden md:flex">
                <span class="user-name">{user.nombre1 || user.username}</span>
                <span class="user-role">{roles[0] || 'Usuario'}</span>
            </div>
            <ChevronDown size={14} class="ml-1 text-slate-400 group-hover:text-slate-600 transition-colors" />
        </div>
    </div>
</header>

<style>
    .global-header {
        height: 64px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 1.5rem;
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        position: sticky;
        top: 0;
        z-index: 30;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 1;
        min-width: 0;
    }

    .header-center {
        flex: 0 1 400px;
        display: flex;
        justify-content: center;
        min-width: 300px;
    }

    .header-right {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        flex: 1;
        justify-content: flex-end;
        min-width: 200px;
    }

    .search-box {
        width: 100%;
        max-width: 400px;
        position: relative;
        display: flex;
        align-items: center;
        padding: 0.5rem 1rem;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        color: #94a3b8;
        transition: all 0.2s;
    }

    .search-box:hover {
        background: #f1f5f9;
        border-color: #e2e8f0;
    }

    .search-input {
        margin-left: 0.75rem;
        font-size: 0.875rem;
    }

    @media (min-width: 1024px) {
        .search-input {
            font-size: 0.95rem;
        }
    }

    .icon-btn {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        position: relative;
        cursor: pointer;
        transition: all 0.2s;
    }

    .icon-btn:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    .pulse-dot {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 8px;
        height: 8px;
        background: #ef4444;
        border-radius: 50%;
        border: 2px solid #fff;
    }

    .user-pill {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.375rem 0.75rem 0.375rem 0.375rem;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .user-pill:hover {
        background: #f1f5f9;
        border-color: #e2e8f0;
    }

    .avatar-sm {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #3b82f6;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
    }

    @media (min-width: 1024px) {
        .avatar-sm {
            width: 32px;
            height: 32px;
            font-size: 0.85rem;
        }
    }

    .user-meta {
        display: flex;
        flex-direction: column;
        line-height: 1;
    }

    .user-name {
        font-size: 0.8125rem;
        font-weight: 700;
        color: #1e293b;
    }

    .user-role {
        font-size: 0.625rem;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        margin-top: 0.125rem;
    }

    @media (min-width: 1024px) {
        .user-name {
            font-size: 0.875rem;
        }

        .user-role {
            font-size: 0.7rem;
        }
    }

</style>
