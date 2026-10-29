# Backlog de Trabajo: Módulo API REST para Aplicación Móvil del Estudiante (UTAMED)

* **Fecha**: 2026-10-29
* **Rama Base**: `main`
* **Metodología**: Triada estricta `OBSERVACIONES` > `INTERPRETACION E INVESTIGACION` > `PLANIFICACION`
* **Calibración**: Escala Fibonacci de 5 niveles (1, 2, 3, 5 y 8 pts)
* **Objetivo de Negocio y Arquitectura**: Abstraer la lógica del dominio del estudiante (documentada en `docs/ARQUITECTURA_BACKEND_ESTUDIANTE.md` e implementada en `app/Http/Controllers/Student/*` y servicios asociados) en un módulo `/api/v1/estudiante/` stateless, consumido exclusivamente por una aplicación móvil nativa/híbrida para estudiantes.
* **Prefijo Kanban**: `APP-`
* **Ajustes de Alcance Confirmados**:
  * Autenticación exclusiva con RUT y contraseña (sin solicitud de correo).
  * Perfil en modo de solo lectura (sin ediciones deliberadas de contacto ni contraseña en la app).
  * Cambio de contraseña restringido exclusivamente a vencimiento de fecha (`VIGENCIA_PASSWORD_MESES = 6`) o primer ingreso tras sincronización.
  * Bitácora detallada fuera de alcance (se consumen directamente las agendas de cada actividad).
  * Rendimiento estrictamente individual (sin métricas de otros alumnos ni `promedio_curso`).
  * Módulo de mensajería por componentes denominado oficialmente **Mensajero** (`curso.mensaje`), diferenciado de los mensajes de agenda por actividad (`agenda.agenda`).
  * Módulo externo de préstamos de equipos (SGEQ) postergado para etapas posteriores.

---

## 1. Observaciones Técnicas y Diagnóstico

### [Identidad y Autenticación] Ingreso Estricto por RUT y Token Stateless
* **Elemento / Contexto**: `routes/web.php:588`, `App\Models\Usuario\Usuario`, `App\Support\Rut`.
* **Problema reportado**: La app móvil no debe utilizar el flujo de sesión web basado en cookies de navegador ni admitir inicio de sesión con correo electrónico. Debe validar credenciales de forma determinista usando el RUT normalizado del alumno (`Rut::normalizar()`) junto a su contraseña cifrada en `passhash`.
* **Evidencia en código**:
  * `Usuario::debeCambiarPassword()` y `motivoCambioPasswordObligatorio()`: La API detecta si la clave superó los 6 meses de vigencia o si es primer ingreso, emitiendo un flag `requiere_cambio_password` para forzar la actualización de clave antes de liberar el resto de rutas.

### [Perfil e Inmutabilidad] Credencial Oficial de Solo Lectura
* **Elemento / Contexto**: `app/Http/Controllers/Student/PerfilController.php`, `App\Services\FotoIntranetService.php`.
* **Problema reportado**: La aplicación móvil no admitirá modificaciones deliberadas de datos personales, teléfono ni redes sociales. Los datos provienen íntegramente de la Intranet UTA y se exponen como credencial digital de solo lectura.
* **Evidencia en código**:
  * `FotoIntranetService::getImagenPerfilURL($user->rut)`: Emite una URL firmada y segura hacia el proxy de imágenes de Intranet.

### [Dashboard y Cursos] Scoping por Componente y Alertas de Mensajero
* **Elemento / Contexto**: `app/Http/Controllers/Student/DashboardController.php`, `app/Services/MensajeriaService.php`, `app/Services/Student/ResumenActividadesEstudiante.php`.
* **Problema reportado**: El dashboard móvil debe concentrar las asignaturas vigentes, el docente titular de la sección específica donde está inscrito el estudiante (no el docente genérico del curso administrativo), las entregas de actividades de los próximos 7 días (calculando holguras base y personales) y el contador de mensajes no leídos provenientes exclusivamente del módulo **Mensajero**.
* **Evidencia en código**:
  * `DashboardController::mensajesSinLeer`: Agrupa mensajes no leídos de `curso.mensaje` por curso mediante `MensajeriaService::noLeidosPorComponente`. No incluye el sistema de préstamos SGEQ por definición de alcance móvil.

### [Rendimiento Académico] Enfoque Estrictamente Individual
* **Elemento / Contexto**: `app/Services/Student/RendimientoCursoEstudiante.php`, `app/Models/Curso/Asistencia.php`.
* **Problema reportado**: Para resguardar la privacidad y focalizarse en el avance personal del estudiante, la app móvil no debe mostrar promedios del curso ni comparativas estadísticas con otros alumnos.
* **Evidencia en código**:
  * `RendimientoCursoEstudiante::calcular`: Se debe omitir la clave `promedio_curso`, entregando únicamente `mi_promedio`, notas ponderadas de las actividades sumativas por componente y asistencia acumulada frente al límite del 70%.

### [Actividades y Entregas vs Mensajero] Deslinde Claro entre Agendas y Mensajero
* **Elemento / Contexto**: `app/Http/Controllers/Student/ActivityController.php`, `app/Http/Controllers/Student/AgendaController.php`, `app/Http/Controllers/Student/MensajeriaController.php`.
* **Problema reportado**: Existen dos subsistemas de comunicación asíncrona que no deben confundirse:
  1. **Agenda de Actividad** (`agenda.agenda`): Hilo de diálogo y entregas de archivos vinculado a un grupo de trabajo y una actividad específica. Maneja subida segura con `AgendaArchiveHandler` fuera de DB transaction, bloqueo por evaluación docente (`yaFueEvaluado()`), holguras y cancelación de la última entrega activa con `pendiente_de_borrado = true`.
  2. **Mensajero** (`curso.mensaje`): Canales de comunicación por componente (Cátedra, Taller, Laboratorio) para avisos masivos del equipo docente y consultas directas sobre la sección.

---

## 2. Interpretación e Investigación (La Vista Amplia: Lo que «No Se Vio»)

### 2.1. Desacople Limpio del Monolito Web a API JSON
El código actual en `app/Http/Controllers/Student/*` mezcla la consulta de datos de negocio con la construcción de vistas para Inertia.js (`Inertia::render`).
* **Estrategia API**: La capa móvil consumirá controladores dedicados en `app/Http/Controllers/Api/V1/Student/` que reutilizan la lógica y modelos existentes, encapsulando las respuestas en `JsonResource` / DTOs con tipado estricto.
* **Scoping Anti-IDOR**: Cada petición verifica:
  1. Que el usuario autenticado posea el rol `Estudiante`.
  2. Que mantenga inscripción activa (`INSCRITO`) en el curso.
  3. Que pertenezca al componente o grupo de actividad antes de emitir información o servir archivos binarios.

### 2.2. Manejo de Fechas sin Ambigüedad Horaria
Para prevenir desajustes entre el servidor UTC y los dispositivos móviles de los estudiantes en Chile (horarios UTC-4 o UTC-3), las fechas límite de entrega se formatean estrictamente en `'Y-m-d'`, y los eventos cronológicos en estándar ISO-8601.

---

## 3. Planificación (Releases y Tablero Kanban)

### Release 1: Autenticación Móvil, Identidad y Dashboard (R1)
*Rama sugerida:* `feature/app-api-auth-perfil-dashboard`
*Puntos estimados:* 14 pts

* ### `[APP-01]` [5 pts] [R1] [P0] Autenticación y Emisión de Token Móvil mediante RUT y Contraseña
  * **Historia de Usuario**: Como estudiante de UTAMED, quiero iniciar sesión exclusivamente con mi RUT y contraseña desde la aplicación móvil para acceder de forma segura y exclusiva a mis asignaturas académicas.
  * **Rutas / Endpoints**:
    * `POST /api/v1/auth/login`
    * `POST /api/v1/auth/logout`
    * `GET /api/v1/auth/me`
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Auth/StudentAuthController.php`
    * `app/Models/Usuario/Usuario.php`
    * `app/Support/Rut.php`
    * `app/Http/Middleware/IsStudentApi.php`
  * **Problema & Causa**: Desacoplar la sesión de cookies web hacia tokens Bearer stateless con verificación de rol `Estudiante`. El login valida estrictamente RUT (normalizado con `Rut::normalizar`) y contraseña; no solicita ni procesa correo. Si el estudiante tiene clave vencida (`debeCambiarPassword()`), se notifica en el payload.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Login acepta exclusivamente RUT y contraseña.
    * [ ] Deniega con 403 Forbidden si el usuario no tiene rol o perfil de estudiante.
    * [ ] Emite token Bearer revocado en logout.
    * [ ] Retorna objeto de estudiante y flag `requiere_cambio_password`.

* ### `[APP-02]` [2 pts] [R1] [P1] Consulta de Perfil Institucional y Fotografía Oficial (Solo Lectura)
  * **Historia de Usuario**: Como estudiante, quiero consultar mis datos institucionales oficiales y mi foto de credencial en mi perfil móvil de solo lectura para verificar mi condición de alumno regular.
  * **Rutas / Endpoints**:
    * `GET /api/v1/estudiante/perfil`
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Student/PerfilApiController.php`
    * `app/Services/FotoIntranetService.php`
    * `app/Http/Resources/Api/Student/PerfilResource.php`
    * `app/Http/Controllers/Student/PerfilController.php`
  * **Problema & Causa**: Exponer los datos de `Usuario`, `Estudiante` y `Carrera` exclusivamente en modo de solo lectura (la app no permite modificaciones deliberadas del perfil) junto a la URL firmada generada por `FotoIntranetService`.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Retorna nombre completo, RUT formateado, correo institucional, carrera, cohorte (`agno_ingreso`) y total de cursos inscritos.
    * [ ] Retorna `url_foto_perfil` firmada y segura generada desde el proxy de Intranet.
    * [ ] Los datos son estrictamente inmutables; no se exponen endpoints de actualización deliberada de perfil.

* ### `[APP-03]` [2 pts] [R1] [P1] Cambio Obligatorio de Contraseña por Vencimiento de Fecha
  * **Historia de Usuario**: Como estudiante cuya contraseña ha vencido (o en su primer ingreso), quiero actualizar obligatoriamente mi clave desde la app móvil para restablecer el acceso a mi cuenta.
  * **Rutas / Endpoints**:
    * `POST /api/v1/auth/cambio-password-obligatorio`
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Student/PasswordApiController.php`
    * `app/Http/Controllers/Settings/PasswordController.php`
    * `app/Models/Usuario/Usuario.php`
  * **Problema & Causa**: No se permiten cambios de contraseña deliberados en la app móvil. La actualización de clave solo está habilitada cuando `Usuario::debeCambiarPassword()` es verdadero (por vigencia de 6 meses vencida o primer ingreso tras sincronización Intranet).
  * **Criterios de Aceptación (DoD)**:
    * [ ] Endpoint restringido: solo permite ejecución si el usuario tiene flag de cambio obligatorio activo (`debeCambiarPassword()`).
    * [ ] Valida contraseña actual (`current_password`) y complejidad de la nueva clave (`Password::defaults()`).
    * [ ] Actualiza `passhash` y fecha de modificación en `Usuario::cambiarPassword()`, levantando el bloqueo.

* ### `[APP-04]` [5 pts] [R1] [P0] Dashboard Principal y Alertas Académicas
  * **Historia de Usuario**: Como estudiante, quiero ver en la pantalla de inicio mis cursos activos, entregas próximas a vencer y mensajes nuevos del Mensajero para priorizar mi carga académica semanal.
  * **Rutas / Endpoints**:
    * `GET /api/v1/estudiante/dashboard`
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Student/DashboardApiController.php`
    * `app/Services/Student/ResumenActividadesEstudiante.php`
    * `app/Services/MensajeriaService.php`
    * `app/Http/Controllers/Student/DashboardController.php`
  * **Problema & Causa**: Resumir en un solo payload optimizado: tarjetas de cursos vigentes con docente asignado a la sección del estudiante (resolviendo componente titular), tareas con vencimiento dentro de los próximos 7 días (calculando holguras base y personales), últimas 5 notas recibidas y contador de mensajes no leídos del módulo **Mensajero** (`curso.mensaje`) por curso. El módulo de préstamos SGEQ queda expresamente fuera de alcance.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Retorna cursos del semestre y año vigente con docente titular de la sección/componente asignado.
    * [ ] Retorna lista `proximas_a_vencer` con fecha límite calculada incluyendo holguras.
    * [ ] Retorna `notas_recientes` individuales del estudiante.
    * [ ] Retorna badge con conteo de `mensajes_no_leidos` del **Mensajero** por cada curso.
    * [ ] No incluye integración ni referencias al sistema SGEQ.

---

### Release 2: Cursos, Syllabus, Rendimiento y Calendario (R2)
*Rama sugerida:* `feature/app-api-cursos-rendimiento-syllabus`
*Puntos estimados:* 17 pts

* ### `[APP-05]` [3 pts] [R2] [P1] Catálogo de Cursos Inscritos por Período
  * **Historia de Usuario**: Como estudiante, quiero explorar la lista de cursos en los que estoy o he estado inscrito, filtrando por año y semestre, para revisar mis asignaturas.
  * **Rutas / Endpoints**:
    * `GET /api/v1/estudiante/cursos` (Query params: `semestre`, `agno`)
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Student/CourseApiController.php`
    * `app/Http/Resources/Api/Student/CursoResource.php`
    * `app/Http/Controllers/Student/CourseController.php`
  * **Problema & Causa**: Exponer las asignaturas con estado `INSCRITO`, formateando código de curso, código de asignatura, nombre, carrera, grupo y disponibilidad de programa.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Por defecto responde el semestre y año vigente si no se envían parámetros.
    * [ ] Filtra por año y semestre especificado.
    * [ ] Incluye flag `tiene_programa` indicando si existe syllabus aprobado o completo.

* ### `[APP-06]` [3 pts] [R2] [P1] Ficha Detallada del Curso y Componentes Asignados
  * **Historia de Usuario**: Como estudiante, quiero ver los detalles de un curso inscrito, sus componentes y unidades para conocer la organización docente de la asignatura.
  * **Rutas / Endpoints**:
    * `GET /api/v1/estudiante/cursos/{curso}`
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Student/CourseApiController.php`
    * `app/Http/Resources/Api/Student/CursoDetalleResource.php`
    * `app/Http/Controllers/Student/CourseController.php`
  * **Problema & Causa**: Resolver la ficha de curso validando inscripción activa (anti-IDOR 403), listando los componentes específicos del estudiante (`curso.inscripcion_componente`) y unidades de aprendizaje.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Deniega con 403 Forbidden si el alumno no está inscrito en el curso solicitado.
    * [ ] Expone componentes asignados (Cátedra, Taller, Laboratorio) y créditos/horas.

* ### `[APP-07]` [5 pts] [R2] [P0] Monitoreo de Rendimiento Académico Individual y Asistencia por Curso
  * **Historia de Usuario**: Como estudiante, quiero consultar mis notas parciales individuales, mi promedio ponderado y mi asistencia acumulada por curso para evaluar mi progreso sin visualizar datos de otros compañeros.
  * **Rutas / Endpoints**:
    * `GET /api/v1/estudiante/cursos/{curso}/rendimiento`
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Student/RendimientoApiController.php`
    * `app/Services/Student/RendimientoCursoEstudiante.php`
    * `app/Models/Curso/Asistencia.php`
  * **Problema & Causa**: Exponer el cálculo de rendimiento acotado estrictamente a la dimensión individual del estudiante autenticado. Por regla de negocio de la app móvil, no se debe calcular ni retornar `promedio_curso` ni ninguna métrica comparativa sobre el rendimiento de otros estudiantes.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Retorna `mi_promedio` individual y notas ponderadas por componente del alumno.
    * [ ] Retorna porcentaje de asistencia acumulada en `curso.asistencia` y alerta si está bajo el 70%.
    * [ ] No incluye `promedio_curso` ni datos estadísticos agregados de otros estudiantes.

* ### `[APP-08]` [3 pts] [R2] [P2] Consulta de Programa Oficial y Syllabus de Asignatura
  * **Historia de Usuario**: Como estudiante, quiero leer el programa académico oficial y syllabus del curso para conocer los resultados de aprendizaje, bibliografía y pautas de evaluación.
  * **Rutas / Endpoints**:
    * `GET /api/v1/estudiante/cursos/{curso}/programa`
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Student/ProgramaApiController.php`
    * `app/Services/Student/StudentSyllabusPresenter.php`
    * `app/Traits/ParsesSyllabus.php`
  * **Problema & Causa**: Exponer las secciones normalizadas del JSONB del syllabus priorizando versión `APROBADO` sobre `BASICO_COMPLETO`, vinculando el equipo docente específico del componente del alumno.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Serializa identificación, competencias, unidades, recursos bibliográficos y metodología.
    * [ ] Solo expone programas autorizados para visualización estudiantil.

* ### `[APP-09]` [3 pts] [R2] [P1] Calendario Académico Integrado de Entregas
  * **Historia de Usuario**: Como estudiante, quiero revisar en un calendario cronológico las fechas límites de entregas del semestre para planificar con tiempo mis tareas.
  * **Rutas / Endpoints**:
    * `GET /api/v1/estudiante/calendario` (Query params: `semestre`, `agno`, `id_curso` opcional)
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Student/CalendarioApiController.php`
    * `app/Services/Student/ResumenActividadesEstudiante.php`
  * **Problema & Causa**: Exponer `ResumenActividadesEstudiante::calendarioDelPeriodo` asegurando formato de fecha sin desfases horarios (`Y-m-d`), con estado de la actividad y grupo.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Retorna eventos ordenados cronológicamente de todos los cursos inscritos del período.
    * [ ] Permite filtrar por un curso específico.
    * [ ] Serializa fechas en formato `'Y-m-d'` para prevenir desajustes horarios entre UTC y Chile.

---

### Release 3: Actividades, Entregas y Mensajero (R3)
*Rama sugerida:* `feature/app-api-actividades-entregas-mensajero`
*Puntos estimados:* 23 pts

* ### `[APP-10]` [5 pts] [R3] [P0] Consulta de Actividades Evaluativas, Rúbricas y Enunciados
  * **Historia de Usuario**: Como estudiante, quiero consultar el listado de actividades de mi curso, abrir su detalle con las pautas y rúbricas, y descargar el enunciado oficial para preparar mi trabajo.
  * **Rutas / Endpoints**:
    * `GET /api/v1/estudiante/cursos/{curso}/actividades`
    * `GET /api/v1/estudiante/cursos/{curso}/actividades/{actividad}`
    * `GET /api/v1/estudiante/cursos/{curso}/actividades/{actividad}/enunciado`
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Student/ActivityApiController.php`
    * `app/Http/Resources/Api/Student/ActividadDetalleResource.php`
    * `app/Http/Controllers/Student/ActivityController.php`
  * **Problema & Causa**: Controlar que la actividad pertenezca al curso (`componente.id_curso === curso.id_curso`), sea visible (`visible = true`), resuelva compañeros de grupo e integrantes, y sirva la descarga segura del archivo adjunto.
  * **Criterios de Aceptación (DoD):**
    * [ ] Valida guardrails IDOR (pertenencia de actividad a curso y matrícula activa).
    * [ ] Retorna integrantes del grupo, tipo de actividad (sumativa/formativa) y rúbrica asignada.
    * [ ] Descarga binaria con `Content-Type` y `Content-Disposition` del enunciado.

* ### `[APP-11]` [5 pts] [R3] [P0] Consulta de Calificaciones, Rúbrica Evaluada y Feedback Docente
  * **Historia de Usuario**: Como estudiante, quiero consultar la nota obtenida en una actividad evaluada, los puntajes de la rúbrica y los comentarios del docente para conocer mis fortalezas y aspectos a mejorar.
  * **Rutas / Endpoints**:
    * `GET /api/v1/estudiante/cursos/{curso}/actividades/{actividad}/evaluacion`
    * `GET /api/v1/estudiante/cursos/{curso}/actividades/{actividad}/entregas/{agenda}/descargar`
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Student/ActivityApiController.php`
    * `app/Http/Resources/Api/Student/EvaluacionResource.php`
    * `app/Http/Controllers/Student/ActivityController.php`
  * **Problema & Causa**: Exponer la evaluación final: nota grupal, ajuste de décimas (`integrante_grupo.diferencia_decimas`), desglose de niveles de la rúbrica evaluada y descarga segura del archivo que fue calificado (exclusivo para miembros del grupo).
  * **Criterios de Aceptación (DoD):**
    * [ ] Informa nota grupal y nota individual propia ajustada por décimas.
    * [ ] Desglosa la rúbrica evaluada con criterios, niveles y puntajes obtenidos.
    * [ ] Descarga de archivo de entrega restringida exclusivamente a integrantes del grupo correspondiente.

* ### `[APP-12]` [8 pts] [R3] [P0] Gestión de Entregas de Tareas y Mensajes de Agenda por Actividad
  * **Historia de Usuario**: Como estudiante, quiero interactuar en la agenda de mi actividad enviando mensajes y dudas al profesor, subiendo mi archivo de entrega dentro del plazo con validación de holgura, y cancelando mi última entrega no evaluada si necesito rectificarla.
  * **Rutas / Endpoints**:
    * `GET /api/v1/estudiante/grupos-asignados/{grupo}/agenda`
    * `POST /api/v1/estudiante/grupos-asignados/{grupo}/agenda` (Mensaje de duda de actividad)
    * `POST /api/v1/estudiante/grupos-asignados/{grupo}/entregas` (Subir archivo entrega)
    * `DELETE /api/v1/estudiante/grupos-asignados/{grupo}/entregas/{agenda}` (Cancelar entrega)
    * `POST /api/v1/estudiante/grupos-asignados/{grupo}/agenda/leida` (Marcar agenda leída)
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Student/AgendaApiController.php`
    * `app/Services/Archive/Handlers/AgendaArchiveHandler.php`
    * `app/Http/Requests/Archive/AgendaFileRequest.php`
    * `app/Services/Agenda/LecturaAgendaService.php`
    * `app/Http/Controllers/Student/AgendaController.php`
  * **Problema & Causa**: Flujo crítico de entrega en `agenda.agenda`: no debe confundirse con el módulo **Mensajero**. Procesa archivos con `AgendaArchiveHandler` fuera de transacción DB; aplica `lockForUpdate()` con verificación de fecha límite + holguras (base y personal) y bloqueo si ya fue evaluado (`yaFueEvaluado()`); previene doble envío idempotente en ventana de 10s; y en cancelación, aplica soft delete con `pendiente_de_borrado = true` y auditoría en agenda.
  * **Criterios de Aceptación (DoD):**
    * [ ] Valida pertenencia del estudiante al grupo asignado (`IntegranteGrupo`).
    * [ ] Deniega subidas si el grupo ya fue evaluado o el plazo real con holgura venció.
    * [ ] Manejo de doble envío repetido sin duplicar filas en base de datos.
    * [ ] Cancelación restringida a la última entrega activa no evaluada.
    * [ ] Endpoint `leida` registra confirmación de lectura en `agenda.lectura_agenda`.

* ### `[APP-13]` [5 pts] [R3] [P1] Mensajero: Canales y Avisos por Componente de Curso
  * **Historia de Usuario**: Como estudiante, quiero usar el **Mensajero** para leer los anuncios oficiales de mi sección y enviar mensajes a mis profesores por componente (Cátedra, Taller, Laboratorio) manteniendo una comunicación directa con el equipo docente.
  * **Rutas / Endpoints**:
    * `GET /api/v1/estudiante/cursos/{curso}/mensajero`
    * `GET /api/v1/estudiante/cursos/{curso}/mensajero/componentes/{componente}`
    * `POST /api/v1/estudiante/cursos/{curso}/mensajero/componentes/{componente}/mensaje`
  * **Archivos**:
    * `app/Http/Controllers/Api/V1/Student/MensajeriaApiController.php`
    * `app/Services/Student/MensajeriaCursoEstudiante.php`
    * `app/Services/MensajeriaService.php`
    * `app/Http/Controllers/Student/MensajeriaController.php`
  * **Problema & Causa**: Exponer la lógica del módulo **Mensajero** (`curso.mensaje`), el cual opera a nivel de componente del curso (no confundir con los mensajes de agenda por actividad en `agenda.agenda`): bandeja con pestañas por componente (Cátedra, Taller, Laboratorio), conteo de mensajes sin leer, panel de conversación con difusiones generales del docente e intercambios privados, envío de mensajes al equipo docente con fallback al titular, y auto-marcado de lectura al abrir el canal.
  * **Criterios de Aceptación (DoD):**
    * [ ] Valida inscripción activa del alumno en el componente del curso.
    * [ ] Lista avisos generales de difusión y la conversación privada sostenida con el equipo docente.
    * [ ] El envío asigna automáticamente al docente titular o al docente seleccionado del componente.
    * [ ] Marca automáticamente como leídos los mensajes del canal al abrir el componente en el **Mensajero**.

---

## 4. Métricas de Planificación del Módulo API Estudiante (Ajustado)

* **Total de Tarjetas Activas**: 13
* **Distribución por Releases**:
  * **Release 1 (Autenticación Móvil, Identidad y Dashboard)**: 4 tarjetas — 14 pts
  * **Release 2 (Cursos, Syllabus, Rendimiento y Calendario)**: 5 tarjetas — 17 pts
  * **Release 3 (Actividades, Entregas y Mensajero)**: 4 tarjetas — 23 pts
* **Total Puntos de Historia (Fibonacci)**: 54 pts
