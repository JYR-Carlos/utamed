<script lang="ts" module>
  /**
   * Qué cuenta la retícula: `reposo` mientras se completa el formulario,
   * `trabajando` mientras viaja al servidor, `listo` cuando terminó bien.
   */
  export type Escena = 'reposo' | 'trabajando' | 'listo';

  /** `sobre`: al terminar se traza un sobre (enlace enviado); `centro`: ondas. */
  export type Figura = 'centro' | 'sobre';

  type Celda = { x: number; y: number; d: string; t?: number; k?: 1 | 2 | 3 };

  /** Una señal: recorre las esquinas dadas celda por celda, con retraso creciente. */
  function tramo(g: number, puntos: [number, number][], inicio: number, paso: number, dur: number): Celda[] {
    const celdas: [number, number][] = [];
    for (let i = 0; i < puntos.length - 1; i++) {
      let [x, y] = puntos[i];
      const [x2, y2] = puntos[i + 1];
      const dx = Math.sign(x2 - x);
      const dy = Math.sign(y2 - y);
      while (x !== x2 || y !== y2) {
        celdas.push([x, y]);
        x += dx;
        y += dy;
      }
    }
    celdas.push(puntos[puntos.length - 1]);
    return celdas.map(([c, f], i) => ({ x: c * g + 1, y: f * g + 1, d: (inicio + i * paso).toFixed(2), t: dur }));
  }

  /** Anillos de celdas alrededor del rectángulo de columnas c0–c1 y filas f0–f1. */
  function anillos(g: number, [c0, c1, f0, f1]: number[], ks: number[], inicio: number): Celda[] {
    const celdas: Celda[] = [];
    for (const k of ks) {
      for (let c = c0 - k; c <= c1 + k; c++) {
        for (let f = f0 - k; f <= f1 + k; f++) {
          if (c === c0 - k || c === c1 + k || f === f0 - k || f === f1 + k) {
            celdas.push({ x: c * g + 1, y: f * g + 1, d: (inicio + k * 0.14).toFixed(2), k: Math.max(k, 1) as 1 | 2 | 3 });
          }
        }
      }
    }
    return celdas;
  }

  type Escenas = { reposo: Celda[]; trabajando: Celda[]; listo: Celda[] };

  const llegan = (g: number, tramos: [[number, number][], number][]) =>
    tramos.flatMap(([p, inicio]) => tramo(g, p, inicio, 0.06, 1.2));

  // Coordenadas de las láminas del lienzo (Ingreso · Recuperar / Restablecer / Cambio obligatorio).
  const ESCRITORIO: Record<Figura, Escenas> = {
    centro: {
      reposo: [
        ...tramo(28, [[1, 3], [6, 3], [6, 1], [16, 1], [16, 3], [21, 3]], 0.8, 0.09, 7),
        ...tramo(28, [[22, 15], [15, 15], [15, 13], [6, 13], [6, 16], [1, 16]], 3.1, 0.09, 7),
        ...tramo(28, [[4, 20], [4, 7], [1, 7]], 5.4, 0.09, 7),
      ],
      trabajando: llegan(28, [
        [[[0, 7], [9, 7]], 0],
        [[[22, 8], [12, 8]], 0],
        [[[10, 0], [10, 6]], 0.3],
        [[[11, 20], [11, 9]], 0.3],
      ]),
      listo: anillos(28, [10, 11, 7, 8], [0, 1, 2, 3], 0.3),
    },
    sobre: {
      reposo: [
        ...tramo(28, [[1, 4], [8, 4], [8, 8], [14, 8], [14, 5], [21, 5]], 0.8, 0.09, 7),
        ...tramo(28, [[22, 16], [16, 16], [16, 11], [10, 11], [10, 14], [2, 14]], 3.1, 0.09, 7),
        ...tramo(28, [[12, 1], [12, 6], [18, 6], [18, 12], [22, 12]], 5.4, 0.09, 7),
      ],
      trabajando: llegan(28, [
        [[[0, 9], [7, 9]], 0],
        [[[22, 8], [14, 8]], 0],
        [[[11, 0], [11, 6]], 0.3],
        [[[11, 17], [11, 11]], 0.3],
      ]),
      listo: anillos(28, [8, 13, 7, 10], [1, 2, 3], 1.3),
    },
  };

  const MOVIL: Record<Figura, Escenas> = {
    centro: {
      reposo: [
        ...tramo(24, [[0, 1], [3, 1], [3, 4], [0, 4]], 0.8, 0.09, 6),
        ...tramo(24, [[16, 4], [13, 4], [13, 1], [16, 1]], 3.4, 0.09, 6),
      ],
      trabajando: llegan(24, [
        [[[0, 2], [6, 2]], 0],
        [[[16, 3], [9, 3]], 0],
        [[[0, 3], [6, 3]], 0.3],
        [[[16, 2], [9, 2]], 0.3],
      ]),
      listo: anillos(24, [7, 8, 2, 3], [0, 1, 2], 0.3),
    },
    sobre: {
      reposo: [
        ...tramo(24, [[0, 1], [5, 1], [5, 3], [11, 3], [11, 0], [16, 0]], 0.8, 0.09, 6),
        ...tramo(24, [[16, 4], [13, 4], [13, 2], [8, 2], [8, 4], [2, 4], [2, 0]], 3.6, 0.09, 6),
      ],
      trabajando: llegan(24, [
        [[[0, 1], [5, 1]], 0],
        [[[16, 1], [10, 1]], 0],
        [[[0, 3], [5, 3]], 0.3],
        [[[16, 3], [10, 3]], 0.3],
      ]),
      listo: anillos(24, [6, 9, 1, 3], [1, 2], 1.3),
    },
  };

  /** El sobre y su marca de «enviado»: posición y tamaño en cada retícula. */
  const SOBRE = {
    escritorio: { x: 224, y: 196, ancho: 168, alto: 112, trazo: 3, marca: { x: 393, y: 169, lado: 27, icono: 18 } },
    movil: { x: 144, y: 24, ancho: 96, alto: 72, trazo: 2.5, marca: { x: 241, y: 1, lado: 23, icono: 15 } },
  };
</script>

<script lang="ts">
  /**
   * Retícula viva del panel noche en las pantallas de contraseña (dirección
   * A · Noche): líneas blancas con un barrido de luz y celdas amarillas que
   * se encienden según la escena. Sin figuras dibujadas con celdas, salvo el
   * sobre del enlace enviado.
   *
   * Es decorativa (aria-hidden). Con movimiento reducido queda la retícula
   * quieta y, si corresponde, el sobre ya dibujado.
   */
  import { Check } from 'lucide-svelte';

  interface Props {
    escena: Escena;
    figura?: Figura;
    /** Franja de 24 px de la cabecera móvil en vez del panel de 28 px. */
    movil?: boolean;
    /** Posición y alto (clases de Tailwind). */
    class?: string;
  }

  let { escena, figura = 'centro', movil = false, class: clase = '' }: Props = $props();

  const g = $derived(movil ? 24 : 28);
  const celdas = $derived((movil ? MOVIL : ESCRITORIO)[figura][escena]);
  const sobre = $derived(SOBRE[movil ? 'movil' : 'escritorio']);
  /** Medio trazo: el borde del sobre queda entero dentro de su caja. */
  const m = $derived(sobre.trazo / 2);
  const lineas = (alfa: number, lado: number) =>
    `background-image: linear-gradient(rgb(255 255 255 / ${alfa}) 1px, transparent 1px), linear-gradient(90deg, rgb(255 255 255 / ${alfa}) 1px, transparent 1px); background-size: ${lado}px ${lado}px`;
</script>

<div aria-hidden="true" class="pointer-events-none overflow-hidden {movil ? 'mascara-movil' : 'mascara'} {clase}">
  <div class="reticula absolute inset-0" style={lineas(0.09, g)}></div>
  <div class="barrido absolute inset-0" style={lineas(0.32, g)}></div>

  {#key escena}
    {#each celdas as c, i (i)}
      <span
        class={escena === 'listo' ? 'onda' : 'celda'}
        class:onda1={c.k === 1}
        class:onda2={c.k === 2}
        class:onda3={c.k === 3}
        style:left="{c.x}px"
        style:top="{c.y}px"
        style:width="{g - 1}px"
        style:height="{g - 1}px"
        style:animation-delay="{c.d}s"
        style:animation-duration={c.t ? `${c.t}s` : undefined}
      ></span>
    {/each}

    {#if escena === 'listo' && figura === 'sobre'}
      <svg
        width={sobre.ancho}
        height={sobre.alto}
        viewBox="0 0 {sobre.ancho} {sobre.alto}"
        class="absolute overflow-visible text-dm-amarillo"
        style:left="{sobre.x}px"
        style:top="{sobre.y}px"
      >
        <rect
          class="sobre-fondo"
          x={m}
          y={m}
          width={sobre.ancho - sobre.trazo}
          height={sobre.alto - sobre.trazo}
          rx="2"
          fill="#161949"
        />
        <path
          class="traza"
          pathLength="1"
          d="M{m} {m}H{sobre.ancho - m}V{sobre.alto - m}H{m}Z"
          fill="none"
          stroke="currentColor"
          stroke-width={sobre.trazo}
          stroke-linejoin="round"
          style:animation-delay="0.1s"
        />
        <path
          class="traza"
          pathLength="1"
          d="M{m} {m}L{sobre.ancho / 2} {sobre.alto * 0.57}L{sobre.ancho - m} {m}"
          fill="none"
          stroke="currentColor"
          stroke-width={sobre.trazo}
          stroke-linecap="round"
          stroke-linejoin="round"
          style:animation-delay="0.8s"
        />
      </svg>
      <span
        class="marca absolute flex items-center justify-center rounded-[3px] bg-dm-amarillo text-[#161949]"
        style:left="{sobre.marca.x}px"
        style:top="{sobre.marca.y}px"
        style:width="{sobre.marca.lado}px"
        style:height="{sobre.marca.lado}px"
      >
        <Check size={sobre.marca.icono} strokeWidth={3} />
      </span>
    {/if}
  {/key}
</div>

<style>
  .mascara {
    mask-image: radial-gradient(ellipse 72% 68% at 48% 42%, #000 32%, transparent 80%);
  }

  .mascara-movil {
    mask-image: radial-gradient(ellipse 62% 88% at 50% 50%, #000 35%, transparent 88%);
  }

  /* La retícula aparece desde una esquina; el barrido la recorre cada 9 s. */
  .reticula {
    animation: revela 1.6s cubic-bezier(0.2, 0.7, 0.2, 1) both;
  }

  @keyframes revela {
    from {
      clip-path: inset(0 0 100% 100%);
    }
    to {
      clip-path: inset(0 0 0 0);
    }
  }

  .barrido {
    mask-image: linear-gradient(115deg, transparent 44%, #000 50%, transparent 56%);
    mask-size: 250% 100%;
    mask-repeat: no-repeat;
    mask-position: 100% 0;
    animation: barre 9s linear 1.2s infinite;
  }

  @keyframes barre {
    0% {
      mask-position: 100% 0;
    }
    60%,
    100% {
      mask-position: 0% 0;
    }
  }

  .celda,
  .onda {
    position: absolute;
    border-radius: 2px;
    background: var(--color-dm-amarillo);
    opacity: 0;
    animation-iteration-count: infinite;
  }

  .celda {
    animation-name: celda;
    animation-timing-function: linear;
  }

  @keyframes celda {
    0% {
      opacity: 0;
    }
    1.5% {
      opacity: 1;
    }
    9% {
      opacity: 0.35;
    }
    18%,
    100% {
      opacity: 0;
    }
  }

  .onda {
    animation-duration: 3.2s;
    animation-timing-function: ease-out;
  }

  .onda1 {
    animation-name: onda1;
  }

  .onda2 {
    animation-name: onda2;
  }

  .onda3 {
    animation-name: onda3;
  }

  @keyframes onda1 {
    10% {
      opacity: 0.55;
    }
    45%,
    100% {
      opacity: 0;
    }
  }

  @keyframes onda2 {
    10% {
      opacity: 0.32;
    }
    45%,
    100% {
      opacity: 0;
    }
  }

  @keyframes onda3 {
    10% {
      opacity: 0.16;
    }
    45%,
    100% {
      opacity: 0;
    }
  }

  /* El sobre: se traza el borde, se rellena y aparece la marca. */
  .traza {
    stroke-dasharray: 1;
    stroke-dashoffset: 0;
    animation: traza 1s cubic-bezier(0.6, 0, 0.3, 1) both;
  }

  @keyframes traza {
    from {
      stroke-dashoffset: 1;
    }
  }

  .sobre-fondo {
    animation: llena 0.6s ease 0.3s both;
  }

  @keyframes llena {
    from {
      fill-opacity: 0;
    }
  }

  .marca {
    animation: pop 0.45s cubic-bezier(0.3, 1.4, 0.5, 1) 1.3s both;
  }

  @keyframes pop {
    0% {
      transform: scale(0);
    }
    70% {
      transform: scale(1.15);
    }
    100% {
      transform: scale(1);
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .reticula,
    .barrido,
    .celda,
    .onda,
    .traza,
    .sobre-fondo,
    .marca {
      animation: none;
    }
  }
</style>
