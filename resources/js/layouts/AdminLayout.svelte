<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { page } from '@inertiajs/svelte';

    interface Props {
        children: any;
    }

    let { children }: Props = $props();
    let authRoles = $derived(($page.props.auth.roles as string[]) || []);

    const navigation = [
        {
            name: 'Principal',
            items: [
                { name: 'Inicio', href: '/dashboard', icon: '🏠', roles: ['*'] }, // Visible to all
            ],
            roles: ['*'],
        },
        {
            name: 'Gestión Académica',
            items: [
                { name: 'Facultades', href: '/admin/facultades', icon: '🏛️', roles: ['Super Admin', 'Administrador'] },
                { name: 'Departamentos', href: '/admin/departamentos', icon: '🏢', roles: ['Super Admin', 'Administrador'] },
                { name: 'Carreras', href: '/admin/carreras', icon: '🎓', roles: ['Super Admin', 'Administrador'] },
                { name: 'Planes', href: '/admin/planes', icon: '📋', roles: ['Super Admin', 'Administrador'] },
                { name: 'Asignaturas', href: '/admin/asignaturas', icon: '📚', roles: ['Super Admin', 'Administrador'] },
            ],
            roles: ['Super Admin', 'Administrador'],
        },
        {
            name: 'Gestión de Cursos',
            items: [
                { name: 'Cursos', href: '/admin/cursos', icon: '👨‍🏫', roles: ['Super Admin', 'Administrador'] },
            ],
            roles: ['Super Admin', 'Administrador', 'Docente'],
        },
        {
            name: 'Usuarios',
            items: [
                { name: 'Usuarios', href: '/admin/usuarios', icon: '👥', roles: ['Super Admin', 'Administrador'] },
            ],
            roles: ['Super Admin', 'Administrador'],
        },
        // Role specific sections
        {
            name: 'Estudiante',
            items: [
                { name: 'Mis Notas', href: '#', icon: '📝', roles: ['Super Admin', 'Estudiante'] },
                { name: 'Horario', href: '#', icon: '📅', roles: ['Super Admin', 'Estudiante'] },
            ],
            roles: ['Super Admin', 'Estudiante'],
        },
        {
            name: 'Docente',
            items: [
                { name: 'Mis Asignaturas', href: '/docente/cursos', icon: '📚', roles: ['Super Admin', 'Docente'] },
            ],
            roles: ['Super Admin', 'Docente'],
        }
    ];

    function hasRole(requiredRoles: string[]): boolean {
        if (requiredRoles.includes('*')) return true;
        
        // Super Admin has access to everything
        if (authRoles.includes('Super Admin')) return true;

        return requiredRoles.some(role => authRoles.includes(role));
    }

    let filteredNavigation = $derived(navigation.map(section => ({
        ...section,
        items: section.items.filter(item => hasRole(item.roles))
    })).filter(section => section.items.length > 0)); 

    function isActive(href: string): boolean {
        return $page.url.startsWith(href);
    }

    function navigate(href: string) {
        router.visit(href);
    }

    function handleLogout() {
        router.post('/logout');
    }
</script>

<div class="admin-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1 class="sidebar-title">Panel Admin</h1>
            <p class="sidebar-subtitle">UTAMED</p>
        </div>

        <nav class="sidebar-nav">
            {#each filteredNavigation as section}
                <div class="nav-section">
                    <h2 class="nav-section-title">{section.name}</h2>
                    <ul class="nav-list">
                        {#each section.items as item}
                            <li>
                                <button
                                    onclick={() => navigate(item.href)}
                                    class="nav-item"
                                    class:active={isActive(item.href)}
                                >
                                    <span class="nav-icon">{item.icon}</span>
                                    <span class="nav-text">{item.name}</span>
                                </button>
                            </li>
                        {/each}
                    </ul>
                </div>
            {/each}

            <!-- Logout Section -->
            <div class="nav-section mt-auto border-t border-white/10 pt-4">
                <ul class="nav-list">
                    <li>
                        <button
                            onclick={handleLogout}
                            class="nav-item text-red-400 hover:text-red-300 hover:bg-red-500/10"
                        >
                            <span class="nav-icon">🚪</span>
                            <span class="nav-text">Cerrar Sesión</span>
                        </button>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        {@render children()}
    </main>
</div>

<style>
    .admin-layout {
        display: flex;
        min-height: 100vh;
        background: #f9fafb;
    }

    .sidebar {
        width: 280px;
        background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        color: white;
        display: flex;
        flex-direction: column;
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
    }

    .sidebar-header {
        padding: 2rem 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .sidebar-subtitle {
        font-size: 0.75rem;
        color: #94a3b8;
        margin: 0.25rem 0 0 0;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .sidebar-nav {
        flex: 1;
        padding: 1.5rem 0;
    }

    .nav-section {
        margin-bottom: 2rem;
    }

    .nav-section-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0 1.5rem;
        margin: 0 0 0.75rem 0;
    }

    .nav-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        width: 100%;
        padding: 0.75rem 1.5rem;
        background: transparent;
        border: none;
        color: #cbd5e1;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        text-align: left;
        border-left: 3px solid transparent;
    }

    .nav-item:hover {
        background: rgba(255, 255, 255, 0.05);
        color: white;
    }

    .nav-item.active {
        background: rgba(59, 130, 246, 0.1);
        color: #60a5fa;
        border-left-color: #3b82f6;
    }

    .nav-icon {
        font-size: 1.25rem;
        width: 1.5rem;
        text-align: center;
    }

    .nav-text {
        flex: 1;
    }

    .main-content {
        flex: 1;
        margin-left: 280px;
        min-height: 100vh;
    }

    /* Scrollbar styling for sidebar */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.1);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 3px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.3);
    }
</style>
