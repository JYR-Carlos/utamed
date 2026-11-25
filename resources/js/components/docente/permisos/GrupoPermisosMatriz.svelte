<script lang="ts">
  /**
   * Un bloque de la matriz persona × permiso. Colapsado muestra un resumen;
   * expandido, una tabla con controles tri-estado (✕ / — / ✓) y un botón
   * compacto "Acciones ▾" con menú flotante (`position: fixed`).
   */
  import { ChevronDown, ChevronRight } from 'lucide-svelte';
  import PermisoTriState, {
    type ValorPermiso,
    type EstadoTransitorio,
    type TipoBloqueo,
  } from './PermisoTriState.svelte';
  import ModulosProgramaCell from './ModulosProgramaCell.svelte';
  import { formatSlugAction, esSlugModuloPrograma, numeroModuloPrograma } from '@/utils/permisos';

  interface Permiso {
    slug: string;
    nombre: string;
  }

  interface Miembro {
    id_usuario: number;
    nombre: string;
    es_titular: boolean;
    tipo_componente?: string | null;
  }

  export interface CeldaInfo {
    estado: ValorPermiso;
    activoPorRol: boolean;
    bloqueadoPor: TipoBloqueo;
    motivoBloqueo: string | null;
  }

  interface Props {
    numero: number;
    grupo: { grupo: string; permisos: Permiso[] };
    miembros: Miembro[];
    expandido: boolean;
    onToggleExpandido: () => void;
    getCelda: (idUsuario: number, slug: string) => CeldaInfo;
    getEstado: (idUsuario: number, slug: string) => EstadoTransitorio;
    onChange: (idUsuario: number, slug: string, next: ValorPermiso) => void;
    onBatch?: (idUsuario: number, slugs: string[], estado: ValorPermiso, nombreGrupo: string) => void;
  }

  let {
    numero,
    grupo,
    miembros,
    expandido,
    onToggleExpandido,
    getCelda,
    getEstado,
    onChange,
    onBatch,
  }: Props = $props();

  const columnas = $derived(grupo.permisos.filter((p) => !esSlugModuloPrograma(p.slug)));
  const modulosPrograma = $derived(
    grupo.permisos
      .filter((p) => esSlugModuloPrograma(p.slug))
      .map((p) => ({ slug: p.slug, numero: numeroModuloPrograma(p.slug) ?? 0 }))
      .sort((a, b) => a.numero - b.numero),
  );

  const slugsDeGrupo = $derived(grupo.permisos.map((p) => p.slug));

  function contarPorEstado(idUsuario: number, valor: ValorPermiso): number {
    return grupo.permisos.filter((p) => getCelda(idUsuario, p.slug).estado === valor).length;
  }

  const resumenAcciones = $derived(columnas.map((p) => formatSlugAction(p.slug).toLowerCase()).join(' · '));

  const totalColumnasEfectivas = $derived(columnas.length + (modulosPrograma.length > 0 ? 1 : 0));
  const totalCeldas = $derived(totalColumnasEfectivas * miembros.length);
  const celdasActivas = $derived(
    miembros.reduce((acc, m) => {
      const regulares = columnas.filter((p) => getCelda(m.id_usuario, p.slug).estado === 'asignado').length;
      const algunModulo = modulosPrograma.some((mod) => getCelda(m.id_usuario, mod.slug).estado === 'asignado') ? 1 : 0;
      return acc + regulares + algunModulo;
    }, 0),
  );

  const etiquetaResumenCeldas = $derived.by(() => {
    if (celdasActivas === 0) return '';
    if (totalCeldas > 0 && celdasActivas === totalCeldas) return 'Todos Asignados';
    return celdasActivas === 1 ? '1 Asignado' : `${celdasActivas} Asignados`;
  });

  // Menú desplegable flotante de "Acciones" por persona
  let menuAbiertoPara = $state<number | null>(null);
  let menuPos = $state<{ top: number; left: number }>({ top: 0, left: 0 });
  let menuPanelRef = $state<HTMLDivElement | null>(null);

  function abrirMenuAcciones(idUsuario: number, event: MouseEvent) {
    event.stopPropagation();
    if (menuAbiertoPara === idUsuario) {
      menuAbiertoPara = null;
      return;
    }
    const btn = event.currentTarget as HTMLElement;
    const rect = btn.getBoundingClientRect();
    const menuWidth = 176;
    const estimatedHeight = 116;
    const left = Math.max(8, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8));
    const spaceBelow = window.innerHeight - rect.bottom;
    const top =
      spaceBelow >= estimatedHeight || spaceBelow >= rect.top
        ? rect.bottom + 4
        : Math.max(8, rect.top - estimatedHeight - 4);
    menuPos = { top, left };
    menuAbiertoPara = idUsuario;
  }

  function ejecutarAccionMenu(idUsuario: number, estado: ValorPermiso) {
    menuAbiertoPara = null;
    onBatch?.(idUsuario, slugsDeGrupo, estado, grupo.grupo);
  }

  function cerrarMenuSiClickFuera(event: MouseEvent) {
    if (menuAbiertoPara === null) return;
    const target = event.target as Node;
    if (menuPanelRef?.contains(target)) return;
    menuAbiertoPara = null;
  }

  function iniciales(nombre: string): string {
    return nombre
      .split(' ')
      .slice(0, 2)
      .map((w) => w[0] ?? '')
      .join('')
      .toUpperCase();
  }

  function subtitulo(m: Miembro): string {
    const rol = m.es_titular ? 'Docente de componente' : 'Docente colegiado';
    return m.tipo_componente ? `${rol} · ${m.tipo_componente}` : rol;
  }
</script>

<svelte:window
  onclick={cerrarMenuSiClickFuera}
  onkeydown={(e) => e.key === 'Escape' && (menuAbiertoPara = null)}
  onscroll={() => menuAbiertoPara !== null && (menuAbiertoPara = null)}
/>

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
  <button
    type="button"
    class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50"
    onclick={onToggleExpandido}
    aria-expanded={expandido}
  >
    {#if expandido}
      <ChevronDown class="h-4 w-4 shrink-0 text-ink-2" />
    {:else}
      <ChevronRight class="h-4 w-4 shrink-0 text-ink-2" />
    {/if}
    <span class="shrink-0 font-mono text-[11.5px] text-ink-2">{numero}</span>
    <span class="shrink-0 text-sm font-semibold text-ink">{grupo.grupo}</span>
    {#if !expandido}
      <span class="min-w-0 truncate text-xs text-ink-2">{resumenAcciones}</span>
    {/if}
    {#if etiquetaResumenCeldas}
      <span class="ml-auto shrink-0 rounded-full bg-uta-blue-light px-2.5 py-0.5 text-[11px] font-semibold text-navy">
        {etiquetaResumenCeldas}
      </span>
    {/if}
  </button>

  {#if expandido}
    <div class="overflow-x-auto border-t border-gray-200">
      <table class="w-full table-fixed border-collapse">
        <thead>
          <tr class="bg-gray-50">
            <th class="w-[270px] px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-ink-2">
              Persona
            </th>
            {#each columnas as permiso (permiso.slug)}
              <th class="min-w-[92px] px-1.5 py-2.5 text-center text-[11px] font-semibold leading-tight text-ink-2">
                {formatSlugAction(permiso.slug)}
              </th>
            {/each}
            {#if modulosPrograma.length > 0}
              <th class="w-[190px] px-1.5 py-2.5 text-center text-[11px] font-semibold leading-tight text-ink-2">
                Modificar secciones
              </th>
            {/if}
          </tr>
        </thead>
        <tbody>
          {#each miembros as miembro, i (miembro.id_usuario)}
            <tr class="border-t border-gray-200 {i % 2 === 1 ? 'bg-stone-50' : ''}">
              <td class="px-4 py-2.5">
                <div class="flex items-center justify-between gap-2">
                  <div class="flex min-w-0 items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-uta-blue-light text-[12px] font-semibold text-navy">
                      {iniciales(miembro.nombre)}
                    </div>
                    <div class="flex min-w-0 flex-col">
                      <span class="truncate text-[13px] font-semibold text-ink">{miembro.nombre}</span>
                      <span class="truncate text-[11.5px] text-ink-2">{subtitulo(miembro)}</span>
                    </div>
                  </div>
                  {#if onBatch}
                    <button
                      type="button"
                      class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                      onclick={(e) => abrirMenuAcciones(miembro.id_usuario, e)}
                      aria-haspopup="menu"
                      aria-expanded={menuAbiertoPara === miembro.id_usuario}
                    >
                      Acciones
                      <ChevronDown class="h-3 w-3 text-slate-500" />
                    </button>
                  {/if}
                </div>
              </td>
              {#each columnas as permiso (permiso.slug)}
                {@const celda = getCelda(miembro.id_usuario, permiso.slug)}
                <td class="px-1.5 py-2 text-center">
                  <PermisoTriState
                    valor={celda.estado}
                    activoPorRol={celda.activoPorRol}
                    bloqueo={celda.bloqueadoPor}
                    motivoBloqueo={celda.motivoBloqueo}
                    estado={getEstado(miembro.id_usuario, permiso.slug)}
                    onChange={(next) => onChange(miembro.id_usuario, permiso.slug, next)}
                    label={`${formatSlugAction(permiso.slug)} — ${miembro.nombre}`}
                  />
                </td>
              {/each}
              {#if modulosPrograma.length > 0}
                <td class="px-1.5 py-2 text-center">
                  <ModulosProgramaCell
                    nombrePersona={miembro.nombre}
                    modulos={modulosPrograma.map((mod) => {
                      const celda = getCelda(miembro.id_usuario, mod.slug);
                      return {
                        slug: mod.slug,
                        numero: mod.numero,
                        valor: celda.estado,
                        activoPorRol: celda.activoPorRol,
                        bloqueo: celda.bloqueadoPor,
                        motivoBloqueo: celda.motivoBloqueo,
                        estado: getEstado(miembro.id_usuario, mod.slug),
                      };
                    })}
                    onChange={(slug, next) => onChange(miembro.id_usuario, slug, next)}
                  />
                </td>
              {/if}
            </tr>
          {/each}
        </tbody>
      </table>
    </div>
  {/if}
</div>

{#if menuAbiertoPara !== null && onBatch}
  {@const idUsuarioMenu = menuAbiertoPara}
  {@const asignados = contarPorEstado(idUsuarioMenu, 'asignado')}
  {@const neutros = contarPorEstado(idUsuarioMenu, 'neutro')}
  {@const revocados = contarPorEstado(idUsuarioMenu, 'revocado')}
  {@const total = grupo.permisos.length}
  <div
    bind:this={menuPanelRef}
    role="menu"
    class="fixed z-50 w-44 rounded-lg border border-gray-200 bg-white py-1 shadow-xl"
    style="top:{menuPos.top}px; left:{menuPos.left}px;"
  >
    <button
      type="button"
      role="menuitem"
      disabled={asignados === total}
      class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-medium text-navy hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40"
      onclick={() => ejecutarAccionMenu(idUsuarioMenu, 'asignado')}
    >
      <span class="font-bold">✓</span> Otorgar todos
    </button>
    <button
      type="button"
      role="menuitem"
      disabled={neutros === total}
      class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
      onclick={() => ejecutarAccionMenu(idUsuarioMenu, 'neutro')}
    >
      <span class="font-bold">—</span> Restablecer a neutro
    </button>
    <button
      type="button"
      role="menuitem"
      disabled={revocados === total}
      class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
      onclick={() => ejecutarAccionMenu(idUsuarioMenu, 'revocado')}
    >
      <span class="font-bold">✕</span> Revocar todos
    </button>
  </div>
{/if}
