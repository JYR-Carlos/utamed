<script lang="ts">
  /**
   * UserImport — Importación masiva de usuarios en tres pasos.
   *
   * Antes era un `<input type="file">` suelto con un botón «Guardar»: se
   * subía la planilla a ciegas y, si una fila estaba mal, la importación
   * entera fallaba con un único mensaje y sin decir qué corregir.
   *
   * Ahora:
   *   1. Formato    — columnas esperadas + plantilla descargable.
   *   2. Revisión   — el servidor valida sin escribir nada y devuelve las
   *                   filas con problemas, indicando fila y motivo.
   *   3. Importar   — sólo se confirma cuando no queda nada que corregir.
   */
  import { UserType } from '@/types/usuarios/tipos';

  interface ColumnaImportacion {
    campo: string;
    etiqueta: string;
    obligatorio: boolean;
    ejemplo: string;
  }

  interface FilaConProblema {
    fila: number;
    rut: string | null;
    nombre: string;
    problemas: string[];
  }

  interface Previsualizacion {
    total: number;
    validas: number;
    con_problemas: number;
    errores: FilaConProblema[];
    errores_omitidos: number;
  }

  interface Props {
    tipo: (typeof UserType)[keyof typeof UserType];
    /** Columnas esperadas; las declara el servidor. */
    columnas?: ColumnaImportacion[];
    file?: File | null;
    /** Se pone en true cuando ya no queda nada que corregir. */
    listoParaImportar?: boolean;
  }

  let {
    tipo,
    columnas = [],
    file = $bindable(null),
    listoParaImportar = $bindable(false),
  }: Props = $props();

  const labelMapping: Record<(typeof UserType)[keyof typeof UserType], string> = {
    [UserType.STUDENT]: 'estudiantes',
    [UserType.TEACHER]: 'docentes',
    [UserType.ADMIN]: 'administradores',
  };

  const etiquetaTipo = $derived(labelMapping[tipo]);

  let revisando = $state(false);
  let revision = $state<Previsualizacion | null>(null);
  let errorLectura = $state('');

  // Cambiar de archivo invalida la revisión anterior: confirmar una
  // importación con el informe de otro archivo sería peor que no tenerlo.
  function resetRevision() {
    revision = null;
    errorLectura = '';
    listoParaImportar = false;
  }

  function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    file = target.files?.length ? target.files[0] : null;
    resetRevision();
  }

  function getXsrfToken(): string {
    return decodeURIComponent(
      document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.split('=')[1] ?? '',
    );
  }

  async function revisarArchivo() {
    if (!file || revisando) return;

    revisando = true;
    errorLectura = '';
    revision = null;

    const datos = new FormData();
    datos.append('file', file);
    datos.append('tipo', tipo);

    try {
      const res = await fetch('/admin/usuarios/importar/previsualizar', {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': getXsrfToken(), Accept: 'application/json' },
        body: datos,
      });
      const data = await res.json();

      if (!res.ok || !data.ok) {
        errorLectura = data.mensaje ?? 'No se pudo leer el archivo.';
        return;
      }

      revision = data as Previsualizacion;
      listoParaImportar = revision.con_problemas === 0 && revision.validas > 0;
    } catch {
      errorLectura = 'No se pudo contactar con el servidor. Inténtalo de nuevo.';
    } finally {
      revisando = false;
    }
  }

  const tamanoLegible = $derived(
    file ? `${(file.size / 1024).toFixed(0)} KB` : '',
  );
</script>

<div class="space-y-5">
  <!-- ── Paso 1: formato esperado ─────────────────────────────── -->
  <section>
    <div class="flex items-baseline justify-between gap-3 mb-2">
      <h3 class="text-sm font-semibold text-gray-900">
        Columnas del archivo
      </h3>
      <a
        href="/admin/usuarios/plantilla-importacion?tipo={tipo}"
        class="text-xs font-medium text-blue-600 hover:text-blue-800"
      >
        Descargar plantilla
      </a>
    </div>
    <p class="text-xs text-gray-500 mb-2.5">
      La primera fila del archivo son los encabezados. El orden de las columnas
      debe ser este:
    </p>
    <ol class="flex flex-wrap gap-1.5">
      {#each columnas as columna, i (columna.campo)}
        <li
          class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-[11px] text-gray-700"
        >
          <span class="text-gray-400 tabular-nums">{i + 1}</span>
          {columna.etiqueta}
          {#if columna.obligatorio}
            <span class="text-[var(--action-danger)] font-bold">*</span>
          {/if}
        </li>
      {/each}
    </ol>
    {#if columnas.length}
      <p class="text-[11px] text-gray-400 mt-2">
        <span class="text-[var(--action-danger)] font-bold">*</span> obligatorias.
      </p>
    {/if}
  </section>

  <!-- ── Paso 2: archivo ──────────────────────────────────────── -->
  <section>
    <h3 class="text-sm font-semibold text-gray-900 mb-2">
      Archivo de {etiquetaTipo}
    </h3>

    <label
      class="flex items-center gap-3 rounded-lg border-2 border-dashed border-gray-300 px-4 py-4 cursor-pointer hover:border-blue-400 hover:bg-blue-50/40 transition"
    >
      <input
        type="file"
        accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel"
        onchange={handleFileChange}
        class="sr-only"
      />
      <span class="btn btn-neutral btn-sm shrink-0">Elegir archivo</span>
      <span class="min-w-0 text-sm">
        {#if file}
          <span class="block font-medium text-gray-900 truncate">{file.name}</span>
          <span class="block text-xs text-gray-500">{tamanoLegible}</span>
        {:else}
          <span class="text-gray-500">Ningún archivo seleccionado (.xlsx o .csv)</span>
        {/if}
      </span>
    </label>

    {#if file && !revision}
      <button
        type="button"
        onclick={revisarArchivo}
        disabled={revisando}
        class="btn btn-primary btn-sm mt-3"
      >
        {revisando ? 'Revisando…' : 'Revisar archivo'}
      </button>
      <p class="field-hint">
        Se comprueba el archivo sin guardar nada todavía.
      </p>
    {/if}

    {#if errorLectura}
      <div
        class="mt-3 rounded-lg p-3 text-[13px] bg-[var(--action-danger-soft)] text-[var(--action-danger)]"
      >
        {errorLectura}
      </div>
    {/if}
  </section>

  <!-- ── Paso 3: resultado de la revisión ─────────────────────── -->
  {#if revision}
    <section>
      <h3 class="text-sm font-semibold text-gray-900 mb-2">Resultado de la revisión</h3>

      <div class="flex flex-wrap gap-2 mb-3">
        <span class="badge badge-info">{revision.total} filas leídas</span>
        <span class="badge badge-ok">{revision.validas} listas para importar</span>
        {#if revision.con_problemas > 0}
          <span class="badge badge-warn">{revision.con_problemas} con problemas</span>
        {/if}
      </div>

      {#if revision.con_problemas === 0 && revision.validas > 0}
        <div
          class="rounded-lg p-3 text-[13px] leading-relaxed"
          style="background: var(--state-ok-soft); color: var(--state-ok);"
        >
          El archivo está correcto. Al confirmar se crearán {revision.validas}
          {etiquetaTipo}.
        </div>
      {:else if revision.validas === 0 && revision.con_problemas === 0}
        <div
          class="rounded-lg p-3 text-[13px]"
          style="background: var(--state-warn-soft); color: var(--state-warn);"
        >
          El archivo no tiene filas de datos. Recuerda que la primera fila son los encabezados.
        </div>
      {:else}
        <div
          class="rounded-lg p-3 text-[13px] mb-3"
          style="background: var(--state-warn-soft); color: var(--state-warn);"
        >
          Corrige estas filas en el archivo y vuelve a subirlo. No se ha importado nada.
        </div>

        <div class="max-h-64 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-100">
          {#each revision.errores as error (error.fila)}
            <div class="px-3 py-2.5">
              <p class="text-[13px] font-semibold text-gray-900">
                Fila {error.fila}
                {#if error.nombre || error.rut}
                  <span class="font-normal text-gray-500">
                    · {[error.nombre, error.rut].filter(Boolean).join(' · ')}
                  </span>
                {/if}
              </p>
              <ul class="mt-1 space-y-0.5">
                {#each error.problemas as problema}
                  <li class="text-xs text-[var(--action-danger)]">{problema}</li>
                {/each}
              </ul>
            </div>
          {/each}
        </div>

        {#if revision.errores_omitidos > 0}
          <p class="field-hint">
            Y {revision.errores_omitidos} filas más con problemas.
          </p>
        {/if}
      {/if}

      <button type="button" onclick={revisarArchivo} disabled={revisando} class="btn btn-neutral btn-sm mt-3">
        {revisando ? 'Revisando…' : 'Volver a revisar'}
      </button>
    </section>
  {/if}
</div>
