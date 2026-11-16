# Backlog de Trabajo: Credenciales SuperAdmin, Copia de Cursos, CSRF y Motor de Actas Intranet

* **Fecha**: 2026-11-11
* **Rama Base**: `main`
* **Tablero Trello Activo**: `arreglos-reunion-23-09-2026` (`https://trello.com/b/H5mt3Cd8/arreglos-reunion-23-09-2026`)
* **Metodologia**: Triada `OBSERVACIONES` > `INTERPRETACION E INVESTIGACION` > `PLANIFICACION`
* **Estado**: Refinado y sincronizado con el tablero Trello (`2026-11-11`; registro documental reconstruido desde Trello el `2026-11-12`)

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

1. **Regla Matemática de Calificaciones y Examen para Actas (Refinado 2026-11-12)**:
   * **Promedio Parcial (`PP`) y Tratamiento de Notas Faltantes**: Promedio ponderado de las componentes del curso segun las ponderaciones definidas en el syllabus (`SeccionIXContenido`) y sus actividades sumativas (`COALESCE(integrante_grupo.nota_individual, actividad_asignada_grupo.nota)`). Al cierre o calculo del acta, cualquier actividad sumativa sin calificacion (`null`) se computa automaticamente con nota minima **`1.0`**.
   * **Nota de Presentacion (`NP`) y Regla del Rojo**: Si el estudiante registra **cualquier nota individual `< 4.0` en una actividad sumativa** (incluyendo aquellas evaluadas como `1.0` por `null`, aun cuando el promedio de su componente supere `4.0`), su Nota de Presentacion se trunca a `3.9` (`NP = min(PP, 3.9)`), perdiendo eximicion y debiendo rendir examen. Si no tiene rojos en actividades sumativas, `NP = PP`.
   * **Reprobacion Directa por Asistencia Configurable (`componente.porcentaje_asistencia_obligatoria`, fallback `70%`)**: El umbral de asistencia minima se lee desde `componente.porcentaje_asistencia_obligatoria` (usando `70%` solo como valor por defecto cuando no este definido) y se evalua **por separado en cada componente del curso**. Si el estudiante queda bajo el minimo configurado en cualquier componente obligatorio, **reprueba directamente por inasistencia sin derecho a examen**, quedando su nota final truncada en `min(PP, 3.9)` (tope `3.9`).
   * **Eximicion**: Si el estudiante cumple la asistencia minima en todos los componentes, no tiene ninguna actividad sumativa `< 4.0` y su `NP >= 4.0`, se exime del examen y su Promedio Final es `PF = NP`.
   * **Rendicion de Examen (`80% / 20%`) y Umbral `4.2`**:
     * Razon matematica del `4.2`: Con `NP = 3.9`, $0.8 \times 3.9 + 0.2 \times 4.2 = 3.12 + 0.84 = 3.96 \approx 4.0$. Por tanto, `4.2` es la nota minima de examen que permite alcanzar el `4.0` redondeado a una decima.
     * **Si reprueba el examen (`EX < 4.2`)**: No aprueba la asignatura y su Promedio Final se calcula ponderando la Nota de Presentacion truncada: `PF = round(0.8 * NP + 0.2 * EX, 1)` (lo cual garantiza `PF <= 3.9`).
     * **Si aprueba el examen (`EX >= 4.2`)**: Aprueba la condicion y su Promedio Final se calcula liberando su Promedio Parcial real: `PF = round(0.8 * PP + 0.2 * EX, 1)`.
   * **Persistencia Local y Alcance Backend-First**: Se agregan columnas de pre-acta en `curso.inscripcion_curso` y una tabla local de cierre/actas + bitacora de sincronizacion Oracle en PostgreSQL. El componente de destino oficial para el acta es el que tiene **`componente.genera_acta = true`** (con su `intranet_cur_codigo` e `INS_ID` desde `curso.inscripcion_componente.cod_inscripcion_curso_uta`), reflejando el resultado consolidado del curso. La capa UI/UX se pospone hasta definicion formal; `FEAT-05` entrega backend, migracion, Adapters y Facade invocables desde tests/Tinker/app.

2. **Division de Alcance con Intranet Externa (Oracle) y Desbloqueo de `FEAT-05`**:
   * **Desbloqueado y ejecutado (`EXT-01`, `En revisión`)**:
     * Extraccion y persistencia del correo electronico del alumno desde la columna agregada directamente en la tabla `ALUMNO` de Oracle (`VwAlumno`, `AlumnoIntranetData`, `Estudiante::createFromIntranet`), desbloqueando [`EXT-01`](https://trello.com/c/d72JGlGp).
     * **Regla de Negocio Acordada (Politica de Autoridad de Intranet)**: La Intranet institucional es la fuente canonica de identidad. Por tanto, en cada sincronizacion o alta de alumno, si el registro de Intranet provee un correo electronico valido, este **sobreescribe incondicionalmente** el campo `usuario.email` en UTAMED (manteniendo fallback defensivo a `null` si no trae correo valido o si colisiona).
   * **Desbloqueo y Arquitectura en 4 Sub-bloques (`FEAT-05`, `Pendiente` — Refinada y Lista)**:
     * Durante el refinamiento se unificaron el motor local (`FEAT-02`, 5 pts) y la integracion Oracle (`EXT-02`, 5 pts) en [`FEAT-05`](https://trello.com/c/SOnZUru1) (13 pts, P2). Tras inspeccionar los 4 scripts PHP heredados (`mandar_clases.php`, `mandar_asistencia.php`, `masivoTestear.php`, `masivonotasTestear.php`) y los metadatos vivos de Oracle para el esquema `UTAMED`, **se levanta el bloqueo externo** organizando el alcance en 4 sub-bloques integrados detras de una Fachada + Adapters:
       1. Calculo y cierre local de actas en UTAMED (PostgreSQL).
       2. Envio de clases y asistencia via Adapter (`GESTIONCURSO.f_actual_clases_utamed`, `GESTIONCURSO.f_actual_asistencia_utamed`).
       3. Envio de actividades de evaluacion y notas parciales via Adapter (`GESTIONCURSO.f_actual_sistema_eval_utamed`, `GESTIONCURSO.f_actual_notaparc_utamed`).
       4. Envio transaccional de actas finales a Oracle (`DOCENCIA.DOC_PERIODO`, `REGISTRADURIA.F_OBTENERFOLIODOCTO`, `REGISTRADURIA.REG_FOLIO_DCTO`, `REGISTRADURIA.REG_CAB_ACTA`, `REGISTRADURIA.REG_DET_ACTA`, `REGISTRADURIA.REG_HISTORIA_DENOTA`).

3. **Credenciales en Administracion de Usuarios (`/admin/usuarios`)**:
   * Se elimina por completo el campo `"Tu contraseña *"` (`current_password`) en `passwordChangeModal.svelte`, `Usuarios.svelte` y en las validaciones de `UsuarioController::changePassword` y `UsuarioController::setDefaultPassword`.
   * Se mantiene la capacidad del SuperAdmin para ingresar manualmente una `"Nueva Contraseña"` y `"Confirmar Contraseña"` en `passwordChangeModal.svelte`, incorporando el boton con icono de ojo (`Eye` / `EyeOff`) para visualizar las contraseñas escritas.

4. **Copia de Curso y Selector de Fechas**:
   * En `cursoCopyPreviewModal.svelte`, cuando el año y semestre destino coinciden con los del curso origen (`newAgnoReal === preview.curso.agno_real && newSemestreReal === preview.curso.semestre_real`), se habilita un selector/toggle (`copiar_fechas`, activo por defecto) para conservar las `fecha_limite` de las actividades clonadas. Si el periodo destino es distinto, `copiar_fechas` se fuerza a `false` y las fechas quedan vacias.

5. **Requerimientos en Levantamiento (`Por definir`)**:
   * Los puntos *"ambiente ayudantes tocar mas"* y *"como jefe carrera tener reunion con ignacio"* quedaron formalizados en Trello dentro de la lista `Por definir (preguntar al director)` como [`DEF-01`](https://trello.com/c/b8ulD7M6) y [`DEF-02`](https://trello.com/c/9rQWuNRG), con sus pautas y temarios de relevamiento funcional, sin sumar puntos de historia hasta completar dichas definiciones.

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
  * **Tarjeta Trello**: [`#161` · `BUG-01`](https://trello.com/c/vp8D6aeX) | **Lista**: `Pendiente` | **Asignado**: `@chopancho`
  * **Rutas**: `/admin/cursos`, `/admin/syllabus`, `/admin/usuarios`
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
  * **Problema & Causa**: Tras iniciar sesion via Inertia (`POST /login`), Laravel regenera el ID de sesion y el token CSRF, pero `<meta name="csrf-token">` en `app.blade.php` permanece con el token previo al login al no recargarse el `<head>` en navegaciones SPA. A la vez, `PermissionsModal.svelte` extrae `XSRF-TOKEN` con `c.split('=')[1]` (truncando caracteres `=` en Base64 o leyendo cookies sombreadas), provocando errores HTTP 419 (`CSRF token mismatch.`) al copiar/replicar cursos y al asignar o revocar roles y permisos.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Exponer `'csrf_token' => fn () => csrf_token()` en `HandleInertiaRequests::share()` para que viaje actualizado en cada respuesta de Inertia.
    * [ ] En `resources/js/app.ts` (listener global de navegacion de Inertia), actualizar automaticamente el atributo `content` de `<meta name="csrf-token">` y `window.axios.defaults.headers.common['X-CSRF-TOKEN']` en cada visita/navegacion.
    * [ ] Centralizar un helper `getCsrfToken()` / `csrfHeaders()` que priorice el token sincronizado de Inertia (`X-CSRF-TOKEN`) y corrija la lectura de `XSRF-TOKEN` sin truncar en `=`, incluyendo `credentials: 'same-origin'` en llamadas `fetch()`.
    * [ ] Reemplazar la lectura manual defectuosa de CSRF en `PermissionsModal.svelte`, `SyllabusReplicarModal.svelte`, `cursoCopyPreviewModal.svelte`, `cursoSincronizarIntranetModal.svelte`, `cursoSincronizarMasivoModal.svelte`, `ComponenteTitularModal.svelte`, `cursoApi.ts` y `permisosApi.ts`.
    * [ ] Verificar mediante test Feature / unidad que el cambio de sesion tras login actualiza el prop `csrf_token` y que las peticiones de copia de curso y asignacion de roles responden 200/302 sin error 419.

* ### `[UI-01]` [2 pts] [R1] [P1] Remocion de validacion de contraseña propia de admin y conmutador de visibilidad (ojo) en cambio de contraseña
  * **Tarjeta Trello**: [`#162` · `UI-01`](https://trello.com/c/HEq5ZHSy) | **Lista**: `Pendiente` | **Asignado**: `@chopancho`
  * **Ruta**: `/admin/usuarios`
  * **Archivos**:
    * `app/Http/Controllers/Admin/UsuarioController.php`
    * `resources/js/modules/resources/usuario/components/passwordChangeModal.svelte`
    * `resources/js/pages/admin/Usuarios.svelte`
    * `resources/js/modules/resources/usuario/services/usuarioApi.ts`
  * **Problema & Causa**: `UsuarioController::changePassword` y `UsuarioController::setDefaultPassword` exigen `'current_password' => ['required', 'string', 'current_password']`, y `passwordChangeModal.svelte` obliga al SuperAdmin a escribir su propia contraseña tanto para fijar una clave nueva como para reiniciar a la contraseña por defecto (RUT). Ademas, los campos de nueva contraseña y confirmacion carecen de boton con icono de ojo para verificar visualmente lo escrito, provocando friccion y errores de tipeo en tareas de soporte administrativo.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Eliminar la regla `'current_password'` de `UsuarioController::changePassword` y `UsuarioController::setDefaultPassword` (manteniendo intacta la autorizacion `authorize('update', $usuario)` y la guardia anti-escalada de SuperAdmin).
    * [ ] Eliminar el input `id="current_password"` ("Tu contraseña *") de `passwordChangeModal.svelte` y quitar las validaciones de `current_password` en `Usuarios.svelte` (`openResetDialog`, `handleResetDefault`, `passwordFormData`) y `usuarioApi.ts`.
    * [ ] Incorporar un boton conmutador con icono de ojo (`Eye` / `EyeOff`, alternando `type="text"` o `type="password"`) en los campos "Nueva Contraseña" y "Confirmar Contraseña" dentro de `passwordChangeModal.svelte`.
    * [ ] Actualizar los tests de cambio y reseteo de contraseña administrativa para reflejar que no se requiere `current_password`.

* ### `[FEAT-01]` [3 pts] [R1] [P1] Copia condicional de fechas de actividades con selector cuando el curso destino pertenece al mismo periodo
  * **Tarjeta Trello**: [`#163` · `FEAT-01`](https://trello.com/c/r49hBgUQ) | **Lista**: `Pendiente` | **Asignado**: `@chopancho`
  * **Ruta**: `/admin/cursos` (Modal Copiar Curso)
  * **Archivos**:
    * `app/Services/CursoService.php`
    * `app/Http/Controllers/Admin/CursoController.php`
    * `resources/js/modules/resources/curso/components/cursoCopyPreviewModal.svelte`
    * `tests/Feature/CursoCopiarTest.php`
  * **Problema & Causa**: `CursoService::copiar()` resetea siempre `fecha_limite => null` en todas las actividades clonadas. Cuando se copia un curso dentro del mismo año y semestre (ej. crear la Seccion B a partir de la Seccion A), el administrador pierde todas las fechas ya configuradas; en cambio, cuando se copia a un periodo distinto, las fechas efectivamente no deben copiarse.
  * **Criterios de Aceptación (DoD)**:
    * [ ] En `cursoCopyPreviewModal.svelte`, detectar reactivamente si el periodo destino coincide con el periodo del curso origen (`Number(newAgnoReal) === Number(preview.curso.agno_real) && Number(newSemestreReal) === Number(preview.curso.semestre_real)`).
    * [ ] Cuando sea el mismo periodo, mostrar un selector/checkbox (`copiar_fechas`, marcado por defecto en `true`) que permita elegir si copiar o no las fechas limite de las actividades, actualizando la previsualizacion de la tabla de actividades (mostrando la fecha conservada o `-> vacia` segun el selector).
    * [ ] Cuando el periodo destino sea distinto al de origen, desactivar/ocultar la copia de fechas (`copiar_fechas = false`) y mantener el aviso de que las fechas quedaran vacias.
    * [ ] En `CursoController@copiar` y `CursoService::copiar()`, aceptar el booleano `copiar_fechas`, preservando `$act->fecha_limite` solo cuando `copiar_fechas === true` **y** el curso destino pertenezca al mismo `agno_real` y `semestre_real` que el curso padre (forzando `null` en backend si el periodo difiere).
    * [ ] Añadir pruebas en `tests/Feature/CursoCopiarTest.php` para los tres escenarios: mismo periodo con `copiar_fechas = true`, mismo periodo con `copiar_fechas = false`, y distinto periodo intentando enviar `copiar_fechas = true`.

---

### Release 2: Integracion de Correo Intranet, Motor de Actas y Sincronizacion Oracle
*Foco: Lectura y sincronizacion autoritativa del correo de alumnos desde la tabla `ALUMNO` de Oracle (`EXT-01`, ya en `En revisión`), y epica backend-first unificada (`FEAT-05`) para calculo reglamentario local, Adapters hacia PL/SQL legacy (clases/asistencia/sistema_eval/notaparc) y emision transaccional de actas finales hacia las 5 tablas de Oracle.*

```bash
git checkout main
git pull origin main
git checkout -b feature/r2-calculo-actas-correo-intranet
# Nota: para la epica FEAT-05 en Trello figura la rama sugerida feature/r2-motor-actas-emision-oracle
```

* ### `[EXT-01]` [2 pts] [R2] [P1] Extraccion y persistencia del correo electronico desde tabla `ALUMNO` de Intranet Externa
  * **Tarjeta Trello**: [`#93` · `EXT-01`](https://trello.com/c/d72JGlGp) | **Lista**: `En revisión` | **Asignado**: `@chopancho`
  * **Rama**: `feature/r2-calculo-actas-correo-intranet` (Commit reportado en Trello: `dea3843d`)
  * **Ruta**: `/admin/inscripciones_cursos`, sincronizacion de alumnos desde Intranet (`CursoSincronizarIntranetModal`)
  * **Archivos**:
    * `app/Models/External/VwAlumno.php`
    * `app/DTOs/External/AlumnoIntranetData.php`
    * `app/Models/Usuario/Estudiante.php`
    * `app/Services/IntranetService.php`
    * `tests/Feature/IntranetSincronizacionTest.php`
  * **Problema & Causa**: Inicialmente esta tarjeta figuraba como `[Bloqueada TI]` a la espera de una vista extendida externa. Se confirmo que la columna de correo electronico fue incorporada directamente en la tabla nativa `ALUMNO` de Oracle. Sin embargo, `VwAlumno` no la mapeaba y `Estudiante::createFromIntranet` seguia fijando `'email' => null` al sincronizar alumnos.
  * **Regla de Negocio Acordada (Politica de Autoridad de Intranet)**: La Intranet institucional es la fuente canonica de identidad. Por tanto, en cada sincronizacion o alta de alumno, si el registro de Intranet provee un correo electronico valido, este **sobreescribe incondicionalmente** el campo `usuario.email` en UTAMED, garantizando que las cuentas reflejen siempre el email oficial de la universidad.
  * **Criterios de Aceptación (DoD)**:
    * [x] Mapear y normalizar (con `trim` y `mb_strtolower`, retornando `null` si viene vacio) el atributo de correo electronico en `VwAlumno` y en `AlumnoIntranetData`.
    * [x] Actualizar `Estudiante::createFromIntranet` para guardar el email en `usuario.email` al crear nuevos usuarios estudiantes.
    * [x] Implementar la politica de autoridad de Intranet: en cada sincronizacion, sobreescribir incondicionalmente el email existente en `usuario.email` si el registro de `ALUMNO` provee una casilla valida.
    * [x] Mantener fallback defensivo a `null` si el registro de `ALUMNO` no trae correo valido o si colisiona, sin interrumpir la inscripcion ni la matricula del alumno.
    * [x] Cubrir con test Feature / Unitario la extraccion, normalizacion y sobreescritura autoritativa del email desde `Estudiante::createFromIntranet`.
  * **Registro de Implementacion (Comentario Trello `2026-11-11`)**:
    * Commit `dea3843d` (`feat(ext-01): extraer y sincronizar autoritativamente email de alumno desde intranet`) en rama `feature/r2-calculo-actas-correo-intranet`.
    * Archivos modificados segun comentario de cierre: `app/Models/External/VwAlumno.php`, `app/DTOs/External/AlumnoIntranetData.php`, `app/Providers/IntranetViewConnectionProvider.php`, `app/Models/Usuario/Estudiante.php`, `app/Services/EstudianteService.php`, `database/factories/External/VwAlumnoFactory.php`, `tests/Unit/External/IntranetModelTransformationsTest.php`, `tests/Unit/Services/EstudianteCreateFromIntranetLogicTest.php`, `tests/Feature/EstudianteCreateFromIntranetTest.php`.
    * Movida a `En revisión` (pendiente de aprobacion del usuario para pase a `Hecho`).

* ### `[FEAT-05]` [13 pts] [R2] [P2] Motor reglamentario de calificaciones, calculo de actas, sincronizacion legacy (clases/asistencia/evaluaciones/notas) y emision transaccional en Oracle *(absorbe `[FEAT-02]` y `[EXT-02]`)*
  * **Tarjeta Trello**: [`#94` · `FEAT-05`](https://trello.com/c/SOnZUru1) | **Lista**: `Pendiente` | **Asignado**: `@chopancho`
  * **Rama sugerida**: `feature/r2-motor-actas-emision-oracle`
  * **Alcance**: Backend + Facade + Migracion PostgreSQL + Adapters Oracle *(UI/UX de pre-acta y examen pospuesta hasta definicion formal; invocable desde tests/Tinker/app)*
  * **Archivos estimados**:
    * `database/migrations/` (Columnas de pre-acta en `curso.inscripcion_curso` + tabla local de cierre/actas y bitacora de sync Oracle)
    * `config/services.php` (Codigos configurables de acta Oracle: `ACT_CODIGO`, `TNA_CODIGO`, `THN_CODIGO`, `TDF_CODIGO`)
    * `app/Models/Curso/InscripcionCurso.php` y modelo local de acta/sync
    * `app/Services/Curso/CalculoActaCursoService.php` (Sub-bloque 1)
    * `app/Services/Intranet/Adapters/OracleClasesAsistenciaAdapter.php` (Sub-bloque 2)
    * `app/Services/Intranet/Adapters/OracleEvaluacionesNotasAdapter.php` (Sub-bloque 3)
    * `app/Services/Intranet/OracleActaRepository.php` / `app/Models/External/` (`DOC_PERIODO`, `REG_FOLIO_DCTO`, `REG_CAB_ACTA`, `REG_DET_ACTA`, `REG_HISTORIA_DENOTA` — Sub-bloque 4)
    * `app/Facades/ActaIntranet.php` / `app/Services/Intranet/ActaIntranetFacade.php` (Fachada unificada de los 4 sub-bloques)
    * `tests/Unit/` y `tests/Feature/` (Calculo reglamentario + fakes/mocks Adapters & Oracle)
  * **Problema & Causa**: UTAMED carece actualmente de la maquina reglamentaria para cerrar notas/actas locales (`PP`, `NP` con tope `3.9` por rojo sumativo o falta de nota evaluada como `1.0`, corte de asistencia por `componente.porcentaje_asistencia_obligatoria`, examen `80/20` con umbral `4.2`, `PF` y snapshot local) y de la capa unificada para sincronizar clases, asistencia, evaluaciones, notas parciales y actas finales hacia Oracle. Tras analizar los 4 scripts PHP externos (`mandar_clases.php`, `mandar_asistencia.php`, `masivoTestear.php`, `masivonotasTestear.php`) y los metadatos vivos de Oracle para `UTAMED`, se desbloquea la especificacion en 4 sub-bloques detras de una Fachada unica sin mezclar logica de negocio con los bugs de los scripts heredados (sintaxis rota, programa fijo `527`, lectura erronea desde `agenda.evaluacion.evaluacion_obtenida` e IPs/periodos hardcodeados).
  * **Especificacion Tecnica de los 4 Sub-bloques**:
    1. **Sub-bloque 1 — Calculo y Cierre Local de Actas (PostgreSQL + Backend)**:
       * Lectura de notas desde `COALESCE(integrante_grupo.nota_individual, actividad_asignada_grupo.nota)` y ponderaciones segun `SeccionIXContenido`. Toda actividad sumativa sin nota (`null`) al cierre se evalua como **`1.0`**.
       * **Regla del Rojo (`NP`)**: Cualquier actividad sumativa individual `< 4.0` (incluidas las `null -> 1.0`) trunca `NP = min(PP, 3.9)` y exige examen. Sin rojos, `NP = PP`.
       * **Asistencia configurable**: Evaluada independientemente por cada componente usando `componente.porcentaje_asistencia_obligatoria` (fallback `70%` si es `null`). Si falla cualquier componente obligatorio, reprueba directo por inasistencia sin derecho a examen (`PF = min(PP, 3.9)`).
       * **Eximicion & Examen (`80/20`, umbral `4.2`)**: Sin rojos, asistencia OK y `NP >= 4.0` -> Eximido (`PF = NP`). Con examen: si `EX < 4.2` -> Reprueba con `PF = round(0.8 * NP + 0.2 * EX, 1)`; si `EX >= 4.2` -> Aprueba con `PF = round(0.8 * PP + 0.2 * EX, 1)`.
       * Persistencia en `curso.inscripcion_curso` + tabla local de acta/sync.
    2. **Sub-bloque 2 — Adapter de Clases y Asistencia a Oracle**:
       * Envolver via Adapter el protocolo de `mandar_clases.php` y `mandar_asistencia.php`: invocar primero `GESTIONCURSO.f_actual_clases_utamed(curcodigo, fecha, fecha_creacion, descripcion, tiene_asistencia, interno, rut_usuario, ip)` (`return 1` OK) y luego `GESTIONCURSO.f_actual_asistencia_utamed(curcodigo, interno, rut, fecha_creacion, estado, rut_usuario, ip)` (vinculando el `interno` de la clase).
       * *Pre-requisito operativo de BD*: Se verifico en Oracle que el usuario `UTAMED` aun no posee `GRANT EXECUTE` sobre `GESTIONCURSO.f_actual_clases_utamed` / `f_actual_asistencia_utamed` (el sinonimo `F_ACTUAL_ASISTENCIA_UTAMED_DESA` figura `INVALID`); solicitar el grant a TI o configurar esquema autorizado sin detener el desarrollo del Adapter.
    3. **Sub-bloque 3 — Adapter de Sistema de Evaluacion y Notas Parciales a Oracle**:
       * Envolver via Adapter la secuencia de `masivoTestear.php` y `masivonotasTestear.php` (corrigiendo bugs de los scripts originales):
         * Primero `GESTIONCURSO.f_actual_sistema_eval_utamed(CURCODIGO, CORREL, PORCENTAJE, FECHA, NOMBRE_ACTIVIDAD, TIPO, INTERNO, SUMATIVA, FORMATIVA)` (`tipo = 1` crear/actualizar, `2` eliminar; `commit` en la capa de invocacion porque la funcion no hace `COMMIT` interno).
         * Luego `GESTIONCURSO.f_actual_notaparc_utamed(CURCODIGO, INTERNO, RUT, NOTA, FECHA_CREACION, RUT_USUARIO, IP)` enviando nota con reemplazo `0.0 / null -> 1.0` y mismo `interno` de la actividad (`PRAGMA autonomous_transaction`).
    4. **Sub-bloque 4 — Envio Transaccional de Actas Finales a Oracle (5 Tablas + Folio)**:
       * Destinado al componente del curso con **`genera_acta = true`**, usando `componente.intranet_cur_codigo` (`CUR_CODIGO`) e `INS_ID` desde `curso.inscripcion_componente.cod_inscripcion_curso_uta` para reflejar el resultado consolidado del curso (usuario `UTAMED` ya cuenta con `SELECT` en `DOC_PERIODO`, `EXECUTE` en `F_OBTENERFOLIODOCTO`, y `SELECT/INSERT/UPDATE/DELETE` en las 4 tablas `REG_*`):
         * `DOCENCIA.DOC_PERIODO`: consulta de `PER_CODIGO` por `PER_ANIO` y `SEM_CODIGO`.
         * `REGISTRADURIA.F_OBTENERFOLIODOCTO(ANIO, SEM)` + `REGISTRADURIA.REG_FOLIO_DCTO`: obtencion/incremento de `RFD_FOLIO` (`TDF_CODIGO = 1`, retorna el folio/correlativo usado como `CBA_ID`).
         * `REGISTRADURIA.REG_CAB_ACTA`: cabecera (`CBA_ID`, `ACT_CODIGO`, `CUR_CODIGO`, `CBA_FECHA_CREACION`, `CBA_FECHA_CIERRE`, `CBA_IP`, `CBA_USUARIO`, `CBA_GENERA`).
         * `REGISTRADURIA.REG_DET_ACTA`: detalle por inscripcion (`INS_ID`, `CUR_CODIGO`, `CBA_ID`, `TNA_CODIGO`, `THN_CODIGO`, `DAC_NOTA`, `DAC_NSP`).
         * `REGISTRADURIA.REG_HISTORIA_DENOTA`: auditoria (`HDN_ID`, `INS_ID`, `CUR_CODIGO`, `CBA_ID`, `TNA_CODIGO`, `HDN_FECHA`, `THN_CODIGO`, `HDN_NOTA`, `HDN_IP`, `HDN_USUARIO`).
         * Codigos observados configurables en `config/services.php` mientras TI confirma catalogo/secuencia `HDN_ID` (`ACT_CODIGO`: `3`/`8`, `TNA_CODIGO`: `4`, `THN_CODIGO`: `1` parcial / `3` cierre).
  * **Criterios de Aceptación (DoD)**:
    * [ ] Implementar migracion en PostgreSQL agregando columnas de pre-acta en `curso.inscripcion_curso` (`nota_presentacion`, `nota_examen`, `porcentaje_asistencia`, `promedio_final`, `situacion_acta`) y tabla local de cierre/actas + bitacora de sincronizacion con Oracle.
    * [ ] Implementar `CalculoActaCursoService` (Sub-bloque 1): ponderacion por Syllabus, actividades `null -> 1.0`, truncamiento `NP = min(PP, 3.9)` ante cualquier sumativa individual `< 4.0`, validacion independiente de `componente.porcentaje_asistencia_obligatoria` (fallback `70%`), eximicion y formula examen `80/20` con corte en `4.2`.
    * [ ] Implementar Adapters para Sub-bloque 2 (`GESTIONCURSO.f_actual_clases_utamed`, `GESTIONCURSO.f_actual_asistencia_utamed`) y Sub-bloque 3 (`GESTIONCURSO.f_actual_sistema_eval_utamed`, `GESTIONCURSO.f_actual_notaparc_utamed`), leyendo calificaciones reales desde UTAMED y corrigiendo los bugs de los scripts legacy. Documentar pre-requisito de `GRANT EXECUTE` faltante en clases/asistencia para el usuario `UTAMED`.
    * [ ] Implementar persistencia transaccional del Sub-bloque 4 orientada al componente con `genera_acta = true` (`intranet_cur_codigo` + `INS_ID`): resolucion de `PER_CODIGO` en `DOC_PERIODO`, reserva de folio via `F_OBTENERFOLIODOCTO` / `REG_FOLIO_DCTO`, e insercion/auditoria atomica en `REG_CAB_ACTA`, `REG_DET_ACTA` y `REG_HISTORIA_DENOTA` con codigos configurables en `config/services.php`.
    * [ ] Exponer una Facade unificada que orqueste los 4 sub-bloques de manera modular e integral (ejecucion invocable desde app/Tinker/tests, congelamiento de `curso.estado_acta` y manejo de rollback/errores ante fallos de comunicacion Oracle; alcance UI pospuesto).
    * [ ] Suite de pruebas automatizadas (Unit/Feature) cubriendo todas las reglas de calculo (`null -> 1.0`, rojo `< 4.0`, asistencia por componente, eximicion, examen `4.2`), Adapters y simulacion de exito/fallo/rollback Oracle.

---

### Pendientes de Levantamiento Funcional (Lista `Por definir (preguntar al director)` — Fuera del Sprint de Implementacion)

Los siguientes puntos quedaron registrados como tarjetas formales en la lista `Por definir (preguntar al director)` y **no suman puntos de historia ni generan tareas de codigo** hasta realizar las sesiones correspondientes:

* ### `[DEF-01]` [Por definir] Alcance operativo del ambiente de Ayudantes en portal web
  * **Tarjeta Trello**: [`#164` · `DEF-01`](https://trello.com/c/b8ulD7M6) | **Lista**: `Por definir (preguntar al director)` | **Asignado**: `@chopancho`
  * **Tipo**: Relevamiento Funcional / Definicion de Negocio
  * **Ruta**: `/ayudante/*`, `/ayudante/cursos/{curso}`
  * **Archivos de contexto**: `routes/web.php`, `resources/js/pages/ayudante/Courses/Show.svelte`, `app/Http/Controllers/Docente/DelegacionPermisosController.php`
  * **Contexto & Problema**: El portal del ayudante actualmente solo expone acceso al Programa del Curso (Syllabus) y Mensajeria. No obstante, el backend de UTAMED ya cuenta con infraestructura para delegar permisos granulares (`actividades:ver`, `crear`, `evaluar`, `asistencia:registrar`, `grupos`, `unidades`). Se reporto que el ayudante requiere "tocar mas", pero se debe acordar formalmente con el Director que submodulos se habilitaran operativamente.
  * **Pauta de Preguntas para el Director (Checklist de Relevamiento)**:
    * [ ] ¿El ayudante podra calificar actividades (sumativas y formativas) y retroalimentar a los estudiantes cuando el titular le delegue permisos?
    * [ ] ¿El ayudante podra pasar y editar asistencia en sesiones de catedra, taller o laboratorio?
    * [ ] ¿Se permitira al ayudante visualizar y administrar grupos de trabajo de actividades?
    * [ ] ¿Tendra acceso a la creacion de unidades tematicas o solo a la consulta de material docente?
    * [ ] ¿Como interactuan las acciones del ayudante con la autoria y responsabilidad final del Docente Titular?

* ### `[DEF-02]` [Por definir] Sesion de relevamiento funcional con Ignacio para vista de Jefe de Carrera
  * **Tarjeta Trello**: [`#165` · `DEF-02`](https://trello.com/c/9rQWuNRG) | **Lista**: `Por definir (preguntar al director)` | **Asignado**: `@chopancho`
  * **Tipo**: Relevamiento Funcional / Stakeholder Discovery
  * **Ruta**: `/docente/jefe-carrera/*`
  * **Archivos de contexto**: `app/Http/Controllers/Docente/JefeCarreraController.php`, `resources/js/pages/jefe-carrera/Dashboard.svelte`, `resources/js/pages/jefe-carrera/Seguimiento.svelte`, `resources/js/pages/jefe-carrera/Metricas.svelte`, `resources/js/pages/jefe-carrera/Carrera.svelte`
  * **Contexto & Problema**: Existen vistas preliminares de Dashboard, Seguimiento, Metricas y Carrera para el rol de Jefe de Carrera, pero se reporto la necesidad de "tener reunion con Ignacio" para alinear expectativas institucionales, validar indicadores criticos y definir requerimientos concretos antes de realizar modificaciones sobre el modulo.
  * **Temario de la Reunion con Ignacio (Checklist de Relevamiento)**:
    * [ ] Revision guiada de las vistas actuales de `/docente/jefe-carrera` (Dashboard, Metricas, Seguimiento y Carrera).
    * [ ] Identificar que indicadores y metricas de desempeno estudiantil y docente son de consulta obligatoria para la jefatura.
    * [ ] Definir el umbral de alertas tempranas (estudiantes en riesgo academico o con inasistencia critica).
    * [ ] Acordar si la jefatura requiere permisos de visualizacion o edicion sobre los syllabus y actas del cuerpo docente.
    * [ ] Determinar necesidades de exportacion masiva de datos (planillas Excel, informes ejecutivos en PDF).

---

## 4. Metricas de Planificacion

* **Total de Tarjetas de Implementacion**: 5 tarjetas activas (`FEAT-02` y `EXT-02` absorbidas en `FEAT-05`) + 2 tarjetas de relevamiento en `Por definir (preguntar al director)` (`DEF-01`, `DEF-02`)
* **Puntos por Release**:
  * **Release 1 (Estabilidad CSRF, Credenciales SuperAdmin y Copia de Cursos)**: **8 puntos** (3 tarjetas en `Pendiente`: `BUG-01` [3 pts], `UI-01` [2 pts], `FEAT-01` [3 pts])
  * **Release 2 (Correo Intranet, Motor de Actas y Sincronizacion Oracle)**: **15 puntos** (2 tarjetas: `EXT-01` [2 pts — en `En revisión`] + `FEAT-05` [13 pts — en `Pendiente`, refinada en 4 sub-bloques backend-first])
* **Total de Puntos de Historia Estimados**: **23 puntos** (`2 pts` en `En revisión` [`EXT-01`] + `21 pts` listos en `Pendiente` [Release 1 + `FEAT-05`])
