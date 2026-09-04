<script lang="ts">
  /**
   * /docente/perfil-incompleto — el usuario tiene un rol de docente asignado
   * (usuario_rol_asignacion) pero no existe su fila usuario.docente, así que
   * el sistema no puede asociarle cursos, componentes ni syllabus. El
   * middleware IsDocente redirige aquí en vez de mostrar un dashboard vacío
   * detrás de un mensaje flash — ver [[docente-perfil-vs-rol-rbac]].
   *
   * Mismo tono que SinRol.svelte (caso de borde de RBAC en el login): dice
   * qué falta y quién lo resuelve, sin inventar un contacto que el sistema
   * no tiene configurado.
   */
  import { router } from '@inertiajs/svelte';
  import { UserX, LogOut, RefreshCw } from 'lucide-svelte';

  interface Props {
    usuario: {
      id_usuario: number;
      nombre_completo: string;
      email?: string | null;
      roles: string[];
    };
  }

  let { usuario }: Props = $props();

  function handleLogout() {
    router.post('/logout');
  }

  function handleRefresh() {
    router.visit('/dashboard');
  }
</script>

<svelte:head>
  <title>Perfil incompleto | UTAMED</title>
</svelte:head>

<div class="flex min-h-screen flex-col items-center justify-center bg-[#F5F1EA] px-4 py-12">
  <div class="w-full max-w-md">
    <div class="mb-8 flex flex-col items-center gap-3">
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-[#002F6C] text-[14px] font-bold text-white">
        U
      </div>
      <span class="text-sm font-semibold uppercase tracking-wide text-[#5A5E6E]">UTAMED</span>
    </div>

    <div class="rounded-2xl border border-[#FBD3B4] border-t-[3px] border-t-[#D97706] bg-white p-8 text-center shadow-sm">
      <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-[#FFFBEB] ring-8 ring-[#FFFBEB]/40">
        <UserX size={32} class="text-[#B45309]" />
      </div>

      <span class="text-[10.5px] font-semibold uppercase tracking-wide text-[#B45309]">Perfil incompleto</span>
      <h1 class="mt-1 text-xl font-semibold tracking-tight text-[#1A1A24]">
        Tu cuenta tiene rol docente, pero falta tu ficha de docente
      </h1>
      <p class="mt-3 text-sm leading-relaxed text-[#5A5E6E]">
        Hola, <span class="font-semibold text-[#1A1A24]">{usuario.nombre_completo}</span>.<br />
        Sin esa ficha el sistema no puede asociarte cursos, componentes ni syllabus, así que este panel no tiene nada
        que mostrar. Lo resuelve administración académica; no es algo que puedas crear tú desde aquí.
      </p>

      <div class="mt-5 rounded-xl border border-[#E5E7EB] bg-[#FBFBFA] px-5 py-4 text-left">
        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-[#5A5E6E]">Al escribir a administración, indica</p>
        <ul class="space-y-1 text-sm text-[#1A1A24]">
          <li class="flex items-center justify-between gap-2">
            <span class="text-[#5A5E6E]">Usuario</span>
            <span class="font-mono text-[#1A1A24]">#{usuario.id_usuario}</span>
          </li>
          {#if usuario.email}
            <li class="flex items-center justify-between gap-2">
              <span class="text-[#5A5E6E]">Correo registrado</span>
              <span class="font-mono text-[#1A1A24]">{usuario.email}</span>
            </li>
          {/if}
          <li class="flex items-center justify-between gap-2">
            <span class="text-[#5A5E6E]">Rol{usuario.roles.length === 1 ? '' : 'es'} asignado{usuario.roles.length === 1 ? '' : 's'}</span>
            <span class="font-mono text-[#1A1A24]">{usuario.roles.join(', ')}</span>
          </li>
        </ul>
      </div>

      <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
        <button
          onclick={handleRefresh}
          class="inline-flex items-center justify-center gap-2 rounded-xl border border-[#D6D9E0] bg-white px-5 py-2.5 text-sm font-semibold text-[#1A1A24] shadow-sm transition-colors hover:bg-[#F8FAFC]"
        >
          <RefreshCw size={15} />
          Verificar acceso
        </button>
        <button
          onclick={handleLogout}
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#22213F] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#15142e]"
        >
          <LogOut size={15} />
          Cerrar sesión
        </button>
      </div>
    </div>

    <p class="mt-8 text-center text-xs text-[#5A5E6E]/70">&copy; 2026 Universidad de Tarapacá</p>
  </div>
</div>
