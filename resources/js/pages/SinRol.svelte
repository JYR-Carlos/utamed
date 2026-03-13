<script lang="ts">
  import { page, router } from '@inertiajs/svelte';
  import { ShieldX, LogOut, RefreshCw } from 'lucide-svelte';
  import AppLogoIcon from '@/components/custom/layout/AppLogoIcon.svelte';

  let user = $derived($page.props.auth.user as { nombre1: string; nombre2?: string; apellido1: string; email?: string });

  function handleLogout() {
    router.post('/logout');
  }

  function handleRefresh() {
    router.visit('/dashboard');
  }
</script>

<svelte:head>
  <title>Sin acceso | UTAMED</title>
</svelte:head>

<div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-12">
  <div class="w-full max-w-md">
    <!-- Logo -->
    <div class="mb-8 flex flex-col items-center gap-3">
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-600">
        <AppLogoIcon class="size-8 fill-current text-white" />
      </div>
      <span class="text-sm font-semibold text-slate-500 tracking-wide uppercase">UTAMED</span>
    </div>

    <!-- Tarjeta principal -->
    <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm text-center">
      <!-- Ícono de alerta -->
      <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 ring-8 ring-amber-50/40">
        <ShieldX size={32} class="text-amber-500" />
      </div>

      <!-- Título y descripción -->
      <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Sin roles asignados</h1>
      <p class="mt-3 text-slate-500 text-sm leading-relaxed">
        Hola, <span class="font-semibold text-slate-700">{user.nombre1} {user.apellido1}</span>.<br />
        Tu cuenta está activa pero no tiene ningún rol asignado en el sistema.<br />
        Contacta a un administrador para que te asigne el rol correspondiente.
      </p>

      <!-- Info -->
      <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-left">
        <p class="text-xs font-bold uppercase tracking-wider text-amber-700 mb-2">¿Qué significa esto?</p>
        <ul class="space-y-1 text-sm text-amber-800">
          <li class="flex items-start gap-2">
            <span class="mt-0.5 shrink-0 text-amber-500">•</span>
            No tienes acceso al panel de administración.
          </li>
          <li class="flex items-start gap-2">
            <span class="mt-0.5 shrink-0 text-amber-500">•</span>
            No tienes acceso al área de docentes.
          </li>
          <li class="flex items-start gap-2">
            <span class="mt-0.5 shrink-0 text-amber-500">•</span>
            No tienes acceso al área de estudiantes ni ayudantes.
          </li>
        </ul>
      </div>

      <!-- Email -->
      {#if user.email}
        <p class="mt-4 text-xs text-slate-400">
          Correo registrado: <span class="font-mono text-slate-500">{user.email}</span>
        </p>
      {/if}

      <!-- Acciones -->
      <div class="mt-7 flex flex-col sm:flex-row gap-3 justify-center">
        <button
          onclick={handleRefresh}
          class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors"
        >
          <RefreshCw size={15} />
          Verificar acceso
        </button>
        <button
          onclick={handleLogout}
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 transition-colors"
        >
          <LogOut size={15} />
          Cerrar sesión
        </button>
      </div>
    </div>

    <p class="mt-8 text-center text-xs text-gray-400">&copy; 2026 Universidad de Tarapacá</p>
  </div>
</div>
