<script lang="ts">
    /**
     * Página de registro de nuevos usuarios.
     * 
     * Formulario de auto-registro que permite crear nueva cuenta
     * (aunque en UTAMED típicamente usuarios son creados por administradores).
     * 
     * Características:
     * - Validación de campos (nombre, email único, contraseña confirmada)
     * - Integración con Fortify de Laravel
     * - Manejo de errores de validación
     * - Spinner de carga durante procesamiento
     */
    import RegisteredUserController from '@/actions/Laravel/Fortify/Http/Controllers/RegisteredUserController';
    import InputError from '@/components/custom/common/InputError.svelte';
    import TextLink from '@/components/custom/common/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import AuthBase from '@/layouts/auth/AuthSplitLayout.svelte';
    import { login } from '@/routes';
    import type { BaseFormSnippetProps } from '@/types/forms';
    import { Form, Link } from '@inertiajs/svelte';
    import { Eye, EyeOff } from 'lucide-svelte';

    let showPassword = $state(false);
</script>

<svelte:head>
    <title>Registrarse | UTAmed</title>
</svelte:head>

<AuthBase title="Registrarse" description="Crea tu cuenta para unirte a UTAmed">
    <Form {...RegisteredUserController.store.form()} resetOnSuccess={['password', 'password_confirmation']} className="flex flex-col gap-6">
        {#snippet children({ errors, processing }: BaseFormSnippetProps)}
            <div class="grid gap-6">
                <!-- Name Field -->
                <div class="relative group">
                    <input
                        id="name"
                        name="name"
                        type="text"
                        required
                        autofocus
                        tabindex={1}
                        autocomplete="name"
                        placeholder=" "
                        class="peer w-full bg-transparent border-0 border-b border-border py-2 text-foreground focus:ring-0 focus:border-primary transition-all placeholder-transparent"
                    />
                    <label 
                        for="name" 
                        class="absolute left-0 -top-full text-xs text-muted-foreground peer-placeholder-shown:text-base peer-placeholder-shown:top-2 peer-focus:-top-4 peer-focus:text-xs peer-focus:text-primary transition-all pointer-events-none"
                    >
                        Nombre Completo
                    </label>
                    <InputError message={errors.name} class="mt-2 text-xs" />
                </div>

                <!-- Email Field -->
                <div class="relative group">
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        tabindex={2}
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

                <!-- Password Field -->
                <div class="relative group">
                    <input
                        id="password"
                        name="password"
                        type={showPassword ? "text" : "password"}
                        required
                        tabindex={3}
                        autocomplete="new-password"
                        placeholder=" "
                        class="peer w-full bg-transparent border-0 border-b border-border py-2 pr-10 text-foreground focus:ring-0 focus:border-primary transition-all placeholder-transparent"
                    />
                    <label 
                        for="password" 
                        class="absolute left-0 -top-full text-xs text-muted-foreground peer-placeholder-shown:text-base peer-placeholder-shown:top-2 peer-focus:-top-4 peer-focus:text-xs peer-focus:text-primary transition-all pointer-events-none"
                    >
                        Contraseña
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
                        tabindex={4}
                        autocomplete="new-password"
                        placeholder=" "
                        class="peer w-full bg-transparent border-0 border-b border-border py-2 pr-10 text-foreground focus:ring-0 focus:border-primary transition-all placeholder-transparent"
                    />
                    <label 
                        for="password_confirmation" 
                        class="absolute left-0 -top-full text-xs text-muted-foreground peer-placeholder-shown:text-base peer-placeholder-shown:top-2 peer-focus:-top-4 peer-focus:text-xs peer-focus:text-primary transition-all pointer-events-none"
                    >
                        Confirmar Contraseña
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
                    tabindex={5} 
                    disabled={processing}
                >
                    {#if processing}
                        <Spinner class="h-4 w-4" />
                    {/if}
                    Registrarse
                </Button>

                <div class="mt-6 flex items-center justify-between gap-4">
                    <p class="text-xs text-muted-foreground">¿Ya tienes una cuenta?</p>
                    <Link 
                        href={login().url} 
                        class="px-6 py-2 rounded-xl bg-secondary border border-border text-xs font-medium hover:bg-secondary/80 hover:border-muted-foreground transition-all"
                    >
                        Iniciar sesión
                    </Link>
                </div>
            </div>
        {/snippet}
    </Form>
</AuthBase>
