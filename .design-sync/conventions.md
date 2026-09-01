# UTAMED — lenguaje visual

Este sistema aporta **el lenguaje visual, no componentes**. La aplicación real es
Laravel + Inertia + **Svelte 5** (shadcn-svelte), y Claude Design renderiza React,
así que aquí no hay componentes que importar: hay tokens y clases CSS reales,
compiladas desde `resources/css/app.css` con el propio Tailwind v4 del repo.
Construye el marcado tú mismo y vístelo con este vocabulario; lo que escribas
traduce casi literalmente a los componentes Svelte del proyecto.

## Montaje

No hace falta provider ni wrapper de tema. Basta con que la raíz lleve el fondo y
el color de texto del sistema, y que el contenido viva en el contenedor de página:

```jsx
<div className="bg-background text-foreground min-h-screen">
  <div className="page-shell">…</div>
</div>
```

`page-shell` centra a 1400px con el padding del panel. **El tema oscuro está
desactivado a propósito**: `.dark` existe pero repite los valores claros, así que
no diseñes variantes oscuras.

## Idioma de estilado

Tailwind v4 sobre variables CSS. Hay dos vocabularios y se eligen por intención:

**1. Clases semánticas — para acciones, estados y formularios.** Son la única
fuente para botones e insignias. El color lo decide el ROL de la acción, nunca la
entidad sobre la que actúa; **nunca escribas `bg-blue-600` ni `bg-green-500`**.

| Familia | Clases | Cuándo |
|---|---|---|
| Botones | `btn` + `btn-primary` · `btn-danger` · `btn-neutral` · `btn-ghost` | Una `btn-primary` por vista. `btn-danger` sólo si destruye o retira algo. `btn-neutral` para cancelar y secundarias. `btn-ghost` para terciarias en tabla o cabecera. |
| Tamaño/icono | `btn-sm`, `btn-icon`, `btn-icon-danger` | `btn-icon` garantiza objetivo táctil de 2.25rem. |
| Insignias | `badge` + `badge-ok` · `badge-warn` · `badge-info` · `badge-off` | Comunican situación, no acción. |
| Página | `page-shell`, `page-header`, `page-title`, `page-subtitle`, `page-current` | `page-header` ya reparte título y acciones. |
| Formulario | `field-label`, `field-input`, `field-hint`, `field-error`, `req` | `req` añade el asterisco de obligatorio vía `::after`. |

**2. Utilidades Tailwind con tokens — para todo lo demás** (layout, tarjetas,
tipografía). Los prefijos `bg-` `text-` `border-` `ring-` `fill-` `outline-`
`divide-` combinan con estos nombres de token:

- Superficie: `background`, `foreground`, `card`, `card-foreground`, `popover`, `muted`, `muted-foreground`, `accent`, `border`, `input`, `ring`
- Rol: `primary`, `primary-foreground`, `secondary`, `destructive`
- Marca: `brand` (#2A66AC), `brand-light`, `uta-blue` (#002855), `uta-red` (#8A1538) y sus `-hover` / `-light`
- Barra lateral: `sidebar`, `sidebar-accent`, `sidebar-border`

> Para series de datos no uses `chart-1`…`chart-5`: el tema los declara pero no
> tienen valor, así que no pintan nada. Usa `brand`, `uta-blue` y `uta-red`.

Aceptan variantes `hover:` `focus-visible:` `active:` `disabled:` `group-hover:`
`md:` `lg:` y opacidad (`bg-primary/10`, `hover:bg-primary/90`). Radio por
`rounded-lg` (`--radius` = 0.75rem). Tipografía: Instrument Sans, ya cargada.

Si necesitas un color donde no llega una utilidad, usa la variable del tema:
`style={{ background: 'var(--color-uta-blue)' }}`.

## Dónde está la verdad

`_ds/<carpeta>/styles.css` importa `tokens/tokens.css` (los 102 tokens, con los
comentarios que explican cada rol) y `_ds_bundle.css` (utilidades y capa
semántica compiladas). Léelos antes de inventar un color.

## Ejemplo

```jsx
<div className="page-shell">
  <div className="page-header">
    <div>
      <h1 className="page-title">Gestión de cursos</h1>
      <p className="page-subtitle">Malla vigente 2026</p>
    </div>
    <div className="flex gap-2">
      <button className="btn btn-neutral">Cancelar</button>
      <button className="btn btn-primary">Nuevo curso</button>
    </div>
  </div>

  <div className="rounded-lg border border-border bg-card p-6">
    <span className="badge badge-ok">Activo</span>
    <label className="field-label req mt-4">Nombre</label>
    <input className="field-input" />
    <p className="field-hint">Aparece en la malla curricular.</p>
  </div>
</div>
```
