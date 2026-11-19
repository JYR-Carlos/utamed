<script lang="ts">
  /**
   * Mi perfil del docente — /docente/perfil (revisión «Mi UTAmed»).
   *
   * Nombre, roles y los datos de la Intranet en filas (RUT, correo, cargo,
   * grado académico y título profesional), en sólo lectura. Sin semestre,
   * subtítulos, pie, usuario ni cursos asignados. «Cambiar contraseña» abre
   * un modal que sólo cambia la contraseña (PUT /settings/password).
   */
  import { page, router } from '@inertiajs/svelte';
  import { KeyRound } from 'lucide-svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import ModalInst from '@/components/docente/ModalInst.svelte';
  import FotoPerfil from '@/components/student/dm/FotoPerfil.svelte';
  import { titleCase } from '@/lib/utils';
  import { formatRut } from '@/lib/validators';
  import { aviso, avisoTono, btnPrimario, btnSecundario, campo, chip, cuerpo, etiqueta, tarjeta, titulo, tono } from '@/lib/inst';
  import type { BreadcrumbItem } from '@/types';

  interface Props {
    perfil: {
      nombre_completo: string;
      rut: string;
      email: string;
      grado: string | null;
      titulo: string | null;
      cargo: string | null;
      ultimo_cambio_clave: string | null;
    };
  }

  let { perfil }: Props = $props();

  const urlFotoPerfil = $derived($page.props.auth?.urlFotoPerfil as string | undefined);

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Mi perfil', href: '/docente/perfil' },
  ];

  const esJefe = $derived((($page.props.auth?.roles as string[]) ?? []).includes('Jefe de Carrera'));

  const datos = $derived([
    ['RUT', formatRut(perfil.rut)],
    ['Correo institucional', perfil.email],
    ['Cargo', perfil.cargo],
    ['Grado académico', perfil.grado],
    ['Título profesional', perfil.titulo],
  ] as const);

  // ── Cambiar contraseña ────────────────────────────────────────────────
  let abierto = $state(false);
  let guardando = $state(false);
  let listo = $state(false);
  let errores = $state<Record<string, string>>({});
  let actual = $state('');
  let nueva = $state('');
  let repetida = $state('');

  function abrir() {
    actual = nueva = repetida = '';
    errores = {};
    listo = false;
    abierto = true;
  }

  function guardar(e: SubmitEvent) {
    e.preventDefault();
    guardando = true;
    errores = {};
    router.put(
      '/settings/password',
      { current_password: actual, password: nueva, password_confirmation: repetida },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          listo = true;
          actual = nueva = repetida = '';
        },
        onError: (e) => (errores = e),
        onFinish: () => (guardando = false),
      },
    );
  }

  const fila = 'grid grid-cols-1 gap-1 border-b border-inst-linea py-3.5 last:border-b-0 sm:grid-cols-[200px_minmax(0,1fr)] sm:gap-4';
</script>

<DocenteLayout {breadcrumbs} titulo="Mi perfil">
  {#snippet acciones()}
    <button type="button" class={btnSecundario} onclick={abrir}>
      <KeyRound size={16} aria-hidden="true" />
      Cambiar contraseña
    </button>
  {/snippet}

  <div class="{cuerpo} max-w-[920px]">
    <section aria-labelledby="t-datos" class="{tarjeta} overflow-hidden">
      <div class="flex flex-wrap items-center gap-4 border-b border-inst-linea px-5.5 py-5">
        <FotoPerfil src={urlFotoPerfil} decorativa centrada class="size-16 rounded-full border border-inst-linea bg-inst-accion-s" />
        <span class="flex min-w-0 flex-1 flex-col gap-1.5">
          <h2 id="t-datos" class="{titulo} m-0 text-[22px] leading-7">{titleCase(perfil.nombre_completo)}</h2>
          <span class="flex flex-wrap gap-1.5">
            <span class="{chip} {tono.accion}">Docente</span>
            {#if esJefe}<span class="{chip} {tono.oro}">Jefatura de carrera</span>{/if}
          </span>
        </span>
      </div>

      <dl class="m-0 px-5.5 pt-1 pb-2">
        {#each datos as [clave, valor] (clave)}
          <div class={fila}>
            <dt class="{etiqueta} leading-5">{clave}</dt>
            <dd class="m-0 font-semibold break-words {valor ? '' : 'text-inst-ink-3'}">{valor || 'Sin registro'}</dd>
          </div>
        {/each}
      </dl>
    </section>
  </div>
</DocenteLayout>

{#if abierto}
  <ModalInst titulo="Cambiar contraseña" ancho={480} onCerrar={() => !guardando && (abierto = false)}>
    {#if listo}
      <div class="{aviso} {avisoTono.ok}" role="status"><p class="m-0 font-bold">Tu contraseña quedó cambiada.</p></div>
    {:else}
      <form id="form-clave" class="flex flex-col gap-4" onsubmit={guardar}>
        <label class="flex flex-col gap-1.5">
          <span class="font-bold">Contraseña actual</span>
          <input class={campo} type="password" autocomplete="current-password" bind:value={actual} required />
          {#if errores.current_password}<span class="text-[13px] font-bold text-inst-error">{errores.current_password}</span>{/if}
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="font-bold">Nueva contraseña</span>
          <input class={campo} type="password" autocomplete="new-password" bind:value={nueva} required />
          <span class="text-[13px] text-inst-ink-2">Al menos 8 caracteres, con una mayúscula, números y algún signo.</span>
          {#if errores.password}<span class="text-[13px] font-bold text-inst-error">{errores.password}</span>{/if}
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="font-bold">Repite la nueva contraseña</span>
          <input class={campo} type="password" autocomplete="new-password" bind:value={repetida} required />
          {#if errores.password_confirmation}<span class="text-[13px] font-bold text-inst-error">{errores.password_confirmation}</span>{/if}
        </label>
      </form>
    {/if}
    {#snippet pie()}
      {#if listo}
        <button type="button" class="{btnPrimario} ml-auto" onclick={() => (abierto = false)}>Cerrar</button>
      {:else}
        <button type="button" class="{btnSecundario} ml-auto" disabled={guardando} onclick={() => (abierto = false)}>Cancelar</button>
        <button type="submit" form="form-clave" class={btnPrimario} disabled={guardando || !actual || !nueva || !repetida}>
          Guardar contraseña
        </button>
      {/if}
    {/snippet}
  </ModalInst>
{/if}
