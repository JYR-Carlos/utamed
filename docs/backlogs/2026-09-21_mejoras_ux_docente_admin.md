# Backlog de Trabajo: Mejoras UX y Estabilidad Docente / Admin

* **Fecha**: 2026-09-21
* **Rama Base**: `produccion`
* **Metodología**: Triada `OBSERVACIONES` > `INTERPRETACION E INVESTIGACION` > `PLANIFICACION`

---

## 1. Observaciones

Lista normalizada a partir de los puntos crudos reportados:

### Módulo Admin: Cursos Ofertados
* **Elemento / Contexto**: `/admin/cursos` -> SlideOver «Gestionar curso > Componentes».
* **Problema reportado**: Las componentes de un curso no aparecen ordenadas según la jerarquía institucional CTL.

### Módulo Docente: Programa / Syllabus
* **Elemento / Contexto**: `/docente/cursos/{id}/programa` -> Botón «Imprimir».
* **Problema reportado**: El botón de imprimir programa no funciona / no genera un documento legible.

### Navegación Global: Slide-Overs
* **Elemento / Contexto**: Menús laterales y paneles tipo SlideOver (ej. Gestionar curso en `/admin/cursos`).
* **Problema reportado**: Al abrir un SlideOver, presionar el botón «Atrás» del navegador abandona la página completa en lugar de volver a la pestaña anterior o cerrar el panel.

### Autenticación Global
* **Elemento / Contexto**: Menú de perfil de usuario -> Botón «Cerrar Sesión».
* **Problema reportado**: El botón requiere dos clics para ejecutar el cierre de sesión.

### Módulo Docente: Vista Detalle de Curso (`/docente/cursos/{id}`)
* **Elemento / Contexto**: Card superior «Componentes».
  * **Problema reportado**: Componentes no respetan el orden jerárquico CTL.
* **Elemento / Contexto**: Card superior «Docentes».
  * **Problema reportado**: Muestra desglose innecesario en cursos con docente único; requiere condicional (if-else).
* **Elemento / Contexto**: Sección «Todos los componentes».
  * **Problema reportado**: Subtítulo redundante que debe eliminarse.
* **Elemento / Contexto**: Botón «Programa».
  * **Problema reportado**: Parece texto plano/fantasma en vez de botón interactivo; debe tener fondo resaltado.
* **Elemento / Contexto**: Pestaña «Mi Grupo» -> Columna de acciones.
  * **Problema reportado**: El botón «Ficha» no parece botón interactivo.
* **Elemento / Contexto**: Ficha del estudiante -> Pestaña «Mensajes».
  * **Problema reportado**: Los mensajes no cargan, el spinner se congela y la aplicación dispara peticiones XHR en bucle infinito al servidor. *(Nota de corrección técnica: El usuario mencionó "fecha del estudiante", corrigiéndose a "ficha del estudiante" en `EstudianteDetalleModal.svelte`)*.

### Módulo Docente: Catálogo de Cursos (`/docente/cursos`)
* **Elemento / Contexto**: Tarjetas de cursos asignados.
  * **Problema reportado**: Falta la badge con el grupo (a replicar desde «Cursos que dirijo» en el dashboard) y el código formal de la asignatura (ej. DM095).

### Navegación: Sidebar Docente
* **Elemento / Contexto**: Barra lateral de navegación para rol docente.
  * **Problema reportado**: Períodos y cursos se muestran planos sin opción de colapso.

### Módulo Docente: Mensajería de Curso (`/docente/cursos/{id}/mensajeria`)
* **Elemento / Contexto**: Barra de búsqueda superior al alternar filtros.
  * **Problema reportado**: Al cambiar de componente o alumno, el spinner de carga salta en el flex y desplaza horizontalmente el cuadro de búsqueda (Cumulative Layout Shift).

### Módulo Docente: Evaluación de Actividades (`/docente/cursos/{id}/actividades/{actividad}/evaluacion`)
* **Elemento / Contexto**: Listado de estudiantes asignados individuales.
  * **Problema reportado**: Ocupan demasiado espacio vertical con textos y botones sobredimensionados.
* **Elemento / Contexto**: Parámetro «Holgura».
  * **Problema reportado**: El término no es intuitivo y parece etiqueta estática. Debe renombrarse a «Plazo adicional» y evidenciar que es editable.
* **Elemento / Contexto**: Reevaluación de entregas individuales/grupales.
  * **Problema reportado**: Al crear una segunda evaluación cuando ya existe una previa, la tarjeta en pantalla no actualiza la nota hasta hacer F5 manual. Además, las décimas anteriores no se resetean.
* **Elemento / Contexto**: Modal de agenda / entregas.
  * **Problema reportado**: Clic afuera (backdrop) no cierra la modal.
* **Elemento / Contexto**: Selector de «Entrega a evaluar» en agenda.
  * **Problema reportado**: Los archivos muestran rutas hash/UUID internas incomprensibles en vez del nombre original del archivo.
* **Elemento / Contexto**: Transición «Ver Entregas» > «Evaluar con Rúbrica» y retorno.
  * **Problema reportado**: El scroll vertical se resetea al tope de la página al volver.
* **Elemento / Contexto**: Visualizador de rúbrica bloqueada.
  * **Problema reportado**: Cuando la rúbrica ya tiene evaluaciones previas, el botón «Editor» sigue habilitado y debe bloquearse.

---

## 2. Interpretación e Investigación (La Vista Amplia: Lo que "No Se Vio")

Esta sección analiza el cuadro general que no es evidente al mirar los síntomas de forma aislada. Examina las causas sistémicas y de diseño que conectan y alimentan los defectos observados. *(Nota de alcance: Las siguientes fallas estructurales explican el origen del problema; no representan tarjetas de trabajo adicionales en este sprint).*

### 2.1. Diagnóstico Sistémico y Deuda Técnica
1. **Inconsistencia de Affordance en el Design System**: La falta de una convención uniforme para acciones secundarias, botones con variantes `ghost` y badges interactivos hace que elementos críticos (`Programa`, `Ficha`, `Plazo adicional`) se perciban erróneamente como texto estático o etiquetas informativas.
2. **Fragilidad en la Gestión de Ciclo de Vida Reactivo (Svelte 5 / Inertia)**: Se evidencian dos patrones defectuosos:
   * Dependencias de `$effect` que asumen que una respuesta de red vacía (`length === 0`) equivale a un estado no inicializado, generando bucles de recarga infinitos.
   * Mutaciones POST de Inertia que no actualizan el estado local de colecciones ni resetean estados temporales (persistencia residual de décimas y notas no propagadas en reevaluaciones).
3. **Desacople entre Modales/Overlays y la API de Historial del Navegador**: El uso de modales y Slide-Overs desconectados de la pila de historial (`hash`/`popstate`) quiebra la expectativa natural de navegación web: presionar el botón «Atrás» expulsa al usuario de la vista en lugar de cerrar el panel activo.
4. **Fuga de la Capa de Persistencia hacia la Presentación**: Carencia de transformadores/DTOs dedicados antes de enviar datos al cliente, exponiendo nombres internos de almacenamiento (UUIDs) y desatendiendo reglas de negocio institucionales (jerarquía CTL) en las consultas directas.

### 2.2. Resoluciones de Diseño Clarificadas
* **Estrategia de Salida del Programa**: Se opta por documento estandarizado mediante `@media print` nativo para evitar la sobrecarga y latencia de compilación PDF en el backend.
* **Control de Densidad en KPIs de Curso**: Ocultamiento defensivo de la card de docentes ante docente único para evitar métricas superfluas.
* **Manejo de Densidad en Navegación**: Adopción de acordeones colapsables para períodos históricos en el sidebar docente para evitar el colapso por scroll.

---

## 3. Planificación (Releases y Tablero Kanban)

### Release 1: Estabilidad Crítica y Flujos Rotos
*Foco: Bugs bloquantes, bucles de red, reactividad de notas y prevención de CLS.*

```bash
git checkout produccion
git pull origin produccion
git checkout -b feature/r1-estabilidad-critica
```

* ### `[BUG-01]` [2 pts] [R1] [P0] Loop infinito de peticiones XHR en Ficha del Estudiante
  * **Ruta**: `/docente/cursos/{id}`
  * **Archivos**: `resources/js/pages/docente/components/EstudianteDetalleModal.svelte`
  * **Problema & Causa**: `$effect` verifica `mensajesEstudiante.length === 0` indefinidamente en respuestas vacías, ejecutando `router.reload()` en bucle.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Reemplazar la validación por una bandera booleana de carga ejecutada (`hasLoadedMensajes`).
    * [ ] Detener el ciclo tras el primer `200 OK` aun cuando la lista retorne vacía.
    * [ ] Eliminar el parpadeo del spinner al estabilizar la carga.

* ### `[BUG-02]` [1 pt] [R1] [P0] Cierre de sesión ejecutado en un solo clic
  * **Ruta**: Global (Menú de usuario)
  * **Archivos**: `resources/js/components/custom/common/UserMenuContent.svelte`
  * **Problema & Causa**: Conflicto de eventos entre el contenedor `<DropdownMenuItem>` y `<Link method="post" as="button">`.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Despachar el logout directamente en el evento `onSelect` o manejador `router.post('/logout')`.
    * [ ] Cerrar sesión y redirigir a `/login` al primer clic.

* ### `[BUG-03]` [3 pts] [R1] [P0] Reactividad en reevaluación y reseteo de formulario
  * **Ruta**: `/docente/cursos/{id}/actividades/{actividad}/evaluacion?grupo_id={g}`
  * **Archivos**:
    * `resources/js/pages/docente/Activities/Index.svelte`
    * `resources/js/pages/docente/Activities/components/GrupoCard.svelte`
  * **Problema & Causa**: Al recalificar una entrega, la tarjeta en pantalla conserva la nota vieja hasta hacer F5 manual y el input de décimas no se limpia.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Actualizar reactivamente la nota y estado en el array local `grupos` tras la respuesta de éxito.
    * [ ] Resetear el campo de décimas adicionales a 0 al abrir una reevaluación.
    * [ ] Mantener el scroll vertical sin saltos al confirmar la calificación.

* ### `[BUG-04]` [1 pt] [R1] [P1] Prevención de Cumulative Layout Shift (CLS) en barra de búsqueda
  * **Ruta**: `/docente/cursos/{id}/mensajeria`
  * **Archivos**: `resources/js/components/mensajeria/BandejaStaff.svelte`
  * **Problema & Causa**: El icono `Loader2` entra dinámicamente al flex de la cabecera, empujando el cuadro de búsqueda horizontalmente.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Integrar el spinner dentro del input a la derecha (`absolute`) o reservar un ancho fijo.
    * [ ] Eliminar el desplazamiento del buscador al alternar filtros.

---

### Release 2: Consistencia de UI, Affordance y Dominio
*Foco: Lenguaje de negocio institucional, jerarquía visual y eliminación de fricciones en cards.*

```bash
git checkout produccion
git pull origin produccion
git checkout -b feature/r2-consistencia-ui-dominio
```

* ### `[FEAT-01]` [2 pts] [R2] [P1] Orden jerárquico CTL en lista y tarjetas de componentes
  * **Ruta**: `/admin/cursos` y `/docente/cursos/{id}`
  * **Archivos**:
    * `resources/js/modules/resources/curso/components/cursoSlideOver.svelte`
    * `resources/js/pages/docente/CursoDetalle.svelte`
    * `app/Models/Curso/TipoComponente.php`
  * **Problema & Causa**: Componentes iterados en orden crudo de inserción en base de datos.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Ordenar la tabla del SlideOver admin siguiendo prioridad CTL (`Cátedra = 1, Taller = 2, Laboratorio = 3`).
    * [ ] Aplicar la misma ordenación en la card superior y en la grilla «Todos los componentes» en la vista docente.

* ### `[UI-01]` [2 pts] [R2] [P1] Affordance y jerarquía de botones «Programa» y «Ficha»
  * **Ruta**: `/docente/cursos/{id}`
  * **Archivos**:
    * `resources/js/pages/docente/CursoDetalle.svelte`
    * `resources/js/pages/docente/components/EstudiantesTable.svelte`
  * **Problema & Causa**: El botón «Programa» usa variante `ghost` y «Ficha» se dibuja como texto plano sin delimitador de botón.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Transformar «Programa» en un botón primario destacado con fondo de color institucional.
    * [ ] Convertir «Ficha» en un botón interactivo estructurado con estados hover claros.

* ### `[UI-02]` [2 pts] [R2] [P1] Clarificación y estilo editable de «Plazo adicional»
  * **Ruta**: `/docente/cursos/{id}/actividades/{actividad}/evaluacion`
  * **Archivos**: `resources/js/pages/docente/Activities/components/GrupoCard.svelte`
  * **Problema & Causa**: El término «Holgura» confunde y no evidencia ser un control interactivo.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Renombrar la etiqueta a «Plazo adicional» (o «Días extra»).
    * [ ] Estilizar el valor como badge interactivo / botón con icono de edición.

* ### `[UI-03]` [2 pts] [R2] [P2] Optimización de densidad visual en evaluaciones individuales
  * **Ruta**: `/docente/cursos/{id}/actividades/{actividad}/evaluacion`
  * **Archivos**:
    * `resources/js/pages/docente/Activities/Index.svelte`
    * `resources/js/pages/docente/Activities/components/GrupoCard.svelte`
  * **Problema & Causa**: Tarjetas individuales con márgenes y textos sobredimensionados que requieren scroll vertical excesivo.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Reducir padding vertical de las cards individuales y ajustar tipografía a escala compacta.
    * [ ] Ajustar botones de acción al tamaño estándar (`sm`).

* ### `[UI-04]` [1 pt] [R2] [P2] Limpieza visual de métricas y copy en vista detalle de curso
  * **Ruta**: `/docente/cursos/{id}`
  * **Archivos**: `resources/js/pages/docente/CursoDetalle.svelte`
  * **Problema & Causa**: Card «Docentes» muestra métricas innecesarias para cursos con 1 docente, y «Todos los componentes» tiene un subtítulo redundante.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Ocultar completamente la tarjeta «Docentes» si el curso tiene solo 1 docente asignado.
    * [ ] Remover el subtítulo explicativo *«Sólo el titular ve el curso completo y quién responde por cada componente.»*.

* ### `[FEAT-02]` [2 pts] [R2] [P2] Badge de grupo y código de asignatura en catálogo docente
  * **Ruta**: `/docente/cursos`
  * **Archivos**: `resources/js/pages/docente/Cursos.svelte`
  * **Problema & Causa**: Tarjetas del catálogo principal no muestran la letra del grupo destacada ni el código oficial de la asignatura.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Replicar la badge cuadrada de grupo de `CursoTitularCard.svelte` en la esquina de cada tarjeta.
    * [ ] Exhibir el código de la asignatura (ej. `DM095`) en fuente monoespaciada junto al nombre.

* ### `[FEAT-03]` [2 pts] [R2] [P2] Nombre legible de archivos en «Entrega a evaluar»
  * **Ruta**: `/docente/cursos/{id}/actividades/{actividad}/evaluacion?grupo_id={g}`
  * **Archivos**:
    * `app/Http/Controllers/Docente/DocenteActivityController.php`
    * `app/Services/Docente/ConversacionDocenteService.php`
    * `resources/js/pages/docente/Activities/Index.svelte`
  * **Problema & Causa**: El selector de entregas muestra identificadores UUID crudos de storage en vez del nombre original del archivo.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Enviar al frontend el nombre original del archivo (`nombre_original`) subido por el alumno.
    * [ ] Presentar nombres comprensibles en el menú desplegable de selección de entregas.

---

### Release 3: Navegación, Historial y Salida Impresa
*Foco: Experiencia de navegación avanzada, atajos de teclado/historial y reportes oficiales.*

```bash
git checkout produccion
git pull origin produccion
git checkout -b feature/r3-navegacion-impresion
```

* ### `[NAV-01]` [3 pts] [R3] [P1] Sincronización de Slide-Overs con historial del navegador
  * **Ruta**: `/admin/cursos`
  * **Archivos**: `resources/js/modules/resources/curso/components/cursoSlideOver.svelte`
  * **Problema & Causa**: Presionar el botón «Atrás» abandona la pantalla completa en vez de cerrar el panel lateral.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Sincronizar apertura y pestañas mediante hash de URL (ej. `#secciones`, `#equipo`).
    * [ ] Escuchar `popstate` / `hashchange` para que retroceder en el historial cierre el panel limpiamente.

* ### `[NAV-02]` [3 pts] [R3] [P1] Acordeón colapsable para períodos y cursos en Sidebar Docente
  * **Ruta**: Global (Menú lateral docente)
  * **Archivos**: `resources/js/components/custom/navigation/CourseListNav.svelte`
  * **Problema & Causa**: Períodos históricos y cursos listados de forma plana y continua, saturando el scroll.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Período actual expandido por defecto con toggle para colapsar su lista de cursos.
    * [ ] Períodos anteriores agrupados en acordeones colapsados por defecto.
    * [ ] Recordar el estado de apertura en sesión o `localStorage`.

* ### `[UI-05]` [1 pt] [R3] [P2] Cierre de modal de agenda por clic en fondo (Backdrop)
  * **Ruta**: `/docente/cursos/{id}/actividades/{actividad}/evaluacion`
  * **Archivos**: `resources/js/pages/docente/Activities/Index.svelte`
  * **Problema & Causa**: El overlay oscuro no captura el clic exterior para cerrar el modal.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Agregar manejador `onclick` y `onkeydown` (Escape/Enter) al backdrop para cerrarlo.

* ### `[UI-06]` [2 pts] [R3] [P2] Preservación de scroll al retornar de «Evaluar con Rúbrica»
  * **Ruta**: `/docente/cursos/{id}/actividades/{actividad}/evaluacion`
  * **Archivos**: `resources/js/pages/docente/Activities/Index.svelte`
  * **Problema & Causa**: Al regresar de calificar con rúbrica, la vista salta a la parte superior (`scrollY = 0`).
  * **Criterios de Aceptación (DoD)**:
    * [ ] Configurar `preserveScroll: true` en el retorno o restaurar manualmente la posición de scroll previa.

* ### `[SEC-01]` [1 pt] [R3] [P2] Bloqueo defensivo del botón «Editor» en rúbricas evaluadas
  * **Ruta**: `/docente/cursos/{id}/actividades/{actividad}/evaluacion`
  * **Archivos**: `resources/js/pages/docente/Activities/Index.svelte`
  * **Problema & Causa**: El botón «Editor» permanece activo aun cuando la rúbrica ya tiene evaluaciones previas registradas.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Deshabilitar o esconder el botón «Editor» cuando `puede_editar_rubrica` sea falso.
    * [ ] Agregar tooltip informativo: *«No se puede editar una rúbrica con evaluaciones ya registradas»*.

* ### `[DOC-01]` [3 pts] [R3] [P2] Estilos de impresión académica para el Programa de Curso
  * **Ruta**: `/docente/cursos/{id}/programa`
  * **Archivos**: `resources/js/pages/docente/Programa.svelte`
  * **Problema & Causa**: `window.print()` imprime la pantalla con sidebars, cabeceras web, botones y cortes de página defectuosos.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Definir bloque `@media print` para ocultar barra lateral, botones de acción e historial.
    * [ ] Formatear tipografía, márgenes y tablas para que paginen limpiamente como documento académico formal.

---

### Métricas del Tablero
* **Total de Tarjetas**: 16
* **Release 1 (Estabilidad Crítica)**: 4 tareas · **7 pts**
* **Release 2 (Consistencia y Dominio)**: 6 tareas · **11 pts**
* **Release 3 (Navegación e Impresión)**: 6 tareas · **13 pts**
* **Esfuerzo Total Estimado**: **31 pts**
