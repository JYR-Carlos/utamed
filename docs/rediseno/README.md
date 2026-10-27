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
| 1 | RED-01 | Estudiante (18 pantallas) | Implementado en Svelte en esta rama (ver «Implementación del lote 1»); falta la revisión y pasarlo a Figma |
| 2 | RED-02 | Docente (20) | Pendiente: falta un diseño propuesto |
| 3 | RED-03 | Jefe de carrera, ayudante y dashboards (11) | Pendiente: falta un diseño propuesto |
| 4 | RED-04 | Administración (15) | Pendiente: falta un diseño propuesto |
| 5 | RED-05 | Ingreso y ajustes (12) | Dirección A elegida; portada y login implementados (RED-IMP-05, ver «Lote 5 · Implementación»); las otras 11 pantallas en espera (RED-05b) |

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
| 3 | Ficha del curso · Programa, Biblioteca, Calendario y Bitácora; Programa para imprimir | Publicada |
| 4 | Perfil, Cambiar contraseña, modales (agenda, subir entrega, rúbrica), estados vacíos y de error | Publicada |

Criterios de la tanda 2:

- **Resumen de notas:** mismos datos que `rendimiento` (promedios, evaluadas, peso del componente, aprobación por
  separado, exigencia y asistencia). La asistencia bajo el mínimo usa la insignia de advertencia con texto; el rojo
  queda para errores.
- **Mensajería:** «Generales» e «Individuales» pasan a llamarse «Avisos del curso» y «Conversación con el equipo
  docente» (aprobado el 2026-10-22). El selector de componente va arriba, con el conteo de nuevos. Laboratorio muestra
  el estado vacío.
- **Actividad calificada:** la rúbrica muestra el nivel de cada criterio (`ultima_evaluacion.resultado`) y el puntaje
  (`puntaje_obtenido`). No hay botón de apelación porque la apelación (T48) no está implementada
  (`puedeApelar = false` en `Activities/Index.svelte`); las dudas sobre la nota van a la agenda de la actividad.
  Los nombres de los niveles de la rúbrica son de ejemplo.

Criterios de la tanda 3:

- **Programa:**
  - La ficha (categoría, docentes, SCT, horas) suma una franja «Cómo se evalúa» con los componentes de la sección IX.
  - Las secciones van en tarjetas, con las Unidades primero (T24 y T28) y un índice lateral para saltar entre ellas.
- **Biblioteca:** la sección VIII del programa agrupada por sus subtítulos.
  - El ícono de cada ítem sale del texto: libro si no trae nada, archivo si trae `uuid_archivo` y enlace si trae URL.
  - No hay buscador ni filtros nuevos.
- **Calendario:** mantiene «Este curso» y «Todo el semestre», las vistas Mes y Agenda, el detalle del día, la leyenda y
  el salto a la siguiente fecha.
  - «Vencida» va en ámbar sólido y «Cierra pronto» en ámbar suave, siempre con ícono y texto. El rojo queda para
    errores.
  - En el semestre, cada marca lleva el código y el color del curso.
  - En móvil, la grilla compacta (con puntos) más el detalle del día y la agenda del mes, sin selector de vista.
- **Bitácora:** las mismas entradas y filtros que hoy (actividad y componente), agrupadas por día en una línea de
  tiempo. Cada tipo tiene su color y su etiqueta, y cada entrada lleva a la agenda de su actividad.
- **Programa para imprimir:** una sola columna de lectura en una hoja con la franja del curso, para que se imprima limpio.
  En móvil, las tablas pasan a listas.

Criterios de la tanda 4:

- **Tokens nuevos:** `--error` y `--error-s` (#B42318 sobre #FEF3F2 en claro, #FDA29B sobre #3A1515 en oscuro) y
  `--velo` para el fondo de las ventanas. El rojo sólo aparece en errores reales: campo inválido, contraseña actual
  incorrecta, entrega fallida y error de carga.
- **Perfil:** cabecera noche con el nombre; los datos de contacto (lo único editable, T10) van al centro, y al lado
  «Cuenta» (cambiar contraseña, foto en la Intranet) y los datos de la Intranet en sólo lectura. La ventana que sólo
  avisaba que la foto se cambia en la Intranet pasa a ser un enlace directo. Tiene los estados sin cambios, guardado y
  con error.
- **Cambiar contraseña:** mismos campos y política que `@/lib/password`. Mientras se escribe, lo que falta queda
  neutro y lo cumplido en verde; el rojo queda para el error del servidor. Se suma el botón de mostrar contraseña, que
  no necesita backend.
- **Agenda:** ventana de 880 px con cabecera noche y franja del curso; en móvil, pantalla completa. Abre en el último
  mensaje, como hoy. Muestra todos los tipos del hilo: mensajes propios y del docente, entrega vigente y reemplazada,
  cierre de la actividad, evaluación con nota y «Ver rúbrica», y «Visto por». «Enviar» es la acción primaria.
- **Subir entrega:** ventana por pasos (Archivo, Confirmar, Listo) con los mismos estados que `Entrega.svelte`: elegir,
  elegido, confirmar, subiendo, registrada y error. La descripción obligatoria se marca con texto, no con un asterisco
  rojo. En móvil es una hoja inferior con los botones a lo ancho.
- **Rúbrica:** panel lateral de 760 px; en móvil, pantalla completa. Resumen (puntaje, corte del 4,0 al 60 %,
  criterios), retroalimentación y cada criterio con sus niveles, puntos y descripción; el nivel asignado se marca en
  amarillo con «Nivel asignado». La vista «antes de entregar» es la misma rúbrica sin resultado. No muestra la nota:
  el detalle que recibe el panel no la trae.
- **Estados:** catálogo con los textos actuales de la app: sin cursos, sin grupo, filtro sin resultados, bitácora
  vacía, biblioteca sin programa aprobado, calendario sin fechas, mensajería cargando y bitácora con error de carga.

## Implementación del lote 1

Todo el lote del estudiante está en Svelte en esta rama, desde `0f3053c` hasta `47d334d`. El docente y el admin no
cambian.

**Cómo está armado**

- Tailwind 4 con los tokens de la guía en `resources/css/guia-dm.css` (`@theme static`, prefijo `dm-`) y sus valores
  oscuros bajo `.dark`.
- El color del curso llega por las variables `--curso`, `--curso-s`, `--curso-t`… que arma `estiloCurso()`. Las
  ventanas van en un portal, así que reciben ese estilo por prop.
- Las cadenas de clases que se repiten están en `resources/js/lib/dm.ts`: `btnPrimario`, `campo`, `tarjeta`,
  `etiqueta`, etc. No se suman a una de ellas utilidades de la misma propiedad (`px-*`, `text-*`), porque el orden en el
  CSS no está garantizado. Si hace falta otra medida, se escribe la cadena completa.
- Los componentes compartidos con el docente se ajustan con la variante `alumno:`, que sólo aplica dentro de
  `.vista-alumno` (StudentLayout).
- Las piezas propias de la guía están en `resources/js/components/student/dm/`: `Reticula`, `EstadoVacio` y
  `ModalAlumno` (Dialog de bits-ui con las variantes grande, centro y lateral).
- `RequisitosPassword` suma el tono `dm`; los tonos claro y oscuro de ingreso y ajustes no cambian.

**Cambios de backend (mínimos)**

- `ActivityController` manda `exigencia` para el corte del 4,0 en la rúbrica.
- `MensajeriaService::sinLeer()` marca con `nuevo` los mensajes que el alumno aún no había leído al abrir la bandeja,
  antes de marcarlos como leídos. Lo cubre `tests/Feature/MensajeriaComponenteTest.php`.

**Diferencias con el lienzo, por los datos que existen**

- **Mensajería:** se conserva el selector de destinatario en vez de la casilla «Dirigir al titular», porque el backend
  siempre pide un receptor (por defecto el titular). No hay «Ver N mensajes anteriores» porque no hay paginación. El
  asunto sólo se pide al abrir una conversación nueva.
- **Actividad:** no existe la numeración «Actividad 03» ni una columna de descripción. La rúbrica sólo aparece en las
  sumativas.
- **Rúbrica:** no muestra la nota, porque el detalle que recibe el panel no la trae.
- **Perfil:** la cabecera dice «N cursos inscritos» y no «este semestre», porque `total_cursos` cuenta todas las
  inscripciones vigentes.
- **Actividad sin grupo:** «Escribir al equipo docente» lleva a la pestaña Mensajería del curso.

**Verificación**

- svelte-check queda en 9 errores, todos anteriores a la rama, y sin warnings nuevos.
- Cada pantalla se revisó con capturas de Playwright a 1280 y 390 px, en claro y en oscuro, con datos ficticios.
  Perfil y Contraseña se montaron con `createInertiaApp` y respuestas Inertia simuladas, para ver los estados guardado
  y con error.
- Las props de las páginas se probaron contra la BD local; falta recorrerlo con la sesión de un alumno real. No hay un
  alumno de prueba con clave, y crearlo es un cambio en la BD que se tiene que pedir.

**Trampa de Svelte 5:** recorta el espacio al principio y al final del contenido de una etiqueta (`<span> sin
leer</span>` se ve «sin leer» pegado a lo anterior). Donde el espacio importa se escribe `{' '}`.

## Lote 5 · Ingreso (propuestas)

El 2026-10-22 se adelantaron la portada (`Welcome.svelte`) y el login (`auth/Login.svelte`) en el lienzo, página
«Lote 5 · Ingreso». Hay dos propuestas, cada pantalla a 1440 y 390 px y con claro/oscuro. El estado del login se elige
en las propiedades del tablero, y desde la portada se llega al login de la misma propuesta.

- **A · Noche:** sigue al panel aprobado.
  - Portada: la cabecera es noche con el degradado profundo (#161949 → #660959) y la retícula. El acceso va en una
    tarjeta blanca, como «Lo de hoy» en el Inicio.
  - Login: la pantalla se parte en un panel de marca noche y el formulario sobre fondo claro.
- **B · Amarillo dm:** toma la portada de la guía.
  - Portada: amarillo a sangre con la retícula del logotipo, y UTAMED en la condensada a 184 px. La ficha del curso se
    muestra con sus siete pestañas, que se pueden recorrer.
  - Login: una tarjeta blanca sobre el amarillo.
  - En oscuro, el amarillo pasa a noche y el nombre queda en amarillo.

Criterios comunes:

- **Marcas (cap. 02):** primero el isologotipo UTA y después el cuadro dm, separados por el área de protección de la
  UTA.
  - El isologotipo es un marcador, porque la guía no lo reproduce y falta el archivo oficial.
  - El dm es el cuadro de 150 px de Instagram, sin ampliar (asset `/_blob/fc366b8d1d4a6f3b6faea49ec7474232` del lienzo).
- **Recursos (cap. 06):** un solo recurso dm por pantalla, la retícula. La barra tricolor va al pie como firma UTA, que
  la guía pide en el ingreso.
- **Login:** mismos campos y estados que `Login.svelte` y `ErrorAlert.svelte`, con los mensajes reescritos según el
  cap. 10: qué pasó y cómo se arregla.
  - El rojo queda para los errores. El bloqueo por intentos va como advertencia y la verificación de correo como
    información.
  - El botón de mostrar contraseña deja de tener `tabindex=-1`.
  - El formato del RUT se avisa al enviar, no mientras se escribe.
- **Portada:**
  - Se conservan los ocho principios del modelo educativo y el contacto del CITE.
  - Salen los enlaces muertos del pie (`href="#"`: Portal de Estudiantes, Biblioteca Digital, Calendario Académico) y
    la foto desenfocada de fondo. No va foto hasta tener trabajo real de estudiantes con crédito (cap. 07).
- **Trato neutro:** «Ingresa a UTAMED» en vez de «Bienvenido de vuelta».

Decisiones del usuario (2026-10-27):

- Dirección A. El rediseño del ingreso vale para todos los roles; las interfaces internas de docente, jefe y admin
  esperan su propio diseño.
- Título «Bienvenido a mi UTA<sup>med</sup>», con «med» como exponente.
- Un solo error de ingreso, «Credenciales incorrectas», sin distinguir RUT de contraseña.
- Soporte en cite@gestion.uta.cl.
- Las otras pantallas del lote (recuperar y restablecer contraseña, 2FA, confirmar contraseña, verificar correo,
  cambio obligatorio, ajustes y SinRol) quedan en espera en RED-05b.

## Lote 5 · Implementación

Portada (`Welcome.svelte`) y login (`auth/Login.svelte`) en Svelte, tarjeta RED-IMP-05.

- **Piezas compartidas** en `components/ingreso/`: `UtaMed` (el exponente, todo en em, así sirve en los dos tamaños
  de título), `BotonTema` (el mismo ciclo Claro → Oscuro → Sistema de la barra del alumno) y `LogosInstitucionales`.
  La clase `vista-ingreso` toma el foco dm de `guia-dm.css`.
- **Logos:** `public/img/uta-isologotipo.png` y `public/img/dm-logotipo.jpeg` (renombrados para no tener espacios en
  la URL). El isologotipo trae el texto en blanco y sólo se lee sobre noche; por eso el pie de la portada es noche y
  no claro como en el lienzo.
- **Diferencias con el lienzo:**
  - «med» es un poco más grande que en el lienzo (0,55 em en vez de 0,42), como pidió el usuario.
  - La portada móvil sigue la edición del usuario en la de escritorio: sin texto de apoyo ni tarjeta de acceso.
    Para entrar queda el botón «Ingresar» de la cabecera.
  - El RUT se sigue formateando al escribir (el guion se agrega solo), así que el aviso «falta el dígito verificador»
    pasó a «El RUT está incompleto…», que se muestra al enviar.
  - Sale el estado «correo sin verificar»: el backend nunca lo mandaba al login.
- **Backend:**
  - `LoginErrorCode` queda con `INVALID_CREDENTIALS`, `USER_INACTIVE` y `RATE_LIMIT_EXCEEDED`.
  - La cuenta desactivada se revisa después de la contraseña.
  - El throttle del login (`login.store`) vuelve al formulario con los segundos de pausa, en vez de un 429 en modal.
  - Los registros del servidor siguen distinguiendo la causa.
- **Verificación:**
  - `tests/Feature/Auth`: pasan 33 de 34. Falla `RegistrationTest`, que ya fallaba antes de este cambio: la regla
    `uncompromised()` rechaza `Password123!`.
  - svelte-check: los 9 errores de siempre, ninguno nuevo. El build de Vite pasa.
  - Playwright a 1440 y 390 px, en claro y oscuro: contraste AA, texto de 13 px o más y objetivos de 44 px sin
    fallos. También se probaron los estados RUT incompleto, credenciales y pausa por intentos.
- **Riesgo que queda:** un RUT inexistente responde algo más rápido que una contraseña errónea, porque no llega a
  comparar el hash. Para cerrar esa vía habría que comparar contra un hash ficticio.

## Decisiones

- Las direcciones se diferencian por cuánto lenguaje dm llevan (retícula, condensada, noche), no por la paleta: todas
  respetan los roles de color de la guía (noche estructura, azul actúa, amarillo marca lo activo).
- El menú lateral es noche en claro y en oscuro; dentro de él los colores de curso usan su versión oscura.
- La Tarjeta de curso usa sólo datos que existen: código, nombre, docente y la próxima entrega (derivable de
  `proximasAVencer`). Nada de porcentaje de avance mientras el backend no lo calcule.
- La mensajería se entra desde el curso, no desde el menú; el menú del estudiante tiene Inicio, «Mis cursos» y la lista
  de cursos.
