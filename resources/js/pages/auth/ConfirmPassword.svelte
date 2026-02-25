<script lang="ts">
    /**
     * Página para confirmar contraseña del usuario autenticado.
     * 
     * Requerida antes de acceder a áreas sensibles del sistema.
     * El usuario debe re-ingresar su contraseña para proceder.
     * 
     * Características:
     * - Campo de contraseña para verificación
     * - Integración con Fortify
     * - Validación y manejo de errores
     */
    import InputError from '@/components/custom/common/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import AuthLayout from '@/layouts/AuthLayout.svelte';
    import type { BaseFormSnippetProps } from '@/types/forms';
    import { Form, useForm } from '@inertiajs/svelte';
    import { LoaderCircle } from 'lucide-svelte';
    import password from '@/routes/password';
</script>

<svelte:head>
    <title>Confirm Password</title>
</svelte:head>

<AuthLayout title="Confirm your password" description="This is a secure area of the application. Please confirm your password before continuing.">
    <Form method="post" action={password.confirm.store().url} class="space-y-6">
        {#snippet children({ errors, processing }: BaseFormSnippetProps)}
            <div class="space-y-6">
                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        name="password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="current-password"
                        autofocus
                    />

                    <InputError message={errors.password} />
                </div>

                <div class="flex items-center">
                    <Button type="submit" class="w-full" disabled={processing}>
                        {#if processing}
                            <LoaderCircle class="h-4 w-4 animate-spin" />
                        {/if}
                        Confirm Password
                    </Button>
                </div>
            </div>
        {/snippet}
    </Form>
</AuthLayout>
