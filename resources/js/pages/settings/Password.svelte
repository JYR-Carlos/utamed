<script lang="ts">
    /**
     * Página de configuración de contraseña.
     * 
     * Permite a usuarios cambiar su contraseña actual por una nueva.
     * 
     * Características:
     * - Validación de contraseña actual
     * - Confirmación de nueva contraseña
     * - Requisitos de la contraseña a la vista mientras se escribe (RequisitosPassword)
     * - Manejo de errores con focus automático en campos con error
     * - Mensaje de éxito tras actualización
     * - Reset de formulario tras éxito o error
     *
     * El cambio obligatorio (primer ingreso o clave vencida) no pasa por aquí:
     * el controlador lo manda a `auth/CambioPasswordObligatorio`, que no usa el
     * layout de la aplicación.
     */
    import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
    import HeadingSmall from '@/components/custom/common/HeadingSmall.svelte';
    import InputError from '@/components/custom/common/InputError.svelte';
    import RequisitosPassword from '@/components/custom/common/RequisitosPassword.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import SettingsLayout from '@/layouts/settings/Layout.svelte';
    import { edit } from '@/routes/user-password';
    import { type BreadcrumbItem } from '@/types';
    import type { ProfileFormSnippetProps } from '@/types/forms';
    import { Form } from '@inertiajs/svelte';
    import { fade } from 'svelte/transition';

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            title: 'Cambiar contraseña',
            href: edit().url,
        },
    ];

    let passwordInput = $state(null as unknown as HTMLInputElement);
    let currentPasswordInput = $state(null as unknown as HTMLInputElement);
    let nuevaPassword = $state('');
    let confirmacion = $state('');
</script>

<svelte:head>
    <title>Cambiar contraseña</title>
</svelte:head>

<AppLayout breadcrumbs={breadcrumbItems}>
    <SettingsLayout>
        <div class="space-y-6">
            <HeadingSmall title="Cambiar contraseña" description="Usa una contraseña larga y difícil de adivinar para mantener tu cuenta segura" />

            <Form
                {...PasswordController.update.form()}
                options={{ preserveScroll: true }}
                onSuccess={() => {
                    nuevaPassword = '';
                    confirmacion = '';
                }}
                onError={(errors) => {
                    nuevaPassword = '';
                    confirmacion = '';
                    if (errors.password) {
                        passwordInput?.focus();
                    }

                    if (errors.current_password) {
                        currentPasswordInput?.focus();
                    }
                }}
                resetOnSuccess
                resetOnError={['password', 'password_confirmation', 'current_password']}
                class="space-y-6"
            >
                {#snippet children({ errors, processing, recentlySuccessful }: ProfileFormSnippetProps)}
                    <div class="grid gap-2">
                        <Label for="current_password">Contraseña actual</Label>
                        <Input
                            ref={currentPasswordInput}
                            name="current_password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="current-password"
                            placeholder="Contraseña actual"
                        />

                        <InputError message={errors.current_password} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">Nueva contraseña</Label>
                        <Input
                            ref={passwordInput}
                            name="password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Nueva contraseña"
                            bind:value={nuevaPassword}
                        />

                        <RequisitosPassword password={nuevaPassword} confirmacion={confirmacion} />

                        <InputError message={errors.password} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirma la nueva contraseña</Label>
                        <Input
                            name="password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Confirma la nueva contraseña"
                            bind:value={confirmacion}
                        />

                        <InputError message={errors.password_confirmation} />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>Guardar contraseña</Button>

                        {#if recentlySuccessful}
                            <p class="text-sm text-neutral-600" transition:fade={{ duration: 150 }}>Guardado.</p>
                        {/if}
                    </div>
                {/snippet}
            </Form>
        </div>
    </SettingsLayout>
</AppLayout>
