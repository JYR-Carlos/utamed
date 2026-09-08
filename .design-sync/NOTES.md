# design-sync — notas de sincronización

## Por qué esto no usa el convertidor del skill

El skill `design-sync` (y el runtime de claude.ai/design) son **sólo React**: el
convertidor emite previsualizaciones `.jsx` y un bundle que externaliza
`react`/`react-dom` a `window.React`. Este repo es Laravel + Inertia + **Svelte 5**
(shadcn-svelte, 475 `.svelte`), sin Storybook, sin `*.stories.*`, sin paquete
`dist/`. Los componentes Svelte no pueden renderizar ahí, así que **no se suben
componentes**.

Decisión del equipo (2026-08-31): sincronizar **sólo el lenguaje visual**. Se
descartó portar los primitivos shadcn a React porque serían componentes
derivados, no el `dist/` compilado del proyecto.

Forma resultante: `shape: "css-only"`. `.design-sync/build-css.mjs` es el
convertidor; se ejecuta con `node .design-sync/build-css.mjs`.

## Cómo se construye

Compila `resources/css/app.css` con el propio Tailwind v4 del repo, vía la API
de `@tailwindcss/node` (`compile()`) + `@tailwindcss/oxide` (`Scanner`). Emite:

| Fichero | Qué es |
|---|---|
| `tokens/tokens.css` | Los bloques `@theme inline`, `:root` y `.dark` levantados literalmente de app.css (102 declaraciones) |
| `_ds_bundle.css` | La hoja compilada: base, utilidades y la capa semántica `.btn-*`/`.badge-*`/`.page-*`/`.field-*` (~595 KB) |
| `styles.css` | El cierre `@import` que reciben los diseños: tokens → fuente remota → bundle |
| `README.md` | `conventions.md` + índice generado de clases y tokens |
| `_ds_bundle.js` | Namespace vacío y honesto (`window.UtamedDS = {}`); el self-check espera el fichero |

Fuente tipográfica: Instrument Sans **no está en disco**, se sirve desde
`https://fonts.bunny.net` (ver `resources/views/app.blade.php:13`). Por eso va
como `@import url(...)` remoto en `styles.css` y no hay `fonts/`.

## Trampas encontradas (leer antes de tocar el build)

- **La lista blanca no puede ir por `@source inline(...)`.** Sus candidatos
  viajan en `compiler.sources`, y el `Scanner` propio que construimos reemplaza
  esa lista, así que se descartan en silencio y la lista blanca queda en no-op.
  Se expanden en JS (`cross()`) y se pasan a `compiler.build([...scanned, ...safelist])`.
- **El producto cartesiano completo son 19 MB de CSS.** Prefijos × tokens ×
  variantes × opacidades ≈ 65k reglas. Las tres capas actuales dan 595 KB.
  No amplíes `VARIANTS`/`OPACITIES` sin mirar el tamaño resultante.
- **Escapado al verificar clases.** Tailwind escribe `.hover\:bg-primary\/90`;
  un `grep 'hover.bg-primary'` da 0 falsos negativos porque hay DOS caracteres
  (`\` y `:`). Verifica con el `esc()` del script de comprobación, no a ojo.
- Sin la lista blanca, el compilado sólo trae las utilidades que la app usa
  HOY: `bg-brand`, `text-card-foreground`, `bg-uta-red` faltaban.

## Hallazgo sobre el sistema de diseño (no es un problema del sync)

`--chart-1` … `--chart-5` se mapean en `@theme inline`
(`--color-chart-N: var(--chart-N)`) pero **nunca reciben valor** en `:root`.
`bg-chart-1` compila a `background: var(--color-chart-1)` y no pinta nada — en la
app real igual. Están excluidos de `conventions.md` a propósito. Si el equipo les
da valores en `app.css`, quitar el aviso de `conventions.md`.

El resto de variables no definidas son legítimas: `--bits-*` / `--reka-*` /
`--radix-*` las inyecta bits-ui en runtime, y `--default-font-*` son internas de
Tailwind con fallback.

## Riesgos en la próxima sincronización

- `conventions.md` es **de sus autores**. No reescribirlo: revalidar los nombres
  contra el build nuevo y proponer cambios si alguno dejó de existir.
- El tema oscuro está desactivado a propósito (`.dark` repite los valores
  claros). No "arreglarlo" aquí.
- No hay `_ds_sync.json`: sin componentes no hay nada que un diff pudiera
  saltarse, y el envelope del skill pide hashes de bundle/render que esta forma
  no produce. Cada sincronización reconstruye y resube — es barato y correcto.
- Si algún día se porta la UI a React, esta forma se descarta entera y se pasa
  al convertidor normal del skill.
