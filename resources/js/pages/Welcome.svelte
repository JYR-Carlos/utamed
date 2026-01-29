<script lang="ts">
    import { dashboard, login } from '@/routes';
    import { Link, page } from '@inertiajs/svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';

    interface Props {
        canRegister: boolean;
    }

    let { canRegister }: Props = $props();
    let user = $derived($page.props.auth.user);
</script>

<svelte:head>
    <title>UtaMed</title>
</svelte:head>

<div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-white p-6">
    <div class="w-full max-w-md space-y-8 bg-white dark:bg-gray-900 p-8 rounded-lg shadow-lg border border-gray-200 dark:border-gray-800 text-center">
        
        <div class="flex flex-col items-center gap-4">
             <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-indigo-600">
                <AppLogoIcon class="h-10 w-10 text-white fill-current" />
            </div>
            <h1 class="text-3xl font-bold tracking-tight">UtaMed</h1>
            <p class="text-muted-foreground">Sistema de Gestión Académica</p>
        </div>

        <div class="flex flex-col gap-4">
             {#if user}
                <Link
                    href={dashboard()}
                    class="inline-flex h-10 items-center justify-center rounded-md bg-indigo-600 px-8 text-sm font-medium text-white shadow transition-colors hover:bg-indigo-700 w-full"
                >
                    Ir al Dashboard
                </Link>
            {:else}
                <Link
                    href={login()}
                    class="inline-flex h-10 items-center justify-center rounded-md bg-indigo-600 px-8 text-sm font-medium text-white shadow transition-colors hover:bg-indigo-700 w-full"
                >
                    Iniciar Sesión
                </Link>
            {/if}
        </div>
        
        <div class="text-xs text-muted-foreground mt-8">
            &copy; 2026 Universidad de Tarapacá. Todos los derechos reservados.
        </div>
    </div>
</div>
