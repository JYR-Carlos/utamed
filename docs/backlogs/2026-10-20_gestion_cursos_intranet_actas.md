# Backlog de Trabajo: Gestion Academica, Intranet, Cursos y Actas

* **Fecha**: 2026-10-20
* **Rama Base**: `fix/arreglos-administrador` (Worktree de referencia: `main`)
* **Metodologia**: Triada `OBSERVACIONES` > `INTERPRETACION E INVESTIGACION` > `PLANIFICACION`

---

## 1. Observaciones

Lista estructurada a partir de la inspeccion empirica directa del codigo fuente y contraste con los puntos reportados:

### Modulo Calendario: Calendario General Unificado
* **Elemento / Contexto**: Navegacion global de estudiante (`resources/js/components/custom/layout/RoleSidebar.svelte`) y rutas web.
* **Problema reportado**: Solo existe calendario multi-curso para el rol docente (`/docente/calendario`). Los estudiantes unicamente disponen de una pestaña de calendario confinada a la vista individual de un curso (`resources/js/pages/student/Courses/components/CursoCalendario.svelte`). Falta un calendario general transversal (estilo LMS institucional / eLearn) que agrupe entregas, evaluaciones y sesiones de todas las asignaturas inscritas del estudiante en una sola linea de tiempo.
* **Evidencia en codigo**:
  - `app/Http/Controllers/Docente/CalendarioController.php`: Implementa la agregacion multi-curso unicamente para docentes filtrando por `id_docente_titular` o componentes asignados.
  - `routes/web.php`: No existe ruta `/estudiante/calendario`.
  - `RoleSidebar.svelte:336-350`: La seccion de estudiante no cuenta con enlace a calendario general, a diferencia del bloque docente.

### Modulo Actas e Intranet: Reserva de Numero Especial y Persistencia Tripartita
* **Elemento / Contexto**: Cierre academico, sincronizacion de calificaciones y generacion de actas oficiales hacia Oracle.
* **Problema reportado**: Al generar un acta oficial, se debe reservar un numero especial correlativo de acta, asociar las llaves de Intranet (`INS_ID` del alumno y `CUR_CODIGO` de la componente), y estructurar el acta en tres tablas formales (cabecera, cuerpo y auditoria).
* **Evidencia en codigo**:
  - `app/Services/IntranetService.php`: Mapea `INS_ID` (`cod_inscripcion_curso_uta`) y `cur_codigo`, pero actualmente solo gestiona la lectura de inscripciones entrantes.
  - `app/Models/Curso/Curso.php`: Posee la columna `estado_acta` (`NO_ENVIADO`), y `curso.componente` posee `genera_acta`, pero no existe el modelo ni el flujo transaccional para reservar el folio oficial ni persistir las tres tablas (cabecera del acta, lineas/cuerpo con notas por alumno, y registro inmutable de auditoria).
* **Nota de correccion tecnica**: El usuario menciono "juntar los ins id y curcodigo"; en el modelo relacional institucional, `CUR_CODIGO` identifica el acta/seccion en Oracle y `INS_ID` identifica la inscripcion individual del alumno en dicha acta.

### Modulo Admin: Inscripcion Automatica, Extraccion de Correos, Cohorte y Limpieza de UI
* **Elemento / Contexto**: `/admin/inscripciones_cursos` (`resources/js/modules/resources/inscripcion/components/rosterTable.svelte`) y `App\Services\EstudianteService`.
* **Problema reportado**:
  1. La ejecucion de alumnos automaticos desde la administracion debe operar de forma robusta.
  2. Los correos electronicos de los estudiantes no se guardan en la creacion desde Intranet (`email => null`), quedando el usuario sin casilla oficial hasta que se acceda a la vista de Oracle gestionada por soporte (Victor).
  3. El año de ingreso (`agno_ingreso`) se calcula por defecto con el año actual (`now()->year`), corrompiendo la cohorte real del alumno.
  4. En la tabla de alumnos (`rosterTable.svelte`), la columna "Usuario" muestra un identificador redundante (cuerpo del RUT sin DV) que sobrecarga visualmente la tabla sin utilidad operativa.
* **Evidencia en codigo**:
  - `app/Models/Usuario/Estudiante.php:135`: `Usuario::firstOrCreate` fija `'email' => null`.
  - `app/Models/Usuario/Estudiante.php:146`: Fija `'agno_ingreso' => $agnoIngreso ?? (int)now()->year`.
  - `app/Models/External/VwAlumno.php`: Mapea la tabla `ALUMNO` de Oracle que carece de columna de correo institucional (se requiere acceso a la vista provista por soporte institucional).
  - `rosterTable.svelte:108`: La columna `{ key: 'usuario', label: 'Usuario' }` renderiza `{studentUsername(item)}`.

### Sistema de Diseño: Modo Oscuro (Dark Theme)
* **Elemento / Contexto**: `resources/css/app.css` y `resources/js/hooks/useAppearance.svelte.ts`.
* **Problema reportado**: El soporte de modo oscuro no funciona o fue forzado a apariencia clara.
* **Evidencia en codigo**:
  - `resources/css/app.css:229-240`: Existe la regla explicita:
    ```css
    /* Force light theme for now to avoid system dark mode black backgrounds as per user preference */
    .dark {
        --background: hsl(210 33% 97%);
        --foreground: hsl(220 30% 10%);
        --card: hsl(0 0% 100%);
        --card-foreground: hsl(220 30% 10%);
        ...
    }
    ```
    Las variables de CSS en clase `.dark` sobreescriben los tonos oscuros con valores identicos al tema claro, anulando la reactividad de `useAppearance.svelte.ts` y la preferencia del sistema.

### Modulo Inscripciones: Inscripcion Temporal
* **Elemento / Contexto**: `app/Models/Curso/InscripcionCurso.php` y `resources/js/modules/resources/inscripcion/types/inscripcion.types.ts`.
* **Problema reportado**: Se requiere soportar la inscripcion temporal de estudiantes que asisten y rinden actividades antes de que su situacion contractual o de convalidacion quede oficializada en Intranet.
* **Evidencia en codigo**:
  - `app/Http/Requests/StoreInscripcionCursoRequest.php` y `UpdateInscripcionCursoRequest.php`: Restringen `estado_inscripcion` a `['INSCRITO', 'RETIRADO', 'SUSPENDIDO', 'APROBADO', 'REPROBADO']`.
  - `InscripcionCursoController.php`: Valida `['INSCRITO', 'RETIRADO', 'ANULADO', 'SUSPENDIDO']`.
  - Falta incorporar formalmente el estado o indicador `TEMPORAL` en la logica de autorizacion de evaluacion, bitacora y asistencia.

### Seguridad y Permisos: Visibilidad Restringida de Codigos Intranet (INS_ID y COD_CURSO)
* **Elemento / Contexto**: Vistas de nominas de estudiantes y listas de componentes en perfiles docente y administrativo.
* **Problema reportado**: Los codigos tecnicos de Intranet (`INS_ID` y `cod_curso` / `cur_codigo`) deben ser visibles exclusivamente para el rol Administrador. Docentes y alumnos no deben visualizarlos en sus tablas.
* **Evidencia en codigo**:
  - `resources/js/modules/resources/inscripcion/components/rosterTable.svelte`: Expone codigos tecnicos de inscripcion y curso.
  - `resources/js/modules/resources/curso/components/cursoSlideOver.svelte`: Lista componentes y codigos sin verificacion defensiva de permisos por rol (`isAdmin`).

### Modulo Cursos: Visualizacion de Grupo como Badge
* **Elemento / Contexto**: Tablas administrativas (`cursoListAdmin.svelte`, `cursoSelector.svelte`, `rosterTable.svelte`).
* **Problema reportado**: El grupo de la asignatura nunca se ha renderizado como un badge distintivo en las tablas principales; el nombre del curso a menudo contiene el grupo incrustado como texto plano al final ("GRUPO A").
* **Evidencia en codigo**:
  - `cursoListAdmin.svelte:119-125`: Renderiza unicamente `{item.nombre || ...}` y omite `{item.letra_grupo}`.
  - `CourseListNav.svelte:4`: Ya implementa una expresion regular defensiva `base.replace(new RegExp('\\s+Grupo\\s+${curso.letra_grupo}$'), '')` para limpiar el nombre y renderizar el chip, patron que no fue replicado en las tablas de administracion.

### Modulo Cursos: Copia Integral de Curso y Desacople de Modal
* **Elemento / Contexto**: `app/Services/CursoService.php:151` y `resources/js/modules/resources/curso/components/cursoCopyPreviewModal.svelte`.
* **Problema reportado**:
  1. Al duplicar un curso, el servicio actual omite la copia del programa (syllabus) y de las rubricas asociadas a las actividades evaluativas.
  2. Debe excluirse terminantemente la copia de alumnos inscritos, registros de agenda y fechas limite concretas.
  3. El modal de previsualizacion exige ingresar manualmente el `cod_curso` (tipo number), cuando este debe calcularse automaticamente o desacoplarse.
  4. Se requiere incorporar un lookup/buscador por codigo formal de asignatura (`cod_asignatura`, ej. DM095) para localizar cursos fuente rapidamente.
* **Evidencia en codigo**:
  - `CursoService.php:151-246`: Clona `unidades`, `componentes`, `docenteComponentes` y `actividades`, pero omite `rubricas` y `programas` (syllabus vigente).
  - `cursoCopyPreviewModal.svelte:18`: Contiene el campo `<input id="new-cod-curso" type="number" required>`.
  - `docs/backlogs/2026-10-09_copia_curso_codigo_y_modal.md`: Documenta el requerimiento pendiente de autogenerar `cod_curso` mediante `siguienteCodCurso()`.

### Modulo Cursos: Gestion de Docente Titular (Asignacion y Desasignacion)
* **Elemento / Contexto**: `app/Services/CursoService.php:268` (`update`) y `resources/js/modules/resources/curso/components/cursoSlideOver.svelte`.
* **Problema reportado**: Es imposible desasignar al docente titular de un curso; cuando se intenta desvincular o dejar vacio, el backend ignora el cambio. Ademas, no existe una accion directa y comoda en el slide-over para gestionar la asignacion/desasignacion del profesor.
* **Evidencia en codigo**:
  - `CursoService.php:292-295`:
    ```php
    $newDocenteTitularId = null;
    if (!empty($data['id_docente_sugerido'])) {
        $newDocenteTitularId = $data['id_docente_sugerido'];
        $updateData['id_docente_titular'] = $newDocenteTitularId;
    }
    ```
    Si el cliente envia `id_docente_sugerido` nulo o vacio para desasignar al profesor, la condicion `!empty()` omite la asignacion a `$updateData`, manteniendo inmutable el profesor anterior en la base de datos.
  - `courseTeamModal.svelte:102`: Tiene hardcodeado `role_name: 'ayudante'`, impidiendo asignar docentes titulares o asociados.

### Modulo Syllabus: Inmutabilidad Definitiva tras Aprobacion
* **Elemento / Contexto**: `app/Http/Controllers/Admin/ProgramaController.php`, `Administrativo/ProgramaController.php` y `Docente/JefeCarreraController.php`.
* **Problema reportado**: La regla institucional exige que una vez que el syllabus es aprobado (`APROBADO`), queda congelado permanentemente y no puede volver a editarse ni rechazarse bajo ninguna circunstancia. Actualmente, los controladores permiten rechazar y editar programas ya aprobados.
* **Evidencia en codigo**:
  - `Admin/ProgramaController.php:236-237`: "A diferencia del docente, aqui si se puede editar un programa APROBADO: guardar crea una version nueva."
  - `Admin/ProgramaController.php:741`: `$estadosPermitidos = ['COMPLETO', 'APROBADO', 'BASICO_COMPLETO']` permite rechazar programas en estado `APROBADO` devolviendolos a `BORRADOR`.
  - `Docente/JefeCarreraController.php:535`: Permite que un Jefe de Carrera devuelva a revision un programa `APROBADO`.


---

## 2. Interpretacion e Investigacion (La Vista Amplia: Lo que "No Se Vio")

Esta seccion analiza el panorama general que conecta y explica los sintomas individuales descritos en la Fase 1. Identifica las fallas de arquitectura, patrones defectuosos y deuda tecnica de fondo. *(Nota de alcance estricta: Las siguientes fallas estructurales se documentan con fines explicativos y de contexto; no constituyen tarjetas adicionales ni amplian el alcance del sprint).*

### 2.1. Diagnostico Sistemico y Deuda Tecnica

1. **Desacople Arquitectonico entre el Dominio UTAMED y el Contrato de Intranet (Oracle)**:
   * UTAMED modela el proceso pedagogico continuo (unidades, rubricas analiticas, hitos de syllabus, mensajeria reactiva), mientras que Oracle gobierna la persistencia contractual y registral (folios numericos `CUR_CODIGO`, inscripciones `INS_ID`, actas de calificacion semestral).
   * La ausencia de una capa adaptadora formal y simetrica para actas provoca que los identificadores de bajo nivel (`INS_ID`, `CUR_CODIGO`) se filtren a la capa de presentacion, exponiendo llaves tecnicas internas a docentes y estudiantes.
   * La creacion de estudiantes desde Oracle se diseño como un "consumidor pasivo" incompleto: extrae unicamente nombres y apellidos desde la tabla `ALUMNO`, asume que el año actual es el año de cohorte y descarta el correo electronico, obligando a parches manuales posteriores.

2. **Ausencia de Maquinas de Estado Terminales (Immutability Violation)**:
   * El ciclo de vida del syllabus en la aplicacion sufria de un modelo de grafo con ciclos infinitos: cualquier estado, incluso `APROBADO`, podia retroceder o ser sobreescrito mediante endpoints de revision o edicion.
   * La falta de proteccion estricta a nivel de Policy (`ProgramaPolicy`) y a nivel de base de datos permitia que roles con permisos administrativos o de jefatura pudieran forzar transiciones de vuelta a `BORRADOR`. En el diseño institucional universitario, un programa de asignatura aprobado constituye un decreto curricular inmutable que no admite regresion ni modificaciones ad-hoc.

3. **Antipatron de Truncamiento en Nulabilidad (`!empty` vs Explicit Null)**:
   * En los servicios de aplicacion (particularmente en `CursoService::update`), existe el patron defectuoso de evaluar `if (!empty($data['campo']))` antes de actualizar relaciones foraneas (`id_docente_titular`).
   * Esto genera una incapacidad estructural para "desasignar" o "limpiar" valores: si el cliente envia `null` o una cadena vacia con la intencion deliberada de desvincular al docente, el servicio lo interpreta como "campo no provisto" y conserva el valor previo. Para corregir esto, se debe diferenciar entre la ausencia de una llave (`array_key_exists`) y el envio de un valor nulo.

4. **Incompatibilidad de Tokens en el Sistema de Diseño y Neutralizacion de Temas**:
   * El framework de Tailwind CSS v4 y las utilidades `useAppearance` estan plenamente integradas en la infraestructura, pero la regla CSS agregada en `resources/css/app.css` piso forzosamente las variables de `.dark` con valores identicos a `.light` como solucion rapida a inconsistencias de contraste previas.
   * La falta de una capa de tokens semanticos de alto contraste (superficies, bordes y tipografias en escala neutra 900-950) impidio habilitar el modo oscuro de forma natural.

5. **Inconsistencia de Transformadores de Presentacion para Metadatos de Curso**:
   * La propiedad `letra_grupo` se gestiona como columna independiente en `curso.curso`, pero los seeders y registros historicos de `administrativo.asignatura` consolidaron el grupo en el string del nombre ("FISICA I GRUPO A").
   * La ausencia de un transformador unificado en el backend (Resource/DTO) delego la responsabilidad de formateo a la UI, provocando que algunas pantallas limpien el string y muestren chips (`CourseListNav`), mientras que las tablas administrativas despliegan nombres duplicados y carecen de badges visuales.

### 2.2. Resoluciones de Diseño Clarificadas

* **Estrategia de Inmutabilidad de Syllabus**: El estado `APROBADO` se convierte en un nodo terminal estricto. Toda peticion de edicion (`GET /admin/cursos/{curso}/programa/editar`) o de devolucion (`POST /reject`) sobre un syllabus aprobado debe ser bloqueada tanto en la Policy como en el controlador, retornando un error de regla de negocio inquebrantable.
* **Modelo de Desasignacion Docente**: El endpoint de actualizacion de curso adoptara semantica REST pura: enviar `id_docente_sugerido: null` ejecutara la remocion del titular en `curso.curso`, la revocacion de su rol contextual `Docente Titular` y la actualizacion coherente de sus asignaciones en componentes colegiados.
* **Manejo de Inscripcion Temporal**: Se introduce el valor `TEMPORAL` en la enumeracion y reglas de validacion de `estado_inscripcion`. Los alumnos con inscripcion temporal seran legibles en listas, agendas y evaluaciones, distinguiendose mediante un badge ambar informativo.
* **Generacion de Actas Oficiales**: UTAMED desacoplara la previsualizacion de notas de la emision del acta oficial. El flujo transaccional de emision consultara el servicio de folios para reservar el correlativo, construira la cabecera del acta, insertara las lineas de detalle asociando `INS_ID` con su calificacion final, y registrara la firma y fecha del actor en la tabla de auditoria inmutable.


---

## 3. Planificacion (Releases y Tablero Kanban)

### Release 1: Reglas Criticas de Dominio, Inmutabilidad y Gestion de Cursos
*Foco: Congelamiento estricto de syllabus aprobado, desasignacion reactiva de docentes y duplicacion integral de cursos sin fuga de alumnos ni fechas.*

```bash
git checkout main
git pull origin main
git checkout -b feature/r1-reglas-dominio-cursos
```

* ### `[SEC-01]` [2 pts] [R1] [P0] Inmutabilidad estricta y bloqueo definitivo de syllabus aprobado
  * **Ruta**: `/admin/cursos/{curso}/programa/editar`, `/admin/cursos/{curso}/programa/rechazar`, `/docente/jefe-carrera/programas/{id}/rechazar`
  * **Archivos**:
    * `app/Policies/ProgramaPolicy.php`
    * `app/Http/Controllers/Admin/ProgramaController.php`
    * `app/Http/Controllers/Administrativo/ProgramaController.php`
    * `app/Http/Controllers/Docente/JefeCarreraController.php`
    * `resources/js/pages/docente/SyllabusWizard.svelte`
  * **Problema & Causa**: El sistema permite que administradores y jefes de carrera devuelvan a `BORRADOR` o editen programas en estado `APROBADO`. En el modelo curricular universitario, la aprobacion oficial es un decreto terminal e inmutable que no admite excepciones ni reversiones, ni siquiera para el rol SuperAdmin.
  * **Criterios de Aceptación (DoD)**:
    * [ ] En `ProgramaPolicy`, prohibir incondicionalmente las acciones `update` y `reject` si `$programa->estado === 'APROBADO'`, anulando cualquier bypass previo de SuperAdmin o administrador global.
    * [ ] En `Admin/ProgramaController@reject`, `Administrativo/ProgramaController@reject` y `JefeCarreraController@rechazarPrograma`, remover de forma estricta `'APROBADO'` de `$estadosPermitidos` y devolver error 422/ValidationException ante cualquier intento forzado por API o HTTP.
    * [ ] En `Admin/ProgramaController@edit`, abortar con redireccion defensiva y flash de error ("El syllabus aprobado es inmutable y no admite modificaciones") si el programa esta `APROBADO`.
    * [ ] En la interfaz (`SyllabusWizard.svelte`, `SyllabusRevisor.svelte` y `Programa.svelte`), suprimir de forma definitiva los botones de edicion, guardado y rechazo cuando el syllabus este `APROBADO`, desplegando badge informativo de "Documento Oficial Sellado".

* ### `[FEAT-01]` [3 pts] [R1] [P0] Desasignacion y gestion reactiva del docente titular
  * **Ruta**: `/admin/cursos` (Slide-over de gestion de curso)
  * **Archivos**:
    * `app/Services/CursoService.php`
    * `app/Http/Requests/UpdateCursoRequest.php`
    * `resources/js/modules/resources/curso/components/cursoSlideOver.svelte`
    * `resources/js/modules/resources/curso/components/cursoForm.svelte`
  * **Problema & Causa**: `CursoService::update` evalua `if (!empty($data['id_docente_sugerido']))`, omitiendo el campo si se envia `null`. Esto hace tecnicamente imposible desasignar al titular del curso desde la interfaz.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Modificar `CursoService::update` para evaluar la existencia explicita de la llave (`array_key_exists('id_docente_sugerido', $data)`), permitiendo asignar `null` a `id_docente_titular`.
    * [ ] Revocar el rol contextual `Docente Titular` en `curso.usuario_rol_asignacion` cuando un docente titular es desasignado o reemplazado.
    * [ ] Incorporar un boton directo "Desasignar docente titular" en la pestaña Equipo de `cursoSlideOver.svelte` con confirmacion inline.
    * [ ] Permitir que el selector de docente titular en `cursoForm.svelte` admita la opcion "Sin docente asignado" (`null`) y persista el cambio exitosamente.

* ### `[REV-01]` [3 pts] [R1] [P1] Revision y ajuste de copia de curso sobre sistema subyacente
  * **Ruta**: `/admin/cursos` (Modal de copia de curso)
  * **Archivos**:
    * `app/Services/CursoService.php`
    * `app/Http/Controllers/Admin/CursoController.php`
    * `resources/js/modules/resources/curso/components/cursoCopyPreviewModal.svelte`
    * `resources/js/modules/resources/curso/components/cursoSlideOver.svelte`
  * **Problema & Causa**: Existe una implementacion subyacente para clonacion de curso (`CursoService::copiar`, `nextIndiceGrupo`, `siguienteCodCurso`), pero requiere auditoria y extension para no omitir el syllabus vigente ni las rubricas evaluativas, asegurando a la vez que no arrastre alumnos ni fechas, que el `cod_curso` se autogenere y que el modal permita lookup por `cod_asignatura`.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Auditar el flujo existente de `CursoService::copiar` y validar el cálculo automático de `cod_curso` vía `siguienteCodCurso()` sin exigir input manual en `cursoCopyPreviewModal.svelte`.
    * [ ] Extender la duplicación para clonar la estructura del syllabus vigente (secciones, resultados de aprendizaje y unidades tematicas) sin duplicar firmas ni fechas de aprobacion pasadas.
    * [ ] Clonar las rubricas analíticas vinculadas a las actividades evaluativas copiadas manteniendo la integridad de criterios y ponderaciones.
    * [ ] Verificar de forma estricta que no se dupliquen alumnos inscritos, registros de agenda ni fechas limites concretas (`fecha_limite => null`).
    * [ ] Incorporar lookup con buscador predictivo por codigo formal de asignatura (`cod_asignatura`, ej: "DM095") para seleccionar el curso fuente.

---

### Release 2: Sincronizacion Intranet, Inscripciones y Actas Oficiales
*Foco: Extraccion fidedigna de correos y cohortes de alumnos, soporte de matriculas temporales y persistencia formal de actas de calificaciones en 3 tablas.*

```bash
git checkout main
git pull origin main
git checkout -b feature/r2-intranet-actas-inscripciones
```

* ### `[EXT-01]` [3 pts] [R2] [P2] [Solicitud Futura / Bloqueada por TI] Extraccion de email y cohorte real desde vista extendida de Victor
  * **Ruta**: `/admin/inscripciones_cursos`, sincronizacion Intranet
  * **Archivos**:
    * `app/Services/EstudianteService.php`
    * `app/Models/Usuario/Estudiante.php`
    * `app/Models/External/VwAlumno.php`
    * `app/Services/IntranetService.php`
  * **Problema & Causa**: Al dar de alta alumnos desde Oracle, `Estudiante::createFromIntranet` guarda `'email' => null` y fija `'agno_ingreso' => (int)now()->year` debido a que la tabla basica `ALUMNO` carece de estos datos. Esta tarjeta queda catalogada como **solicitud futura** a la espera de que soporte TI institucional (Victor) otorgue acceso y credenciales a la vista extendida de alumnos con correos.
  * **Criterios de Aceptación (DoD)**:
    * [ ] [Bloqueante externo] Obtener confirmacion y permisos de lectura sobre la vista de Oracle provista por Victor.
    * [ ] Mapear la nueva vista en `app/Models/External/` extrayendo el correo electronico institucional y el año de admision de la cohorte.
    * [ ] Modificar `Estudiante::createFromIntranet` para persistir el email institucional en `usuario.email` y el año de ingreso en `estudiante.agno_ingreso`.
    * [ ] Incorporar fallback defensivo que asigne `null` en email sin abortar la sincronizacion si el registro de Intranet no dispone de casilla.

* ### `[FEAT-05]` [5 pts] [R2] [P1] Arquitectura de emision de actas: reserva de folio y persistencia en 3 tablas
  * **Ruta**: `/admin/cursos`, modulo de calificaciones y cierre academico
  * **Archivos**:
    * `app/Services/IntranetService.php`
    * `app/Models/Curso/Curso.php`
    * `app/Models/Curso/Componente.php`
    * `database/migrations/` (Nuevas tablas de actas: cabecera, cuerpo y auditoria)
  * **Problema & Causa**: Actualmente UTAMED solo posee un flag `estado_acta` en `curso.curso`. No existe la infraestructura transaccional para reservar un numero de folio oficial, vincular las llaves de Oracle (`CUR_CODIGO` de la componente e `INS_ID` del alumno) ni persistir el registro legal tripartito requerido institucionalmente. Ademas, se requiere levantar con Victor (TI) la documentacion de las funciones y procedimientos PL/SQL que Oracle expone para interactuar con actas.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Solicitar y documentar con Victor (TI) las funciones, vistas o procedimientos PL/SQL que la Intranet (Oracle) expone para la reserva del numero especial y la creacion/cierre de actas.
    * [ ] Crear las migraciones y modelos Eloquent para la triada de actas: `acta_cabecera` (folio reservado, cod_curso, cur_codigo, periodo, docente titular, estado), `acta_cuerpo` (id_cabecera, ins_id, rut_alumno, nota_final, asistencia, situacion) y `acta_auditoria` (id_cabecera, id_usuario_firma, accion, fecha_hora, snapshot_json).
    * [ ] Implementar metodo en `IntranetService` (o servicio de actas dedicado) para solicitar/reservar el numero especial correlativo de acta ante las funciones identificadas con Victor.
    * [ ] Garantizar la asociacion exacta entre `INS_ID` (almacenado en `cod_inscripcion_curso_uta`) y `CUR_CODIGO` (almacenado en la componente generadora de acta).
    * [ ] Ejecutar la generacion y sellado del acta dentro de una transaccion atomica con insercion sincronizada en las tres tablas.

---

### Release 3: Consistencia Visual, Seguridad de Informacion y Experiencia de Usuario
*Foco: Restriccion defensiva de metadatos de intranet, depuracion de tablas, modo oscuro, badges de grupo y calendario transversal de estudiante.*

```bash
git checkout main
git pull origin main
git checkout -b feature/r3-ui-calendario-darkmode
```

* ### `[SEC-02]` [2 pts] [R3] [P1] Blindaje de codigos intranet y remocion de columna usuario en nomina
  * **Ruta**: `/admin/inscripciones_cursos`, `/admin/cursos`
  * **Archivos**:
    * `resources/js/modules/resources/inscripcion/components/rosterTable.svelte`
    * `resources/js/modules/resources/curso/components/cursoSlideOver.svelte`
    * `resources/js/pages/docente/CursoDetalle.svelte`
  * **Problema & Causa**: La columna "Usuario" en `rosterTable.svelte` repite innecesariamente el RUT sin DV (`studentUsername`), restando espacio en pantalla. Ademas, identificadores tecnicos de integracion de Oracle (`INS_ID` / `cod_inscripcion_uta` y `CUR_CODIGO` / `cod_curso`) se despliegan en vistas consultadas por profesores y alumnos, cuando deben ser exclusivos para administradores. *(Nota de absorcion: Absorbe la tarea UI-01 de depuracion de columnas)*.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Eliminar la columna `{ key: 'usuario', label: 'Usuario' }` de `rosterTable.svelte`, reasignando el espacio horizontal para el nombre completo y el correo institucional del estudiante.
    * [ ] Condicionar la visualizacion de columnas y badges con `INS_ID` y `CUR_CODIGO` exclusivamente al rol Administrador (`isAdmin`).
    * [ ] Ocultar en la tabla de alumnos cualquier dato o folio tecnico de Intranet para usuarios con rol docente o estudiante.
    * [ ] En la lista de componentes de `cursoSlideOver.svelte`, mostrar el codigo de acta `CUR_CODIGO` unicamente cuando la sesion activa pertenezca a administracion.

* ### `[UI-02]` [2 pts] [R3] [P2] Renderizado de letra de grupo como badge en tablas de administracion
  * **Ruta**: `/admin/cursos`, `/admin/inscripciones_cursos`
  * **Archivos**:
    * `resources/js/modules/resources/curso/components/cursoListAdmin.svelte`
    * `resources/js/modules/resources/inscripcion/components/cursoSelector.svelte`
  * **Problema & Causa**: Las tablas administrativas muestran el nombre de la asignatura con el grupo concatenado ("COMPUTACION GRUPO A") o sin distincion de grupo, omitiendo la visualizacion de `letra_grupo` como un badge estructurado.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Limpiar mediante expresion regular cualquier sufijo redundante de grupo incrustado en el texto de `item.nombre`.
    * [ ] Renderizar un badge `<span class="badge ...">Grupo {item.letra_grupo}</span>` contiguo al nombre del curso siempre que `letra_grupo` este presente.
    * [ ] Aplicar la misma estandarizacion en las cards de seleccion de curso en `cursoSelector.svelte`.

* ### `[FEAT-06]` [5 pts] [R3] [P2] Calendario general academico unificado (estilo eLearn)
  * **Ruta**: `/estudiante/calendario`
  * **Archivos**:
    * `app/Http/Controllers/Student/CalendarioController.php` (Nuevo controlador)
    * `resources/js/pages/student/Calendario.svelte` (Nueva vista basada en `@/modules/resources/calendario`)
    * `resources/js/components/custom/layout/RoleSidebar.svelte`
    * `routes/web.php`
  * **Problema & Causa**: Los estudiantes solo disponen de calendarios fragmentados por curso individual. Carecen de una vista agregada transversal que reuna todas sus actividades, fechas limite y entregas academicas en una interfaz unificada similar a eLearn.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Crear la ruta `GET /estudiante/calendario` protegida por autenticacion y rol estudiante.
    * [ ] Implementar `Student\CalendarioController` que consulte todos los cursos en los que el alumno posee `estado_inscripcion = 'INSCRITO'` y agregue sus actividades evaluativas con `fecha_limite`.
    * [ ] Reutilizar el modulo `@/modules/resources/calendario` (`CalendarMonth`, `CalendarWeek`, `CourseFilter`, `EventPill`) en `student/Calendario.svelte` enfocado en entregas y evaluaciones con filtro reactivo por curso.
    * [ ] Añadir el enlace "Calendario" con icono `Calendar` en `RoleSidebar.svelte` dentro del bloque de navegacion de Estudiante.

* ### `[UI-03]` [3 pts] [R3] [P2] Habilitacion y calibracion del modo oscuro
  * **Ruta**: Global
  * **Archivos**:
    * `resources/css/app.css`
    * `resources/js/hooks/useAppearance.svelte.ts`
    * `resources/js/components/custom/common/AppearanceTabs.svelte`
  * **Problema & Causa**: El modo oscuro fue anulado en `app.css` forzando variables claras dentro del selector `.dark`. Los componentes soportan clases `dark:`, pero el tema no se activa.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Remover el bloque de sobrescritura forzada clara en `.dark` dentro de `resources/css/app.css`.
    * [ ] Configurar tokens de tema oscuro calibrados con contraste accesible (superficies Slate-900 / Neutral-900, fondos Slate-950, texto blanco y Slate-200, bordes Slate-800).
    * [ ] Verificar que la alternancia de tema desde `AppearanceTabs.svelte` o la deteccion automatica del sistema aplique la clase `.dark` fluidamente en toda la interfaz sin saltos de contraste ni regresiones visuales.

---

## 4. Metricas de Planificacion

* **Total de Tarjetas**: 9
* **Puntos por Release**:
  * **Release 1 (Reglas de Dominio y Cursos)**: 8 puntos (3 tarjetas)
  * **Release 2 (Intranet, Inscripciones y Actas)**: 8 puntos (2 tarjetas)
  * **Release 3 (UI, Navegacion y Calendario)**: 12 puntos (4 tarjetas)
* **Total de Puntos de Historia Estimados**: **28 puntos**
