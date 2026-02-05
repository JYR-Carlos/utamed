<script lang="ts">
    /**
     * Página de configuración de apariencia.
     * 
     * Permite a usuarios personalizar la apariencia de la interfaz.
     * 
     * Características:
     * - Selector de tema (claro/oscuro/sistema)
     * - Configuración de preferencias visuales
     * - Persistencia de configuración en localStorage
     */
    import AppearanceTabs from '@/components/custom/common/AppearanceTabs.svelte';
    import HeadingSmall from '@/components/custom/common/HeadingSmall.svelte';
    import { type BreadcrumbItem } from '@/types';

    import AppLayout from '@/layouts/AppLayout.svelte';
    import SettingsLayout from '@/layouts/settings/Layout.svelte';

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            title: 'Appearance settings',
            href: '/settings/appearance',
        },
    ];
</script>

<svelte:head>
    <title>Appearance Settings</title>
</svelte:head>

<AppLayout breadcrumbs={breadcrumbItems}>
    <SettingsLayout>
        <div class="space-y-6">
            <HeadingSmall title="Appearance settings" description="Update your account's appearance settings" />
            <AppearanceTabs />
        </div>
    </SettingsLayout>
</AppLayout>
