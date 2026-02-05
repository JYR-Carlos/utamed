<script lang="ts">
    /**
     * Página de login del sistema UtaMed.
     * 
     * Formulario de autenticación que permite a usuarios ingresar sus
     * credenciales (email/username y contraseña).
     * 
     * Características:
     * - Campo de email o username para flexibilidad en login
     * - Opción de "Recuérdame" para mantener sesión
     * - Enlace a recuperación de contraseña
     * - Validación de errores con display de mensajes
     * - Spinner de carga durante autenticación
     * - Soporte para login con Fortify (Laravel)
     */
    import AuthenticatedSessionController from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
    import InputError from '@/components/custom/common/InputError.svelte';
    import TextLink from '@/components/custom/common/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import AuthBase from '@/layouts/AuthLayout.svelte';
    import { register } from '@/routes';
    import { request } from '@/routes/password';
    import type { BaseFormSnippetProps } from '@/types/forms';
    import { Form } from '@inertiajs/svelte';

    /**
     * Props recibidas del servidor.
     */
    interface Props {
        /** Mensaje de estado (ej: "Email verificado") */
        status?: string;
        /** Si la contraseña puede ser recuperada */
        canResetPassword: boolean;
        /** Si está habilitado el registro de usuarios */
        canRegister: boolean;
    }

    let { status, canResetPassword, canRegister }: Props = $props();
</script>

<svelte:head>
    <title>Login</title>
</svelte:head>

<AuthBase title="Portal Académico" description="Ingresa tus credenciales para acceder">
    {#if status}
        <div class="mb-4 text-center text-sm font-medium text-green-600">
            {status}
        </div>
    {/if}

    <Form {...AuthenticatedSessionController.store.form()} resetOnSuccess={['password']} className="flex flex-col gap-6">
        {#snippet children({ errors, processing }: BaseFormSnippetProps)}
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email" class="text-gray-900">Email o Usuario</Label>
                    <Input
                        id="email"
                        name="email"
                        type="text"
                        required
                        autofocus
                        tabindex={1}
                        autocomplete="username"
                        placeholder="ej. usuario o rut"
                        class="bg-white text-gray-900 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 dark:bg-white dark:text-gray-900 dark:border-gray-300 dark:placeholder:text-gray-400"
                    />
                    <InputError message={errors.email} />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="text-gray-900">Contraseña</Label>
                        {#if canResetPassword}
                            <TextLink href={request().url} class="text-sm text-indigo-600 hover:text-indigo-500" tabindex={5}>¿Olvidaste tu contraseña?</TextLink>
                        {/if}
                    </div>
                    <Input
                        id="password"
                        name="password"
                        type="password"
                        required
                        tabindex={2}
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="bg-white text-gray-900 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 dark:bg-white dark:text-gray-900 dark:border-gray-300 dark:placeholder:text-gray-400"
                    />
                    <InputError message={errors.password} />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" tabindex={3} />
                        <span>Recordarme</span>
                    </Label>
                </div>

                <Button type="submit" class="mt-4 w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 rounded-md transition-colors" tabindex={4} disabled={processing}>
                    {#if processing}
                        <Spinner class="mr-2 h-4 w-4" />
                    {/if}
                    Iniciar Sesión
                </Button>
            </div>
        {/snippet}
    </Form>
</AuthBase>
