<script lang="ts">
  /**
   * Página de evaluación/calificación de una actividad.
   *
   * El docente puede:
   * - Ver todos los grupos (actividad_asignada) de la actividad
   * - Crear nuevos grupos y agregar estudiantes
   * - Asignar nota grupal y cambiar estado
   * - Asignar nota individual y diferencia de décimas por integrante
   * - Eliminar integrantes y grupos
   */
  import { router, Link } from '@inertiajs/svelte';
  import { untrack } from 'svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { ArrowLeft, Plus, Trash2, UserPlus, Save, Users, User, BookOpen } from 'lucide-svelte';
  import type { Actividad, Integrante, Grupo, Estado, EstudianteDisponible } from '@/types/actividad';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions/permissions';

  interface Props {
    curso: {
      id_curso: number;
      cod_curso?: string;
      asignatura_nombre?: string;
      nombre?: string;
      es_titular_curso?: boolean;
      userPermissions?: Permission[];
    };
    actividad: Actividad;
    grupos: Grupo[];
    estudiantesDisponibles: EstudianteDisponible[];
    estados: Estado[];
  }

  // ---------------------------------------------------------------------------
  // Props & reactive state
  // ---------------------------------------------------------------------------
  let { curso, actividad, grupos: gruposInit, estudiantesDisponibles, estados }: Props = $props();

  const canCreateGroup = $derived(
    curso.es_titular_curso || hasPermission(curso.userPermissions ?? [], 'actividades/grupos:crear'),
  );
  const canEditGroup = $derived(
    curso.es_titular_curso || hasPermission(curso.userPermissions ?? [], 'actividades/grupos:editar'),
  );
  const canDeleteGroup = $derived(
    curso.es_titular_curso || hasPermission(curso.userPermissions ?? [], 'actividades/grupos:eliminar'),
  );

  // Local mutable copy so we can edit inline without waiting for server round-trips.
  // untrack() suppresses the Svelte 5 state_referenced_locally warning – intentional
  // since Inertia props are static per page visit.
  let grupos = $state<Grupo[]>(untrack(() => gruposInit.map((g) => ({ ...g, integrantes: [...g.integrantes] }))));

  let isLoading = $state(false);

  // Nuevo grupo
  let showNuevoGrupo = $state(false);
  let nuevoGrupoEstadoId = $state<number>(untrack(() => estados[0]?.id_estado ?? 0));
  let nuevoGrupoEstudianteId = $state<number>(0);

  // Para agregar integrante a un grupo existente
  let addingToGrupo = $state<number | null>(null);
  let addEstudianteId = $state<number>(0);

  // Ediciones en vuelo (identificadas por grupo o id_asignado)
  let savingGrupo = $state<Set<number>>(new Set());
  let savingIntegrante = $state<Set<number>>(new Set());

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------
  function formatDate(d: string) {
    return new Date(d).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
  }

  function estadoLabel(idEstado: number | null) {
    if (!idEstado) return 'Sin estado';
    return estados.find((e) => e.id_estado === idEstado)?.titulo ?? 'Desconocido';
  }

  function estadoColor(titulo: string) {
    const t = titulo.toLowerCase();
    if (t.includes('entregad') || t.includes('completad')) return 'badge-success';
    if (t.includes('pendiente') || t.includes('asign')) return 'badge-warning';
    if (t.includes('retraso') || t.includes('tard')) return 'badge-danger';
    return 'badge-neutral';
  }

  /** Estudiantes que aún no están en ningún grupo de esta actividad */
  function estudiantesLibres(grupoIdExcluir?: number): EstudianteDisponible[] {
    const ocupados = new Set<number>();
    grupos.forEach((g) => {
      if (g.grupo === grupoIdExcluir) return;
      g.integrantes.forEach((i) => ocupados.add(i.id_estudiante));
    });
    return estudiantesDisponibles.filter((e) => !ocupados.has(e.id_estudiante));
  }

  // ---------------------------------------------------------------------------
  // Actions
  // ---------------------------------------------------------------------------

  /** Crea un nuevo grupo, opcionalmente con un primer integrante */
  function crearGrupo() {
    if (!nuevoGrupoEstadoId) return;
    isLoading = true;
    router.post(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos`,
      {
        id_estado: nuevoGrupoEstadoId,
        id_estudiante: nuevoGrupoEstudianteId || null,
      },
      {
        onSuccess: () => {
          showNuevoGrupo = false;
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      },
    );
  }

  /** Guarda nota + estado de un grupo */
  function guardarGrupo(g: Grupo) {
    savingGrupo = new Set([...savingGrupo, g.grupo]);
    router.put(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${g.grupo}`,
      { nota: g.nota, id_estado: g.id_estado },
      {
        onSuccess: () => {
          savingGrupo = new Set([...savingGrupo].filter((x) => x !== g.grupo));
        },
        onError: () => {
          savingGrupo = new Set([...savingGrupo].filter((x) => x !== g.grupo));
        },
      },
    );
  }

  /** Elimina un grupo y todos sus integrantes */
  function eliminarGrupo(grupoId: number) {
    if (!confirm('¿Eliminar este grupo y todos sus integrantes?')) return;
    isLoading = true;
    router.delete(`/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}`, {
      onSuccess: () => {
        isLoading = false;
      },
      onError: () => {
        isLoading = false;
      },
    });
  }

  /** Agrega un integrante al grupo */
  function agregarIntegrante(grupoId: number) {
    if (!addEstudianteId) return;
    isLoading = true;
    router.post(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/integrantes`,
      { id_estudiante: addEstudianteId },
      {
        onSuccess: () => {
          addingToGrupo = null;
          addEstudianteId = 0;
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      },
    );
  }

  /** Guarda nota individual de un integrante */
  function guardarIntegrante(grupoId: number, i: Integrante) {
    savingIntegrante = new Set([...savingIntegrante, i.id_asignado_actividad]);
    router.put(
      `/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/integrantes/${i.id_asignado_actividad}`,
      { nota_individual: i.nota_individual, diferencia_decimas: i.diferencia_decimas },
      {
        onSuccess: () => {
          savingIntegrante = new Set([...savingIntegrante].filter((x) => x !== i.id_asignado_actividad));
        },
        onError: () => {
          savingIntegrante = new Set([...savingIntegrante].filter((x) => x !== i.id_asignado_actividad));
        },
      },
    );
  }

  /** Elimina un integrante del grupo */
  function eliminarIntegrante(grupoId: number, asignadoId: number) {
    if (!confirm('¿Quitar este integrante del grupo?')) return;
    isLoading = true;
    router.delete(`/docente/cursos/${curso.id_curso}/actividades/${actividad.id_actividad}/grupos/${grupoId}/integrantes/${asignadoId}`, {
      onSuccess: () => {
        isLoading = false;
      },
      onError: () => {
        isLoading = false;
      },
    });
  }
</script>

<DocenteLayout>
  <div class="px-8 py-8 max-w-4xl mx-auto">
    <!-- ── Header ─────────────────────────────────────────────────── -->
    <div class="mb-8">
      <div class="mb-3">
        <Link
          href={`/docente/cursos/${curso.id_curso}/actividades`}
          class="inline-flex items-center gap-2 text-blue-500 font-medium text-sm hover:text-blue-700"
        >
          <ArrowLeft size={18} />
          Volver a Actividades
        </Link>
      </div>

      <div class="flex justify-between items-start gap-4 flex-col md:flex-row">
        <div>
          <div class="flex flex-wrap gap-2 mb-2">
            {#if actividad.es_grupal}
              <span class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 bg-blue-100 text-blue-700 rounded-full"
                ><Users size={14} />Grupal · máx. {actividad.max_integrantes}</span
              >
            {:else}
              <span class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 bg-pink-100 text-pink-700 rounded-full"
                ><User size={14} />Individual</span
              >
            {/if}
            <span class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 bg-green-50 text-green-700 rounded-full"
              >{actividad.tipo_entrega}</span
            >
            {#if actividad.seccion}
              <span class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 bg-amber-100 text-amber-700 rounded-full"
                ><BookOpen size={14} />{actividad.seccion.tipo}</span
              >
            {/if}
          </div>
          <h1 class="text-3xl font-bold text-gray-900 mb-1">{actividad.nombre}</h1>
          <p class="text-gray-600 text-sm">
            Fecha límite: <strong>{formatDate(actividad.fecha_limite)}</strong>
            {#if actividad.unidad}
              · Unidad: <strong>{actividad.unidad.nombre}</strong>{/if}
          </p>
        </div>

        {#if canCreateGroup}
          <button
            onclick={() => {
              showNuevoGrupo = !showNuevoGrupo;
            }}
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-500 text-white font-semibold text-sm rounded-lg hover:bg-blue-600 disabled:opacity-60 whitespace-nowrap"
          >
            <Plus size={18} />
            {actividad.es_grupal ? 'Nuevo Grupo' : 'Asignar Alumno'}
          </button>
        {/if}
      </div>
    </div>

    <!-- ── Nuevo Grupo Panel ──────────────────────────────────────── -->
    {#if showNuevoGrupo}
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 mb-6">
        <h3 class="font-semibold text-blue-900 mb-4 text-base">
          {actividad.es_grupal ? 'Crear nuevo grupo' : 'Asignar actividad individual'}
        </h3>
        <div class="flex flex-wrap gap-4 items-end">
          <div class="flex flex-col min-w-[180px]">
            <label class="text-xs font-semibold text-gray-700 mb-1" for="ng-estado">Estado inicial *</label>
            <select
              id="ng-estado"
              bind:value={nuevoGrupoEstadoId}
              class="px-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            >
              {#each estados as e}
                <option value={e.id_estado}>{e.titulo}</option>
              {/each}
            </select>
          </div>

          <div class="flex flex-col min-w-[180px]">
            <label class="text-xs font-semibold text-gray-700 mb-1" for="ng-estudiante">
              {actividad.es_grupal ? 'Primer integrante (opcional)' : 'Alumno *'}
            </label>
            <select
              id="ng-estudiante"
              bind:value={nuevoGrupoEstudianteId}
              class="px-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            >
              <option value={0}>-- Seleccionar --</option>
              {#each estudiantesLibres() as e}
                <option value={e.id_estudiante}>{e.nombre_completo}</option>
              {/each}
            </select>
          </div>

          <div class="flex gap-2 items-center">
            <button
              onclick={crearGrupo}
              class="inline-flex items-center gap-2 px-4 py-2 bg-blue-500 text-white font-semibold text-sm rounded-lg hover:bg-blue-600 disabled:opacity-60"
              disabled={isLoading}
            >
              <Save size={16} /> Crear
            </button>
            <button
              onclick={() => (showNuevoGrupo = false)}
              class="px-4 py-2 bg-transparent border border-gray-300 rounded-md text-gray-700 text-sm font-medium cursor-pointer hover:bg-gray-100"
              >Cancelar</button
            >
          </div>
        </div>
      </div>
    {/if}

    <!-- ── Sin grupos ─────────────────────────────────────────────── -->
    {#if grupos.length === 0}
      <div class="text-center py-16 bg-white border border-gray-200 rounded-xl">
        <div class="text-5xl mb-4">📋</div>
        <h3 class="text-lg font-semibold text-gray-700 mb-1">Sin grupos asignados</h3>
        <p class="text-gray-500 text-sm">Crea el primer grupo para comenzar a evaluar esta actividad.</p>
      </div>
    {/if}

    <!-- ── Lista de grupos ────────────────────────────────────────── -->
    {#each grupos as grupo, gi (grupo.grupo)}
      <div class="bg-white border border-gray-200 rounded-xl mb-5 overflow-hidden">
        <!-- Encabezado del grupo -->
        <div class="bg-slate-50 border-b border-gray-200 px-5 py-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-bold text-gray-900">
              {actividad.es_grupal ? `Grupo #${gi + 1}` : (grupo.integrantes[0]?.nombre_completo ?? `Alumno #${gi + 1}`)}
            </h3>
            {#if canDeleteGroup}
              <button
                onclick={() => eliminarGrupo(grupo.grupo)}
                class="p-2 bg-transparent border border-red-300 rounded-lg text-red-500 hover:bg-red-50"
                title="Eliminar grupo"
              >
                <Trash2 size={16} />
              </button>
            {/if}
          </div>

          <!-- Nota y estado del grupo -->
          <div class="flex flex-wrap gap-4 items-end">
            <div class="flex flex-col">
              <label class="text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wider" for="nota-grupo-{grupo.grupo}">Nota grupal</label>
              <input
                id="nota-grupo-{grupo.grupo}"
                type="number"
                min="0"
                max="10"
                step="0.1"
                bind:value={grupo.nota}
                class="w-[90px] px-2 py-2 border border-gray-300 rounded-md text-sm text-center focus:outline-none focus:border-blue-500"
                placeholder="—"
              />
            </div>

            <div class="flex flex-col">
              <label class="text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wider" for="estado-grupo-{grupo.grupo}">Estado</label>
              <select
                id="estado-grupo-{grupo.grupo}"
                bind:value={grupo.id_estado}
                class="px-3 py-2 border border-gray-300 rounded-md text-sm min-w-[150px] focus:outline-none focus:border-blue-500"
              >
                <option value={null}>Sin estado</option>
                {#each estados as e}
                  <option value={e.id_estado}>{e.titulo}</option>
                {/each}
              </select>
            </div>

            {#if canEditGroup}
              <button
                onclick={() => guardarGrupo(grupo)}
                class="inline-flex items-center gap-2 px-3 py-2 bg-green-500 text-white text-xs font-semibold rounded-lg hover:bg-green-600 disabled:opacity-60 whitespace-nowrap"
                disabled={savingGrupo.has(grupo.grupo)}
              >
                <Save size={14} />
                {savingGrupo.has(grupo.grupo) ? 'Guardando…' : 'Guardar'}
              </button>
            {/if}
          </div>
        </div>

        <!-- Tabla de integrantes -->
        <div class="px-5 py-4">
          <div class="flex justify-between items-center mb-3">
            <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Integrantes ({grupo.integrantes.length})</h4>
            {#if actividad.es_grupal && canCreateGroup}
              <button
                onclick={() => {
                  addingToGrupo = addingToGrupo === grupo.grupo ? null : grupo.grupo;
                  addEstudianteId = 0;
                }}
                class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-gray-300 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-100 hover:border-blue-500 hover:text-blue-500"
              >
                <UserPlus size={14} /> Agregar
              </button>
            {/if}
          </div>

          <!-- Panel agregar integrante -->
          {#if addingToGrupo === grupo.grupo}
            <div class="flex gap-2 items-center flex-wrap bg-green-50 p-3 rounded-lg mb-3">
              <select
                bind:value={addEstudianteId}
                class="px-2 py-1.5 border border-gray-300 rounded-md text-xs bg-white focus:outline-none focus:border-blue-500"
              >
                <option value={0}>-- Seleccionar alumno --</option>
                {#each estudiantesLibres(grupo.grupo) as e}
                  <option value={e.id_estudiante}>{e.nombre_completo}</option>
                {/each}
              </select>
              <button
                onclick={() => agregarIntegrante(grupo.grupo)}
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-500 text-white text-xs font-semibold rounded-lg hover:bg-blue-600 disabled:opacity-60"
                disabled={!addEstudianteId || isLoading}
              >
                Agregar
              </button>
              <button
                onclick={() => {
                  addingToGrupo = null;
                }}
                class="px-3 py-1.5 bg-transparent border border-gray-300 rounded-md text-gray-700 text-xs font-medium cursor-pointer hover:bg-gray-100"
                >Cancelar</button
              >
            </div>
          {/if}

          {#if grupo.integrantes.length === 0}
            <p class="text-center text-gray-400 text-sm py-4">Sin integrantes asignados.</p>
          {:else}
            <table class="w-full border-collapse text-sm">
              <thead>
                <tr>
                  <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 border-b border-gray-200"
                    >Nombre</th
                  >
                  <th
                    class="text-left px-3 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 border-b border-gray-200 w-[110px]"
                    >Nota individual</th
                  >
                  <th
                    class="text-left px-3 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 border-b border-gray-200 w-[110px]"
                    >Dif. décimas</th
                  >
                  <th
                    class="text-left px-3 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 border-b border-gray-200 w-[80px]"
                  ></th>
                </tr>
              </thead>
              <tbody>
                {#each grupo.integrantes as integrante (integrante.id_asignado_actividad)}
                  <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 border-b border-gray-100 font-medium text-gray-900">{integrante.nombre_completo}</td>
                    <td class="px-3 py-2 border-b border-gray-100">
                      <input
                        type="number"
                        min="0"
                        max="10"
                        step="0.1"
                        bind:value={integrante.nota_individual}
                        class="w-[75px] px-2 py-1.5 border border-gray-300 rounded-md text-xs text-center focus:outline-none focus:border-blue-500"
                        placeholder="—"
                      />
                    </td>
                    <td class="px-3 py-2 border-b border-gray-100">
                      <input
                        type="number"
                        min="-10"
                        max="10"
                        step="1"
                        bind:value={integrante.diferencia_decimas}
                        class="w-[75px] px-2 py-1.5 border border-gray-300 rounded-md text-xs text-center focus:outline-none focus:border-blue-500"
                        placeholder="0"
                      />
                    </td>
                    <td class="px-3 py-2 border-b border-gray-100">
                      <div class="flex gap-1 justify-end">
                        <button
                          onclick={() => guardarIntegrante(grupo.grupo, integrante)}
                          class="p-1.5 bg-transparent border border-green-300 rounded-lg text-green-600 hover:bg-green-50"
                          title="Guardar nota"
                          disabled={savingIntegrante.has(integrante.id_asignado_actividad)}
                        >
                          <Save size={14} />
                        </button>
                        {#if actividad.es_grupal}
                          <button
                            onclick={() => eliminarIntegrante(grupo.grupo, integrante.id_asignado_actividad)}
                            class="p-1.5 bg-transparent border border-red-300 rounded-lg text-red-500 hover:bg-red-50"
                            title="Quitar del grupo"
                          >
                            <Trash2 size={14} />
                          </button>
                        {/if}
                      </div>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          {/if}
        </div>
      </div>
    {/each}
  </div>
</DocenteLayout>
