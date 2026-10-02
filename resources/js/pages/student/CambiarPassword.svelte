<script lang="ts">
  /**
   * Cambio de contraseña dentro del portal del estudiante.
   *
   * Es la misma ruta y el mismo PUT que `settings/password`
   * (Settings\PasswordController); el controlador elige esta vista para quien
   * sólo es estudiante, en lugar de la página genérica de ajustes.
   */
  import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
  import InputError from '@/components/custom/common/InputError.svelte';
  import RequisitosPassword from '@/components/custom/common/RequisitosPassword.svelte';
  import { DESCRIPCION_POLITICA_PASSWORD } from '@/lib/password';
  import { Input } from '@/components/ui/input';
  import { Label } from '@/components/ui/label';
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import type { ProfileFormSnippetProps } from '@/types/forms';
  import { Form, Link } from '@inertiajs/svelte';
  import { CheckCircle2, KeyRound, ArrowLeft } from 'lucide-svelte';
  import { fade } from 'svelte/transition';

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mi Perfil', href: '/estudiante/perfil' },
    { title: 'Cambiar contraseña', href: '/settings/password' },
  ];

  let passwordInput = $state(null as unknown as HTMLInputElement);
  let currentPasswordInput = $state(null as unknown as HTMLInputElement);
  let nuevaPassword = $state('');
  let confirmacion = $state('');
</script>

<svelte:head>
  <title>Cambiar contraseña | UTAMED</title>
</svelte:head>

<StudentLayout {breadcrumbs}>
  <div class="h-full px-5 md:px-10 lg:px-20 bg-white relative">
    <div class="relative mx-auto max-w-xl px-4 py-6">
      <Link
        href="/estudiante/perfil"
        class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-uta-blue"
      >
        <ArrowLeft class="h-4 w-4" />
        Volver a mi perfil
      </Link>

      <header class="mb-6 flex flex-col gap-1">
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight leading-tight">
          Cambiar contraseña
        </h1>
        <p class="text-sm text-slate-500">
          {DESCRIPCION_POLITICA_PASSWORD} Tu sesión sigue abierta después del cambio.
        </p>
      </header>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
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
            if (errors.password) passwordInput?.focus();
            if (errors.current_password) currentPasswordInput?.focus();
          }}
          resetOnSuccess
          resetOnError={['password', 'password_confirmation', 'current_password']}
          class="flex flex-col gap-5"
        >
          {#snippet children({ errors, processing, recentlySuccessful }: ProfileFormSnippetProps)}
            <div class="grid gap-2">
              <Label for="current_password">Contraseña actual</Label>
              <Input
                id="current_password"
                ref={currentPasswordInput}
                name="current_password"
                type="password"
                autocomplete="current-password"
              />
              <InputError message={errors.current_password} />
            </div>

            <div class="grid gap-2">
              <Label for="password">Nueva contraseña</Label>
              <Input
                id="password"
                ref={passwordInput}
                name="password"
                type="password"
                autocomplete="new-password"
                bind:value={nuevaPassword}
              />
              <RequisitosPassword password={nuevaPassword} confirmacion={confirmacion} />
              <InputError message={errors.password} />
            </div>

            <div class="grid gap-2">
              <Label for="password_confirmation">Confirma la nueva contraseña</Label>
              <Input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                bind:value={confirmacion}
              />
              <InputError message={errors.password_confirmation} />
            </div>

            <div class="flex flex-wrap items-center gap-4">
              <button type="submit" disabled={processing} class="btn btn-primary">
                <KeyRound class="h-4 w-4" />
                Guardar contraseña
              </button>

              {#if recentlySuccessful}
                <p
                  class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700"
                  transition:fade={{ duration: 150 }}
                  role="status"
                >
                  <CheckCircle2 class="h-4 w-4" />
                  Tu contraseña se actualizó.
                </p>
              {/if}
            </div>
          {/snippet}
        </Form>
      </section>
    </div>
  </div>
</StudentLayout>
