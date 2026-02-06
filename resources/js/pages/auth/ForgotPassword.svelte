<script lang="ts">
    /**
     * Página para solicitar enlace de recuperación de contraseña.
     * 
     * Permite a usuarios solicitar un enlace de reset enviado por email
     * si no recuerdan su contraseña.
     * 
     * Características:
     * - Campo de email para identificar usuario
     * - Integración con Fortify para envío de enlace
     * - Mensaje de estado post-envío
     * - Validación de email
     */
    import PasswordResetLinkController from '@/actions/Laravel/Fortify/Http/Controllers/PasswordResetLinkController';
    import InputError from '@/components/custom/common/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Spinner } from '@/components/ui/spinner';
    import AuthBase from '@/layouts/auth/AuthSplitLayout.svelte';
    import { login } from '@/routes';
    import type { BaseFormSnippetProps } from '@/types/forms';
    import { Form, Link } from '@inertiajs/svelte';
    import { LoaderCircle } from 'lucide-svelte';

    /**
     * Props recibidas del servidor.
     */
    interface Props {
        /** Mensaje de estado (ej: "Enlace enviado") */
        status?: string;
    }

    let { status }: Props = $props();
</script>

<svelte:head>
    <title>¿Olvidaste tu contraseña? | UTAMed</title>
</svelte:head>

<AuthBase title="¿Olvidaste tu contraseña?" description="Ingresa tu correo para recibir un enlace de recuperación">
    {#if status}
        <div class="mb-6 text-sm font-medium text-green-600 bg-green-600/10 p-4 rounded-xl border border-green-600/20">
            {status}
        </div>
    {/if}

    <Form {...PasswordResetLinkController.store.form()} className="flex flex-col gap-8">
        {#snippet children({ errors, processing }: BaseFormSnippetProps)}
            <div class="grid gap-8">
                <p class="text-sm text-muted-foreground leading-relaxed">
                    ¿Olvidaste tu contraseña? No hay problema. Solo dinos tu dirección de correo electrónico y te enviaremos un enlace para restablecerla que te permitirá elegir una nueva.
                </p>

                <!-- Email Field -->
                <div class="relative group">
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autofocus
                        tabindex={1}
                        autocomplete="email"
                        placeholder=" "
                        class="peer w-full bg-transparent border-0 border-b border-border py-2 text-foreground focus:ring-0 focus:border-primary transition-all placeholder-transparent"
                    />
                    <label 
                        for="email" 
                        class="absolute left-0 -top-full text-xs text-muted-foreground peer-placeholder-shown:text-base peer-placeholder-shown:top-2 peer-focus:-top-4 peer-focus:text-xs peer-focus:text-primary transition-all pointer-events-none"
                    >
                        Correo Electrónico
                    </label>
                    <InputError message={errors.email} class="mt-2 text-xs" />
                </div>

                <div class="flex flex-col gap-4">
                    <Button 
                        type="submit" 
                        class="w-full bg-primary hover:bg-primary/90 active:scale-[0.98] text-primary-foreground font-semibold py-6 rounded-2xl shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2" 
                        tabindex={2} 
                        disabled={processing}
                    >
                        {#if processing}
                            <LoaderCircle class="h-4 w-4 animate-spin" />
                        {/if}
                        Enviar enlace de recuperación
                    </Button>

                    <Link 
                        href={login().url} 
                        class="text-center text-xs text-muted-foreground hover:text-primary transition-colors py-2"
                    >
                        Volver al inicio de sesión
                    </Link>
                </div>
            </div>
        {/snippet}
    </Form>
</AuthBase>

