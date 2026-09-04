<script lang="ts">
  /**
   * Un bloque de la matriz persona × permiso (uno de los 7 grupos
   * delegables). Colapsado muestra un resumen; expandido, una tabla con la
   * columna de persona fija y una columna por permiso — salvo los 9 módulos
   * de "Programas", que se resumen en ModulosProgramaCell para no abrir
   * nueve columnas de 34px.
   */
  import { ChevronDown, ChevronRight } from 'lucide-svelte';
  import PermisoSwitch from './PermisoSwitch.svelte';
  import ModulosProgramaCell from './ModulosProgramaCell.svelte';
  import { formatSlugAction, esSlugModuloPrograma, numeroModuloPrograma } from '@/utils/permisos';

  type Estado = 'reposo' | 'guardando' | 'confirmado' | 'error';

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

  interface Props {
    numero: number;
    grupo: { grupo: string; permisos: Permiso[] };
    miembros: Miembro[];
    expandido: boolean;
    onToggleExpandido: () => void;
    isChecked: (idUsuario: number, slug: string) => boolean;
    getEstado: (idUsuario: number, slug: string) => Estado;
    onToggle: (idUsuario: number, slug: string, next: boolean) => void;
  }

  let { numero, grupo, miembros, expandido, onToggleExpandido, isChecked, getEstado, onToggle }: Props = $props();

  const columnas = $derived(grupo.permisos.filter((p) => !esSlugModuloPrograma(p.slug)));
  const modulosPrograma = $derived(
    grupo.permisos
      .filter((p) => esSlugModuloPrograma(p.slug))
      .map((p) => ({ slug: p.slug, numero: numeroModuloPrograma(p.slug) ?? 0 }))
      .sort((a, b) => a.numero - b.numero),
  );

  const resumenAcciones = $derived(columnas.map((p) => formatSlugAction(p.slug).toLowerCase()).join(' · '));

  const totalColumnasEfectivas = $derived(columnas.length + (modulosPrograma.length > 0 ? 1 : 0));
  const totalCeldas = $derived(totalColumnasEfectivas * miembros.length);
  const celdasActivas = $derived(
    miembros.reduce((acc, m) => {
      const regulares = columnas.filter((p) => isChecked(m.id_usuario, p.slug)).length;
      const algunModulo = modulosPrograma.some((mod) => isChecked(m.id_usuario, mod.slug)) ? 1 : 0;
      return acc + regulares + algunModulo;
    }, 0),
  );

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

<div class="overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-sm">
  <button
    type="button"
    class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-[#FAFBFC]"
    onclick={onToggleExpandido}
    aria-expanded={expandido}
  >
    {#if expandido}
      <ChevronDown class="h-4 w-4 shrink-0 text-[#5A5E6E]" />
    {:else}
      <ChevronRight class="h-4 w-4 shrink-0 text-[#5A5E6E]" />
    {/if}
    <span class="shrink-0 font-mono text-[11.5px] text-[#5A5E6E]">{numero}</span>
    <span class="shrink-0 text-sm font-semibold text-[#1A1A24]">{grupo.grupo}</span>
    {#if !expandido}
      <span class="min-w-0 truncate text-xs text-[#5A5E6E]">{resumenAcciones}</span>
    {/if}
    <span class="ml-auto shrink-0 font-mono text-[11.5px] text-[#5A5E6E]">
      {celdasActivas} de {totalCeldas} celdas activas
    </span>
  </button>

  {#if expandido}
    <div class="overflow-x-auto border-t border-[#E5E7EB]">
      <table class="w-full table-fixed border-collapse">
        <thead>
          <tr class="bg-[#FAFBFC]">
            <th class="w-[260px] px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-[#5A5E6E]">
              Persona
            </th>
            {#each columnas as permiso (permiso.slug)}
              <th class="px-1.5 py-2.5 text-center text-[11px] font-semibold leading-tight text-[#5A5E6E]">
                {formatSlugAction(permiso.slug)}
              </th>
            {/each}
            {#if modulosPrograma.length > 0}
              <th class="w-[190px] px-1.5 py-2.5 text-center text-[11px] font-semibold leading-tight text-[#5A5E6E]">
                Modificar secciones
              </th>
            {/if}
          </tr>
        </thead>
        <tbody>
          {#each miembros as miembro, i (miembro.id_usuario)}
            <tr class="border-t border-[#E5E7EB] {i % 2 === 1 ? 'bg-[#FCFBF9]' : ''}">
              <td class="px-4 py-2.5">
                <div class="flex items-center gap-2.5">
                  <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#E8EDF5] text-[12px] font-semibold text-[#002F6C]">
                    {iniciales(miembro.nombre)}
                  </div>
                  <div class="flex min-w-0 flex-col">
                    <span class="truncate text-[13px] font-semibold text-[#1A1A24]">{miembro.nombre}</span>
                    <span class="truncate text-[11.5px] text-[#5A5E6E]">{subtitulo(miembro)}</span>
                  </div>
                </div>
              </td>
              {#each columnas as permiso (permiso.slug)}
                <td class="px-1.5 py-2 text-center">
                  <PermisoSwitch
                    checked={isChecked(miembro.id_usuario, permiso.slug)}
                    estado={getEstado(miembro.id_usuario, permiso.slug)}
                    onToggle={(next) => onToggle(miembro.id_usuario, permiso.slug, next)}
                    label={`${formatSlugAction(permiso.slug)} — ${miembro.nombre}`}
                  />
                </td>
              {/each}
              {#if modulosPrograma.length > 0}
                <td class="px-1.5 py-2 text-center">
                  <ModulosProgramaCell
                    nombrePersona={miembro.nombre}
                    modulos={modulosPrograma.map((mod) => ({
                      slug: mod.slug,
                      numero: mod.numero,
                      activo: isChecked(miembro.id_usuario, mod.slug),
                      estado: getEstado(miembro.id_usuario, mod.slug),
                    }))}
                    onToggle={(slug, next) => onToggle(miembro.id_usuario, slug, next)}
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
