import type { Rubrica } from '@/types/rubrica';
import { puntajeMinimoAprobacion } from '@/lib/notas';
import { matrizDe, pts } from '@/lib/rubricaMatriz';

/**
 * Imprime la rúbrica sola, en una hoja apaisada: la tabla de criterios y
 * niveles con su encabezado. Se arma en una ventana aparte, con los nodos
 * creados uno por uno (los textos los escribe el docente: nunca como HTML),
 * para no imprimir la página que queda detrás de la ventana de la rúbrica.
 *
 * Devuelve false si el navegador no dejó abrir la ventana.
 */
export function imprimirRubrica(
    rubrica: Rubrica,
    encabezado: { titulo: string; subtitulo?: string },
    opciones: { esSumativa?: boolean; exigencia?: number } = {},
): boolean {
    const ventana = window.open('', '_blank', 'width=1200,height=800');
    if (!ventana) return false;

    const d = ventana.document;
    const m = matrizDe(rubrica);
    const el = <K extends keyof HTMLElementTagNameMap>(tag: K, texto?: string, attrs: Record<string, string> = {}) => {
        const nodo = d.createElement(tag);
        if (texto !== undefined) nodo.textContent = texto;
        for (const [k, v] of Object.entries(attrs)) nodo.setAttribute(k, v);
        return nodo;
    };

    d.documentElement.lang = 'es';
    d.title = `Rúbrica · ${encabezado.titulo}`;
    d.head.append(
        el(
            'style',
            `@page { size: landscape; margin: 12mm; }
            body { margin: 0; font: 10pt/1.4 Mulish, Arial, sans-serif; color: #1d2333; }
            h1 { margin: 0 0 2pt; font: bold 15pt 'Century Gothic', Arial, sans-serif; }
            p { margin: 0 0 10pt; color: #4a5168; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
            th, td { border: 1px solid #9aa3b8; padding: 5pt 6pt; vertical-align: top; text-align: left; white-space: pre-line; overflow-wrap: anywhere; }
            thead th { background: #eef1f7; text-align: center; }
            thead th:first-child { width: 20%; }
            tbody th { background: #f7f8fb; }
            .peso { display: block; margin-top: 3pt; font-weight: normal; color: #4a5168; }
            .pts { display: block; font-weight: normal; }`,
        ),
    );

    d.body.append(el('h1', encabezado.titulo));
    const corte = opciones.esSumativa === false ? '' : ` · nota 4,0 con ${pts(puntajeMinimoAprobacion(m.maximo, opciones.exigencia ?? 60))}`;
    d.body.append(el('p', [encabezado.subtitulo, `${m.criterios.length} criterios · ${pts(m.maximo)} como máximo${corte}`].filter(Boolean).join(' · ')));

    const tabla = el('table');
    const filaCabecera = el('tr');
    filaCabecera.append(el('th', 'Criterio', { scope: 'col' }));
    for (const col of m.columnas) {
        const th = el('th', col.nombre, { scope: 'col' });
        if (col.puntos !== null) th.append(el('span', pts(col.puntos), { class: 'pts' }));
        filaCabecera.append(th);
    }
    tabla.append(el('thead'));
    tabla.tHead!.append(filaCabecera);

    const cuerpo = el('tbody');
    for (const c of m.criterios) {
        const tr = el('tr');
        const th = el('th', c.nombre, { scope: 'row' });
        th.append(el('span', c.peso ?? `máx. ${pts(c.maximo)}`, { class: 'peso' }));
        tr.append(th);
        for (const celda of c.celdas) {
            const td = el('td', celda?.texto ?? '');
            if (celda && !m.puntajePorColumna) td.prepend(el('span', pts(celda.puntos), { class: 'pts' }));
            tr.append(td);
        }
        cuerpo.append(tr);
    }
    tabla.append(cuerpo);
    d.body.append(tabla);

    ventana.addEventListener('afterprint', () => ventana.close());
    ventana.focus();
    ventana.print();
    return true;
}
