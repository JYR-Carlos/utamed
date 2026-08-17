<script lang="ts">
    /**
     * Página para establecer nueva contraseña.
     * 
     * Permite a usuarios restablecer su contraseña usando enlace
     * recibido por email con token de validación.
     * 
     * Características:
     * - Recibe token y email del enlace
     * - Validación de nueva contraseña y confirmación
     * - Integración con Fortify
     * - Mensajes de error específicos
     */
    import AuthBase from '@/layouts/auth/AuthSplitLayout.svelte';
    import type { BaseFormSnippetProps } from '@/types/forms';
    import { Form, Link } from '@inertiajs/svelte';
    import { Eye, EyeOff, LoaderCircle } from 'lucide-svelte';
    import NewPasswordController from '@/actions/Laravel/Fortify/Http/Controllers/NewPasswordController';
    import InputError from '@/components/custom/common/InputError.svelte';
    import { Button } from '@/components/ui/button';

    /**
     * Props recibidas del URL/servidor.
     */
    interface Props {
        /** Token de reset del URL */
        token: string;
        /** Email del usuario del URL */
        email: string;
    }

    let { token, email }: Props = $props();
    let showPassword = $state(false);
</script>

<svelte:head>
    <title>Restablecer Contraseña | UTAmed</title>
</svelte:head>

<AuthBase title="Restablecer Contraseña" description="Ingresa tu nueva contraseña a continuación">
    <Form
        {...NewPasswordController.store.form()}
        transform={(data) => ({ ...data, token, email })}
        resetOnSuccess={['password', 'password_confirmation']}
        className="flex flex-col gap-8"
    >
        {#snippet children({ errors, processing }: BaseFormSnippetProps)}
            <div class="grid gap-8">
                <!-- Email Field (Readonly) -->
                <div class="relative group opacity-60">
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value={email}
                        readonly
                        class="peer w-full bg-transparent border-0 border-b border-border py-2 text-foreground focus:ring-0 transition-all"
                    />
                    <label 
                        for="email" 
                        class="absolute left-0 -top-4 text-xs text-muted-foreground transition-all pointer-events-none"
                    >
                        Correo Electrónico
                    </label>
                </div>

                <!-- Password Field -->
                <div class="relative group">
                    <input
                        id="password"
                        name="password"
                        type={showPassword ? "text" : "password"}
                        required
                        autofocus
                        tabindex={1}
                        autocomplete="new-password"
                        placeholder=" "
                        class="peer w-full bg-transparent border-0 border-b border-border py-2 pr-10 text-foreground focus:ring-0 focus:border-primary transition-all placeholder-transparent"
                    />
                    <label 
                        for="password" 
                        class="absolute left-0 -top-full text-xs text-muted-foreground peer-placeholder-shown:text-base peer-placeholder-shown:top-2 peer-focus:-top-4 peer-focus:text-xs peer-focus:text-primary transition-all pointer-events-none"
                    >
                        Nueva Contraseña
                    </label>
                    <InputError message={errors.password} class="mt-2 text-xs" />
                </div>

                <!-- Confirm Password Field -->
                <div class="relative group">
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type={showPassword ? "text" : "password"}
                        required
                        tabindex={2}
                        autocomplete="new-password"
                        placeholder=" "
                        class="peer w-full bg-transparent border-0 border-b border-border py-2 pr-10 text-foreground focus:ring-0 focus:border-primary transition-all placeholder-transparent"
                    />
                    <label 
                        for="password_confirmation" 
                        class="absolute left-0 -top-full text-xs text-muted-foreground peer-placeholder-shown:text-base peer-placeholder-shown:top-2 peer-focus:-top-4 peer-focus:text-xs peer-focus:text-primary transition-all pointer-events-none"
                    >
                        Confirmar Nueva Contraseña
                    </label>
                    <button 
                        type="button" 
                        class="absolute right-0 top-2 text-muted-foreground hover:text-foreground transition-colors"
                        onclick={() => showPassword = !showPassword}
                    >
                        {#if showPassword}
                            <EyeOff size={18} />
                        {:else}
                            <Eye size={18} />
                        {/if}
                    </button>
                    <InputError message={errors.password_confirmation} class="mt-2 text-xs" />
                </div>

                <Button 
                    type="submit" 
                    class="mt-4 w-full bg-primary hover:bg-primary/90 active:scale-[0.98] text-primary-foreground font-semibold py-6 rounded-2xl shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2" 
                    tabindex={3} 
                    disabled={processing}
                >
                    {#if processing}
                        <LoaderCircle class="h-4 w-4 animate-spin" />
                    {/if}
                    Restablecer Contraseña
                </Button>
            </div>
        {/snippet}
    </Form>
</AuthBase>

