<script lang="ts">
  /**
   * Cambio de contraseña dentro del portal del estudiante (guía dm).
   *
   * Es la misma ruta y el mismo PUT que `settings/password`
   * (Settings\PasswordController); el controlador elige esta vista para quien
   * sólo es estudiante, en lugar de la página genérica de ajustes.
   */
  import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
  import RequisitosPassword from '@/components/custom/common/RequisitosPassword.svelte';
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import { btnPrimario, enlace, etiqueta, tarjeta, tituloCond } from '@/lib/dm';
  import type { BreadcrumbItem } from '@/types';
  import type { ProfileFormSnippetProps } from '@/types/forms';
  import { Form, Link } from '@inertiajs/svelte';
  import { ArrowLeft, CircleAlert, CircleCheck, Eye, EyeOff, KeyRound } from 'lucide-svelte';

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mi perfil', href: '/estudiante/perfil' },
    { title: 'Cambiar contraseña', href: '/settings/password' },
  ];

  type Clave = 'current_password' | 'password' | 'password_confirmation';

  const CAMPOS: { id: Clave; label: string; autocomplete: 'current-password' | 'new-password'; mostrar: string; ocultar: string }[] = [
    { id: 'current_password', label: 'Contraseña actual', autocomplete: 'current-password', mostrar: 'Mostrar contraseña actual', ocultar: 'Ocultar contraseña actual' },
    { id: 'password', label: 'Nueva contraseña', autocomplete: 'new-password', mostrar: 'Mostrar nueva contraseña', ocultar: 'Ocultar nueva contraseña' },
    { id: 'password_confirmation', label: 'Confirma la nueva contraseña', autocomplete: 'new-password', mostrar: 'Mostrar confirmación', ocultar: 'Ocultar confirmación' },
  ];

  let visible = $state<Record<Clave, boolean>>({ current_password: false, password: false, password_confirmation: false });
  let entradas = $state<Partial<Record<Clave, HTMLInputElement>>>({});
  // Ligados a los campos sólo para los requisitos en vivo; el Form envía lo
  // que haya en los inputs. Al resetear, el Form vacía los inputs pero no
  // este estado, así que se vacía a mano.
  const VACIO: Record<Clave, string> = { current_password: '', password: '', password_confirmation: '' };
  let valores = $state({ ...VACIO });

  // Como `campo` de la guía dm, pero con espacio a la derecha para el botón de ver.
  const CAMPO_CLAVE =
    'block min-h-11 w-full rounded-[10px] border border-dm-control bg-dm-tarjeta py-2 pr-13 pl-3 font-dm text-[15px] text-dm-ink focus:border-dm-enlace focus:outline-2 focus:outline-offset-0 focus:outline-dm-enlace aria-invalid:border-dm-error aria-invalid:ring-1 aria-invalid:ring-dm-error';
</script>

<svelte:head>
  <title>Cambiar contraseña | UTAMED</title>
</svelte:head>

<StudentLayout {breadcrumbs}>
  <div class="mx-auto flex w-full max-w-280 flex-col gap-5 px-4 pt-4 pb-10 lg:px-10 lg:pt-6 lg:pb-12">
    <Link href="/estudiante/perfil" class="{enlace} self-start">
      <ArrowLeft size={16} aria-hidden="true" />Volver a mi perfil
    </Link>

    <header class="flex max-w-160 flex-col gap-2">
      <span class="{etiqueta} text-dm-ink-3">Cuenta</span>
      <h1 class="{tituloCond} text-4xl leading-9 lg:text-[44px] lg:leading-11">Cambiar contraseña</h1>
      <p class="text-[15px] leading-[22px] text-dm-ink-2">Es la que usas para entrar a UTAMED. Tu sesión sigue abierta después del cambio.</p>
    </header>

    <section aria-label="Formulario de cambio de contraseña" class="{tarjeta} max-w-160">
      <Form
        {...PasswordController.update.form()}
        options={{ preserveScroll: true }}
        onSuccess={() => {
          valores = { ...VACIO };
          visible = { current_password: false, password: false, password_confirmation: false };
        }}
        onError={(errors) => {
          valores = { ...VACIO };
          if (errors.password) entradas.password?.focus();
          if (errors.current_password) entradas.current_password?.focus();
        }}
        resetOnSuccess
        resetOnError={['password', 'password_confirmation', 'current_password']}
        class="flex flex-col gap-5.5 p-4 sm:p-7"
      >
        {#snippet children({ errors, processing, recentlySuccessful }: ProfileFormSnippetProps)}
          {#each CAMPOS as f (f.id)}
            {@const mensaje = errors[f.id]}
            {@const descrita = [f.id === 'password' ? 'requisitos' : '', mensaje ? `${f.id}-error` : ''].filter(Boolean).join(' ')}
            <div class="flex flex-col gap-1.5 {f.id === 'password' ? 'border-t border-dm-linea pt-5.5' : ''}">
              <label for={f.id} class="text-sm leading-5 font-semibold">{f.label}</label>
              <div class="relative">
                <input
                  id={f.id}
                  name={f.id}
                  bind:this={entradas[f.id]}
                  bind:value={valores[f.id]}
                  type={visible[f.id] ? 'text' : 'password'}
                  autocomplete={f.autocomplete}
                  aria-invalid={mensaje ? 'true' : undefined}
                  aria-describedby={descrita || undefined}
                  class={CAMPO_CLAVE}
                />
                <button
                  type="button"
                  aria-label={visible[f.id] ? f.ocultar : f.mostrar}
                  aria-pressed={visible[f.id]}
                  onclick={() => (visible[f.id] = !visible[f.id])}
                  class="absolute top-0 right-0 flex size-11 cursor-pointer items-center justify-center rounded-[10px] text-dm-ink-2 hover:text-dm-ink"
                >
                  {#if visible[f.id]}<EyeOff size={20} aria-hidden="true" />{:else}<Eye size={20} aria-hidden="true" />{/if}
                </button>
              </div>
              {#if mensaje}
                <span id="{f.id}-error" class="flex items-start gap-1.5 text-sm leading-5 text-dm-error">
                  <CircleAlert size={16} aria-hidden="true" class="mt-0.5 shrink-0" />{mensaje}
                </span>
              {/if}
            </div>
          {/each}

          <div id="requisitos" class="flex flex-col gap-2.5 rounded-[10px] bg-dm-sutil p-4">
            <span class="{etiqueta} text-dm-ink-3">La nueva contraseña necesita</span>
            <RequisitosPassword password={valores.password} confirmacion={valores.password_confirmation} tono="dm" />
          </div>

          <div class="flex flex-wrap items-center gap-4 border-t border-dm-linea pt-5.5">
            <button type="submit" disabled={processing} class="{btnPrimario} max-sm:w-full">
              <KeyRound size={18} aria-hidden="true" />Guardar contraseña
            </button>
            <Link href="/estudiante/perfil" class="{enlace} px-2">Cancelar</Link>
            {#if recentlySuccessful}
              <p role="status" class="inline-flex items-center gap-1.5 text-sm leading-5 font-semibold text-dm-ok">
                <CircleCheck size={16} aria-hidden="true" />Tu contraseña se actualizó.
              </p>
            {/if}
          </div>
        {/snippet}
      </Form>
    </section>
  </div>
</StudentLayout>
