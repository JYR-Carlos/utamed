/**
 * stackTable — acción Svelte para tablas que se dibujan como tarjetas en móvil.
 *
 * Copia el texto de cada `<th>` en el `data-label` de las celdas de su
 * columna; el CSS de `.table-stack` (app.css) usa ese atributo como
 * etiqueta cuando la tabla se apila por debajo de `md`. Usarla junto con
 * `class="table-stack"`:
 *
 *   <table class="table-stack" use:stackTable>
 *
 * Se vuelve a aplicar cuando cambian las filas (paginación, búsqueda), y
 * como sólo escribe atributos —que no observa— no se dispara a sí misma.
 */
export function stackTable(table: HTMLTableElement) {
    const etiquetaAcciones = /^acciones$/i;

    function aplicar() {
        const cabeceras = Array.from(table.querySelectorAll('thead th')).map((th) =>
            (th.textContent ?? '').replace(/\s+/g, ' ').trim(),
        );

        table.querySelectorAll('tbody tr').forEach((fila) => {
            Array.from(fila.children).forEach((celda, i) => {
                if (celda.tagName !== 'TD') return;

                const td = celda as HTMLTableCellElement;
                const cabecera = cabeceras[i] ?? '';
                const esAcciones = etiquetaAcciones.test(cabecera);
                // Una celda que ocupa varias columnas (estado vacío) no tiene etiqueta.
                const etiqueta = td.colSpan > 1 || esAcciones ? '' : cabecera;

                if (td.dataset.label !== etiqueta) td.dataset.label = etiqueta;
                if (esAcciones && td.dataset.actions === undefined) td.dataset.actions = '';
            });
        });
    }

    aplicar();

    const observador = new MutationObserver(aplicar);
    observador.observe(table, { childList: true, subtree: true, characterData: true });

    return {
        destroy() {
            observador.disconnect();
        },
    };
}
