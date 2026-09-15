<script lang="ts">
  import type { Rubrica } from '@/types/rubrica';
  import { router } from '@inertiajs/svelte';
  import RubricaView from '../../student/Activities/Agenda/Rubrica.svelte';
  import { X, Plus, Trash2, Eye, Pencil, ChevronLeft, Lock } from 'lucide-svelte';
  import { puntajeMinimoAprobacion } from '@/lib/notas';

  interface Props {
    rubrica?: Rubrica | null;
    idCurso: number;
    idActividad: number;
    /**
     * Sumativa → la rúbrica se convierte en una nota de 1,0 a 7,0 con 60 % de
     * exigencia, y no lleva escala cualitativa. Formativa → al revés: la escala
     * cualitativa es todo el resultado y no hay nota. Lo decide la actividad,
     * así que viene del padre.
     */
    esSumativa: boolean;
    bloqueada?: boolean;
    onClose: () => void;
  }

  let { rubrica = null, idCurso, idActividad, esSumativa, bloqueada = false, onClose }: Props = $props();

  // ── Draft types ────────────────────────────────────────────────────────────
  /**
   * La celda ya no lleva puntaje: el puntaje es de la columna. Dejarlo aquí
   * permitiría que dos celdas de la misma columna valieran distinto, que es
   * justo lo que se quería quitar.
   */
  type EscalaDraft = { _id: string; criterio: string };
  type ColumnaDraft = { _id: string; nombre: string; puntos: number | string };
  type NivelDraft = {
    _id: string;
    nombre: string;
    descripcion: string;
    /** Peso del criterio en porcentaje; entre todos deben sumar 100. */
    ponderacion: number | string;
    escalas: EscalaDraft[];
  };
  type EscalaCalif = { _id: string; puntaje_minimo: number | string; evaluacion: string };

  /**
   * Nombres de arranque para 3, 4 y 5 niveles. El docente los puede reescribir
   * uno por uno después de aplicarlos: la plantilla es un punto de partida, no
   * un catálogo cerrado.
   */
  const PLANTILLAS_NIVELES: Record<number, readonly string[]> = {
    3: ['Insuficiente', 'Aceptable', 'Destacado'],
    4: ['Insuficiente', 'Regular', 'Bueno', 'Excelente'],
    5: ['Muy Deficiente', 'Deficiente', 'Aceptable', 'Bueno', 'Excelente'],
  };

  function uid() {
    return Math.random().toString(36).slice(2, 10);
  }

  function makeEscalas(n: number): EscalaDraft[] {
    return Array.from({ length: n }, () => ({ _id: uid(), criterio: '' }));
  }

  function makeNivel(cols: number): NivelDraft {
    return { _id: uid(), nombre: '', descripcion: '', ponderacion: '', escalas: makeEscalas(cols) };
  }

  /**
   * Reparte 100 % entre `total` criterios con dos decimales.
   *
   * El sobrante va al primero: con tres criterios, 33,33 % cada uno suma 99,99
   * y la rúbrica no se podría guardar nunca. Repartir parejo y cuadrar el resto
   * en una fila es lo que hace que «exactamente 100» sea alcanzable a mano.
   */
  function repartirPonderacion(total: number): number[] {
    if (total <= 0) return [];
    const base = Math.floor((100 / total) * 100) / 100;
    const resto = Math.round((100 - base * total) * 100) / 100;
    return Array.from({ length: total }, (_, i) =>
      i === 0 ? Math.round((base + resto) * 100) / 100 : base,
    );
  }

  // ── Init ──────────────────────────────────────────────────────────────────

  /**
   * Columnas de la rúbrica que se está editando. Tres casos, en este orden:
   *
   *  1. La rúbrica ya trae `columnas` → se usan tal cual.
   *  2. Rúbrica guardada antes de que las columnas tuvieran nombre: se
   *     reconstruye una por cada escala del primer criterio. El puntaje sale
   *     del mayor que aparezca en esa columna, porque en el formato viejo cada
   *     fila podía tener el suyo, y quedarse con el menor bajaría en silencio
   *     el máximo alcanzable de la rúbrica.
   *  3. Rúbrica nueva → la plantilla de 3 niveles, para que arranque con los
   *     nombres puestos en vez de «Nivel 1».
   */
  function initColumnas(): ColumnaDraft[] {
    const guardadas = rubrica?.columnas;
    if (guardadas?.length) {
      return guardadas.map((c) => ({
        _id: (c as any).id ?? (c as any)._id ?? uid(),
        nombre: c.nombre,
        puntos: c.puntos,
      }));
    }

    const filas = rubrica?.niveles ?? [];
    if (filas.length) {
      const cols = filas[0]?.escalas?.length ?? 3;
      return Array.from({ length: cols }, (_, i) => ({
        _id: uid(),
        nombre: `Nivel ${i + 1}`,
        puntos: filas.reduce((max, f) => Math.max(max, Number(f.escalas[i]?.puntos) || 0), 0),
      }));
    }

    return PLANTILLAS_NIVELES[3].map((nombre) => ({ _id: uid(), nombre, puntos: '' }));
  }

  const columnasIniciales = initColumnas();

  function initNiveles(): NivelDraft[] {
    if (!rubrica?.niveles?.length) {
      const nuevo = makeNivel(columnasIniciales.length);
      return [{ ...nuevo, ponderacion: 100 }];
    }

    // Rúbricas guardadas sin ponderación: se reparte el 100 % en partes
    // iguales en vez de dejarlas en cero. Con cero, abrir una rúbrica vieja
    // para corregirle una tilde la volvería inguardable hasta rellenar a mano
    // todas las filas.
    const reparto = repartirPonderacion(rubrica.niveles.length);

    return rubrica.niveles.map((n, i) => ({
      _id: (n as any).id ?? (n as any)._id ?? uid(),
      nombre: n.nombre,
      descripcion: n.descripcion,
      ponderacion: n.ponderacion ?? reparto[i],
      escalas: n.escalas.map((e) => ({
        _id: (e as any).id ?? (e as any)._id ?? uid(),
        criterio: e.criterio,
      })),
    }));
  }

  function initEscalaCalif(): EscalaCalif[] {
    const src = rubrica?.detalles_evaluacion?.escala_evaluacion;
    if (!src?.length) {
      const maxCol = columnasIniciales.reduce((m, c) => Math.max(m, Number(c.puntos) || 0), 0);
      const filasCount = rubrica?.niveles?.length ?? 1;
      const totalEstimado = maxCol > 0 ? filasCount * maxCol : 100;
      const minAprobado = Math.round(totalEstimado * 0.6);
      return [
        { _id: uid(), puntaje_minimo: minAprobado, evaluacion: 'Aprobado' },
        { _id: uid(), puntaje_minimo: 0, evaluacion: 'Reprobado' },
      ];
    }
    return src.map((e) => ({
      _id: (e as any).id ?? (e as any)._id ?? uid(),
      puntaje_minimo: e.puntaje_minimo,
      evaluacion: e.evaluacion,
    }));
  }

  let columnas = $state<ColumnaDraft[]>(columnasIniciales);
  let niveles = $state<NivelDraft[]>(initNiveles());
  let escalaCal = $state<EscalaCalif[]>(initEscalaCalif());
  let tab = $state<'editor' | 'preview'>(bloqueada ? 'preview' : 'editor');
  let saving = $state(false);
  let saveSuccess = $state(false);
  let error = $state<string | null>(null);

  $effect(() => {
    let isPoppedByBrowser = false;

    // Preservar el state de Inertia para que isValidState(state) no falle ni desmonte la página
    if (typeof window !== 'undefined') {
      window.history.pushState(window.history.state, '', window.location.href);
    }

    const handlePopState = () => {
      isPoppedByBrowser = true;
      onClose();
    };

    window.addEventListener('popstate', handlePopState);

    return () => {
      window.removeEventListener('popstate', handlePopState);
      if (!isPoppedByBrowser && !saveSuccess && typeof window !== 'undefined') {
        window.history.back();
      }
    };
  });

  // ── Computed ──────────────────────────────────────────────────────────────

  /**
   * Lo máximo que puede sacar un criterio: el mayor puntaje entre las columnas.
   * Como el puntaje es de la columna, ese techo es igual para todos los
   * criterios, y el total pasa a ser una multiplicación en vez de una suma
   * fila por fila.
   */
  const puntajeMaximoCriterio = $derived(
    columnas.reduce((m, c) => Math.max(m, Number(c.puntos) || 0), 0),
  );

  const puntajeTotal = $derived(niveles.length * puntajeMaximoCriterio);

  /** Puntaje que hay que alcanzar para el 4,0. Sólo aplica a las sumativas. */
  const puntajeParaCuatro = $derived(puntajeMinimoAprobacion(puntajeTotal));

  /**
   * Suma de ponderaciones, redondeada a dos decimales.
   *
   * El redondeo no es cosmético: 33.34 + 33.33 + 33.33 da 100.00000000000001 en
   * coma flotante, y sin redondear la comparación con 100 fallaría justo en el
   * caso que el reparto automático produce.
   */
  const ponderacionTotal = $derived(
    Math.round(niveles.reduce((sum, n) => sum + (Number(n.ponderacion) || 0), 0) * 100) / 100,
  );

  const errorMonotonia = $derived.by(() => {
    if (columnas.length <= 1) return null;
    const pts = columnas.map((c) => {
      const v = typeof c.puntos === 'number' ? c.puntos : parseFloat(String(c.puntos));
      return isNaN(v) ? null : v;
    });
    if (pts.some((p) => p === null || p < 0)) {
      return 'Todos los niveles deben tener un puntaje mayor o igual a 0.';
    }
    const isAsc = pts.every((p, i) => i === 0 || (p !== null && pts[i - 1] !== null && p > pts[i - 1]!));
    const isDesc = pts.every((p, i) => i === 0 || (p !== null && pts[i - 1] !== null && p < pts[i - 1]!));
    if (!isAsc && !isDesc) {
      return 'Los puntajes de los niveles deben estar ordenados (estrictamente creciente o decreciente) sin repetir valores.';
    }
    return null;
  });

  const ponderacionValida = $derived(ponderacionTotal === 100 && errorMonotonia === null);

  function igualarPonderaciones() {
    const reparto = repartirPonderacion(niveles.length);
    niveles = niveles.map((n, i) => ({ ...n, ponderacion: reparto[i] }));
  }

  const rubricaPreview = $derived<Rubrica>({
    columnas: columnas.map((c) => ({
      id: c._id,
      nombre: c.nombre.trim() || '(nivel)',
      puntos: Number(c.puntos) || 0,
    })),
    niveles: niveles.map((n) => ({
      id: n._id,
      nombre: n.nombre || '(criterio)',
      descripcion: n.descripcion || '',
      ponderacion: Number(n.ponderacion) || 0,
      nro_escalas: columnas.length,
      puntaje_minimo: 0,
      puntaje_total: puntajeMaximoCriterio,
      escalas: n.escalas.map((e, i) => ({
        id: e._id,
        // El puntaje se copia desde la columna en vez de leerse de la celda:
        // así todo lo que ya consumía `escalas[].puntos` —la vista del alumno,
        // la matriz de evaluación, el puntaje obtenido que se persiste— sigue
        // funcionando sin enterarse del cambio.
        puntos: Number(columnas[i]?.puntos) || 0,
        criterio: e.criterio || '',
      })),
    })),
    detalles_evaluacion: {
      puntaje_total: puntajeTotal,
      // En una sumativa la escala cualitativa no se guarda, ni siquiera si la
      // rúbrica venía con una de antes: si quedara escrita, la vista del alumno
      // —que la dibuja cuando existe— seguiría mostrando «Aprobado» junto a la
      // nota, que es la mezcla que se pidió separar.
      escala_evaluacion: esSumativa
        ? []
        : escalaCal.map((e) => ({
            puntaje_minimo: Number(e.puntaje_minimo) || 0,
            evaluacion: e.evaluacion || '',
          })),
    },
  });

  // ── Mutations ─────────────────────────────────────────────────────────────
  function addNivel() {
    niveles = [...niveles, makeNivel(columnas.length)];
  }

  function removeNivel(id: string) {
    if (niveles.length <= 1) return;
    niveles = niveles.filter((n) => n._id !== id);
  }

  function addColumna() {
    columnas = [...columnas, { _id: uid(), nombre: `Nivel ${columnas.length + 1}`, puntos: '' }];
    niveles = niveles.map((n) => ({
      ...n,
      escalas: [...n.escalas, { _id: uid(), criterio: '' }],
    }));
  }

  function removeColumna(idx: number) {
    if (columnas.length <= 1) return;
    columnas = columnas.filter((_, i) => i !== idx);
    niveles = niveles.map((n) => ({ ...n, escalas: n.escalas.filter((_, i) => i !== idx) }));
  }

  /**
   * Aplica una de las plantillas de nombres.
   *
   * Renombra y ajusta la cantidad de columnas, pero conserva lo ya escrito en
   * las posiciones que sobreviven —el puntaje de la columna y la descripción de
   * cada celda—: la plantilla nombra los niveles, no reinicia el trabajo. Si la
   * plantilla tiene menos columnas que la rúbrica actual las sobrantes se
   * pierden, que es exactamente lo que se pide al elegir «3 niveles».
   */
  function aplicarPlantilla(cantidad: number) {
    const nombres = PLANTILLAS_NIVELES[cantidad];
    if (!nombres) return;

    columnas = nombres.map((nombre, i) => ({
      _id: columnas[i]?._id ?? uid(),
      nombre,
      puntos: columnas[i]?.puntos ?? '',
    }));

    niveles = niveles.map((n) => ({
      ...n,
      escalas: Array.from(
        { length: nombres.length },
        (_, i) => n.escalas[i] ?? { _id: uid(), criterio: '' },
      ),
    }));
  }

  function addEscalaCalif() {
    escalaCal = [...escalaCal, { _id: uid(), puntaje_minimo: '', evaluacion: '' }];
  }

  function removeEscalaCalif(id: string) {
    escalaCal = escalaCal.filter((e) => e._id !== id);
  }

  // ── Save ──────────────────────────────────────────────────────────────────
  function validate(): boolean {
    if (columnas.some((c) => !c.nombre.trim())) {
      error = 'Todos los niveles de desempeño deben tener un nombre.';
      return false;
    }
    if (niveles.some((n) => !n.nombre.trim())) {
      error = 'Todos los criterios deben tener un nombre.';
      return false;
    }
    const pts = columnas.map((c) => Number(c.puntos));
    if (pts.some((p) => isNaN(p) || p < 0)) {
      error = 'Los puntajes de los niveles deben ser números mayores o iguales a 0.';
      return false;
    }
    if (columnas.length > 1) {
      const isAsc = pts.every((p, i) => i === 0 || p > pts[i - 1]);
      const isDesc = pts.every((p, i) => i === 0 || p < pts[i - 1]);
      if (!isAsc && !isDesc) {
        error =
          'Los puntajes de los niveles deben estar ordenados (estrictamente creciente o decreciente) sin valores repetidos.';
        return false;
      }
    }
    if (!esSumativa) {
      if (escalaCal.every((e) => !e.evaluacion.trim())) {
        error =
          'Una actividad formativa se cierra con su escala cualitativa: define al menos un nivel (por ejemplo «Aprobado»).';
        return false;
      }
      if (escalaCal.some((e) => Number(e.puntaje_minimo) < 0 || isNaN(Number(e.puntaje_minimo)))) {
        error = 'Los puntajes mínimos de la escala de evaluación deben ser mayores o iguales a 0.';
        return false;
      }
      if (escalaCal.some((e) => Number(e.puntaje_minimo) > puntajeTotal)) {
        error = `Los puntajes mínimos de la escala de evaluación no pueden superar el puntaje total de la rúbrica (${puntajeTotal} pts).`;
        return false;
      }
    }
    if (!ponderacionValida) {
      error = `Las ponderaciones deben sumar exactamente 100 % (ahora suman ${ponderacionTotal} %).`;
      return false;
    }
    if (niveles.some((n) => n.escalas.some((e) => !e.criterio.trim()))) {
      error = 'Todas las celdas de la rúbrica deben tener una descripción.';
      return false;
    }
    error = null;
    return true;
  }

  function guardar() {
    if (bloqueada || !validate()) return;
    saving = true;
    router.post(
      `/docente/cursos/${idCurso}/rubrica`,
      { rubrica: rubricaPreview as any, id_actividad: idActividad },
      {
        replace: true,
        preserveState: false,
        onSuccess: () => {
          saving = false;
          saveSuccess = true;
          onClose();
        },
        onError: (errores) => {
          saving = false;
          // El servidor revalida la ponderación por su cuenta. Si rechaza, hay
          // que decir por qué: con un «Error al guardar» genérico, el docente
          // ve un botón habilitado que no funciona y no tiene cómo saber que el
          // problema es la suma de los porcentajes.
          error = Object.values(errores ?? {})[0] ?? 'Error al guardar la rúbrica.';
        },
      },
    );
  }

  function handlePuntosKeydown(e: KeyboardEvent) {
    if (['e', 'E', '+', '-'].includes(e.key)) {
      e.preventDefault();
    }
  }

  function handlePuntosInput(col: ColumnaDraft, e: Event) {
    const input = e.currentTarget as HTMLInputElement;
    const raw = input.value.trim();
    if (raw === '') {
      col.puntos = '';
      return;
    }
    let num = parseFloat(raw);
    if (isNaN(num)) {
      col.puntos = '';
      return;
    }
    if (num < 0) {
      num = 0;
      input.value = '0';
    }
    col.puntos = num;
  }

  function handlePonderacionKeydown(e: KeyboardEvent) {
    if (['e', 'E', '+', '-'].includes(e.key)) {
      e.preventDefault();
    }
  }

  function handlePonderacionInput(nivel: NivelDraft, e: Event) {
    const input = e.currentTarget as HTMLInputElement;
    const raw = input.value.replace(',', '.');
    if (raw === '') {
      nivel.ponderacion = '';
      return;
    }
    let num = parseFloat(raw);
    if (isNaN(num)) {
      nivel.ponderacion = '';
      return;
    }
    if (num < 0) {
      num = 0;
      input.value = '0';
    } else if (num > 100) {
      num = 100;
      input.value = '100';
    } else if (raw.includes('.')) {
      const [intPart, decPart] = raw.split('.');
      if (decPart && decPart.length > 2) {
        input.value = `${intPart}.${decPart.slice(0, 2)}`;
        num = parseFloat(input.value);
      }
    }
    nivel.ponderacion = num;
  }

  function handleFormativaKeydown(e: KeyboardEvent) {
    if (['e', 'E', '+', '-'].includes(e.key)) {
      e.preventDefault();
    }
  }

  function handleFormativaInput(esc: EscalaCalif, e: Event) {
    const input = e.currentTarget as HTMLInputElement;
    const raw = input.value.trim();
    if (raw === '') {
      esc.puntaje_minimo = '';
      return;
    }
    let num = parseFloat(raw);
    if (isNaN(num)) {
      esc.puntaje_minimo = '';
      return;
    }
    if (num < 0) {
      num = 0;
      input.value = '0';
    } else if (num > puntajeTotal) {
      num = puntajeTotal;
      input.value = String(puntajeTotal);
    }
    esc.puntaje_minimo = num;
  }
</script>

<!-- Full-screen overlay -->
<div class="fixed inset-0 z-[70] flex flex-col bg-white overflow-hidden">
  <!-- ── Header ── -->
  <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b bg-white shrink-0 gap-4">
    <div class="flex items-center gap-3 min-w-0">
      <button
        type="button"
        onclick={onClose}
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 hover:bg-gray-100 transition text-xs font-semibold text-gray-700 shrink-0"
        title="Volver"
      >
        <ChevronLeft class="w-4 h-4" />
        <span>Volver</span>
      </button>
      <h2 class="text-sm sm:text-base font-bold text-gray-900 truncate">
        {rubrica ? 'Rúbrica de la Actividad' : 'Crear Rúbrica'}
      </h2>

      <!-- Tabs -->
      <div class="flex gap-1 ml-2 bg-gray-100 rounded-lg p-1 shrink-0">
        <button
          onclick={() => (tab = 'editor')}
          class="flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-md transition {tab ===
          'editor'
            ? 'bg-white shadow text-primary'
            : 'text-gray-500 hover:text-gray-700'}"
        >
          <Pencil class="w-3 h-3" />
          Editor
        </button>
        <button
          onclick={() => (tab = 'preview')}
          class="flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-md transition {tab ===
          'preview'
            ? 'bg-white shadow text-primary'
            : 'text-gray-500 hover:text-gray-700'}"
        >
          <Eye class="w-3 h-3" />
          Vista Previa
        </button>
      </div>
    </div>

    {#if !bloqueada}
      <button
        onclick={guardar}
        disabled={saving || !ponderacionValida}
        title={ponderacionValida ? undefined : `Las ponderaciones suman ${ponderacionTotal} %`}
        class="shrink-0 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-xl hover:opacity-90 transition disabled:opacity-50"
      >
        {saving ? 'Guardando…' : 'Guardar Rúbrica'}
      </button>
    {:else}
      <div class="flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 border border-amber-200 text-amber-800 text-xs font-semibold rounded-xl shrink-0">
        <Lock class="w-3.5 h-3.5 text-amber-600" />
        <span>Rúbrica bloqueada (evaluaciones iniciadas)</span>
      </div>
    {/if}
  </div>

  {#if bloqueada}
    <div class="px-6 py-2.5 bg-amber-50 border-b border-amber-200 text-xs sm:text-sm text-amber-800 flex items-center gap-2 shrink-0">
      <Lock class="w-4 h-4 text-amber-600 shrink-0" />
      <span>Esta rúbrica no se puede editar porque ya han comenzado las evaluaciones de la actividad. Puedes consultar sus criterios en la pestaña <strong>Vista Previa</strong>.</span>
    </div>
  {/if}

  {#if error || errorMonotonia}
    <div class="px-6 py-2 bg-red-50 border-b border-red-200 text-sm text-red-700 shrink-0">
      {error || errorMonotonia}
    </div>
  {/if}

  <!-- ── Body ── -->
  <div class="flex-1 overflow-auto">
    {#if tab === 'editor'}
      <div class="px-4 sm:px-8 py-6 max-w-[1400px] mx-auto">
        <!-- Resumen de puntaje -->
        <div
          class="mb-6 flex flex-wrap items-center gap-6 bg-primary/5 rounded-2xl px-6 py-4 border border-primary/10"
        >
          <div>
            <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">Puntaje Total</p>
            <p class="text-3xl font-black text-primary">{puntajeTotal} pts</p>
          </div>
          <p class="text-xs text-gray-500 max-w-xs">
            {niveles.length} criterio{niveles.length === 1 ? '' : 's'} × {puntajeMaximoCriterio} pts (nivel máximo). Puntaje por columna.
          </p>

          <!--
            Contador vivo de ponderación. Va junto al puntaje total y no dentro
            de la tabla porque es una propiedad de la rúbrica entera: mirando
            una fila no se puede saber si el conjunto cuadra.
          -->
          <div class="border-l border-primary/10 pl-6">
            <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">Ponderación</p>
            <p
              class="text-3xl font-black {ponderacionValida ? 'text-emerald-600' : 'text-amber-600'}"
            >
              {ponderacionTotal}%
            </p>
            {#if !ponderacionValida}
              <button
                onclick={igualarPonderaciones}
                class="text-[11px] font-semibold text-primary/70 underline hover:no-underline"
              >
                Repartir en partes iguales
              </button>
            {/if}
          </div>

          <!-- Plantillas de niveles -->
          <div class="ml-auto flex flex-col gap-1.5">
            <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">Niveles</p>
            <div class="flex flex-wrap items-center gap-1.5">
              {#each [3, 4, 5] as cantidad}
                <button
                  onclick={() => aplicarPlantilla(cantidad)}
                  title={PLANTILLAS_NIVELES[cantidad].join(' · ')}
                  class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition {columnas.length ===
                  cantidad
                    ? 'border-primary/40 bg-primary/10 text-primary'
                    : 'border-gray-200 bg-white text-gray-500 hover:border-primary/30 hover:text-primary'}"
                >
                  {cantidad} niveles
                </button>
              {/each}
            </div>
          </div>
        </div>

        <!-- Tabla editor -->
        <div class="overflow-x-auto rounded-3xl border-2 border-gray-100 mb-8">
          <table class="w-full border-collapse" style="min-width: {200 + columnas.length * 220}px">
            <thead>
              <tr class="bg-gray-50 border-b-2 border-gray-100">
                <th
                  class="px-5 py-4 text-left text-xs font-black text-gray-500 uppercase tracking-wider border-r border-gray-100"
                  style="width:220px; min-width:200px"
                >
                  Criterio de Evaluación
                </th>
                <!--
                  El nombre y el puntaje del nivel viven en la cabecera, no en
                  cada celda: el puntaje es de la columna entera, y tenerlo una
                  sola vez es lo que impide que dos celdas de la misma columna
                  terminen valiendo distinto.
                -->
                {#each columnas as columna, ci (columna._id)}
                  <th
                    class="px-4 py-3 text-center border-r border-gray-100 last:border-r-0 align-top"
                    style="min-width:200px"
                  >
                    <div class="flex flex-col gap-2">
                      <div class="flex items-center gap-1.5">
                        <input
                          type="text"
                          bind:value={columna.nombre}
                          placeholder="Nombre del nivel"
                          class="w-full text-xs font-bold text-gray-700 uppercase tracking-wide bg-white border border-gray-200 rounded-xl px-3 py-2 text-center focus:outline-none focus:border-primary/40 focus:ring-1 focus:ring-primary/20"
                        />
                        {#if columnas.length > 1}
                          <button
                            onclick={() => removeColumna(ci)}
                            class="p-0.5 text-gray-300 hover:text-red-400 transition rounded shrink-0"
                            title="Eliminar nivel"
                          >
                            <Trash2 class="w-3 h-3" />
                          </button>
                        {/if}
                      </div>
                      <div
                        class="flex items-center justify-center gap-1.5 rounded-xl px-3 py-1.5 transition-colors {errorMonotonia
                          ? 'bg-red-50 border border-red-300'
                          : 'bg-primary/5 border border-primary/20'}"
                      >
                        <input
                          type="number"
                          bind:value={columna.puntos}
                          onkeydown={handlePuntosKeydown}
                          oninput={(e) => handlePuntosInput(columna, e)}
                          placeholder="0"
                          min="0"
                          step="1"
                          aria-label="Puntaje del nivel {columna.nombre || ci + 1}"
                          class="w-14 text-sm font-black {errorMonotonia ? 'text-red-700' : 'text-primary'} bg-transparent text-center focus:outline-none"
                        />
                        <span class="text-[10px] font-bold uppercase {errorMonotonia ? 'text-red-400' : 'text-primary/60'}">pts</span>
                      </div>
                    </div>
                  </th>
                {/each}
                <th class="px-3 py-4 border-l border-gray-100" style="width:56px">
                  <button
                    onclick={addColumna}
                    title="Agregar nivel de desempeño"
                    class="inline-flex flex-col items-center gap-0.5 text-primary/50 hover:text-primary transition"
                  >
                    <Plus class="w-4 h-4" />
                    <span class="text-[9px] font-bold uppercase tracking-wide">Nivel</span>
                  </button>
                </th>
              </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
              {#each niveles as nivel (nivel._id)}
                <tr class="hover:bg-gray-50/30 transition-colors group">
                  <!-- Criterio column -->
                  <td class="px-5 py-4 border-r border-gray-100 align-top bg-gray-50/50">
                    <div class="flex flex-col gap-2">
                      <input
                        type="text"
                        bind:value={nivel.nombre}
                        placeholder="Nombre del criterio"
                        class="w-full text-sm font-bold text-gray-800 bg-white border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:border-primary/40 focus:ring-1 focus:ring-primary/20"
                      />
                      <textarea
                        bind:value={nivel.descripcion}
                        placeholder="Descripción (opcional)"
                        rows="2"
                        class="w-full text-xs text-gray-500 italic bg-white border border-gray-200 rounded-xl px-3 py-2 resize-none focus:outline-none focus:border-primary/40 focus:ring-1 focus:ring-primary/20"
                      ></textarea>
                      <div
                        class="flex items-center gap-1.5 bg-white border rounded-xl px-3 py-2 {ponderacionValida
                          ? 'border-gray-200'
                          : 'border-amber-300'}"
                      >
                        <input
                          type="number"
                          bind:value={nivel.ponderacion}
                          onkeydown={handlePonderacionKeydown}
                          oninput={(e) => handlePonderacionInput(nivel, e)}
                          placeholder="0"
                          min="0"
                          max="100"
                          step="1"
                          aria-label="Ponderación de {nivel.nombre || 'este criterio'} en porcentaje"
                          class="w-16 text-sm font-bold text-gray-800 bg-transparent focus:outline-none"
                        />
                        <span class="text-xs text-gray-400 font-medium">% de la nota</span>
                      </div>
                      {#if Number(nivel.ponderacion) > 100}
                        <p class="text-[10px] text-red-600 font-medium">No puede superar el 100%.</p>
                      {/if}
                      {#if niveles.length > 1}
                        <button
                          onclick={() => removeNivel(nivel._id)}
                          class="inline-flex items-center gap-1 text-[11px] text-red-400 hover:text-red-600 transition self-start opacity-0 group-hover:opacity-100"
                        >
                          <Trash2 class="w-3 h-3" />
                          Eliminar fila
                        </button>
                      {/if}
                    </div>
                  </td>

                  <!-- Escala columns -->
                  <!-- La celda ya sólo describe: su puntaje lo pone la columna. -->
                  {#each nivel.escalas as escala, ci (escala._id)}
                    <td class="px-5 py-4 border-r border-gray-100 last:border-r-0 align-top">
                      <textarea
                        bind:value={escala.criterio}
                        placeholder="Descripción del nivel de desempeño…"
                        aria-label="{nivel.nombre || 'Criterio'} — {columnas[ci]?.nombre ||
                          `nivel ${ci + 1}`}"
                        rows="4"
                        class="w-full text-sm text-gray-600 bg-white border border-gray-200 rounded-xl px-3 py-2 resize-none focus:outline-none focus:border-primary/40 focus:ring-1 focus:ring-primary/20 leading-snug"
                      ></textarea>
                    </td>
                  {/each}

                  <td class="px-3 py-4 align-middle border-l border-gray-100"></td>
                </tr>
              {/each}

              <!-- Add row -->
              <tr>
                <td colspan={columnas.length + 2} class="px-5 py-3">
                  <button
                    onclick={addNivel}
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-primary/60 hover:text-primary transition"
                  >
                    <Plus class="w-4 h-4" />
                    Agregar criterio
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!--
          Lo de abajo depende del tipo de actividad: una formativa se cierra con
          la escala cualitativa y una sumativa con la nota de 1,0 a 7,0. Se
          muestra una u otra, nunca las dos, porque tenerlas juntas es lo que
          hacía que una sumativa terminara con un «Aprobado» al lado del 5,4.
        -->
        {#if esSumativa}
          <div class="bg-gray-50/50 rounded-3xl border border-gray-100 p-6">
            <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">
              Conversión a nota
            </p>
            <div class="flex flex-wrap items-end gap-8">
              <div>
                <p class="text-xs text-gray-500">Puntaje para nota 4.0</p>
                <p class="text-3xl font-black text-primary">
                  {puntajeParaCuatro}<span class="text-base font-bold text-gray-400"
                    >/{puntajeTotal} pts</span
                  >
                </p>
              </div>
              <p class="text-xs text-gray-500 max-w-md leading-relaxed">
                Escala de 1,0 a 7,0 (60 % de exigencia). Aprobación (4,0) con {puntajeParaCuatro} de {puntajeTotal} pts.
              </p>
            </div>
          </div>
        {:else}
        <div class="bg-gray-50/50 rounded-3xl border border-gray-100 p-6">
          <div class="flex items-center justify-between mb-4 max-w-lg">
            <p class="text-xs font-black text-gray-400 uppercase tracking-widest">
              Escala de Calificación
            </p>
            <span class="text-xs text-gray-500 font-medium">
              Máximo: <strong class="text-primary font-bold">{puntajeTotal} pts</strong>
            </span>
          </div>
          <div class="flex flex-col gap-2 max-w-lg">
            {#each escalaCal as esc (esc._id)}
              {@const fueraDeRango = Number(esc.puntaje_minimo) > puntajeTotal}
              <div class="flex flex-col gap-1">
                <div class="flex items-center gap-3">
                  <div
                    class="flex items-center gap-2 bg-white border rounded-xl px-3 py-2 w-36 shrink-0 transition-colors {fueraDeRango
                      ? 'border-red-400 bg-red-50/30'
                      : 'border-gray-200'}"
                  >
                    <span class="text-xs text-gray-400 font-medium">≥</span>
                    <input
                      type="number"
                      bind:value={esc.puntaje_minimo}
                      onkeydown={handleFormativaKeydown}
                      oninput={(e) => handleFormativaInput(esc, e)}
                      placeholder="0"
                      min="0"
                      max={puntajeTotal}
                      class="w-full text-sm font-bold text-primary bg-transparent focus:outline-none"
                    />
                    <span class="text-xs text-gray-400">pts</span>
                  </div>
                  <span class="text-gray-400">→</span>
                  <input
                    type="text"
                    bind:value={esc.evaluacion}
                    placeholder="ej: Aprobado"
                    class="flex-1 text-sm bg-white border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:border-primary/40"
                  />
                  <button
                    onclick={() => removeEscalaCalif(esc._id)}
                    class="p-1.5 text-gray-300 hover:text-red-400 transition shrink-0"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
                {#if fueraDeRango}
                  <p class="text-[11px] text-red-600 ml-1">
                    Supera el puntaje total ({puntajeTotal} pts).
                  </p>
                {/if}
              </div>
            {/each}
            <button
              onclick={addEscalaCalif}
              class="inline-flex items-center gap-1.5 text-sm font-medium text-primary/60 hover:text-primary transition mt-1"
            >
              <Plus class="w-4 h-4" />
              Agregar nivel de calificación
            </button>
          </div>
        </div>
        {/if}
      </div>
    {:else}
      <!-- Preview -->
      <div class="px-4 sm:px-8 py-6 max-w-5xl mx-auto">
        <div
          class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 bg-primary/5 p-6 rounded-3xl border-2 border-primary/10"
        >
          <div class="text-center md:text-left">
            <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">Puntaje Máximo</p>
            <p class="text-3xl font-black text-primary">{puntajeTotal} pts</p>
          </div>
          <div
            class="text-center border-y md:border-y-0 md:border-x border-primary/10 py-4 md:py-0"
          >
            <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">Criterios</p>
            <p class="text-3xl font-black text-primary">{niveles.length}</p>
          </div>
          <div class="text-center md:text-right">
            <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
              Niveles de Desempeño
            </p>
            <p class="text-3xl font-black text-primary">{columnas.length}</p>
          </div>
        </div>

        <RubricaView rubrica={rubricaPreview} {esSumativa} />

        {#if !esSumativa}
        <div class="mt-8 bg-gray-50/50 p-6 rounded-3xl border border-gray-100">
          <p class="text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest">
            Escala de calificación aplicada:
          </p>
          <div class="flex flex-wrap gap-3">
            {#each rubricaPreview.detalles_evaluacion.escala_evaluacion as esc}
              <div
                class="flex items-center gap-2 px-4 py-2 bg-white rounded-xl border border-gray-200 shadow-sm"
              >
                <span class="w-2.5 h-2.5 rounded-full bg-primary shrink-0"></span>
                <span class="text-xs font-bold text-gray-700">{esc.evaluacion || '—'}</span>
                <span class="text-xs text-gray-400 ml-1">≥ {esc.puntaje_minimo} pts</span>
              </div>
            {/each}
          </div>
        </div>
        {/if}
      </div>
    {/if}
  </div>
</div>
