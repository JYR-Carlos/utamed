/**
 * Genera resources/css/tema-oscuro.css: el modo oscuro de toda la plataforma.
 *
 * Las vistas escriben colores de la paleta de Tailwind (`bg-white`,
 * `text-gray-600`, `border-blue-200`…) y tokens propios (`text-ink`,
 * `bg-navy`…). En vez de añadir `dark:` a cada clase, bajo `.dark` se
 * redefinen las variables que esas clases leen:
 *
 * - `--color-*` (texto, borde, anillo, icono): la escala se invierte, de modo
 *   que `text-gray-900` pasa a ser el texto más claro y `border-gray-200` un
 *   borde oscuro.
 * - `--background-color-*` (fondos y degradados): Tailwind v4 los busca antes
 *   que `--color-*`, así que se declaran aparte. Los tonos 50–400 se vuelven
 *   superficies oscuras teñidas y los 500–950 se conservan, porque llevan
 *   texto blanco encima (botones, insignias sólidas).
 *
 * `text-white` sigue siendo blanco; `bg-white` pasa a ser la superficie de las
 * tarjetas. Uso: `node scripts/generar-tema-oscuro.mjs`.
 */
import { writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const TW = require('tailwindcss/colors');

const SALIDA = new URL('../resources/css/tema-oscuro.css', import.meta.url);

const NEUTRAS = ['slate', 'gray', 'zinc', 'neutral', 'stone'];
const CROMATICAS = ['red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose'];
const TONOS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

/** Superficie de las tarjetas en oscuro: base de todas las mezclas. */
const SUPERFICIE = '#111a2b';

/** Grises en oscuro, iguales para las cinco familias neutras. */
const NEUTRA_TEXTO = { 50: '#172238', 100: '#1e2b45', 200: '#24314a', 300: '#34435f', 400: '#6b7a95', 500: '#8c99b0', 600: '#a3afc3', 700: '#b4bfd1', 800: '#d3dae6', 900: '#e7ecf5', 950: '#f2f5fa' };
const NEUTRA_FONDO = { 50: '#152036', 100: '#1a2640', 200: '#233050', 300: '#2e3c5c', 400: '#4a5876', 500: '#5d6b85', 600: '#4a5876', 700: '#3a4863', 800: '#2d3a55', 900: '#243049', 950: '#1a2538' };

/**
 * Tokens propios: valor claro exacto, valor oscuro para texto/borde y, si se
 * usa como fondo con otro valor, `fondo`.
 */
const TOKENS = {
    ink: { claro: '#1a1a24', oscuro: '#e7ecf5', fondo: '#2d3a55' },
    'ink-2': { claro: '#5a5e6e', oscuro: '#b4bfd1' },
    'ink-3': { claro: '#8a8e9c', oscuro: '#8c99b0' },
    line: { claro: '#d6d9e0', oscuro: '#2e3c5c' },
    'line-control': { claro: '#7b8aa0', oscuro: '#5a6c8e' },
    navy: { claro: '#002f6c', oscuro: '#8db8f2', fondo: '#2f6fbf' },
    'navy-hover': { claro: '#00214d', oscuro: '#b4d0f7', fondo: '#275c9f' },
    'navy-2': { claro: '#1b4789', oscuro: '#b4d0f7', fondo: '#275c9f' },
    'uta-blue': { claro: '#002855', oscuro: '#8db8f2', fondo: '#2f6fbf' },
    'uta-blue-hover': { claro: '#001f42', oscuro: '#b4d0f7', fondo: '#275c9f' },
    'uta-blue-light': { claro: '#e6edf5', oscuro: '#1f3a60', fondo: '#142b4a' },
    brand: { claro: '#2a66ac', oscuro: '#8db8f2', fondo: '#2f6fbf' },
    'brand-hover': { claro: '#1e4d8a', oscuro: '#b4d0f7', fondo: '#275c9f' },
    'brand-light': { claro: '#eef4fb', oscuro: '#1f3a60', fondo: '#142b4a' },
    'uta-red': { claro: '#8a1538', oscuro: '#f2a0b6', fondo: '#a3274f' },
    'uta-red-hover': { claro: '#6e1029', oscuro: '#f7c0cf', fondo: '#8a1f42' },
    'uta-red-light': { claro: '#f5e8ec', oscuro: '#5a2236', fondo: '#351624' },
    sand: { claro: '#f5f1ea', oscuro: '#2a3040', fondo: '#1c2333' },
    'sand-2': { claro: '#e8e4dc', oscuro: '#343a4b', fondo: '#262d3e' },
    'sand-line': { claro: '#d0cbc1', oscuro: '#3a4152' },
    ocre: { claro: '#8a5f00', oscuro: '#f3c063' },
    celeste: { claro: '#5b9bd5', oscuro: '#7cb4e8', fondo: '#3f82c4' },
};

// ── Color: oklch/hex → OKLab para mezclar sin ensuciar el tono ────────────
const lin = (c) => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
const gam = (c) => (c <= 0.0031308 ? 12.92 * c : 1.055 * c ** (1 / 2.4) - 0.055);

function aOklab(color) {
    if (color.startsWith('oklch')) {
        const [, l, c, h] = color.match(/oklch\(([\d.]+)%\s+([\d.]+)\s+([\d.]+)\)/).map(Number);
        const rad = (h * Math.PI) / 180;
        return [l / 100, c * Math.cos(rad), c * Math.sin(rad)];
    }
    const [r, g, b] = [1, 3, 5].map((i) => lin(parseInt(color.slice(i, i + 2), 16) / 255));
    const l = Math.cbrt(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b);
    const m = Math.cbrt(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b);
    const s = Math.cbrt(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b);
    return [0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s, 1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s, 0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s];
}

function aHex([L, a, b]) {
    const l = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const m = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s = (L - 0.0894841775 * a - 1.291485548 * b) ** 3;
    const rgb = [4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s, -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s, -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s];
    return '#' + rgb.map((c) => Math.round(Math.min(1, Math.max(0, gam(Math.max(0, c)))) * 255).toString(16).padStart(2, '0')).join('');
}

/** `peso` de `color` sobre la superficie oscura. */
const sobreSuperficie = (color, peso) => {
    const a = aOklab(color);
    const b = aOklab(SUPERFICIE);
    return aHex(a.map((v, i) => v * peso + b[i] * (1 - peso)));
};

// ── Escalas cromáticas en oscuro ──────────────────────────────────────────
// Texto/borde: los tonos claros (50–300) se usan casi siempre como borde de
// una insignia teñida, así que se vuelven el color mezclado con la superficie;
// los oscuros (600–950) son texto y pasan a su tono claro simétrico.
const cromaticaTexto = (p) => ({
    50: sobreSuperficie(TW[p][500], 0.16),
    100: sobreSuperficie(TW[p][500], 0.24),
    200: sobreSuperficie(TW[p][500], 0.34),
    300: sobreSuperficie(TW[p][500], 0.5),
    400: TW[p][400],
    500: TW[p][400],
    600: TW[p][400],
    700: TW[p][300],
    800: TW[p][200],
    900: TW[p][100],
    950: TW[p][50],
});

// Fondo: 50–400 son tintes suaves (fondo de insignias y avisos); de 500 en
// adelante son rellenos sólidos con texto blanco y se conservan (no se
// redefinen).
const cromaticaFondo = (p) => ({
    50: sobreSuperficie(TW[p][500], 0.12),
    100: sobreSuperficie(TW[p][500], 0.18),
    200: sobreSuperficie(TW[p][500], 0.28),
    300: sobreSuperficie(TW[p][500], 0.42),
    400: sobreSuperficie(TW[p][400], 0.7),
});

// ── Salida ────────────────────────────────────────────────────────────────
const bloque = (sangria, pares) => pares.map(([k, v]) => `${sangria}${k}: ${v};`).join('\n');
const escala = (prefijo, familia, valores) => Object.entries(valores).map(([t, v]) => [`--${prefijo}-${familia}-${t}`, v]);

const temaClaro = [
    ...Object.entries(TOKENS).map(([n, t]) => [`--color-${n}`, t.claro]),
    ...Object.entries(TOKENS).filter(([, t]) => t.fondo).map(([n, t]) => [`--background-color-${n}`, t.claro]),
    ['--background-color-white', '#fff'],
    ['--background-color-black', '#000'],
    ...[...NEUTRAS, ...CROMATICAS].flatMap((p) => escala('background-color', p, Object.fromEntries(TONOS.map((t) => [t, TW[p][t]])))),
];

const temaOscuro = [
    ['--color-black', NEUTRA_TEXTO[900]],
    ['--background-color-white', SUPERFICIE],
    ...NEUTRAS.flatMap((p) => escala('color', p, NEUTRA_TEXTO)),
    ...CROMATICAS.flatMap((p) => escala('color', p, cromaticaTexto(p))),
    ...NEUTRAS.flatMap((p) => escala('background-color', p, NEUTRA_FONDO)),
    ...CROMATICAS.flatMap((p) => escala('background-color', p, cromaticaFondo(p))),
    ...Object.entries(TOKENS).map(([n, t]) => [`--color-${n}`, t.oscuro]),
    ...Object.entries(TOKENS).filter(([, t]) => t.fondo).map(([n, t]) => [`--background-color-${n}`, t.fondo]),
];

const css = `/*
 * GENERADO por scripts/generar-tema-oscuro.mjs — no editar a mano.
 * Los tokens semánticos (--background, --card, --action-*, --state-*) del
 * modo oscuro viven en app.css; aquí sólo la paleta que leen las clases.
 */

@theme static {
${bloque('    ', temaClaro)}
}

@layer base {
    /* Sólo en pantalla: al imprimir (syllabus, actas) se usa siempre el claro. */
    @media screen {
        .dark {
${bloque('            ', temaOscuro)}
        }
    }
}
`;

writeFileSync(SALIDA, css);
console.log(`tema-oscuro.css: ${temaClaro.length} variables claras, ${temaOscuro.length} oscuras`);
