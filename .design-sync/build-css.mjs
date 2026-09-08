// design-sync converter (off-script, CSS-only shape).
//
// This repo is Laravel + Inertia + Svelte 5; claude.ai/design renders React,
// so no component bundle can ship (see .design-sync/NOTES.md). What DOES ship
// is the design language: the token layer and the semantic class layer from
// resources/css/app.css, compiled by the repo's own Tailwind v4 so a rendered
// design receives real, resolvable CSS.
//
// Emits into ds-bundle/:
//   tokens/tokens.css  - the decision layer (brand + role + state tokens)
//   _ds_bundle.css     - the full compiled stylesheet (base, utilities, .btn-*/.badge-*/...)
//   styles.css         - the @import closure the app hands to every design
import { compile } from '@tailwindcss/node';
import { Scanner } from '@tailwindcss/oxide';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const NL = String.fromCharCode(10);
const ROOT = resolve(process.cwd());
const OUT = join(ROOT, 'ds-bundle');
const CSS_ENTRY = join(ROOT, 'resources/css/app.css');
// Instrument Sans is served from bunny.net by resources/views/app.blade.php:13.
const REMOTE_FONTS = ['https://fonts.bunny.net/css?family=instrument-sans:400,500,600'];

// Lift a top-level `<selector> { ...declarations... }` block out of the source
// verbatim (brace-counting, so nested comments/braces survive).
function liftBlocks(css, selectorRe) {
  const out = [];
  for (const m of css.matchAll(selectorRe)) {
    let i = m.index + m[0].length, depth = 1;
    while (i < css.length && depth > 0) {
      if (css[i] === '{') depth++;
      else if (css[i] === '}') depth--;
      i++;
    }
    out.push({ head: m[0], body: css.slice(m.index + m[0].length, i - 1) });
  }
  return out;
}

const src = readFileSync(CSS_ENTRY, 'utf8');

// ---- tokens/tokens.css : the design-decision layer -------------------------
// `@theme inline` maps Tailwind's utility namespace onto their raw vars; the
// `:root`/`.dark` blocks hold the raw values. Both are pure custom-property
// declarations, so they lift into plain CSS unchanged.
const theme = liftBlocks(src, /@theme\s+inline\s*\{/g);
const roots = liftBlocks(src, /(?<=\n)\s{4}:root\s*\{/g);
const darks = liftBlocks(src, /(?<=\n)\s{4}\.dark\s*\{/g);

const tokens = [
  '/* UTAMED design tokens - lifted from resources/css/app.css.',
  '   The raw values (:root) and the Tailwind namespace mapped onto them',
  '   (@theme inline). Edit them in app.css, never here. */',
  '',
  ...theme.map((b) => `:root {\n${b.body.trimEnd()}\n}`),
  ...roots.map((b) => `:root {\n${b.body.trimEnd()}\n}`),
  ...darks.map((b) => `.dark {\n${b.body.trimEnd()}\n}`),
].join('\n\n') + '\n';

mkdirSync(join(OUT, 'tokens'), { recursive: true });
writeFileSync(join(OUT, 'tokens', 'tokens.css'), tokens);
const tokenCount = (tokens.match(/^\s*--[\w-]+\s*:/gm) || []).length;
console.error(`  tokens.css: ${tokenCount} declarations (${theme.length} @theme, ${roots.length} :root, ${darks.length} .dark)`);

// ---- _ds_bundle.css : the compiled stylesheet ------------------------------
// The scan above only yields utilities this codebase happens to use TODAY, so
// `bg-brand`, `text-card-foreground`, `bg-uta-red` and friends would be missing
// and a design that used them would silently render unstyled. Safelist the full
// token x prefix matrix (plus the interactive variants and opacity steps a
// design agent reaches for) so the whole design language is actually available.
const COLOR_TOKENS = [
  'background', 'foreground', 'card', 'card-foreground', 'popover', 'popover-foreground',
  'primary', 'primary-foreground', 'secondary', 'secondary-foreground',
  'muted', 'muted-foreground', 'accent', 'accent-foreground',
  'destructive', 'destructive-foreground', 'border', 'input', 'ring',
  'brand', 'brand-hover', 'brand-light',
  'uta-blue', 'uta-blue-hover', 'uta-blue-light',
  'uta-red', 'uta-red-hover', 'uta-red-light',
  'sidebar', 'sidebar-foreground', 'sidebar-primary', 'sidebar-primary-foreground',
  'sidebar-accent', 'sidebar-accent-foreground', 'sidebar-border', 'sidebar-ring',
  'chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5',
];
// Kept deliberately narrow - the full prefix x variant x opacity cross product
// is ~65k rules (19 MB of CSS). Three targeted layers cover what a design
// actually writes at ~1/60th the size.
const COLOR_PREFIXES = ['bg', 'text', 'border', 'ring', 'fill', 'stroke', 'outline', 'divide', 'caret', 'decoration', 'from', 'to', 'via'];
const PAINT_PREFIXES = ['bg', 'text', 'border'];
const VARIANTS = ['hover:', 'focus-visible:', 'active:', 'disabled:', 'group-hover:', 'data-[state=active]:', 'md:', 'lg:'];
// Opacity modifiers only make sense on the colours something is actually
// layered over, and only at the steps shadcn's own variants use.
const FADEABLE = ['primary', 'secondary', 'destructive', 'accent', 'muted', 'foreground', 'border', 'ring', 'brand', 'uta-blue', 'uta-red', 'sidebar-accent'];
const OPACITIES = ['/5', '/10', '/20', '/30', '/50', '/80', '/90', '/95'];

// Expanded in JS and handed straight to compiler.build(). `@source inline(...)`
// would be the idiomatic route, but its candidates ride on `compiler.sources`,
// and the custom Scanner below replaces that list - so they'd be silently
// dropped and the safelist would be a no-op.
const cross = (...lists) => lists.reduce((acc, l) => acc.flatMap((a) => l.map((b) => a + b)), ['']);

const safelist = [
  // 1. every prefix x every token, plain.
  ...cross(COLOR_PREFIXES, ['-'], COLOR_TOKENS),
  // 2. the interactive/responsive variants, paint prefixes only.
  ...cross(VARIANTS, PAINT_PREFIXES, ['-'], COLOR_TOKENS),
  // 3. opacity steps on the layerable colours, with and without hover.
  ...cross(['', 'hover:'], PAINT_PREFIXES, ['-'], FADEABLE, OPACITIES),
  // 4. radii and ring widths the semantic layer leans on.
  ...cross(['rounded-'], ['none', 'sm', 'md', 'lg', 'xl', '2xl', '3xl', 'full']),
  ...cross(['', 'focus-visible:'], ['ring-'], ['0', '1', '2', '4', '8']),
  ...cross(['ring-offset-'], ['0', '1', '2', '4']),
];
const compiler = await compile(src, { base: join(ROOT, 'resources/css'), onDependency() {} });

// Scan the app's own markup so the emitted utility set is the vocabulary this
// codebase actually uses - the same classes their engineers read and write.
const sources = [
  { base: join(ROOT, 'resources/js'), pattern: '**/*.{svelte,ts,js,tsx,jsx}', negated: false },
  { base: join(ROOT, 'resources/views'), pattern: '**/*.blade.php', negated: false },
  ...compiler.sources,
];
const scanner = new Scanner({ sources });
const scanned = scanner.scan();
const candidates = [...new Set([...scanned, ...safelist])];
const bundleCss = compiler.build(candidates);

writeFileSync(join(OUT, '_ds_bundle.css'), bundleCss);
console.error(`  _ds_bundle.css: ${(bundleCss.length / 1024).toFixed(0)} KB from ${candidates.length} candidates (${scanned.length} scanned + ${safelist.length} safelisted)`);

// ---- styles.css : the closure every rendered design receives ---------------
writeFileSync(
  join(OUT, 'styles.css'),
  [
    '@import "./tokens/tokens.css";',
    ...REMOTE_FONTS.map((u) => `@import url("${u}");`),
    '@import "./_ds_bundle.css";',
  ].join('\n') + '\n',
);
console.error('  styles.css: 3 @import(s)');

// ---- _ds_bundle.js : an honest empty namespace -----------------------------
// The app's self-check expects this file. There are no React components to put
// in it (the real ones are Svelte), so it declares an empty namespace rather
// than pretending to export anything.
const header = {
  namespace: 'UtamedDS',
  components: [],
  sourceHashes: {},
  inlinedExternals: [],
};
writeFileSync(
  join(OUT, '_ds_bundle.js'),
  `/* @ds-bundle: ${JSON.stringify(header)} */` + NL +
    '// UTAMED ships its design language (tokens + CSS), not runtime components.' + NL +
    '// See README.md - build markup directly and style it with the documented classes.' + NL +
    'window.UtamedDS = window.UtamedDS || {};' + NL,
);

// ---- README.md : conventions header + generated index -----------------------
const conventions = readFileSync(join(ROOT, '.design-sync/conventions.md'), 'utf8');

const tokenIndex = [...tokens.matchAll(/^\s*(--[\w-]+)\s*:\s*([^;]+);/gm)]
  .map((m) => `| \`${m[1]}\` | \`${m[2].trim()}\` |`);

const semanticClasses = [...new Set(
  [...bundleCss.matchAll(/^\s{0,4}\.((?:btn|badge|page|field|req)[\w-]*)/gm)].map((m) => m[1]),
)].sort();

writeFileSync(
  join(OUT, 'README.md'),
  [
    conventions.trimEnd(),
    '',
    '---',
    '',
    '## Índice generado',
    '',
    `Generado desde \`resources/css/app.css\` por \`.design-sync/build-css.mjs\`.`,
    `Tailwind v4 · ${candidates.length} candidatos (${scanned.length} del código, ${safelist.length} de la lista blanca).`,
    '',
    `### Clases semánticas (${semanticClasses.length})`,
    '',
    semanticClasses.map((c) => `\`.${c}\``).join(' · '),
    '',
    `### Tokens (${tokenIndex.length})`,
    '',
    '| Token | Valor |',
    '|---|---|',
    ...tokenIndex,
    '',
  ].join(NL),
);
console.error(`  README.md: conventions header + ${semanticClasses.length} classes, ${tokenIndex.length} tokens`);

// The sentinel the app uses to fence its manifest/copy machinery.
writeFileSync(join(OUT, '_ds_needs_recompile'), JSON.stringify({ by: 'design-sync-cli' }) + NL);
