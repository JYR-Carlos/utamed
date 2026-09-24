# Backlog de Trabajo: Portal Estudiante, Seguridad y Agenda

* **Fecha**: 2026-09-24
* **Rama Base**: `main`
* **Metodología**: Triada `OBSERVACIONES` > `INTERPRETACION E INVESTIGACION` > `PLANIFICACION`
* **Calibración**: Escala Fibonacci de 5 niveles (1, 2, 3, 5 y 8 pts)

---

## 1. Observaciones Técnicas y Diagnóstico

A partir de la minuta de reunión y la auditoría empírica sobre el código fuente de UTAMed:

### Autenticación y Cuentas
* **Ruta**: `/settings/profile`, `/settings/password`, `/forgot-password`
* **Diagnóstico**: Existen campos institucionales expuestos a mass-assignment; recuperación de clave no restringía estrictamente a correo `@alumnos.uta.cl`; presencia de componente de auto-eliminación de cuenta (`DeleteUser.svelte`) incompatible con reglas académicas institucionales.

### Dashboard y Tarjetas de Curso (`/estudiante/dashboard`)
* **Diagnóstico**: Redundancia de período semestral en `CourseCard.svelte` que puede ser reemplazado por acceso a mensajería de curso; se renderiza código interno en vez de `cod_asignatura`; presencia de bloque obsoleto «Novedades» y buscador global sin utilidad para el rol estudiante.

### Estructura de Curso y Syllabus (`/estudiante/cursos/{id}`)
* **Diagnóstico**: Duplicidad de botones a «Ver programa»; orden de secciones en `Show.svelte` desarticulado; el syllabus del estudiante resumía contenidos omitiendo el desglose completo que visualiza el docente; ausencia de salida limpia a PDF.

### Detalle de Actividades y Reglas de Entrega (`/estudiante/cursos/{id}/actividades/{act}`)
* **Diagnóstico**: `<section>` duplicado de fecha límite; dispersión de tarjetas de plazo y estado de entrega; bug en `Index.svelte:124` que permitía reemplazo de archivos aún después de calificada la entrega si el plazo seguía abierto.

### Agenda e Interacciones (`Agenda.svelte`)
* **Diagnóstico**: Falta de alineación conversacional (estudiante derecha / docente izquierda); exceso de filtros y colores heterogéneos; doble botón «Ver/Descargar» para archivos adjuntos.

---

## 2. Planificación y Releases Temáticas

### Release 1: Seguridad, Perfil y Cuentas
*Rama sugerida:* `feature/student-auth-security`
*Puntos estimados:* 18 pts

* `[SEC-T16]` [3 pts] [R1] [P0] Autenticación — Implementar cambio de contraseña para estudiante
* `[SEC-T17]` [2 pts] [R1] [P0] Autenticación — Restringir recuperación exclusivamente a correo institucional `@alumnos.uta.cl`
* `[SEC-T52]` [1 pt] [R1] [P0] Cuenta — Eliminar funcionalmente el auto-borrado de cuenta en UI y backend
* `[SEC-T54]` [2 pts] [R1] [P1] Autenticación — Saneamiento trim de contraseñas con verificación en Feature Tests
* `[FEAT-T10]` [3 pts] [R1] [P1] Perfil — Campos de contacto (redes sociales, correo personal y celular) con privacidad
* `[SEC-T12]` [2 pts] [R1] [P1] Perfil — Blindaje mass-assignment de campos institucionales inmutables
* `[FEAT-T14]` [3 pts] [R1] [P1] Perfil — Carga de foto institucional remota (`foto_url`) desde Intranet
* `[UI-T15]` [1 pt] [R1] [P2] Perfil — Redirección a Intranet UTA para actualización de foto de perfil
* `[UI-T18]` [1 pt] [R1] [P2] Navegación — Ocultar buscador global y atajo `Ctrl+K` para perfil estudiante

### Release 2: Dashboard y Navegación del Estudiante
*Rama sugerida:* `feature/student-dashboard-navigation`
*Puntos estimados:* 13 pts

* `[UI-T01]` [1 pt] [R2] [P1] Rúbrica — Mostrar nombre de la actividad en cabecera de creación
* `[FEAT-T02]` [2 pts] [R2] [P1] Dashboard — Sección «Próximas a vencer» con cálculo implícito de fecha límite
* `[FEAT-T04]` [2 pts] [R2] [P1] Dashboard — Generalizar «Notas recientes» a formativas y sumativas
* `[NAV-T05]` [2 pts] [R2] [P1] Dashboard — Apertura directa de agenda mediante parámetro `?abrir=agenda`
* `[UI-T08]` [1 pt] [R2] [P2] Dashboard — Eliminar bloque «Novedades de tus actividades»
* `[UI-T09]` [1 pt] [R2] [P2] Dashboard — Reubicar «Notas recientes» a columna lateral derecha
* `[UI-T19]` [1 pt] [R2] [P2] Dashboard — Mostrar código de asignatura oficial en `CourseCard`
* `[UI-T20]` [2 pts] [R2] [P1] Dashboard — Reemplazar semestre por acceso directo a mensajería de curso
* `[UI-T22]` [1 pt] [R2] [P2] Navegación — Reemplazar etiqueta «Dashboard» por «Inicio» transversalmente

### Release 3: Estructura de Curso, Syllabus y Rendimiento
*Rama sugerida:* `feature/student-course-syllabus-performance`
*Puntos estimados:* 16 pts

* `[UI-T23]` [1 pt] [R3] [P1] Curso — Estandarizar botones y eliminar redundancia de «Ver programa»
* `[UI-T24]` [1 pt] [R3] [P2] Syllabus — Reordenar secciones del programa situando Unidades primero
* `[UI-T26]` [3 pts] [R3] [P1] Curso — Reordenar secciones: Sobre el curso → Próximas entregas → Actividades → Docentes
* `[DOC-T28]` [5 pts] [R3] [P1] Syllabus — Formato institucional completo en cards con botón de impresión PDF
* `[FEAT-T30]` [5 pts] [R3] [P1] Curso — Drawer lateral de «Rendimiento» (promedio ponderado, comparativa de curso y asistencia)
* `[BUG-T53]` [1 pt] [R3] [P0] Datos DM095 — Corregir porcentaje de asistencia obligatoria a 70% en seeder

### Release 4: Detalle de Actividades y Reglas de Entrega
*Rama sugerida:* `feature/student-activity-submissions`
*Puntos estimados:* 10 pts

* `[UI-T33]` [1 pt] [R4] [P1] Actividad — Quitar bloque redundante duplicado de «Fecha de entrega»
* `[UI-T35]` [1 pt] [R4] [P1] Actividad — Integrar botón/badge de rúbrica dentro de `ActivityHeaderCard`
* `[FEAT-T36]` [5 pts] [R4] [P0] Actividad — Tarjeta integral de entrega y reemplazo (fusión de plazo/entrega, banner informativo y blindaje de reemplazo)
* `[UI-T37]` [1 pt] [R4] [P2] Actividad — Apertura interactiva de agenda desde `ActivityAgendaCard`
* `[UI-T43]` [2 pts] [R4] [P1] Agenda / Archivos — Acción única inteligente de descarga/apertura en nueva pestaña según MIME

### Release 5: Agenda del Estudiante y Auditoría E2E
*Rama sugerida:* `feature/student-agenda-e2e`
*Puntos estimados:* 16 pts

* `[FEAT-T07]` [3 pts] [R5] [P1] Agenda — Confirmación de lectura («Visto») en interacciones de la agenda
* `[UI-T42]` [3 pts] [R5] [P1] Agenda — Rediseño integral de interfaz de chat (alineación profesor/alumno, cabecera unificada y sin filtros)
* `[FEAT-T41]` [5 pts] [R5] [P2] Agenda — Compilar interacciones de todas las actividades como Bitácora del Curso
* `[DOC-T03]` [5 pts] [R5] [P0] Auditoría — Inspección exhaustiva E2E del flujo completo del estudiante

---

## 3. Backlog Institucional / Por Definir

* `[FEAT-T48]` [5 pts] [R-Post] [P3] Agenda — Diseñar e implementar flujo de apelación de entregas (pendiente de formalización de reglamento institucional)
* `[FEAT-T50]` [8 pts] [R-Post] [P3] Mensajería — Reorientación y rediseño del sistema de mensajería (canales por destinatario y saneamiento de tablas heredadas)

---

## 4. Métricas de Planificación del Tablero

* **Tarjetas Activas en Tablero**: 52 tarjetas
  * *Portal Estudiante / Reunión 23-09*: 35 tarjetas (86 pts)
  * *Portal Docente y Admin / Backlog 21-09*: 17 tarjetas (34 pts)
* **Total Puntos de Historia Estimados (Fibonacci)**: **120 pts**
* **Distribución por Nivel de Esfuerzo**:
  * `1 pt`: 18 tarjetas (18 pts)
  * `2 pts`: 17 tarjetas (34 pts)
  * `3 pts`: 9 tarjetas (27 pts)
  * `5 pts`: 7 tarjetas (35 pts)
  * `8 pts`: 1 tarjeta (8 pts)
