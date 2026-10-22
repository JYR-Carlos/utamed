<script lang="ts">
  /**
   * Panel de asistencia de un componente (vista del docente).
   *
   * Modelo: la asistencia se registra por inscripción-componente. No hay entidad
   * "sesión": una sesión es IMPLÍCITA — el conjunto de filas que comparten
   * (dia, hora_inicio, hora_fin). Este panel agrupa por ese trío.
   *
   * Layout de la lámina «Detalle de curso»: dos paneles.
   *  - Izquierda: las sesiones, agrupadas por día. Elegir una la abre a la derecha.
   *  - Derecha: el pase de lista de la sesión elegida, con interruptor por alumno.
   * Debajo queda el resumen por estudiante, que es donde vive el aviso de
   * asistencia bajo el mínimo obligatorio del componente.
   *
   * Carga sus propios datos vía GET JSON cuando cambia el componente activo, y
   * usa router.post/put/delete (Inertia) para crear/editar/eliminar sesiones,
   * recargando la lista tras cada cambio.
   */
  import { router } from '@inertiajs/svelte';
  import { formatFechaLarga } from '@/utils/formatters';
  import {
    CalendarPlus,
    Loader2,
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

  // ── Pase de lista (crear / editar) ──
  /** 'idle' = nada abierto; 'editar' = sesión existente; 'nueva' = fecha/hora editables. */
  let modo = $state<'idle' | 'nueva' | 'editar'>('idle');
  let guardando = $state(false);
  let formError = $state<string | null>(null);
  let fDia = $state('');
  let fInicio = $state('08:00');
  let fFin = $state('09:30');
  /** Mapa id_inscripcion_componente → presente. */
  let presentes = $state<Record<number, boolean>>({});
  /** Móvil: la lista de sesiones muestra sólo la última hasta pedir el resto. */
  let verTodas = $state(false);

  const hoy = new Date().toISOString().slice(0, 10);

  /** Identidad de una sesión: el trío que la define en la BD. */
  const claveSesion = (s: Sesion) => `${s.dia}|${s.hora_inicio}|${s.hora_fin}`;
  const claveActiva = $derived(modo === 'editar' ? `${fDia}|${fInicio}|${fFin}` : '');

  /** Sesiones agrupadas por día, respetando el orden que envía el servidor. */
  const sesionesPorDia = $derived.by(() => {
    const grupos: { dia: string; items: Sesion[] }[] = [];
    for (const s of sesiones) {
      const ultimo = grupos.at(-1);
      if (ultimo && ultimo.dia === s.dia) ultimo.items.push(s);
      else grupos.push({ dia: s.dia, items: [s] });
    }
    return grupos;
  });

  // Recargar cuando cambia el componente activo.
  $effect(() => {
    idComponente;
    cargar();
  });

  async function cargar() {
    cargando = true;
    error = null;
    modo = 'idle';
    try {
      // GET lazy de datos del panel (no muta estado del servidor → no requiere CSRF).
      // TODO(D-03): migrar a router.reload({ only:[...] }) cuando el controlador exponga prop lazy.
      const res = await fetch(
        `/docente/cursos/${idCurso}/componentes/${idComponente}/asistencia`,
        { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
      );
      if (!res.ok) throw new Error();
      const data = await res.json();
      estudiantes = (data.estudiantes ?? []).sort(
        (a: EstudianteAsistencia, b: EstudianteAsistencia) =>
          (a.nombre ?? '').localeCompare(b.nombre ?? '', 'es', { sensitivity: 'base' }),
      );
      sesiones = data.sesiones ?? [];
      porcentajeObligatorio = data.porcentaje_asistencia_obligatoria ?? null;
      // La sesión más reciente queda abierta: es la que el docente acaba de tomar.
      if (sesiones.length > 0) abrirSesion(sesiones[0]);
    } catch {
      error = 'No se pudo cargar la asistencia.';
    } finally {
      cargando = false;
    }
  }

  function nuevaSesion() {
    modo = 'nueva';
    formError = null;
    fDia = hoy;
    fInicio = '08:00';
    fFin = '09:30';
    // Por defecto todos presentes (coincide con el default de la BD).
    presentes = Object.fromEntries(estudiantes.map((e) => [e.id_inscripcion_componente, true]));
  }

  function abrirSesion(s: Sesion) {
    modo = 'editar';
    formError = null;
    fDia = s.dia;
    fInicio = s.hora_inicio;
    fFin = s.hora_fin;
    const map: Record<number, boolean> = {};
    for (const e of estudiantes) map[e.id_inscripcion_componente] = true;
    for (const r of s.registros) map[r.id_inscripcion_componente] = r.esta_presente;
    presentes = map;
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

  const todosPresentes = $derived(
    estudiantes.length > 0 && totalPresentesForm === estudiantes.length,
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

    if (modo === 'editar') router.put(url, payload, opts);
    else router.post(url, payload, opts);
  }

  function eliminarSesion() {
    if (
      !confirm(`¿Eliminar la sesión del ${formatFechaLarga(fDia)} (${fInicio}–${fFin})?`)
    )
      return;
    router.delete(`/docente/cursos/${idCurso}/componentes/${idComponente}/asistencia`, {
      data: { dia: fDia, hora_inicio: fInicio, hora_fin: fFin },
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => cargar(),
      onError: (errors: Record<string, string>) => {
        error = (Object.values(errors)[0] as string) ?? 'No se pudo eliminar la sesión.';
      },
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

  function pctColor(porc: number): string {
    if (bajoMinimo(porc)) return 'text-red-700';
    return porc >= 75 ? 'text-emerald-700' : 'text-amber-700';
  }

  const CAMPO =
    'px-2.5 py-1.5 text-[13px] rounded-lg border border-line bg-white focus:outline-none focus:border-navy';
  // Mismos botones que el detalle del curso (mockup).
  const BTN_PRIMARIO =
    'inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-uta-blue px-4 text-sm font-semibold text-white transition-colors hover:bg-uta-blue-hover sm:h-10';
  const BTN_SECUNDARIO =
    'inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-lg border border-line-control bg-white px-4 text-sm font-semibold text-uta-blue transition-colors hover:bg-uta-blue-light sm:h-10';
</script>

<div class="flex flex-col gap-4">
  {#if cargando}
    <div class="flex items-center justify-center gap-2 py-16 text-ink-2 text-sm">
      <Loader2 size={18} class="animate-spin" />
      Cargando asistencia…
    </div>
  {:else if error}
    <div class="text-center py-12 text-red-700 text-sm">{error}</div>
  {:else if estudiantes.length === 0}
    <div
      class="flex flex-col items-center justify-center gap-2 py-14 text-center rounded-xl border border-dashed bg-sand border-sand-line"
    >
      <Users size={26} class="text-ink-3" />
      <p class="text-sm text-ink-2 m-0">Sin estudiantes inscritos en este componente.</p>
    </div>
  {:else if sesiones.length === 0 && modo === 'idle'}
    <!-- Sin sesiones: un solo estado vacío, con la acción adentro. -->
    <div
      class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-slate-300 bg-white px-5 py-14 text-center"
    >
      <ClipboardCheck size={28} class="text-slate-500" aria-hidden="true" />
      <div class="flex flex-col gap-1">
        <p class="m-0 text-base font-semibold text-ink">Aún no hay sesiones de {tipoComponente}.</p>
        <p class="m-0 text-sm text-slate-600">Registra la primera para pasar lista.</p>
      </div>
      <button type="button" onclick={nuevaSesion} class={BTN_PRIMARIO}>
        <CalendarPlus size={16} aria-hidden="true" />
        Nueva sesión
      </button>
    </div>
  {:else}
    <div class="flex flex-col lg:flex-row gap-[18px] items-start">
      <!-- ── Panel izquierdo: sesiones ── -->
      <div class="w-full lg:w-[360px] lg:flex-none flex flex-col gap-2.5">
        <div class="flex items-center gap-2.5">
          <h3 class="text-sm font-semibold text-ink m-0">Sesiones</h3>
          <span class="text-xs text-ink-2">
            {sesiones.length}
            {sesiones.length === 1 ? 'registrada' : 'registradas'}
          </span>
          <!-- Primario sólo mientras no hay una lista abierta: ahí manda «Guardar lista». -->
          <button type="button" onclick={nuevaSesion} class="ml-auto {modo === 'idle' ? BTN_PRIMARIO : BTN_SECUNDARIO}">
            <CalendarPlus size={16} aria-hidden="true" />
            Nueva sesión
          </button>
        </div>

        {#if sesiones.length === 0}
          <p class="m-0 text-sm text-slate-600">Ésta será la primera sesión del componente.</p>
        {:else}
          {#each sesionesPorDia as grupo, g (grupo.dia)}
            <!-- En móvil se ve sólo la última sesión; el resto, con «Ver las N sesiones». -->
            <span
              class="font-mono text-xs uppercase tracking-[0.08em] text-ink-2 pt-1.5 first:pt-0.5 {g > 0 && !verTodas
                ? 'max-lg:hidden'
                : ''}"
            >
              {formatFechaLarga(grupo.dia)}
            </span>
            {#each grupo.items as s, j (claveSesion(s))}
              {@const activa = claveSesion(s) === claveActiva}
              <button
                onclick={() => abrirSesion(s)}
                aria-current={activa ? 'true' : undefined}
                class="flex w-full items-center gap-3 rounded-[10px] border px-3 py-2.5 text-left transition-colors duration-150 {activa
                  ? 'bg-slate-50 border-slate-300 border-l-[3px] border-l-navy'
                  : 'bg-white border-gray-200 hover:bg-stone-50'} {(g > 0 || j > 0) && !verTodas ? 'max-lg:hidden' : ''}"
              >
                <span class="flex flex-col gap-0.5 min-w-0">
                  <span
                    class="font-mono text-[13px] font-semibold tabular-nums {activa
                      ? 'text-navy'
                      : 'text-ink'}">{s.hora_inicio} – {s.hora_fin}</span
                  >
                  <span class="text-[11.5px] text-ink-2">{tipoComponente}</span>
                </span>
                <span class="ml-auto flex flex-none flex-col items-end gap-0.5">
                  <span class="font-mono text-sm font-semibold tabular-nums text-ink"
                    >{s.presentes}/{s.total}</span
                  >
                  <span class="text-xs text-ink-2">presentes</span>
                </span>
              </button>
            {/each}
          {/each}
          {#if sesiones.length > 1}
            <button type="button" onclick={() => (verTodas = !verTodas)} class="{BTN_SECUNDARIO} lg:hidden">
              {verTodas ? 'Ver sólo la última sesión' : `Ver las ${sesiones.length} sesiones`}
            </button>
          {/if}
        {/if}
      </div>

      <!-- ── Panel derecho: pase de lista. En móvil, con una lista abierta, va
           primero; sin lista abierta no se muestra (el aviso sólo sirve al lado
           de las sesiones, en escritorio). ── -->
      <div
        class="w-full lg:flex-1 min-w-0 rounded-xl border border-gray-200 flex flex-col {modo === 'idle'
          ? 'max-lg:hidden'
          : 'max-lg:order-first'}"
      >
        {#if modo === 'idle'}
          <div
            class="flex flex-col items-center justify-center gap-2 py-16 text-center text-ink-2"
          >
            <ClipboardCheck size={28} class="text-ink-3" />
            <p class="text-[13px] m-0">
              Elige una sesión de la izquierda o registra una nueva para pasar lista.
            </p>
          </div>
        {:else}
          <!-- Cabecera -->
          <div
            class="flex flex-wrap items-center gap-3.5 rounded-t-xl border-b border-gray-200 bg-sand px-4 py-3"
          >
            <div class="flex flex-col gap-0.5 min-w-0">
              <span class="text-sm font-semibold text-ink"
                >Pase de lista · {tipoComponente}</span
              >
              {#if modo === 'editar'}
                <span class="font-mono text-[11.5px] text-ink-2">
                  {formatFechaLarga(fDia)} · {fInicio} – {fFin}
                </span>
              {:else}
                <div class="flex flex-wrap items-center gap-2 pt-1">
                  <input type="date" bind:value={fDia} class={CAMPO} aria-label="Fecha" />
                  <input type="time" bind:value={fInicio} class={CAMPO} aria-label="Hora inicio" />
                  <span class="text-muted-foreground">–</span>
                  <input type="time" bind:value={fFin} class={CAMPO} aria-label="Hora término" />
                </div>
              {/if}
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-3.5">
              <div class="flex flex-col items-end">
                <span class="font-mono text-lg font-semibold leading-tight tabular-nums text-ink"
                  >{totalPresentesForm}/{estudiantes.length}</span
                >
                <span class="text-[11px] text-ink-2">presentes</span>
              </div>
              {#if modo === 'editar'}
                <button
                  onclick={eliminarSesion}
                  class="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-red-100 hover:text-red-700"
                  aria-label="Eliminar sesión"
                >
                  <Trash2 size={15} />
                </button>
              {/if}
              <button type="button" onclick={() => marcarTodos(!todosPresentes)} class={BTN_SECUNDARIO}>
                {todosPresentes ? 'Desmarcar todos' : 'Marcar todos'}
              </button>
              <button type="button" onclick={guardar} disabled={guardando} class="{BTN_PRIMARIO} disabled:opacity-50">
                {#if guardando}
                  <Loader2 size={14} class="animate-spin" />
                {:else}
                  <Check size={14} />
                {/if}
                Guardar lista
              </button>
            </div>
          </div>

          {#if formError}
            <p class="border-b border-gray-200 px-4 py-2 text-[12.5px] text-red-700 m-0">
              {formError}
            </p>
          {/if}

          <!-- Grilla de alumnos -->
          <div class="grid grid-cols-1 md:grid-cols-2">
            {#each estudiantes as e, i (e.id_inscripcion_componente)}
              {@const pres = !!presentes[e.id_inscripcion_componente]}
              <div
                class="flex items-center gap-3 border-b border-gray-200 px-4 py-2.5 {i % 2 === 0
                  ? 'md:border-r md:border-r-gray-200'
                  : ''} {Math.floor(i / 2) % 2 === 1 ? 'bg-stone-50' : ''}"
              >
                <span class="w-[22px] shrink-0 font-mono text-[12px] tabular-nums text-ink-2"
                  >{String(i + 1).padStart(2, '0')}</span
                >
                <!-- Nombre completo: con truncate se cortaba justo el apellido que distingue. -->
                <span class="min-w-0 flex-1 break-words text-[13.5px] font-medium text-ink">{e.nombre}</span>
                <span class="ml-auto flex flex-none items-center gap-2.5">
                  <span class="text-xs font-semibold {pres ? 'text-emerald-700' : 'text-slate-600'}"
                    >{pres ? 'Presente' : 'Ausente'}</span
                  >
                  <!-- El ::before agranda la zona táctil a 44 px sin mover el diseño. -->
                  <button
                    role="switch"
                    aria-checked={pres}
                    aria-label="{e.nombre}: marcar {pres ? 'ausente' : 'presente'}"
                    onclick={() => togglePresente(e.id_inscripcion_componente)}
                    class="relative flex h-[22px] w-[38px] shrink-0 items-center rounded-full p-0.5 transition-colors duration-150 before:absolute before:-inset-x-1.5 before:-inset-y-[11px] before:content-[''] {pres
                      ? 'justify-end bg-emerald-600'
                      : 'justify-start bg-slate-500'}"
                  >
                    <span class="h-[18px] w-[18px] rounded-full bg-white shadow-sm"></span>
                  </button>
                </span>
              </div>
            {/each}
          </div>

          <div class="flex items-center gap-3 px-4 py-2.5 text-[12px] text-ink-2">
            <span class="mr-auto">
              {estudiantes.length}
              {estudiantes.length === 1 ? 'estudiante' : 'estudiantes'} · el orden sigue la lista de
              clase
            </span>
            {#if modo === 'nueva'}
              <button
                onclick={() => (sesiones.length > 0 ? abrirSesion(sesiones[0]) : (modo = 'idle'))}
                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 font-medium text-ink-2 transition-colors hover:bg-sand"
              >
                <X size={13} />
                Descartar
              </button>
            {/if}
          </div>
        {/if}
      </div>
    </div>

    <!-- ── Resumen por estudiante ── -->
    {#if sesiones.length > 0}
      <div class="flex flex-col gap-2">
        <div class="flex items-baseline gap-2.5">
          <h3 class="text-sm font-semibold text-ink m-0">Asistencia por estudiante</h3>
          {#if porcentajeObligatorio != null}
            <span class="text-[11.5px] text-ink-2">
              Mínimo obligatorio del componente:
              <strong class="font-semibold text-ink">{Number(porcentajeObligatorio)} %</strong>
            </span>
          {/if}
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200">
          <table class="w-full border-collapse text-sm">
            <thead class="bg-sand">
              <tr>
                <th
                  class="px-3 py-2.5 sm:px-4 text-left text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-2"
                  >Estudiante</th
                >
                <th
                  class="px-3 py-2.5 sm:px-4 text-right text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-2"
                  >Asistió</th
                >
                <th
                  class="px-3 py-2.5 sm:px-4 text-right text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-2"
                  >%</th
                >
              </tr>
            </thead>
            <tbody>
              {#each resumen as r, i (r.estudiante.id_inscripcion_componente)}
                <tr class="border-t border-gray-200 {i % 2 === 1 ? 'bg-stone-50' : ''}">
                  <td class="px-3 py-2.5 sm:px-4">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                      <span class="text-[13.5px] text-ink">{r.estudiante.nombre}</span>
                      {#if bajoMinimo(r.porcentaje)}
                        <span
                          class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-xs font-semibold text-amber-700"
                        >
                          <AlertTriangle size={12} aria-hidden="true" /> Bajo mínimo
                        </span>
                      {/if}
                    </div>
                  </td>
                  <td class="px-3 py-2.5 sm:px-4 text-right font-mono tabular-nums text-[13px] text-ink-2"
                    >{r.presentes}/{r.total}</td
                  >
                  <td
                    class="px-3 py-2.5 sm:px-4 text-right font-mono text-[13px] font-semibold tabular-nums {pctColor(
                      r.porcentaje,
                    )}">{r.porcentaje}%</td
                  >
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      </div>
    {/if}
  {/if}
</div>
