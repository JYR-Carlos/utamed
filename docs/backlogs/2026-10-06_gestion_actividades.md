# Backlog de Trabajo: Modulo de Actividades (UX, Estabilidad y Multi-Curso)

* **Fecha**: 2026-10-06
* **Rama Base**: `main`
* **Tablero Trello**: [arreglos-reunion-23-09-2026](https://trello.com/b/H5mt3Cd8/arreglos-reunion-23-09-2026) (Lista: `Pendiente`)
* **Metodología**: Triada `OBSERVACIONES` > `INTERPRETACION E INVESTIGACION` > `PLANIFICACION`

---

## 1. Observaciones

Lista normalizada a partir de los puntos crudos reportados, contrastada contra la implementacion real del repositorio:

### Modulo Estudiante y Docente: Header y Enunciado de Actividad
* **Elemento / Contexto**: `/estudiante/cursos/{curso}/actividad/{actividad}` (`resources/js/pages/student/Activities/Index.svelte`) y `/docente/cursos/{curso}/actividades/{actividad}/evaluacion` (`resources/js/pages/docente/Activities/Index.svelte`).
* **Problema reportado**: Mover mensaje / accion de enunciado al header de actividad.
* **Evidencia en codigo**: En la vista de estudiante, el acceso al enunciado (`archivo_enunciado`) se ubica desconectado en el panel lateral (`<aside>`), mientras que el encabezado principal (`ActivityHeaderCard.svelte`) solo aloja la capsula de rubrica. En la vista docente, el enunciado se gestiona desde modales aislados o la tabla principal sin presencia directa en la cabecera de la actividad.
* **Nota de correccion**: El usuario reporto "mover mensaje de enunciado al header de actividad"; en el sistema el enunciado no es un mensaje de chat, sino un recurso documental formal asociado (`uuid_archivo_enunciado`) descargable y previsualizable (`Enunciado.svelte`), cuyo trigger debe integrarse ergonomicamente en `ActivityHeaderCard.svelte` para coexistir con la rubrica.

### Sistema de Diseno: Consistencia Cromatica de Estados de Actividad
* **Elemento / Contexto**: Tablero Kanban (`ActividadesPorEstado.svelte`), gestion docente (`Activities/Index.svelte`) y tarjetas de alumno (`actividadEstado.ts`, `ActivityDeadlineCard.svelte`).
* **Problema reportado**: Colores confusos o inconsistentes entre actividades cerradas, activas, etc.
* **Evidencia en codigo**: En `resources/js/pages/docente/Activities/Index.svelte` (linea 555), la funcion local `getEstadoColor` asigna `bg-green-100 text-green-800 border-green-300` a las actividades `CERRADA`, invirtiendo la semantica convencional (verde denota exito o actividad abierta). En contraste, `resources/js/utils/actividadEstado.ts` asigna verde esmeralda a `activa` y pizarra neutro (`#475569`) a `cerrada`. Esta discrepancia genera confusion cognitiva en los docentes al ver una actividad vencida resaltada en verde.

### Modulo Docente: Visualizacion de Avance de Evaluacion
* **Elemento / Contexto**: `/docente/cursos/{id}/actividades` -> Vista Tablero Kanban (`resources/js/pages/docente/components/ActividadesPorEstado.svelte`).
* **Problema reportado**: El como se evalua se ve raro e incompleto; es una progressbar en vez de cards informativos.
* **Evidencia en codigo**: En `ActividadesPorEstado.svelte` (lineas 213-230), el progreso de evaluacion de la actividad se renderiza como una barra de progreso lineal minima (`h-1.5 rounded-full bg-[#002F6C]`) con una linea de texto plano compacta (`{calificados}/{total_grupos} calificadas · prom. {promedio}`). Carece de tarjetas o badges estructurados con KPIs visibles (total grupos, entregas recibidas, pendientes de calificar y nota promedio), impidiendo al docente diagnosticar el estado de la correccion a simple vista.

### Navegacion Global: Breadcrumbs en Mensajeria y Curso
* **Elemento / Contexto**: `/estudiante/cursos/{curso}` (`resources/js/pages/student/Courses/Show.svelte`) frente a `/estudiante/cursos/{curso}/mensajeria` (`resources/js/pages/student/Mensajeria.svelte`).
* **Problema reportado**: Bug al presionar mensajes: el breadcrumb muestra el nombre de grupo (correcto), pero en el curso muestra el nombre de la asignatura (incorrecto).
* **Evidencia en codigo**: En `resources/js/pages/student/Courses/Show.svelte` (linea 82), el breadcrumb fuerza la resolucion `{ title: curso?.asignatura_nombre ?? curso?.nombre ?? 'Curso', href: '' }`, priorizando el nombre de la asignatura institucional sin seccion ni grupo. En contraste, en `Mensajeria.svelte` y los controladores de mensajeria se consume `curso.nombre` (el cual incluye la seccion, ej. "Taller de Diseno Grafico (Seccion 1)") y la pastilla de grupo `letra_grupo`. Adicionalmente, en `Student/ActivityController.php` (linea 233), `'nombre_curso'` se sobreescribe con `$curso->asignacionPlan?->asignatura?->nombre`, propagando la inconsistencia en las rutas hijas.

### Infraestructura y Resiliencia: Carga y Concurrencia en Subida/Descarga
* **Elemento / Contexto**: Endpoints de subida de entrega (`AgendaController::storeEntrega`), enunciado (`DocenteActivityController::subirEnunciado`) y descargas de archivos (`ActivityController::descargarEnunciado`, `descargarEntrega`).
* **Problema reportado**: Analizar si el sistema se cae ante alto flujo de subida y descarga simultanea.
* **Evidencia en codigo**: En `app/Http/Controllers/Student/AgendaController.php` (lineas 135-156), la llamada a `AgendaArchiveHandler::store(...)` se ejecuta dentro de una transaccion de base de datos activa (`DB::beginTransaction()`). Operaciones de I/O de disco lentas bajo concurrencia masiva (ej. entregas al limite del plazo) retienen conexiones abiertas a PostgreSQL, provocando saturacion de conexiones del pool (`connection pool exhaustion`). Asimismo, las descargas se transfieren a traves del proceso PHP-FPM con `response()->file()` en lugar de utilizar mecanismos de aceleracion de servidor web (`X-Accel-Redirect` / `X-Sendfile`), y no existe soporte de subida por bloques (chunking) para archivos pesados.

### Modulo Docente: Actividades Multi-Curso para Misma Asignatura
* **Elemento / Contexto**: Creacion y gestion de actividades en `/docente/cursos/{curso}/actividades` (`DocenteActivityController::store`, `resources/js/pages/docente/Actividades.svelte`, `ActividadForm.svelte`).
* **Problema reportado**: Crear funcion para crear actividades para multiples cursos de la misma asignatura y mismo docente, o copiarlas para evitar doble trabajo.
* **Evidencia en codigo**: `DocenteActivityController::store` crea la actividad vinculada a un unico `id_componente` del curso actual. Cuando un docente dicta multiples secciones de la misma asignatura (mismo `id_asignacion_plan`), debe repetir manualmente la creacion de la actividad en cada curso, reingresando fechas, ponderacion, configuracion, rubrica y enunciado. No existe un flujo de replicacion masiva en el alta ni un mecanismo de clonacion de actividades entre cursos hermanos.

---

## 2. Interpretación e Investigación (La Vista Amplia: Lo que "No Se Vio")

Esta sección analiza el panorama sistémico y arquitectónico que conecta los síntomas aislados observados en la Fase 1. Identifica las causas de fondo, patrones frágiles y deuda técnica acumulada en el ciclo de vida de las actividades. *(Nota de alcance: Las siguientes fallas estructurales explican el origen de los problemas y orientan las soluciones; no representan tarjetas de trabajo adicionales en este sprint).*

### 2.1. Diagnóstico Sistémico y Deuda Técnica

1. **Retención de Conexiones de Base de Datos durante Operaciones de I/O (File Upload Contention)**:
   * En `AgendaController::storeEntrega`, el almacenamiento del archivo (`AgendaArchiveHandler::store`) se invoca dentro de `DB::beginTransaction()`.
   * El servicio `AbstractArchiveService` procesa validaciones, hashing, compresión síncrona y escritura en disco antes de devolver el resultado.
   * En escenarios de alta concurrencia (como el vencimiento de una entrega donde decenas de estudiantes suben archivos pesados en los últimos 15 minutos), cada worker de PHP-FPM mantiene bloqueada una conexión relacional a PostgreSQL mientras espera que termine el I/O del sistema de archivos. Esto agota rápidamente el `max_connections` del servidor de base de datos (`connection pool starvation`), provocando caídas en cascada no por fallas de almacenamiento, sino por saturación relacional.

2. **Inconsistencia de Modelo de Identidad: Asignatura (Catálogo) vs. Curso (Sección Operativa)**:
   * El sistema maneja una jerarquía donde la `Asignatura` es la entidad curricular abstracta y el `Curso` es la instancia académica viva con período, sección, letra de grupo y profesor asignado.
   * En componentes de navegación (`Show.svelte`, `ActivityController.php`), se ha confundido la fuente de verdad al resolver el título del curso mediante `$curso->asignacionPlan?->asignatura?->nombre`, omitiendo la identificación de la sección (`letra_grupo` / `(Sección X)`).
   * Por el contrario, en mensajería se construyó la vista extrayendo `$curso->nombre` y `letra_grupo`. Esta bifurcación es la causante directa de que el usuario perciba que en Mensajería el breadcrumb se comporta correctamente, mientras que en la vista del curso se "pierde" la sección y muestra la asignatura genérica.

3. **Duplicación y Desincronización de Paletas en el Design System (Hardcoded State Styles)**:
   * A pesar de existir `resources/js/utils/actividadEstado.ts` como módulo canónico de derivación de estados (`planificada`, `activa`, `cerrada`), componentes críticos de la vista docente (`Activities/Index.svelte`) reimplementaron funciones locales como `getEstadoColor` con asignaciones cromáticas opuestas (`CERRADA` asignado a verde de éxito).
   * La falta de uso forzado de constantes compartidas genera interfaces discordantes entre la vista del estudiante (donde lo cerrado es gris pizarra inactivo) y la del profesor.

4. **Tratamiento Simplista del Progreso Evaluativo (Falta de Modelado de KPIs Docentes)**:
   * La información de evaluación fue reducida a una barra lineal CSS en el Kanban bajo la suposición de que solo importaba el porcentaje numérico completado.
   * Sin embargo, para la gestión pedagógica real, el docente necesita visualizar de forma atómica: total de grupos/estudiantes asignados, entregas recepcionadas, evaluaciones pendientes de corrección y promedio de notas. Condensar esto en una barra `h-1.5` mutila la utilidad del tablero.

5. **Acoplamiento Rígido de Actividades al Árbol de Componentes de un Único Curso**:
   * Las actividades pertenecen a `curso.componente`, limitando su ciclo de vida al curso donde fueron creadas.
   * La ausencia de un servicio de duplicación y replicación entre cursos hermanos del mismo docente (cursos con el mismo `id_asignacion_plan` en el mismo período lectivo) obliga al usuario a realizar tareas redundantes de digitación y reconfiguración de rúbricas y enunciados.

### 2.2. Resoluciones Arquitectónicas Clarificadas

* **Estrategia de Blindaje ante Alto Flujo de Carga/Descarga**:
  * Desacoplar la escritura física de disco de la transacción de base de datos en `AgendaController`: primero persistir el archivo en disco (`AgendaArchiveHandler::store`), y luego abrir una transacción relacional breve de milisegundos para insertar el registro de agenda con el `uuid_archivo`. Si la transacción de base de datos falla, se gatilla la limpieza física en disco (`cleanupPartialResults`).
  * Para descargas masivas, mantener `BinaryFileResponse` pero asegurar que no se carguen contenidos completos en buffers de memoria PHP (`output_buffering`).
* **Unificación de Breadcrumb de Curso**:
  * Estandarizar el rótulo de curso en todos los breadcrumbs para mostrar el formato completo institucional: `{nombre_asignatura} ({seccion_o_grupo})` o `{curso.nombre}` cuando contenga la sección, asegurando consistencia total entre cursos y mensajería.
* **Componente de Métricas de Evaluación en Kanban**:
  * Sustituir la barra `h-1.5` de `ActividadesPorEstado.svelte` por micro-cards o cápsulas de datos de alta densidad que informen el estado de calificación sin saturar la tarjeta.
* **Flujo de Replicación de Actividades Multi-Curso**:
  * Proveer tanto opción de replicar al crear en `ActividadForm.svelte` (checkbox o selector multi-curso de la misma asignatura) como acción de clonación explícita de actividad existente hacia otros cursos asignados al docente.

---

## 3. Planificación (Releases y Tablero Kanban)

### Release 1: Estabilidad, Resiliencia y Consistencia Visual
*Foco: Desacople transaccional en subida masiva, unificación cromática de estados y consistencia de breadcrumbs.*

```bash
git checkout main
git pull origin main
git checkout -b feature/r1-estabilidad-consistencia-actividades
```

* ### `[BUG-01]` [2 pts] [R1] [P0] Corrección de nombre de curso y sección en breadcrumbs de curso y actividades
  * **Trello**: [#78 BUG-01](https://trello.com/c/tp48Hm2F)
  * **Ruta**: `/estudiante/cursos/{curso}`, `/estudiante/cursos/{curso}/actividad/{actividad}`
  * **Archivos**:
    * `resources/js/pages/student/Courses/Show.svelte`
    * `app/Http/Controllers/Student/ActivityController.php`
    * `resources/js/pages/student/Activities/Index.svelte`
  * **Problema & Causa**: En `Show.svelte` (línea 82), el breadcrumb prioriza `curso?.asignatura_nombre` mostrando el nombre genérico de la asignatura en lugar del curso con su sección/grupo, a diferencia de Mensajería que muestra correctamente la sección y grupo del curso. En `ActivityController.php` (línea 233), `'nombre_curso'` se envía como el nombre de la asignatura, perdiendo la identidad del grupo en las pantallas anidadas.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Mostrar en el breadcrumb de `Show.svelte` el nombre completo del curso con su sección o letra de grupo (`curso.nombre` o `${curso.asignatura_nombre} (Grupo ${curso.letra_grupo})`).
    * [ ] Enviar desde `Student/ActivityController.php` el nombre real del curso en `'nombre_curso'` sin forzar el nombre de la asignatura abstracta.
    * [ ] Mantener consistencia exacta en la miga de pan entre la vista de curso, la vista de actividad y la bandeja de mensajería.

* ### `[UI-01]` [1 pt] [R1] [P0] Unificación cromática semántica para estados de actividad
  * **Trello**: [#79 UI-01](https://trello.com/c/svrNJYRJ)
  * **Ruta**: `/docente/cursos/{curso}/actividades/{actividad}/evaluacion`, `/docente/cursos/{curso}/actividades`
  * **Archivos**:
    * `resources/js/pages/docente/Activities/Index.svelte`
    * `resources/js/pages/docente/Activities/components/GrupoCard.svelte`
    * `resources/js/pages/docente/Activities/components/EstudiantesTabla.svelte`
    * `resources/js/utils/actividadEstado.ts`
  * **Problema & Causa**: `getEstadoColor` en `docente/Activities/Index.svelte` (línea 555) asigna clase de color verde (`bg-green-100 text-green-800 border-green-300`) a actividades y grupos en estado `CERRADA`, invirtiendo el significado semántico respecto a `actividadEstado.ts` donde verde representa `activa` y gris pizarra representa `cerrada`.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Reemplazar la función local `getEstadoColor` por las constantes institucionales centralizadas de `resources/js/utils/actividadEstado.ts` (`PILL_ESTADO`, `PUNTO_ESTADO`).
    * [ ] Mostrar actividades y grupos cerrados en tono pizarra neutro/inactivo (`border-[#CBD5E1] bg-[#F1F5F9] text-[#475569]`).
    * [ ] Mostrar actividades y grupos activos en tono esmeralda (`border-[#A7F3D0] bg-[#ECFDF5] text-[#047857]`).
    * [ ] Mantener consistencia visual idéntica entre la vista de estudiante y la vista de docente.

* ### `[FEAT-01]` [5 pts] [R1] [P0] Blindaje de concurrencia y desacople transaccional en subida y descarga de archivos
  * **Trello**: [#80 FEAT-01](https://trello.com/c/0QOjDYWx)
  * **Ruta**: `/estudiante/grupos-asignados/{grupo}/entregas`, `/estudiante/cursos/{curso}/actividades/{actividad}/enunciado/descargar`, `/estudiante/cursos/{curso}/actividades/{actividad}/entregas/{agenda}/descargar`
  * **Archivos**:
    * `app/Http/Controllers/Student/AgendaController.php`
    * `app/Services/Archive/Handlers/AgendaArchiveHandler.php`
    * `app/Services/Archive/AbstractArchiveService.php`
    * `app/Http/Controllers/Student/ActivityController.php`
    * `app/Http/Controllers/Docente/DocenteActivityController.php`
  * **Problema & Causa**: En `AgendaController::storeEntrega`, `AgendaArchiveHandler::store` (escritura en disco, cálculo de hash SHA-256 y optimización síncrona) se ejecuta dentro de una transacción abierta `DB::beginTransaction()`. En picos de entrega simultánea, múltiples workers de PHP bloquean conexiones a PostgreSQL esperando operaciones lentas de I/O de disco, colapsando el pool de base de datos. Además, las descargas mediante `response()->file()` sin optimización de streaming consumen workers de PHP durante transferencias de red lentas.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Desacoplar la persistencia física en disco fuera de la transacción relacional: persistir primero el archivo con rollback defensivo (`cleanupPartialResults`) si falla la inserción en base de datos.
    * [ ] Abrir la transacción relacional `DB::transaction()` únicamente para las inserciones atómicas en las tablas `agenda.agenda` y `operaciones.archivo`.
    * [ ] Validar que descargas en `ActivityController` desactiven compresión y buffering de salida para evitar el consumo acumulativo de memoria RAM en el servidor.
    * [ ] Registrar logs con métricas de tiempo de ejecución y duración de I/O en cada subida para diagnóstico de concurrencia.

---

### Release 2: Ergonomía de Actividades y Visualización de Evaluación
*Foco: Integración del enunciado en la cabecera principal y sustitución de la progressbar por cards informativos en el Kanban.*

```bash
git checkout main
git pull origin main
git checkout -b feature/r2-ux-header-evaluacion-actividades
```

* ### `[UI-02]` [2 pts] [R2] [P1] Integración de acceso a enunciado en cabecera principal de actividad (ActivityHeaderCard)
  * **Trello**: [#81 UI-02](https://trello.com/c/uk6KME7w)
  * **Ruta**: `/estudiante/cursos/{curso}/actividad/{actividad}`
  * **Archivos**:
    * `resources/js/pages/student/Activities/cards/ActivityHeaderCard.svelte`
    * `resources/js/pages/student/Activities/Index.svelte`
  * **Problema & Causa**: El botón y previsualización del enunciado se ubican en el `<aside>` lateral derecho como un botón suelto, desconectado del contexto descriptivo y de la cápsula de rúbrica en `ActivityHeaderCard`, obligando a navegar verticalmente en dispositivos móviles.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Integrar un control destacado para «Ver Enunciado» / «Descargar Enunciado» directamente en `ActivityHeaderCard.svelte` junto a las etiquetas de tipo y la cápsula de rúbrica.
    * [ ] Mostrar badge con tipo y peso del archivo de enunciado (ej. `PDF · 2,4 MB`).
    * [ ] Permitir abrir el modal de previsualización `Enunciado.svelte` desde el header con un solo clic.
    * [ ] Remover el botón redundante de enunciado en el aside lateral de `student/Activities/Index.svelte`.

* ### `[UI-03]` [3 pts] [R2] [P1] Transformación de progressbar de evaluación a cards informativos de KPIs en Kanban
  * **Trello**: [#82 UI-03](https://trello.com/c/eq6X0o32)
  * **Ruta**: `/docente/cursos/{curso}/actividades` (Tablero Kanban)
  * **Archivos**:
    * `resources/js/pages/docente/components/ActividadesPorEstado.svelte`
    * `resources/js/types/actividad.ts`
  * **Problema & Causa**: En `ActividadesPorEstado.svelte` (líneas 213-230), el estado de avance de evaluación se presenta como una barra de progreso lineal comprimida (`h-1.5`) que no comunica el detalle de entregas ni el estado real de corrección, viéndose incompleta.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Reemplazar la barra de progreso `h-1.5` por un bloque de micro-cards / indicadores informativos estructurados en la base de cada tarjeta del tablero.
    * [ ] Desglosar claramente tres métricas clave:
      * Entregas realizadas vs. total de grupos (ej. `8/10 entregas`).
      * Evaluaciones completadas vs. pendientes (ej. `5 calificadas`, `3 por evaluar`).
      * Promedio de notas visible con formato `formatNota` (ej. `prom. 5.4`).
    * [ ] Asignar alerta visual destacada si existen entregas sin calificar en actividades cerradas.

---

### Release 3: Creación y Replicación Multi-Curso
*Foco: Replicación masiva y clonación de actividades entre cursos hermanos de la misma asignatura y docente titular.*

```bash
git checkout main
git pull origin main
git checkout -b feature/r3-actividades-multi-curso
```

* ### `[FEAT-02]` [5 pts] [R3] [P1] Replicación y clonación de actividades entre múltiples cursos de la misma asignatura
  * **Trello**: [#83 FEAT-02](https://trello.com/c/Ua0glGYx)
  * **Ruta**: `/docente/cursos/{curso}/actividades`
  * **Archivos**:
    * `app/Http/Controllers/Docente/DocenteActivityController.php`
    * `resources/js/pages/docente/Actividades.svelte`
    * `resources/js/modules/resources/actividad/components/actividadForm.svelte`
    * `resources/js/components/docente/ActividadesTabla.svelte`
    * `resources/js/pages/docente/components/ActividadesPorEstado.svelte`
  * **Problema & Causa**: Cuando un docente titular imparte múltiples secciones de la misma asignatura (mismo `id_asignacion_plan`), debe crear la actividad repetitivamente en cada curso de forma manual, reconfigurando formulario, rúbrica y subiendo el enunciado individualmente.
  * **Criterios de Aceptación (DoD)**:
    * [ ] En `ActividadForm.svelte`, detectar automáticamente si el docente dicta otros cursos activos para la misma asignatura y desplegar un selector multi-sección: «Replicar también en: [Curso B, Curso C]».
    * [ ] En el backend (`DocenteActivityController::store`), si se seleccionan cursos adicionales, crear la actividad en cada uno vinculándola al componente homólogo (`id_tipo_componente`) y unidad homóloga.
    * [ ] Reutilizar en los cursos replicados el archivo de enunciado (`uuid_archivo_enunciado`) y duplicar la rúbrica asociada (`Rubrica`) si ya existe.
    * [ ] En actividades individuales, ejecutar `GrupoIndividualService::asegurarGruposDelCurso` en cada curso destino.
    * [ ] Incorporar acción «Clonar a otro curso» en el menú de acciones de `ActividadesTabla.svelte` y `ActividadesPorEstado.svelte` para duplicar actividades ya creadas.

---

## 4. Métricas de Planificación

* **Total de Tarjetas**: 6 tarjetas (1 BUG, 3 UI, 2 FEAT).
* **Distribución por Release**:
  * **Release 1 (Estabilidad, Resiliencia y Consistencia Visual)**: 8 pts (3 tarjetas: 1 BUG, 1 UI, 1 FEAT).
  * **Release 2 (Ergonomía de Actividades y Visualización de Evaluación)**: 5 pts (2 tarjetas: 2 UI).
  * **Release 3 (Creación y Replicación Multi-Curso)**: 5 pts (1 tarjeta: 1 FEAT).
* **Total de Puntos de Historia Estimados**: 18 pts.
