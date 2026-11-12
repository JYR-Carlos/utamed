# Backlog de Trabajo: Credenciales SuperAdmin, Copia de Cursos, CSRF y Motor de Actas Intranet

* **Fecha**: 2026-11-11
* **Rama Base**: `main`
* **Tablero Trello Activo**: `arreglos-reunion-23-09-2026` (`https://trello.com/b/H5mt3Cd8/arreglos-reunion-23-09-2026`)
* **Metodologia**: Triada `OBSERVACIONES` > `INTERPRETACION E INVESTIGACION` > `PLANIFICACION`
* **Estado**: Pendiente de ejecucion de `backlog-refiner` para refinamiento y sincronizacion con Trello

---

## 1. Observaciones

Inspeccion empirica directa del codigo fuente contrastada con los requerimientos y errores reportados el 2026-11-11:

### Modulo Administracion de Usuarios: Restablecimiento y Cambio de Contraseña por SuperAdmin
* **Elemento / Contexto**: `/admin/usuarios` (`resources/js/pages/admin/Usuarios.svelte` y `resources/js/modules/resources/usuario/components/passwordChangeModal.svelte`).
* **Problema reportado**:
  1. Eliminar la exigencia de validar la propia contraseña del administrador (`current_password`) al restablecer o cambiar la contraseña de un usuario.
  2. Agregar un boton con icono de ojo para alternar la visibilidad (mostrar/ocultar) de la contraseña nueva.
* **Evidencia en codigo**:
  - `app/Http/Controllers/Admin/UsuarioController.php:1180-1187` (`changePassword`) y `1239-1245` (`setDefaultPassword`): Ambos endpoints exigen `'current_password' => ['required', 'string', 'current_password']`.
  - `resources/js/pages/admin/Usuarios.svelte:427-430` y `440-443`: `openResetDialog()` y `handleResetDefault()` bloquean el flujo en cliente con un toast si `passwordFormData.current_password` esta vacio.
  - `resources/js/modules/resources/usuario/components/passwordChangeModal.svelte:43-56`: Renderiza el campo obligatorio `id="current_password"` ("Tu contraseña *").
  - `resources/js/modules/resources/usuario/components/passwordChangeModal.svelte:77-103`: Los inputs `id="new_password"` e `id="password_confirmation"` tienen `type="password"` fijo sin conmutador de visibilidad, a diferencia de `resources/js/components/admin/UserForm.svelte:273-301` que ya implementa el patron de icono de ojo con SVG inline.

### Modulo Ayudantia: Alcance Operativo del Ambiente de Ayudantes
* **Elemento / Contexto**: `/ayudante/dashboard` y `/ayudante/cursos/{curso}` (`routes/web.php:652-674`, `resources/js/pages/ayudante/Courses/Show.svelte`).
* **Problema reportado**: El ambiente de ayudantes requiere mayor capacidad operativa ("ambiente ayudantes tocar mas").
* **Evidencia en codigo**:
  - `routes/web.php:652-674` y `resources/js/pages/ayudante/Courses/Show.svelte:90-165`: El portal de ayudante actualmente solo expone acceso a **Programa de Curso (Syllabus)** y **Mensajeria**.
  - `app/Http/Controllers/Docente/DelegacionPermisosController.php:43-95` y `CursoPermisosController.php:50-55`: El backend ya permite delegar a miembros del equipo permisos granulares sobre **Actividades** (`actividades:ver`, `crear`, `editar`, `eliminar`, `evaluar`, `dar_feedback`, `descargar_entregas`), **Grupos** (`actividades/grupos:*`), **Unidades** (`cursos/unidades:*`), **Asistencia** (`componentes/asistencia:registrar`, `editar`) e **Inscripciones** (`cursos/inscripciones:ver`), pero esas vistas y rutas no estan conectadas en el ambiente `/ayudante`.

### Modulo Jefatura de Carrera: Relevamiento con Ignacio
* **Elemento / Contexto**: `/docente/jefe-carrera/*` (`app/Http/Controllers/Docente/JefeCarreraController.php`, `resources/js/pages/jefe-carrera/`).
* **Problema reportado**: "como jefe carrera tener reunion con ignacio".
* **Evidencia en codigo**:
  - Existen las vistas `Dashboard.svelte`, `Seguimiento.svelte`, `Metricas.svelte` y `Carrera.svelte` bajo `resources/js/pages/jefe-carrera/`, ademas de controladores en `app/Http/Controllers/Docente/JefeCarrera/`. El punto reportado corresponde a una instancia de definicion funcional con el stakeholder (Ignacio) antes de especificar cambios sobre dicho entorno.

### Modulo Cursos: Copia Condicional de Fechas segun Periodo Academico
* **Elemento / Contexto**: `/admin/cursos` (`resources/js/modules/resources/curso/components/cursoCopyPreviewModal.svelte`, `app/Http/Controllers/Admin/CursoController.php:982`, `app/Services/CursoService.php:216`).
* **Problema reportado**: Al copiar un curso, si el curso destino pertenece al mismo periodo academico (`agno_real` y `semestre_real`), se debe permitir copiar las fechas de las actividades mediante un selector (copiar o no copiar); si es un periodo distinto, no se deben copiar las fechas.
* **Evidencia en codigo**:
  - `app/Services/CursoService.php:364`: En `CursoService::copiar()`, la creacion de cada `Actividad` fija incondicionalmente `'fecha_limite' => null`.
  - `app/Http/Controllers/Admin/CursoController.php:985-998`: `copiar()` valida unicamente `nombre`, `fecha_inicio`, `agno_real` y `semestre_real`, sin recibir parametro booleano `copiar_fechas`.
  - `resources/js/modules/resources/curso/components/cursoCopyPreviewModal.svelte:305-319` y `460-467`: Muestra un aviso fijo indicando que todas las fechas quedaran vacias (`line-through -> vacia`), sin comparar `(newAgnoReal, newSemestreReal)` contra `(preview.curso.agno_real, preview.curso.semestre_real)` ni ofrecer selector al usuario.

### Transversal (Cursos y Asignacion de Roles): Error CSRF Token Mismatch (HTTP 419)
* **Elemento / Contexto**:
  - Copia/replicacion de cursos: `resources/js/modules/resources/programa/components/SyllabusReplicarModal.svelte:125`, `resources/js/modules/resources/curso/components/cursoCopyPreviewModal.svelte:212`, `cursoSincronizarIntranetModal.svelte:120,172`.
  - Asignacion de roles y permisos: `resources/js/components/custom/admin/permissions-modal/PermissionsModal.svelte:454-461, 574, 646, 679`.
* **Problema reportado**: Se dispara el error `CSRF token mismatch` tanto al copiar curso como en el asistente de asignacion de roles.
* **Evidencia en codigo**:
  - `resources/views/app.blade.php:8`: Renderiza `<meta name="csrf-token" content="{{ csrf_token() }}">` unicamente en la carga inicial del documento HTML. Cuando el usuario inicia sesion via Inertia (`POST /login`), Laravel regenera la sesion y el token CSRF (`$request->session()->regenerate()`), pero la navegacion SPA de Inertia no recarga `<head>`. Como resultado, todos los componentes que hacen `fetch()` leyendo `document.querySelector('meta[name="csrf-token"]')?.content` (`SyllabusReplicarModal.svelte:125`, `ComponenteTitularModal.svelte:79`, `cursoSincronizarIntranetModal.svelte:120`) envian el token pre-login caducado en `X-CSRF-TOKEN`.
  - `resources/js/components/custom/admin/permissions-modal/PermissionsModal.svelte:454-461`: Extrae la cookie mediante `document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1]`, lo cual trunca cualquier signo `=` no codificado en Base64 o toma una cookie `XSRF-TOKEN` sombreada/desactualizada sin enviar `credentials: 'same-origin'` ni sincronizar con el token de sesion activo de Inertia (`HandleInertiaRequests` no comparte `csrf_token` en `$page.props`).
* **Nota de correccion tecnica**: El reporte menciona `crsf token mismatch` como dos fallos aislados en "copiar curso" y "asignacion de roles", pero ambos provienen del mismo desacoplamiento entre la regeneracion de sesion de Laravel en navegacion SPA (Inertia) y las peticiones manuales `fetch()` / `axios` que leen un `<meta name="csrf-token">` estatico o una cookie `XSRF-TOKEN` desincronizada.

### Modulo Intranet Externa (Oracle): Correo en `ALUMNO` y Tablas Oficiales de Actas
* **Elemento / Contexto**: `app/Models/External/`, `app/Models/Usuario/Estudiante.php`, `app/Services/IntranetService.php`. (Relacionado con tarjetas Trello existentes [`EXT-01`](https://trello.com/c/d72JGlGp) y [`FEAT-05`](https://trello.com/c/SOnZUru1)).
* **Problema reportado**:
  1. Se agrego la columna de correo electronico directamente en la tabla `ALUMNO` de Intranet.
  2. El flujo de foliado y persistencia de actas en la Intranet externa utiliza 5 tablas especificas:
     - `DOC_PERIODO`: entrega el codigo del periodo (`PER_CODIGO`).
     - `REG_FOLIO_DCTO`: se consulta por `PER_CODIGO` para obtener los folios generados en ese periodo y calcular el siguiente folio (`RFD_FOLIO` = `MAX + 1`, no es autoincremental).
     - `REG_CAB_ACTA`: cabecera del acta oficial.
     - `REG_DET_ACTA`: detalle del acta por estudiante, vinculando `INS_ID` y `CBA_ID` (donde `CBA_ID` corresponde al `RFD_FOLIO` generado).
     - `REG_HISTORIA_DENOTA`: tabla de auditoria de cambios de notas.
  3. Las funciones/procedimientos de base de datos del proceso seran enviadas por Rodrigo.
* **Evidencia en codigo**:
  - `app/Models/External/VwAlumno.php:12-32`: Mapea la tabla `ALUMNO` de Oracle pero aún no declara el atributo de correo electronico.
  - `app/Models/Usuario/Estudiante.php:135`: `Estudiante::createFromIntranet` persiste `'email' => null`.
  - `app/Models/External/IntranetBaseModel.php:24-37`: Bloquea por defecto `save()`, `update()` y `delete()` asumiendo que todos los modelos de `App\Models\External` son de solo lectura.
  - No existen modelos en `app/Models/External/` para `DOC_PERIODO`, `REG_FOLIO_DCTO`, `REG_CAB_ACTA`, `REG_DET_ACTA` ni `REG_HISTORIA_DENOTA`.
* **Nota de correccion respecto al backlog previo (`2026-10-20`)**:
  - En `EXT-01` se habia supuesto que el correo vendria en una vista externa separada provista por Victor; ahora se confirma que la columna fue añadida directamente a la tabla `ALUMNO`.
  - En `FEAT-05` se habia planteado crear 3 tablas locales (`acta_cabecera`, `acta_cuerpo`, `acta_auditoria`); ahora se precisa que las 3 tablas de actas (`REG_CAB_ACTA`, `REG_DET_ACTA`, `REG_HISTORIA_DENOTA`) junto con `DOC_PERIODO` y `REG_FOLIO_DCTO` pertenecen al esquema de Intranet externo.

### Modulo Calificaciones y Actas: Motor de Calculo de Notas, Asistencia y Flujos Condicionales
* **Elemento / Contexto**: `app/Services/Student/RendimientoCursoEstudiante.php`, `app/Models/Curso/InscripcionCurso.php`, `app/Models/Curso/InscripcionComponente.php`.
* **Problema reportado**:
  Para rellenar los datos de las actas se requiere calcular:
  1. Promedio parcial (`prom_parcial`), Nota de presentacion (`nota_presentacion`), Porcentaje de asistencia (`% asistencia`) y Promedio final (`prom_final`).
  2. Regla de componente/nota reprobada ("rojo"): si el estudiante tiene un rojo, la nota de presentacion queda en `3.9`, tras lo cual rinde examen ponderado `80%` promedio parcial (o presentacion) y `20%` examen para obtener el promedio final.
  3. Flujos condicionales a evaluar:
     - Si tiene rojo.
     - Si tiene menos de `70%` de asistencia en Taller (`T`) y Laboratorio (`L`).
     - Calculo de promedio final con examen.
* **Evidencia en codigo**:
  - `app/Services/Student/RendimientoCursoEstudiante.php:33-94`: Calcula promedios ponderados por componente y asistencia (`ASISTENCIA_MINIMA = 70`), pero solo con fines informativos para el portal del alumno; no aplica truncamiento a `3.9` por componente reprobado, ni contempla examen `80/20`, ni distingue el bloqueo por asistencia en `T` y `L` para el acta.
  - `app/Models/Base/Curso/BaseInscripcionCurso.php:27-35`: `curso.inscripcion_curso` solo posee la columna `promedio_parcial` (`decimal`) y `estado_inscripcion`; carece de columnas o estructura para `nota_presentacion`, `nota_examen`, `promedio_final` y `porcentaje_asistencia`.

---

## 2. Interpretacion e Investigacion

Esta seccion analiza el panorama estructural que explica los sintomas observados en la Fase 1 y documenta las decisiones de diseño y reglas de negocio acordadas. *(Nota de alcance estricta: Las fallas estructurales y de deuda tecnica se documentan con fines explicativos y no generan tarjetas adicionales fuera de los requerimientos reportados).*

### 2.1. Diagnostico Sistemico y Deuda Tecnica ("Lo que no se vio")

1. **Desincronizacion de Tokens CSRF entre Navegacion SPA (Inertia) y Peticiones Manuales (`fetch` / `axios`)**:
   * El sintoma reportado como `csrf token mismatch` en dos modulos distintos ("copiar curso" y "asignacion de roles") responde a un unico defecto arquitectonico en la capa de transporte del frontend.
   * Por un lado, `resources/views/app.blade.php` inyecta `<meta name="csrf-token" content="{{ csrf_token() }}">` solo durante el renderizado inicial de Blade (tipicamente en `/login`). Al autenticar mediante Inertia (`POST /login`), Laravel ejecuta `$request->session()->regenerate()`, invalidando el token previo y emitiendo uno nuevo, pero el DOM `<head>` nunca se recarga. Todo componente que ejecuta `fetch()` leyendo `meta[name="csrf-token"]` (`SyllabusReplicarModal.svelte`, `cursoSincronizarIntranetModal.svelte`, `ComponenteTitularModal.svelte`) envia un token de invitado caducado.
   * Por otro lado, los modales que leen `document.cookie` manualmente para enviar `X-XSRF-TOKEN` (`PermissionsModal.svelte`) utilizan `c.split('=')[1]`, truncando el relleno Base64 (`=`) cuando no viene codificado o leyendo cookies `XSRF-TOKEN` sombreadas entre rutas/subdominios, mientras que `HandleInertiaRequests` no expone `csrf_token` en `$page.props` para mantener sincronizado el cliente.

2. **Friccion Operativa por Doble Reautenticacion en Acciones de Soporte Administrativo**:
   * En `UsuarioController::changePassword` y `UsuarioController::setDefaultPassword`, se exigia `current_password` del propio administrador bajo el supuesto de mitigar secuestro de sesion. En la operacion diaria de mesa de ayuda y soporte universitario, obligar al SuperAdmin a tipear su propia clave en cada restablecimiento masivo y a la vez ocultar con asteriscos la nueva clave asignada sin un conmutador visual (`Eye` / `EyeOff`) provoca errores de tipeo y bloqueos innecesarios, cuando la sesion ya esta protegida por middleware de autenticacion y `IsAdmin`.

3. **Rigidez de Clonacion Temporal en `CursoService::copiar`**:
   * El servicio de copia de cursos se diseño originalmente pensando solo en la transicion de un semestre al siguiente (por ello reseteaba forzosamente `fecha_limite => null` en todas las actividades). Sin embargo, un caso de uso frecuente dentro de un mismo periodo academico es configurar primero el Paralelo A (con su calendario de evaluaciones ya fechado) y clonarlo hacia el Paralelo B del mismo año y semestre, donde descartar las fechas obliga a reingresarlas manualmente una por una.

4. **Brecha entre el Calculo Informativo de Notas y el Cierre Reglamentario de Actas**:
   * `RendimientoCursoEstudiante` fue concebido como un visor de progreso parcial para el estudiante (promediando actividades sumativas evaluadas a la fecha). El cierre de actas exige, en cambio, una maquina de reglas reglamentarias universitarias: deteccion de calificaciones rojas individuales (`< 4.0`) que truncan la Nota de Presentacion (`NP`) a `3.9`, verificacion de asistencia minima (`70%`) independiente en cada componente practico (`T` y `L`), y una funcion de Promedio Final (`PF`) por tramos segun el rendimiento en el examen (`>= 4.2` vs `< 4.2`).

### 2.2. Decisiones de Diseño y Reglas de Negocio Clarificadas

1. **Regla Matemática de Calificaciones y Examen para Actas**:
   * **Promedio Parcial (`PP`)**: Promedio ponderado de las componentes del curso segun las ponderaciones definidas en el syllabus y sus actividades sumativas.
   * **Nota de Presentacion (`NP`) y Regla del Rojo**: Si el estudiante registra **cualquier nota `< 4.0` en una actividad sumativa individual** del curso, su Nota de Presentacion se trunca a `3.9` (`NP = min(PP, 3.9)`), perdiendo la eximicion y debiendo rendir examen. Si no tiene rojos en actividades sumativas, `NP = PP`.
   * **Reprobacion Directa por Asistencia en Componentes Practicos (`T` y `L`)**: La asistencia minima del `70%` se evalua **por separado en cada componente practico** (Taller `T >= 70%` y Laboratorio `L >= 70%`). Si el estudiante tiene `< 70%` en cualquiera de ellos, **reprueba directamente por inasistencia sin derecho a examen**, quedando su nota final truncada en `min(PP, 3.9)` (tope `3.9`).
   * **Eximicion**: Si el estudiante cumple asistencia (`>= 70%` en `T` y `L`), no tiene ninguna actividad sumativa `< 4.0` y su `NP >= 4.0`, se exime del examen y su Promedio Final es `PF = NP`.
   * **Rendicion de Examen (`80% / 20%`) y Umbral `4.2`**:
     * Razon matematica del `4.2`: Con `NP = 3.9`, $0.8 \times 3.9 + 0.2 \times 4.2 = 3.12 + 0.84 = 3.96 \approx 4.0$. Por tanto, `4.2` es la nota minima de examen que permite alcanzar el `4.0` redondeado a una decima.
     * **Si reprueba el examen (`EX < 4.2`)**: No aprueba la asignatura y su Promedio Final se calcula ponderando la Nota de Presentacion truncada: `PF = round(0.8 * NP + 0.2 * EX, 1)` (lo cual garantiza `PF <= 3.9`).
     * **Si aprueba el examen (`EX >= 4.2`)**: Aprueba la condicion y su Promedio Final se calcula liberando su Promedio Parcial real: `PF = round(0.8 * PP + 0.2 * EX, 1)`.
   * **Registro del Examen en UTAMED**: La nota de examen (`nota_examen`) se registrara directamente en la nomina/pre-acta del curso sobre `curso.inscripcion_curso` (junto con los campos calculados de pre-acta).

2. **Division de Alcance con Intranet Externa (Oracle) y Funciones de Rodrigo**:
   * **Ejecutable de inmediato**:
     * Extraccion y persistencia del correo electronico del alumno desde la columna agregada en la tabla `ALUMNO` de Oracle (`VwAlumno` y `Estudiante::createFromIntranet`), desbloqueando parcialmente [`EXT-01`](https://trello.com/c/d72JGlGp).
     * Motor local de calculo de pre-acta en UTAMED (`PP`, `NP`, `% asistencia` en `T` y `L`, `nota_examen`, `PF` y estado/situacion) con interfaz para ingreso de examen y previsualizacion de acta.
   * **Bloqueado / En espera de Rodrigo**:
     * El mapeo definitivo de los modelos `External` de las 5 tablas de actas (`DOC_PERIODO` -> `PER_CODIGO`, `REG_FOLIO_DCTO` -> `RFD_FOLIO = MAX + 1`, `REG_CAB_ACTA`, `REG_DET_ACTA` con `INS_ID` y `CBA_ID = RFD_FOLIO`, y `REG_HISTORIA_DENOTA`) y la ejecucion de las funciones PL/SQL de emision hacia Oracle quedan documentados bajo [`FEAT-05`](https://trello.com/c/SOnZUru1) a la espera de las funciones que enviara Rodrigo.

3. **Credenciales en Administracion de Usuarios (`/admin/usuarios`)**:
   * Se elimina por completo el campo `"Tu contraseña *"` (`current_password`) en `passwordChangeModal.svelte`, `Usuarios.svelte` y en las validaciones de `UsuarioController::changePassword` y `UsuarioController::setDefaultPassword`.
   * Se mantiene la capacidad del SuperAdmin para ingresar manualmente una `"Nueva Contraseña"` y `"Confirmar Contraseña"` en `passwordChangeModal.svelte`, incorporando el boton con icono de ojo (`Eye` / `EyeOff`) para visualizar las contraseñas escritas.

4. **Copia de Curso y Selector de Fechas**:
   * En `cursoCopyPreviewModal.svelte`, cuando el año y semestre destino coinciden con los del curso origen (`newAgnoReal === preview.curso.agno_real && newSemestreReal === preview.curso.semestre_real`), se habilita un selector/toggle (`copiar_fechas`, activo por defecto) para conservar las `fecha_limite` de las actividades clonadas. Si el periodo destino es distinto, `copiar_fechas` se fuerza a `false` y las fechas quedan vacias.

5. **Requerimientos en Levantamiento (`Por definir`)**:
   * Los puntos *"ambiente ayudantes tocar mas"* y *"como jefe carrera tener reunion con ignacio"* quedan registrados como elementos pendientes de levantamiento funcional (`Por definir`) sin generar tarjetas de implementacion hasta realizar dichas sesiones de definicion.

---

## 3. Planificacion (Releases y Tablero Kanban)

### Release 1: Estabilidad CSRF, Credenciales SuperAdmin y Copia de Cursos
*Foco: Resolucion transversal del error 419 CSRF token mismatch en modales administrativos, simplificacion del cambio/reseteo de contraseñas para SuperAdmin con icono de ojo, y copia opcional de fechas en cursos del mismo periodo.*

```bash
git checkout main
git pull origin main
git checkout -b feature/r1-admin-csrf-passwords-copia
```

* ### `[BUG-01]` [3 pts] [R1] [P0] Sincronizacion reactiva de token CSRF en navegacion SPA para copia de cursos y asignacion de roles
  * **Ruta**: `/admin/cursos`, `/admin/syllabus`, `/admin/usuarios`
  * **Archivos**:
    * `app/Http/Middleware/HandleInertiaRequests.php`
    * `resources/js/app.ts`
    * `resources/js/bootstrap.ts`
    * `resources/js/components/custom/admin/permissions-modal/PermissionsModal.svelte`
    * `resources/js/modules/resources/programa/components/SyllabusReplicarModal.svelte`
    * `resources/js/modules/resources/curso/components/cursoCopyPreviewModal.svelte`
    * `resources/js/modules/resources/curso/components/cursoSincronizarIntranetModal.svelte`
    * `resources/js/modules/resources/curso/components/cursoSincronizarMasivoModal.svelte`
    * `resources/js/modules/resources/curso/components/ComponenteTitularModal.svelte`
    * `resources/js/modules/resources/curso/services/cursoApi.ts`
    * `resources/js/modules/resources/curso/services/permisosApi.ts`
  * **Problema & Causa**: Tras iniciar sesion via Inertia (`POST /login`), Laravel regenera el ID de sesion y el token CSRF, pero `<meta name="csrf-token">` en `app.blade.php` permanece con el token previo al login. A la vez, `PermissionsModal.svelte` extrae `XSRF-TOKEN` con `c.split('=')[1]` (truncando caracteres `=` o leyendo cookies `XSRF-TOKEN` desincronizadas), causando errores HTTP 419 (`CSRF token mismatch.`) al copiar/replicar cursos y al asignar o revocar roles y permisos.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Exponer `'csrf_token' => fn () => csrf_token()` en `HandleInertiaRequests::share()` para que viaje actualizado en cada respuesta de Inertia.
    * [ ] En `resources/js/app.ts` (o listener global de eventos de Inertia), actualizar automaticamente el atributo `content` de `<meta name="csrf-token">` y `window.axios.defaults.headers.common['X-CSRF-TOKEN']` en cada visita/navegacion de Inertia.
    * [ ] Centralizar un helper `getCsrfToken()` / `csrfHeaders()` que priorice el token sincronizado de Inertia (`X-CSRF-TOKEN`) y corrija la lectura de `XSRF-TOKEN` sin truncar en `=`, incluyendo `credentials: 'same-origin'` en llamadas `fetch()`.
    * [ ] Reemplazar la lectura manual defectuosa de CSRF en `PermissionsModal.svelte`, `SyllabusReplicarModal.svelte`, `cursoSincronizarIntranetModal.svelte`, `cursoSincronizarMasivoModal.svelte`, `ComponenteTitularModal.svelte`, `cursoApi.ts` y `permisosApi.ts`.
    * [ ] Verificar mediante test Feature/ unidad que el cambio de sesion tras login actualiza el prop `csrf_token` y que las peticiones de copia de curso y asignacion de roles responden 200/302 sin error 419.

* ### `[UI-01]` [2 pts] [R1] [P1] Remocion de validacion de contraseña propia del administrador y conmutador de visibilidad (ojo) en cambio de contraseña
  * **Ruta**: `/admin/usuarios`
  * **Archivos**:
    * `app/Http/Controllers/Admin/UsuarioController.php`
    * `resources/js/modules/resources/usuario/components/passwordChangeModal.svelte`
    * `resources/js/pages/admin/Usuarios.svelte`
    * `resources/js/modules/resources/usuario/services/usuarioApi.ts`
  * **Problema & Causa**: `UsuarioController::changePassword` y `UsuarioController::setDefaultPassword` exigen `'current_password' => ['required', 'string', 'current_password']`, y `passwordChangeModal.svelte` obliga al SuperAdmin a escribir su propia contraseña tanto para fijar una clave nueva como para reiniciar a la contraseña por defecto (RUT). Ademas, los campos de nueva contraseña y confirmacion carecen de boton con icono de ojo para verificar visualmente lo escrito.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Eliminar la regla `'current_password'` de `UsuarioController::changePassword` y `UsuarioController::setDefaultPassword` (manteniendo intacta la autorizacion `authorize('update', $usuario)` y la guardia anti-escalada de SuperAdmin).
    * [ ] Eliminar el input `id="current_password"` ("Tu contraseña *") de `passwordChangeModal.svelte` y quitar las validaciones de `current_password` en `Usuarios.svelte` (`openResetDialog`, `handleResetDefault`, `passwordFormData`) y `usuarioApi.ts`.
    * [ ] Incorporar un boton conmutador con icono de ojo (`Eye` / `EyeOff`, mostrando `type="text"` o `type="password"`) en los campos "Nueva Contraseña" y "Confirmar Contraseña" dentro de `passwordChangeModal.svelte`.
    * [ ] Actualizar los tests de cambio y reseteo de contraseña administrativa para reflejar que no se requiere `current_password`.

* ### `[FEAT-01]` [3 pts] [R1] [P1] Copia condicional de fechas de actividades con selector cuando el curso destino pertenece al mismo periodo
  * **Ruta**: `/admin/cursos` (Modal Copiar Curso)
  * **Archivos**:
    * `app/Services/CursoService.php`
    * `app/Http/Controllers/Admin/CursoController.php`
    * `resources/js/modules/resources/curso/components/cursoCopyPreviewModal.svelte`
    * `tests/Feature/CursoCopiarTest.php`
  * **Problema & Causa**: `CursoService::copiar()` resetea siempre `fecha_limite => null` en todas las actividades clonadas. Cuando se copia un curso dentro del mismo año y semestre (ej. crear el Grupo B a partir del Grupo A), el administrador pierde todas las fechas ya configuradas; en cambio, cuando se copia a un periodo distinto, las fechas efectivamente no deben copiarse.
  * **Criterios de Aceptación (DoD)**:
    * [ ] En `cursoCopyPreviewModal.svelte`, detectar reactivamente si el periodo destino coincide con el periodo del curso origen (`Number(newAgnoReal) === Number(preview.curso.agno_real) && Number(newSemestreReal) === Number(preview.curso.semestre_real)`).
    * [ ] Cuando sea el mismo periodo, mostrar un selector/checkbox (`copiar_fechas`, marcado por defecto en `true`) que permita elegir si copiar o no las fechas limite de las actividades, actualizando la previsualizacion de la tabla de actividades (mostrando la fecha conservada o `-> vacia` segun el selector).
    * [ ] Cuando el periodo destino sea distinto al de origen, desactivar/ocultar la copia de fechas (`copiar_fechas = false`) y mantener el aviso de que las fechas quedaran vacias.
    * [ ] En `CursoController@copiar` y `CursoService::copiar()`, aceptar el booleano `copiar_fechas`, preservando `$act->fecha_limite` solo cuando `copiar_fechas === true` **y** el curso destino pertenezca al mismo `agno_real` y `semestre_real` que el curso padre (forzando `null` en backend si el periodo difiere).
    * [ ] Añadir pruebas en `tests/Feature/CursoCopiarTest.php` para los tres escenarios: mismo periodo con `copiar_fechas = true`, mismo periodo con `copiar_fechas = false`, y distinto periodo intentando enviar `copiar_fechas = true`.

---

### Release 2: Integracion de Correo Intranet y Motor de Calculo de Pre-Actas
*Foco: Lectura del correo de alumnos desde la tabla `ALUMNO` de Oracle, motor local de calculo reglamentario de actas (PP, NP con tope 3.9 por rojo, asistencia 70% en T/L y PF con examen 80/20 umbral 4.2), y preparacion de la integracion con las 5 tablas de actas de Oracle.*

```bash
git checkout main
git pull origin main
git checkout -b feature/r2-calculo-actas-correo-intranet
```

* ### `[EXT-01]` [2 pts] [R2] [P1] Extraccion y persistencia del correo electronico desde tabla `ALUMNO` de Intranet Externa
  * **Tarjeta Trello vinculada**: [`EXT-01`](https://trello.com/c/d72JGlGp)
  * **Ruta**: `/admin/inscripciones_cursos`, sincronizacion de alumnos desde Intranet (`CursoSincronizarIntranetModal`)
  * **Archivos**:
    * `app/Models/External/VwAlumno.php`
    * `app/DTOs/External/AlumnoIntranetData.php`
    * `app/Models/Usuario/Estudiante.php`
    * `app/Services/IntranetService.php`
  * **Problema & Causa**: Se incorporo la columna de correo electronico directamente en la tabla `ALUMNO` de la Intranet externa (Oracle), pero `VwAlumno` no la mapea y `Estudiante::createFromIntranet` sigue fijando `'email' => null` al crear o sincronizar estudiantes.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Mapear y normalizar (con `trim` y `mb_strtolower`, retornando `null` si viene vacio) el atributo de correo electronico en `VwAlumno` y en `AlumnoIntranetData`.
    * [ ] Actualizar `Estudiante::createFromIntranet` para guardar el correo electronico en `usuario.email` al crear el usuario y actualizarlo si el usuario ya existia con `email === null`.
    * [ ] Mantener fallback defensivo a `null` si el registro de `ALUMNO` no trae correo valido o si colisiona, sin interrumpir la inscripcion automatica.
    * [ ] Cubrir con test unitario/feature la creacion y actualizacion de email desde `Estudiante::createFromIntranet`.

* ### `[FEAT-02]` [5 pts] [R2] [P0] Motor de calculo reglamentario de actas (PP, NP, asistencia T/L y PF con examen) y registro de nota de examen
  * **Ruta**: `/docente/cursos/{curso}`, `/admin/cursos` (Nomina / Pre-acta de calificaciones)
  * **Archivos**:
    * `database/migrations/` (Nueva migracion para `nota_examen`, `nota_presentacion`, `porcentaje_asistencia`, `promedio_final` y `situacion_acta` en `curso.inscripcion_curso`)
    * `app/Models/Curso/InscripcionCurso.php`
    * `app/Services/Curso/CalculoActaCursoService.php` (Nuevo servicio de dominio)
    * `app/Http/Controllers/Docente/DocenteCursoController.php` (o controlador de pre-acta/calificaciones)
    * `resources/js/` (Vista/panel de pre-acta e ingreso de nota de examen)
  * **Problema & Causa**: UTAMED carece del motor reglamentario para rellenar los datos de las actas oficiales (`promedio_parcial`, `nota_presentacion`, `% asistencia`, `nota_examen` y `promedio_final`) y de los flujos condicionales exigidos por reglamento universitario.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Agregar soporte de persistencia en `curso.inscripcion_curso` para `nota_presentacion` (`numeric(2,1)`), `nota_examen` (`numeric(2,1)` nullable), `porcentaje_asistencia` (`smallint` nullable) y `promedio_final` (`numeric(2,1)` nullable).
    * [ ] Implementar en `CalculoActaCursoService` el calculo de **Promedio Parcial (`PP`)** ponderando las componentes y sus actividades sumativas.
    * [ ] Implementar la **Regla del Rojo en Nota de Presentacion (`NP`)**: si el estudiante tiene cualquier nota `< 4.0` en una actividad sumativa individual del curso, `NP = min(PP, 3.9)` (si `PP >= 4.0` baja a `3.9`, obligandolo a dar examen); sin rojos, `NP = PP`.
    * [ ] Implementar la **Regla de Asistencia (`< 70%` en `T` y `L`)**: calcular el porcentaje de asistencia en cada componente practico (Taller `T` y Laboratorio `L`) por separado. Si en cualquiera de ellos tiene `< 70%`, reprueba directamente por inasistencia sin derecho a examen y su `PF` queda truncado en `min(PP, 3.9)` (`<= 3.9`).
    * [ ] Implementar el **Flujo de Eximicion y Examen (`80% / 20%` con umbral `4.2`)**:
      * Si cumple asistencia (`>= 70%` en `T` y `L`), no tiene rojos y `NP >= 4.0`, se exime (`PF = NP`).
      * Si rinde examen y obtiene `nota_examen < 4.2`, reprueba y su promedio final es `PF = round(0.8 * NP + 0.2 * nota_examen, 1)`.
      * Si rinde examen y obtiene `nota_examen >= 4.2`, aprueba y su promedio final se calcula con su promedio parcial sin truncar: `PF = round(0.8 * PP + 0.2 * nota_examen, 1)`.
    * [ ] Exponer en la interfaz del curso la tabla de pre-acta con `PP`, `NP`, `% Asistencia (T / L)`, input para registrar `nota_examen` (habilitado solo para alumnos con derecho/obligacion de examen) y `PF` resultante, junto con test unitario exhaustivo de todos los casos borde (`PP >= 4.0` con rojo, `EX = 4.1` vs `EX = 4.2`, asistencia `< 70%` en `T` o `L`).

* ### `[EXT-02]` [5 pts] [R2] [P2] [Bloqueada por Rodrigo / TI] Mapeo de las 5 tablas de actas en Intranet Externa e integracion de funciones PL/SQL
  * **Tarjeta Trello vinculada**: [`FEAT-05`](https://trello.com/c/SOnZUru1)
  * **Ruta**: `/admin/cursos`, cierre y emision de acta hacia Oracle
  * **Archivos**:
    * `app/Models/External/VwDocPeriodo.php` (`DOC_PERIODO`)
    * `app/Models/External/VwRegFolioDcto.php` (`REG_FOLIO_DCTO`)
    * `app/Models/External/VwRegCabActa.php` (`REG_CAB_ACTA`)
    * `app/Models/External/VwRegDetActa.php` (`REG_DET_ACTA`)
    * `app/Models/External/VwRegHistoriaDenota.php` (`REG_HISTORIA_DENOTA`)
    * `app/Services/IntranetService.php`
  * **Problema & Causa**: La emision oficial de actas en la Intranet externa requiere consultar `DOC_PERIODO` (`PER_CODIGO`), reservar el folio en `REG_FOLIO_DCTO` (`RFD_FOLIO = MAX + 1` para dicho periodo), y poblar las 3 tablas de actas: `REG_CAB_ACTA` (cabecera), `REG_DET_ACTA` (detalle con `INS_ID` y `CBA_ID = RFD_FOLIO`) y `REG_HISTORIA_DENOTA` (auditoria de cambios), invocando las funciones que enviara Rodrigo.
  * **Criterios de Aceptación (DoD)**:
    * [ ] [Bloqueante externo] Recibir de Rodrigo las especificaciones de columnas y funciones/procedimientos PL/SQL del proceso de actas en Oracle.
    * [ ] Crear los modelos en `app/Models/External/` para `DOC_PERIODO`, `REG_FOLIO_DCTO`, `REG_CAB_ACTA`, `REG_DET_ACTA` y `REG_HISTORIA_DENOTA`.
    * [ ] Implementar la obtencion de `PER_CODIGO` desde `DOC_PERIODO` y el calculo transaccional de `RFD_FOLIO` (`MAX(RFD_FOLIO) + 1` filtrado por periodo en `REG_FOLIO_DCTO`).
    * [ ] Conectar los resultados de `CalculoActaCursoService` (`PP`, `NP`, `% asistencia`, `nota_examen`, `PF`) con la insercion/invocacion hacia `REG_CAB_ACTA`, `REG_DET_ACTA` (`INS_ID`, `CBA_ID = RFD_FOLIO`) y `REG_HISTORIA_DENOTA`, actualizando `curso.estado_acta` a `ENVIADO`.

---

### Pendientes de Levantamiento Funcional (Lista `Por definir` — Fuera del Sprint de Implementacion)

Los siguientes puntos quedan documentados como compromisos de relevamiento previo y **no suman puntos de historia ni generan tareas de codigo** hasta realizar las sesiones correspondientes:

* **`[DEF-01]` Alcance operativo del ambiente de Ayudantes (`/ayudante/*`)**: Definir que modulos adicionales a Syllabus y Mensajeria (Actividades, Evaluacion, Asistencia, Grupos, Unidades) podra operar el ayudante en funcion de los permisos delegados por el Docente Titular.
* **`[DEF-02]` Reunion con Ignacio sobre vista de Jefe de Carrera (`/docente/jefe-carrera/*`)**: Realizar sesion de levantamiento con Ignacio para acordar requerimientos y ajustes especificos del rol Jefe de Carrera.

---

## 4. Metricas de Planificacion

* **Total de Tarjetas de Implementacion**: 6 (mas 2 hitos en `Por definir`)
* **Puntos por Release**:
  * **Release 1 (Estabilidad CSRF, Credenciales SuperAdmin y Copia de Cursos)**: 8 puntos (3 tarjetas: `BUG-01` [3 pts], `UI-01` [2 pts], `FEAT-01` [3 pts])
  * **Release 2 (Correo Intranet y Motor de Actas)**: 12 puntos (3 tarjetas: `EXT-01` [2 pts], `FEAT-02` [5 pts], `EXT-02` [5 pts — bloqueada por Rodrigo])
* **Total de Puntos de Historia Estimados**: **20 puntos** (15 puntos de ejecucion inmediata + 5 puntos bloqueados por TI/Rodrigo)
