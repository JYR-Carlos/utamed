<script lang="ts">
    /**
     * Página de configuración de contraseña.
     * 
     * Permite a usuarios cambiar su contraseña actual por una nueva.
     * 
     * Características:
     * - Validación de contraseña actual
     * - Confirmación de nueva contraseña
     * - Validación de seguridad (longitud mínima)
     * - Manejo de errores con focus automático en campos con error
     * - Mensaje de éxito tras actualización
     * - Reset de formulario tras éxito o error
     * - Aviso cuando el cambio es obligatorio (primer ingreso o clave vencida)
     */
    import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import HeadingSmall from '@/components/custom/common/HeadingSmall.svelte';
    import InputError from '@/components/custom/common/InputError.svelte';
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

    interface Props {
        /**
         * Por qué el sistema está exigiendo el cambio, o null si la clave está
         * vigente. Lo decide el backend (`Usuario::motivoCambioPasswordObligatorio`),
         * que es el mismo criterio con el que el middleware bloquea la navegación:
         * si la vista lo recalculara por su cuenta, el aviso y el bloqueo podrían
         * discrepar.
         */
        motivoCambioObligatorio: 'primer_ingreso' | 'vencida' | null;
        /** Meses de vigencia de la política, para no repetir el "6" en el texto. */
        vigenciaMeses: number;
    }

    let { motivoCambioObligatorio, vigenciaMeses }: Props = $props();

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            title: 'Password Settings',
            href: edit().url,
        },
    ];

    let passwordInput = $state(null as unknown as HTMLInputElement);
    let currentPasswordInput = $state(null as unknown as HTMLInputElement);

    let avisoObligatorio = $derived(
        motivoCambioObligatorio === 'primer_ingreso'
            ? 'Estás usando la contraseña con la que se creó tu cuenta. Elige una nueva para continuar.'
            : motivoCambioObligatorio === 'vencida'
              ? `Han pasado más de ${vigenciaMeses} meses desde tu último cambio de contraseña. Elige una nueva para continuar.`
              : null,
    );
</script>

<svelte:head>
    <title>Password Settings</title>
</svelte:head>

<AppLayout breadcrumbs={breadcrumbItems}>
    <SettingsLayout>
        <div class="space-y-6">
            <HeadingSmall title="Update Password" description="Ensure your account is using a long, random password to stay secure" />

            {#if avisoObligatorio}
                <div
                    role="alert"
                    class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <p class="font-medium">Cambio de contraseña obligatorio</p>
                    <p class="mt-1">{avisoObligatorio}</p>
                    <p class="mt-1 text-amber-800 dark:text-amber-200/80">
                        Mientras tanto, el resto del sistema no está disponible.
                    </p>
                </div>
            {/if}

            <Form
                {...PasswordController.update.form()}
                options={{ preserveScroll: true }}
                onError={(errors) => {
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
                        <Label for="current_password">Current password</Label>
                        <Input
                            ref={currentPasswordInput}
                            name="current_password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="current-password"
                            placeholder="Current password"
                        />

                        <InputError message={errors.current_password} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">New password</Label>
                        <Input
                            ref={passwordInput}
                            name="password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="New password"
                        />

                        <InputError message={errors.password} />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirm password</Label>
                        <Input
                            name="password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Confirm password"
                        />

                        <InputError message={errors.password_confirmation} />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>Save Password</Button>

                        {#if recentlySuccessful}
                            <p class="text-sm text-neutral-600" transition:fade={{ duration: 150 }}>Saved.</p>
                        {/if}
                    </div>
                {/snippet}
            </Form>
        </div>
    </SettingsLayout>
</AppLayout>
