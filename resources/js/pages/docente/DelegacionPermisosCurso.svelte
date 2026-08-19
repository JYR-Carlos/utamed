<script lang="ts">
  /**
   * DelegacionPermisosCurso
   *
   * Página de delegación de permisos del Docente Titular sobre los integrantes
   * del equipo docente dentro del contexto de un curso específico.
   *
   * Props (via Inertia page render):
   *   - curso       : { id_curso, cod_curso, nombre }
   *   - miembros    : Array<{ id_usuario, id_docente, nombre, es_titular, permisos: Record<slug, bool> }>
   *   - grupos      : Array<{ grupo: string, permisos: Array<{ slug, nombre }> }>
   *   - id_contexto : number
   *
   * Flujo de datos:
   *   1. Laravel inyecta la matriz inicial en el render de Inertia.
   *   2. Al hacer toggle de un switch, se dispara un POST asíncrono con axios.
   *   3. El estado local se actualiza optimistamente; en error se revierte.
   */

  import { router } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import * as Card from '@/components/ui/card';
  import { Badge } from '@/components/ui/badge';
  import * as Switch from '@/components/ui/switch';
  import { Label } from '@/components/ui/label';
  import { Separator } from '@/components/ui/separator';
  import * as Alert from '@/components/ui/alert';
  import { ShieldCheck, ShieldX, Users, ChevronDown, ChevronRight } from 'lucide-svelte';

  // ── Types ──────────────────────────────────────────────────────────────────

  interface Permiso {
    slug: string;
    nombre: string;
  }

  interface Grupo {
    grupo: string;
    permisos: Permiso[];
  }

  interface Miembro {
    id_usuario: number;
    id_docente: number;
    nombre: string;
    es_titular: boolean;
    permisos: Record<string, boolean>;
  }

  interface Curso {
    id_curso: number;
    cod_curso: string;
    nombre: string;
  }

  interface Props {
    curso: Curso;
    miembros: Miembro[];
    grupos: Grupo[];
    id_contexto: number;
  }

  // ── Props from Inertia ──────────────────────────────────────────────────────

  let { curso, miembros, grupos, id_contexto }: Props = $props();

  // ── State ───────────────────────────────────────────────────────────────────

  /**
   * Copia mutable local del mapa de permisos, indexada por id_usuario.
   * Permite actualización optimista sin esperar la respuesta del servidor.
   */
  let permisosLocales = $state<Record<number, Record<string, boolean>>>(
    Object.fromEntries(miembros.map((m) => [m.id_usuario, { ...m.permisos }])),
  );

  /** Conjunto de slugs que están esperando confirmación del servidor (para deshabilitar el switch). */
  let pending = $state<Set<string>>(new Set());

  /** Mensajes de error transitorios: clave = `${id_usuario}:${slug}` */
  let toggleErrors = $state<Record<string, string>>({});

  /** Grupos expandidos por miembro. Por defecto todos abiertos. */
  let expanded = $state<Record<string, boolean>>(
    Object.fromEntries(
      miembros.flatMap((m) =>
        grupos.map((g) => [`${m.id_usuario}:${g.grupo}`, true]),
      ),
    ),
  );

  // ── Derived ─────────────────────────────────────────────────────────────────

  const toggleUrl = $derived(`/docente/cursos/${curso.id_curso}/delegacion-permisos/toggle`);

  // ── Handlers ────────────────────────────────────────────────────────────────

  function pendingKey(idUsuario: number, slug: string): string {
    return `${idUsuario}:${slug}`;
  }

  /**
   * Dispara el toggle de un permiso individual de forma asíncrona.
   * Actualización optimista: el estado local cambia inmediatamente.
   * En caso de error del servidor se revierte.
   */
  function handleToggle(idUsuario: number, slug: string, otorgar: boolean) {
    const key = pendingKey(idUsuario, slug);

    // Actualización optimista
    permisosLocales[idUsuario][slug] = otorgar;
    delete toggleErrors[key];

    pending = new Set([...pending, key]);

    router.post(
      toggleUrl,
      { id_usuario: idUsuario, slug, otorgar },
      {
        preserveScroll: true,
        preserveState: true,
        onError: (errs) => {
          // Revertir el estado local
          permisosLocales[idUsuario][slug] = !otorgar;
          const msg = Object.values(errs)[0] || 'Error al actualizar el permiso.';
          toggleErrors[key] = msg;
        },
        onFinish: () => {
          const next = new Set(pending);
          next.delete(key);
          pending = next;
        }
      }
    );
  }

  function toggleGrupo(idUsuario: number, grupo: string): void {
    const key = `${idUsuario}:${grupo}`;
    expanded[key] = !expanded[key];
  }

  function isExpanded(idUsuario: number, grupo: string): boolean {
    return expanded[`${idUsuario}:${grupo}`] ?? true;
  }

  /**
   * Número de permisos activos para un miembro dentro de un grupo dado.
   */
  function permisosActivosEnGrupo(idUsuario: number, grupo: Grupo): number {
    return grupo.permisos.filter((p) => permisosLocales[idUsuario]?.[p.slug]).length;
  }
</script>

<DocenteLayout>
  <div class="mx-auto max-w-5xl space-y-6 p-6">

    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
          Delegación de Permisos
        </h1>
        <p class="mt-1 text-sm text-slate-500">
          {curso.cod_curso} · {curso.nombre}
        </p>
      </div>
      <Badge variant="outline" class="flex items-center gap-1.5 text-xs">
        <ShieldCheck class="h-3.5 w-3.5 text-emerald-600" aria-hidden="true" />
        Titular del Curso
      </Badge>
    </div>

    <!-- ── Sin miembros ───────────────────────────────────────────────────── -->
    {#if miembros.length === 0}
      <Alert.Root>
        <Users class="h-4 w-4" aria-hidden="true" />
        <Alert.Title>Sin miembros en el equipo</Alert.Title>
        <Alert.Description>
          No hay docentes auxiliares ni ayudantes asignados a este curso. Agrega
          miembros al equipo desde la sección de gestión de equipo antes de
          delegar permisos.
        </Alert.Description>
      </Alert.Root>
    {/if}

    <!-- ── Tarjeta por miembro ────────────────────────────────────────────── -->
    {#each miembros as miembro (miembro.id_usuario)}
      <Card.Root class="overflow-hidden border border-slate-200 shadow-sm">
        <!-- Card Header -->
        <Card.Header class="border-b border-slate-100 bg-slate-50 px-5 py-4">
          <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-700 uppercase">
              {miembro.nombre.charAt(0)}
            </div>
            <div>
              <p class="font-semibold text-slate-800">{miembro.nombre}</p>
              <p class="text-xs text-slate-500">
                {miembro.es_titular ? 'Titular de componente' : 'Docente auxiliar'}
              </p>
            </div>
          </div>
        </Card.Header>

        <!-- Card Content: grupos de permisos -->
        <Card.Content class="divide-y divide-slate-100 p-0">
          {#each grupos as grupo (grupo.grupo)}
            {@const activos = permisosActivosEnGrupo(miembro.id_usuario, grupo)}
            {@const totalGrupo = grupo.permisos.length}
            {@const abierto = isExpanded(miembro.id_usuario, grupo.grupo)}

            <!-- Grupo header (colapsable) -->
            <button
              type="button"
              onclick={() => toggleGrupo(miembro.id_usuario, grupo.grupo)}
              class="flex w-full items-center justify-between px-5 py-3 text-left hover:bg-slate-50 transition-colors"
              aria-expanded={abierto}
              aria-controls="panel-{miembro.id_usuario}-{grupo.grupo}"
            >
              <span class="text-sm font-medium text-slate-700">{grupo.grupo}</span>
              <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400">
                  {activos}/{totalGrupo}
                </span>
                {#if abierto}
                  <ChevronDown class="h-4 w-4 text-slate-400" aria-hidden="true" />
                {:else}
                  <ChevronRight class="h-4 w-4 text-slate-400" aria-hidden="true" />
                {/if}
              </div>
            </button>

            <!-- Permisos del grupo -->
            {#if abierto}
              <div id="panel-{miembro.id_usuario}-{grupo.grupo}" class="grid grid-cols-1 gap-0 divide-y divide-slate-50 bg-white px-5 pb-2 sm:grid-cols-2">
                {#each grupo.permisos as permiso (permiso.slug)}
                  {@const key = pendingKey(miembro.id_usuario, permiso.slug)}
                  {@const isChecked = permisosLocales[miembro.id_usuario]?.[permiso.slug] ?? false}
                  {@const isPending = pending.has(key)}
                  {@const errorMsg = toggleErrors[key]}

                  <div class="flex items-center justify-between gap-3 py-2.5">
                    <div class="min-w-0 flex-1">
                      <Label
                        for={`switch-${miembro.id_usuario}-${permiso.slug}`}
                        class="cursor-pointer text-xs font-normal text-slate-600 leading-tight"
                      >
                        {permiso.nombre}
                        {#if errorMsg}
                          <span class="ml-1 inline-flex items-center gap-0.5 text-red-500">
                            <ShieldX class="h-3 w-3" aria-hidden="true" />
                            {errorMsg}
                          </span>
                        {/if}
                      </Label>
                    </div>
                    <Switch.Root
                      id={`switch-${miembro.id_usuario}-${permiso.slug}`}
                      checked={isChecked}
                      disabled={isPending}
                      onCheckedChange={(checked) => handleToggle(miembro.id_usuario, permiso.slug, checked)}
                      class="data-[state=checked]:bg-emerald-600 data-[disabled]:opacity-50"
                    />
                  </div>
                {/each}
              </div>
            {/if}
          {/each}
        </Card.Content>
      </Card.Root>
    {/each}

  </div>
</DocenteLayout>
