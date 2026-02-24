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
    import InputError from '@/components/custom/common/InputError.svelte';
    import TextLink from '@/components/custom/common/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import AuthBase from '@/layouts/auth/AuthSplitLayout.svelte';
    import { register, login } from '@/routes';
    import { request } from '@/routes/password';
    import type { BaseFormSnippetProps } from '@/types/forms';
    import { Form, useForm } from '@inertiajs/svelte';
    import { Eye, EyeOff } from 'lucide-svelte';

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
    let showPassword = $state(false);

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });
</script>

<svelte:head>
    <title>Iniciar Sesión | UTAMed</title>
</svelte:head>

<AuthBase title="Iniciar Sesión" description="Ingresa los detalles de tu cuenta">
    {#if status}
        <div class="mb-4 text-center text-sm font-medium text-green-600">
            {status}
        </div>
    {/if}

    <Form form={$form} method="post" action={login().url} class="flex flex-col gap-8">
        {#snippet children({ errors, processing }: BaseFormSnippetProps)}
            <div class="grid gap-6">
                <!-- Email/Username Field -->
                <div class="space-y-2">
                    <label 
                        for="email" 
                        class="text-sm font-medium text-muted-foreground ml-1"
                    >
                        Usuario o Correo
                    </label>
                    <div class="relative group">
                        <input
                            id="email"
                            name="email"
                            type="text"
                            required
                            autofocus
                            tabindex={1}
                            autocomplete="username"
                            placeholder="ejemplo@uta.cl"
                            bind:value={$form.data.email}
                            class="w-full bg-secondary/30 border border-border rounded-2xl px-5 py-4 text-foreground focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder:text-muted-foreground/50"
                        />
                    </div>
                    <InputError message={$form.errors.email} class="mt-2 text-xs" />
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center px-1">
                        <label 
                            for="password" 
                            class="text-sm font-medium text-muted-foreground"
                        >
                            Contraseña
                        </label>
                        {#if canResetPassword}
                            <TextLink href={request().url} class="text-xs text-muted-foreground hover:text-primary transition-colors" tabindex={5}>¿Olvidaste tu contraseña?</TextLink>
                        {/if}
                    </div>
                    <div class="relative group">
                        <input
                            id="password"
                            name="password"
                            type={showPassword ? "text" : "password"}
                            required
                            tabindex={2}
                            autocomplete="current-password"
                            placeholder="••••••••"  
                            bind:value={$form.data.password}
                            class="w-full bg-secondary/30 border border-border rounded-2xl px-5 py-4 pr-12 text-foreground focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder:text-muted-foreground/50"
                        />
                        <button 
                            type="button" 
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                            onclick={() => showPassword = !showPassword}
                        >
                            {#if showPassword}
                                <EyeOff size={20} />
                            {:else}
                                <Eye size={20} />
                            {/if}
                        </button>
                    </div>
                    <InputError message={$form.errors.password} class="mt-2 text-xs" />
                </div>

                <div class="flex items-center space-x-2 px-1">
                    <input 
                        type="hidden" 
                        name="remember" 
                        value="off"
                    />
                    <Checkbox 
                        id="remember" 
                        name="remember"
                        checked={Boolean($form.data.remember)}
                        on:change={(e) => { $form.data.remember = e.target.checked; }}
                        tabindex={3} 
                        class="border-border bg-transparent data-[state=checked]:bg-primary data-[state=checked]:border-primary" 
                    />
                    <Label for="remember" class="text-xs text-muted-foreground cursor-pointer hover:text-foreground">Mantenerme conectado</Label>
                </div>

                <Button 
                    type="submit" 
                    class="mt-2 w-full bg-primary hover:bg-primary/90 active:scale-[0.98] text-primary-foreground font-bold py-7 rounded-2xl shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2 text-base" 
                    tabindex={4} 
                    disabled={$form.processing}
                >
                    {#if $form.processing}
                        <Spinner class="h-4 w-4" />
                    {/if}
                    Entrar al Portal
                </Button>

                <div class="mt-6 p-6 rounded-2xl bg-secondary/20 border border-dashed border-border text-center space-y-3">
                    <p class="text-sm text-foreground font-medium">Información de Acceso</p>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Como parte de nuestra comunidad académica, tus credenciales han sido enviadas previamente a tu correo institucional.
                    </p>
                    <p class="text-xs font-medium text-muted-foreground">
                        ¿Problemas para entrar? <br/>
                        <a href="mailto:soporte@uta.cl" class="text-primary hover:underline transition-all">Contactar a soporte: soporte@uta.cl</a>
                    </p>
                </div>
            </div>
        {/snippet}
    </Form>
</AuthBase>

