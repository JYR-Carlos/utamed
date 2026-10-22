# Rediseño con la guía dm v0.1

Rediseño completo de la interfaz de UTAMED con la guía de diseño de Diseño Multimedia (dm v0.1). Se trabaja en la rama
`rediseno-guia-dm`; nada de esto va a `main` hasta que el equipo apruebe cada lote. Los arreglos de auditoría siguen
yendo a `main` sobre la paleta actual.

- Guía dm v0.1: https://claude.ai/artifact/2PwgXZ4pjJz8UscQYU8sAd
- Archivo de Figma: https://www.figma.com/design/gMGFJcNq1gsIvc5WRZNIs3
- Lienzo de Claude (mientras Figma no tenga cuota): https://claude.ai/artifact/JThPrTH1s4KeK39DZzy8kT. Página
  «Direcciones» con la A sobria y la C editorial descartadas; página «Lote 1 · Estudiante» con las pantallas en la
  dirección B, cada una a 1440 y 390 px y con botón de claro/oscuro
- Tablero: `arreglos-reunion-23-09-2026`, tarjetas RED-00 a RED-05

## Flujo

1. Cada lote se diseña en Figma: cada pantalla a 1440 y 390 px, con sus estados (vacío, error, cargando si aplica) y
   en claro; el oscuro se muestra con un espécimen por lote.
2. Se revisa contra la guía y contra los umbrales de la auditoría: texto 4,5:1, texto grande e íconos 3:1, texto de
   13 px o más (12 sólo en etiquetas en mayúsculas), áreas táctiles de 44 px en las acciones principales en móvil.
3. El usuario revisa el lote en Figma. Sólo con su aprobación se implementa en Svelte, en esta rama.
4. Datos ficticios en Figma: ningún nombre, RUT ni nota real, porque Figma es un servicio externo.
5. Una sesión nueva por lote, partiendo de este archivo.
6. Sin cuota de Figma, el lote se arma en el lienzo de Claude con los mismos criterios y se pasa a Figma después.

| Lote | Tarjeta | Alcance | Estado |
|---|---|---|---|
| 0 | RED-00 | Fundamentos: variables, estilos, componentes base y 2–3 direcciones del Inicio del estudiante | Dirección B elegida; faltan componentes en Figma |
| 1 | RED-01 | Estudiante (18 pantallas) | En curso en el lienzo de Claude |
| 2 | RED-02 | Docente (20) | Pendiente |
| 3 | RED-03 | Jefe de carrera, ayudante y dashboards (11) | Pendiente |
| 4 | RED-04 | Administración (15) | Pendiente |
| 5 | RED-05 | Ingreso y ajustes (12) | Pendiente |

## Límites del plan Starter de Figma

El archivo está en el plan Starter de «juan Yampara's team», y eso condiciona cómo se arma:

- **Un modo por colección de variables.** El oscuro vive en colecciones aparte («Color · Oscuro», «Curso · Oscuro») con
  los mismos nombres de variable. Para ver una pantalla en oscuro se duplica el marco y se reasigna cada variable a su
  homónima oscura. Con el plan Professional serían modos reales de una sola colección.
- **Tres páginas como máximo:** Sistema, Direcciones y Pantallas. Cada lote va como una sección dentro de Pantallas.
- **Cuota de llamadas del MCP.** El 2026-10-22 se agotó a mitad del lote 0 («You've reached the Figma MCP tool call
  limit on the Starter plan»). La documentación de Figma no dice cuándo se renueva. Con un puesto Full en Professional
  el límite es de 200 llamadas al día y 10 por minuto.

## Estado del archivo de Figma

Páginas: Sistema `0:1`, Direcciones `2:232`, Pantallas `2:233`.

**Variables**

| Colección | Contenido |
|---|---|
| Primitivos | 58 colores de la guía, sin alcance (no se usan directo) |
| Color | 39 semánticas: `superficie/*`, `estructura/*`, `texto/*`, `borde/*`, `accion/*`, `activo/*`, `estado/*` y `-suave` |
| Curso | `curso-N/{base,suave,texto,borde}` para los 8 cursos |
| Medidas | `espacio/1`–`espacio/8` (4 a 64 px) y `radio/{recto,menu,control,tarjeta,pildora}` |
| Color · Oscuro, Curso · Oscuro | Mismos nombres, valores del tema oscuro |

Cada variable lleva su sintaxis de código web (`var(--…)`), que es la que tendrán los tokens en `app.css`.

**Estilos:** Display/Grande y Media (condensada, sólo títulos grandes), Título/Página, Sección y Tarjeta, Cuerpo/Base,
Interfaz/Base y Fuerte, Meta/Base y Fuerte, Etiqueta/Mayúsculas, Código/Base (JetBrains Mono). Sombra/Flotante para lo
que flota.

**Componentes en Sistema**

| Componente | Nodo | Variantes |
|---|---|---|
| Íconos (Lucide, 30) | marco `3:2` | trazo ligado a `texto/principal` |
| Botón | `4:64` | Primario, Secundario, Terciario y Peligro × Normal, Hover y Deshabilitado |
| Insignia de estado | `4:93` | Correcto, Advertencia, Información, Inactivo y Error |
| Chip de curso | `4:118` | Curso 1–8 |
| Pestaña | `5:19` | Activa e Inactiva |
| Ítem de menú | `5:40` | Sección y Curso × Activo e Inactivo (sobre noche) |
| Campo | `5:63` | Normal, Foco y Error |

**Falta del lote 0**

- Aviso (Información, Advertencia, Error y Correcto), Estado vacío y Tarjeta de curso (Curso 1–8). El script ya está
  escrito; la llamada se cortó por la cuota y Figma la deshizo entera.
- El punto de curso del Ítem de menú debe ligarse a `Curso · Oscuro/curso-N/base`, porque el menú es noche en ambos
  temas (#2441CA sobre #161949 no se distingue).
- Composiciones: menú lateral del estudiante y barra superior.
- Pasar la dirección B y las pantallas del lote 1 del lienzo de Claude a Figma.
- Revisión de contraste AA y tamaños mínimos.

## Lote 1 · Estudiante

Se eligió la dirección B (marca dm) el 2026-10-22. Mientras Figma no tenga cuota, el lote se arma en el lienzo de
Claude, por tandas:

| Tanda | Pantallas | Estado |
|---|---|---|
| 1 | Inicio, Mis cursos, Ficha del curso · Actividades, Actividad por entregar | Publicada |
| 2 | Ficha del curso · Resumen de notas, Ficha del curso · Mensajería, Actividad calificada | Publicada |
| 3 | Ficha del curso · Programa, Biblioteca, Calendario y Bitácora; Syllabus | Pendiente |
| 4 | Perfil, Cambiar contraseña, modales (agenda, subir entrega, rúbrica), estados vacíos y de error | Pendiente |

Criterios de la tanda 2:

- **Resumen de notas:** mismos datos que `rendimiento` (promedios, evaluadas, peso del componente, aprobación por
  separado, exigencia y asistencia). La asistencia bajo el mínimo usa la insignia de advertencia con texto; el rojo
  queda para errores.
- **Mensajería:** propone renombrar «Generales» e «Individuales» a «Avisos del curso» y «Conversación con el equipo
  docente», con el selector de componente arriba y el conteo de nuevos. Laboratorio muestra el estado vacío.
- **Actividad calificada:** la rúbrica muestra el nivel de cada criterio (`ultima_evaluacion.resultado`) y el puntaje
  (`puntaje_obtenido`). No hay botón de apelación porque la apelación (T48) no está implementada
  (`puedeApelar = false` en `Activities/Index.svelte`); las dudas sobre la nota van a la agenda de la actividad.
  Los nombres de los niveles de la rúbrica son de ejemplo.

## Decisiones

- Las direcciones se diferencian por cuánto lenguaje dm llevan (retícula, condensada, noche), no por la paleta: todas
  respetan los roles de color de la guía (noche estructura, azul actúa, amarillo marca lo activo).
- El menú lateral es noche en claro y en oscuro; dentro de él los colores de curso usan su versión oscura.
- La Tarjeta de curso usa sólo datos que existen: código, nombre, docente y la próxima entrega (derivable de
  `proximasAVencer`). Nada de porcentaje de avance mientras el backend no lo calcule.
- La mensajería se entra desde el curso, no desde el menú; el menú del estudiante tiene Inicio, «Mis cursos» y la lista
  de cursos.
