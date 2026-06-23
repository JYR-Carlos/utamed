<script lang="ts">
  /**
   * Panel de asistencia de un componente (vista del docente).
   *
   * Modelo: la asistencia se registra por inscripción-componente. No hay entidad
   * "sesión": una sesión es IMPLÍCITA — el conjunto de filas que comparten
   * (dia, hora_inicio, hora_fin). Este panel agrupa por ese trío.
   *
   * Carga sus propios datos vía GET JSON cuando cambia el componente activo, y
   * usa router.post/put/delete (Inertia) para crear/editar/eliminar sesiones,
   * recargando la lista tras cada cambio.
   */
  import { router } from '@inertiajs/svelte';
  import {
    CalendarPlus,
    Loader2,
    Pencil,
    Trash2,
    Check,
    X,
    Users,
    ClipboardCheck,
    AlertTriangle,
  } from 'lucide-svelte';

  interface EstudianteAsistencia {
    id_inscripcion_componente: number;
    id_estudiante: number;
    nombre: string;
    username: string;
  }

  interface RegistroSesion {
    id_inscripcion_componente: number;
    esta_presente: boolean;
  }

  interface Sesion {
    dia: string;
    hora_inicio: string;
    hora_fin: string;
    total: number;
    presentes: number;
    registros: RegistroSesion[];
  }

  interface Props {
    idCurso: number;
    idComponente: number;
    tipoComponente: string;
  }

  let { idCurso, idComponente, tipoComponente }: Props = $props();

  let cargando = $state(false);
  let error = $state<string | null>(null);
  let estudiantes = $state<EstudianteAsistencia[]>([]);
  let sesiones = $state<Sesion[]>([]);
  let porcentajeObligatorio = $state<number | null>(null);

  // ── Formulario de sesión (crear / editar) ──
  let mostrarForm = $state(false);
  let editando = $state(false); // true → PUT (la fecha/hora quedan fijas)
  let guardando = $state(false);
  let formError = $state<string | null>(null);
  let fDia = $state('');
  let fInicio = $state('08:00');
  let fFin = $state('09:30');
  /** Mapa id_inscripcion_componente → presente. */
  let presentes = $state<Record<number, boolean>>({});

  const hoy = new Date().toISOString().slice(0, 10);

  // Recargar cuando cambia el componente activo.
  $effect(() => {
    idComponente;
    cargar();
  });

  async function cargar() {
    cargando = true;
    error = null;
    mostrarForm = false;
    try {
      const res = await fetch(
        `/docente/cursos/${idCurso}/componentes/${idComponente}/asistencia`,
        { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
      );
      if (!res.ok) throw new Error();
      const data = await res.json();
      estudiantes = data.estudiantes ?? [];
      sesiones = data.sesiones ?? [];
      porcentajeObligatorio = data.porcentaje_asistencia_obligatoria ?? null;
    } catch {
      error = 'No se pudo cargar la asistencia.';
    } finally {
      cargando = false;
    }
  }

  function nuevaSesion() {
    editando = false;
    formError = null;
    fDia = hoy;
    fInicio = '08:00';
    fFin = '09:30';
    // Por defecto todos presentes (coincide con el default de la BD).
    presentes = Object.fromEntries(estudiantes.map((e) => [e.id_inscripcion_componente, true]));
    mostrarForm = true;
  }

  function editarSesion(s: Sesion) {
    editando = true;
    formError = null;
    fDia = s.dia;
    fInicio = s.hora_inicio;
    fFin = s.hora_fin;
    const map: Record<number, boolean> = {};
    for (const e of estudiantes) map[e.id_inscripcion_componente] = true;
    for (const r of s.registros) map[r.id_inscripcion_componente] = r.esta_presente;
    presentes = map;
    mostrarForm = true;
  }

  function cerrarForm() {
    mostrarForm = false;
    formError = null;
  }

  function togglePresente(id: number) {
    presentes = { ...presentes, [id]: !presentes[id] };
  }

  function marcarTodos(valor: boolean) {
    presentes = Object.fromEntries(estudiantes.map((e) => [e.id_inscripcion_componente, valor]));
  }

  const totalPresentesForm = $derived(
    estudiantes.filter((e) => presentes[e.id_inscripcion_componente]).length,
  );

  function guardar() {
    if (!fDia || !fInicio || !fFin) {
      formError = 'Completa fecha y horario.';
      return;
    }
    if (fFin <= fInicio) {
      formError = 'La hora de término debe ser posterior a la de inicio.';
      return;
    }
    guardando = true;
    formError = null;

    const payload = {
      dia: fDia,
      hora_inicio: fInicio,
      hora_fin: fFin,
      asistencias: estudiantes.map((e) => ({
        id_inscripcion_componente: e.id_inscripcion_componente,
        esta_presente: !!presentes[e.id_inscripcion_componente],
      })),
    };

    const url = `/docente/cursos/${idCurso}/componentes/${idComponente}/asistencia`;
    const opts = {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        guardando = false;
        cargar();
      },
      onError: (errors: Record<string, string>) => {
        formError = (Object.values(errors)[0] as string) ?? 'No se pudo guardar.';
        guardando = false;
      },
    };

    if (editando) router.put(url, payload, opts);
    else router.post(url, payload, opts);
  }

  function eliminarSesion(s: Sesion) {
    if (!confirm(`¿Eliminar la sesión del ${formatFecha(s.dia)} (${s.hora_inicio}–${s.hora_fin})?`))
      return;
    router.delete(`/docente/cursos/${idCurso}/componentes/${idComponente}/asistencia`, {
      data: { dia: s.dia, hora_inicio: s.hora_inicio, hora_fin: s.hora_fin },
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => cargar(),
    });
  }

  // ── Resumen por estudiante (% de asistencia sobre todas las sesiones) ──
  interface ResumenEstudiante {
    estudiante: EstudianteAsistencia;
    presentes: number;
    total: number;
    porcentaje: number;
  }

  const resumen = $derived.by<ResumenEstudiante[]>(() => {
    const totalSesiones = sesiones.length;
    return estudiantes.map((e) => {
      let pres = 0;
      for (const s of sesiones) {
        const r = s.registros.find(
          (x) => x.id_inscripcion_componente === e.id_inscripcion_componente,
        );
        if (r?.esta_presente) pres++;
      }
      return {
        estudiante: e,
        presentes: pres,
        total: totalSesiones,
        porcentaje: totalSesiones === 0 ? 0 : Math.round((pres / totalSesiones) * 100),
      };
    });
  });

  function bajoMinimo(porc: number): boolean {
    return porcentajeObligatorio != null && porc < porcentajeObligatorio;
  }

  function formatFecha(d: string): string {
    return new Date(d + 'T00:00:00').toLocaleDateString('es-CL', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  function pctColor(porc: number): string {
    if (bajoMinimo(porc)) return 'text-[#DC2626]';
    return porc >= 75 ? 'text-[#0E7C4A]' : 'text-[#8A5F00]';
  }
</script>

<div class="space-y-5">
  <!-- Encabezado -->
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <div class="flex items-center gap-2 text-sm text-[#5A5E6E]">
      <ClipboardCheck size={16} class="text-[#002F6C]" />
      <span class="font-medium text-[#1A1A24]">{tipoComponente}</span>
      {#if porcentajeObligatorio != null}
        <span class="text-[#8A8E9C]">·</span>
        <span class="text-xs">Mínimo obligatorio: <strong class="text-[#1A1A24]">{porcentajeObligatorio}%</strong></span>
      {/if}
    </div>
    <button
      onclick={nuevaSesion}
      disabled={cargando || estudiantes.length === 0}
      class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold text-white rounded-[10px] transition-all bg-[#002F6C] hover:bg-[#1B4789] disabled:opacity-50 disabled:cursor-not-allowed"
    >
      <CalendarPlus size={15} />
      Nueva sesión
    </button>
  </div>

  {#if cargando}
    <div class="flex items-center justify-center gap-2 py-16 text-[#8A8E9C] text-sm">
      <Loader2 size={18} class="animate-spin" />
      Cargando asistencia…
    </div>
  {:else if error}
    <div class="text-center py-12 text-[#DC2626] text-sm">{error}</div>
  {:else}
    <!-- Formulario de sesión -->
    {#if mostrarForm}
      <div class="border border-[#002F6C]/25 rounded-xl bg-[#F7F9FC] p-5 space-y-4">
        <div class="flex items-center justify-between">
          <h4 class="text-sm font-bold text-[#1A1A24] m-0">
            {editando ? 'Editar sesión' : 'Registrar nueva sesión'}
          </h4>
          <button onclick={cerrarForm} class="text-[#8A8E9C] hover:text-[#1A1A24]" aria-label="Cerrar">
            <X size={16} />
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <label class="flex flex-col gap-1 text-xs font-medium text-[#5A5E6E]">
            Fecha
            <input
              type="date"
              bind:value={fDia}
              disabled={editando}
              class="px-3 py-2 text-sm rounded-lg border border-[#E8E4DC] bg-white focus:outline-none focus:border-[#002F6C] disabled:bg-[#F5F1EA] disabled:text-[#8A8E9C]"
            />
          </label>
          <label class="flex flex-col gap-1 text-xs font-medium text-[#5A5E6E]">
            Hora inicio
            <input
              type="time"
              bind:value={fInicio}
              disabled={editando}
              class="px-3 py-2 text-sm rounded-lg border border-[#E8E4DC] bg-white focus:outline-none focus:border-[#002F6C] disabled:bg-[#F5F1EA] disabled:text-[#8A8E9C]"
            />
          </label>
          <label class="flex flex-col gap-1 text-xs font-medium text-[#5A5E6E]">
            Hora término
            <input
              type="time"
              bind:value={fFin}
              disabled={editando}
              class="px-3 py-2 text-sm rounded-lg border border-[#E8E4DC] bg-white focus:outline-none focus:border-[#002F6C] disabled:bg-[#F5F1EA] disabled:text-[#8A8E9C]"
            />
          </label>
        </div>

        {#if editando}
          <p class="text-[11px] text-[#8A8E9C] -mt-1">
            La fecha y el horario identifican la sesión y no pueden modificarse. Para cambiarlos,
            elimina la sesión y crea una nueva.
          </p>
        {/if}

        <!-- Marcado de estudiantes -->
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase tracking-[0.06em] text-[#8A8E9C]">
            Estudiantes ({totalPresentesForm}/{estudiantes.length} presentes)
          </span>
          <div class="flex gap-2">
            <button onclick={() => marcarTodos(true)} class="text-xs font-medium text-[#0E7C4A] hover:underline">Todos presentes</button>
            <span class="text-[#D0CBC1]">·</span>
            <button onclick={() => marcarTodos(false)} class="text-xs font-medium text-[#DC2626] hover:underline">Todos ausentes</button>
          </div>
        </div>

        <div class="border border-[#E8E4DC] rounded-lg overflow-hidden max-h-72 overflow-y-auto bg-white">
          {#each estudiantes as e, i (e.id_inscripcion_componente)}
            {@const pres = !!presentes[e.id_inscripcion_componente]}
            <div
              class="flex items-center justify-between gap-3 px-4 py-2.5 {i < estudiantes.length - 1 ? 'border-b border-[#E8E4DC]' : ''}"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="flex items-center justify-center h-7 w-7 rounded-full text-[11px] font-semibold text-white shrink-0 bg-[#002F6C]">
                  {e.nombre.charAt(0).toUpperCase()}
                </div>
                <span class="text-sm text-[#1A1A24] truncate">{e.nombre}</span>
              </div>
              <button
                onclick={() => togglePresente(e.id_inscripcion_componente)}
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all shrink-0 {pres ? 'bg-[#E0F5EA] text-[#0E7C4A] border border-[rgba(14,124,74,0.25)]' : 'bg-[#FEE2E2] text-[#DC2626] border border-[rgba(220,38,38,0.25)]'}"
              >
                {#if pres}
                  <Check size={13} /> Presente
                {:else}
                  <X size={13} /> Ausente
                {/if}
              </button>
            </div>
          {/each}
        </div>

        {#if formError}
          <p class="text-xs text-[#DC2626]">{formError}</p>
        {/if}

        <div class="flex justify-end gap-2">
          <button onclick={cerrarForm} class="px-4 py-2 text-sm font-medium text-[#5A5E6E] border border-[#E8E4DC] rounded-[10px] hover:bg-[#F5F1EA]">
            Cancelar
          </button>
          <button
            onclick={guardar}
            disabled={guardando}
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-[10px] bg-[#002F6C] hover:bg-[#1B4789] disabled:opacity-50"
          >
            {#if guardando}<Loader2 size={14} class="animate-spin" />{/if}
            {editando ? 'Guardar cambios' : 'Registrar sesión'}
          </button>
        </div>
      </div>
    {/if}

    {#if estudiantes.length === 0}
      <div class="flex flex-col items-center justify-center py-14 text-center rounded-xl border-2 border-dashed bg-[#F5F1EA] border-[#D0CBC1]">
        <Users size={26} class="text-[#8A8E9C] mb-2" />
        <p class="text-sm text-[#5A5E6E]">Sin estudiantes inscritos en este componente.</p>
      </div>
    {:else}
      <!-- Sesiones registradas -->
      <div>
        <h4 class="text-xs font-semibold uppercase tracking-[0.06em] text-[#8A8E9C] mb-2">
          Sesiones registradas ({sesiones.length})
        </h4>
        {#if sesiones.length === 0}
          <div class="flex flex-col items-center justify-center py-10 text-center rounded-xl border border-dashed bg-[#F5F1EA] border-[#D0CBC1]">
            <ClipboardCheck size={26} class="text-[#8A8E9C] mb-2" />
            <p class="text-sm text-[#5A5E6E]">Aún no hay sesiones. Registra la primera con «Nueva sesión».</p>
          </div>
        {:else}
          <div class="border border-[#E8E4DC] rounded-xl overflow-hidden divide-y divide-[#E8E4DC]">
            {#each sesiones as s (s.dia + s.hora_inicio + s.hora_fin)}
              {@const pct = s.total === 0 ? 0 : Math.round((s.presentes / s.total) * 100)}
              <div class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-[#F5F1EA] transition-colors">
                <div class="min-w-0">
                  <p class="text-sm font-medium text-[#1A1A24]">{formatFecha(s.dia)}</p>
                  <p class="text-xs text-[#8A8E9C]">{s.hora_inicio} – {s.hora_fin}</p>
                </div>
                <div class="flex items-center gap-4 shrink-0">
                  <div class="text-right">
                    <p class="text-sm font-semibold tabular-nums {pctColor(pct)}">{pct}%</p>
                    <p class="text-xs text-[#8A8E9C] tabular-nums">{s.presentes}/{s.total} presentes</p>
                  </div>
                  <div class="flex items-center gap-1">
                    <button onclick={() => editarSesion(s)} class="p-1.5 rounded-lg text-[#8A8E9C] hover:text-[#002F6C] hover:bg-[#E6ECF5]" aria-label="Editar sesión">
                      <Pencil size={14} />
                    </button>
                    <button onclick={() => eliminarSesion(s)} class="p-1.5 rounded-lg text-[#8A8E9C] hover:text-[#DC2626] hover:bg-[#FEE2E2]" aria-label="Eliminar sesión">
                      <Trash2 size={14} />
                    </button>
                  </div>
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </div>

      <!-- Resumen por estudiante -->
      {#if sesiones.length > 0}
        <div>
          <h4 class="text-xs font-semibold uppercase tracking-[0.06em] text-[#8A8E9C] mb-2">
            Asistencia por estudiante
          </h4>
          <div class="border border-[#E8E4DC] rounded-xl overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-[#F5F1EA] border-b border-[#E8E4DC]">
                <tr>
                  <th class="px-4 py-2.5 text-left text-xs font-medium uppercase tracking-[0.06em] text-[#8A8E9C]">Estudiante</th>
                  <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-[0.06em] text-[#8A8E9C]">Asistió</th>
                  <th class="px-4 py-2.5 text-right text-xs font-medium uppercase tracking-[0.06em] text-[#8A8E9C]">%</th>
                </tr>
              </thead>
              <tbody>
                {#each resumen as r, i (r.estudiante.id_inscripcion_componente)}
                  <tr class="{i < resumen.length - 1 ? 'border-b border-[#E8E4DC]' : ''}">
                    <td class="px-4 py-2.5">
                      <div class="flex items-center gap-2">
                        <span class="text-sm text-[#1A1A24]">{r.estudiante.nombre}</span>
                        {#if bajoMinimo(r.porcentaje)}
                          <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-[#FEE2E2] text-[#DC2626]">
                            <AlertTriangle size={9} /> Bajo mínimo
                          </span>
                        {/if}
                      </div>
                    </td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-[#5A5E6E]">{r.presentes}/{r.total}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums font-semibold {pctColor(r.porcentaje)}">{r.porcentaje}%</td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        </div>
      {/if}
    {/if}
  {/if}
</div>
