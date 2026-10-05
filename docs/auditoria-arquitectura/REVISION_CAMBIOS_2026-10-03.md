# Revisión de cambios — 27/09 al 02/10/2026

**Rango revisado:** `1bff4a0..1043d08` (rama `main`, 63 commits, 155 archivos)
**Fecha de la revisión:** 03/10/2026 (actualizada el 05/10/2026 con los commits `e43402d`, `0ec14ff` y `adf9e71`)
**Método:** revisión manual del código + revisión automática en paralelo (`/code-review high`) + pruebas funcionales contra la BD local de desarrollo (dentro de una transacción revertida; la BD quedó intacta).

## Estado de las correcciones (05/10/2026)

Todos los hallazgos quedaron corregidos en la rama `arreglos-reunion-23-09`, un commit por arreglo. Cada corrección se verificó contra la BD local de desarrollo dentro de transacciones revertidas.

| # | Commit | Verificación |
|---|---|---|
| 1, 10, 13 | `36eeec2` | Solo se cancela la última entrega de archivo de un integrante; no se cancela dos veces ni después de cualquier evaluación (incluidas formativas sin vincular); las dos escrituras van en transacción |
| 2, 3 | `6ea34ac` | `ultima_entrega`, «Ver entregas», el contador por actividad y la evaluación con rúbrica ignoran las entregas canceladas |
| 4 | `dc7831d` | Al copiar, el rol de titular queda en el contexto real del curso nuevo |
| 14 | `21ff982` | Copiar un curso copia sus unidades y reasigna las actividades (7 actividades copiadas, todas con unidad del curso nuevo) |
| 5 | `d10f409` | Clave antigua con espacios: entra y se vuelve a guardar recortada; `fecha_cambio_passhash` no cambia; clave incorrecta sigue rechazada |
| 7 | `9bbf1ca` | La tarjeta muestra «Sin fecha límite» en vez de «NaN/NaN» |
| 9 | `0dc8c3c` | `up()` ejecutado con un caso de dos asignaciones huérfanas superpuestas: sin error de `uq_no_solapar_roles` |
| 11 | `83baf23` | `1234567-k` → `001234567K.JPG`; los RUT normales generan la misma URL que antes |
| 12 | `953ab07` | Los tipos de apelación se incluyen en el hilo del docente |
| 6, 8 | `e43402d` | Corregidos por el equipo |

**Decisiones tomadas al corregir:**
- Cancelar la última entrega deja al grupo **sin entrega activa**; la entrega anterior (ya reemplazada) no vuelve a quedar vigente.
- La migración 10 sigue sin reparar los roles «Docente Componente»; si existen en contextos huérfanos, hay que tratarlos aparte.
- Sigue pendiente la decisión de negocio sobre quién puede cancelar una entrega grupal (hoy, cualquier integrante).

---

## Cómo leer este reporte

Cada hallazgo indica:

- **Importancia:** 🔴 Alta (pérdida de datos, notas o accesos incorrectos; corregir antes de producción) · 🟠 Media (comportamiento incorrecto visible o que afecta a un grupo de usuarios) · 🟡 Baja (casos raros, robustez o deuda técnica).
- **Estado:** *Reproducido* (se ejecutó y falló) · *Confirmado en código* (se verificó leyendo el código, sin ejecutar) · *Plausible* (depende de datos que hoy no existen en la BD local).

## Resumen

| # | Importancia | Funcionalidad | Problema | Estado |
|---|---|---|---|---|
| 1 | 🔴 Alta | Cancelar entrega (estudiante) | Se puede «cancelar» cualquier mensaje con archivo, incluso los del docente, y cancelar dos veces | Reproducido |
| 2 | 🔴 Alta | Tarjeta de entrega (estudiante) | Cancelar una entrega antigua oculta la entrega vigente | Reproducido |
| 3 | 🔴 Alta | Ver entregas / Evaluar (docente) | Las entregas canceladas siguen listadas y se pueden evaluar | Reproducido |
| 4 | 🟠 Media | Copiar curso (admin) | El docente titular del curso copiado no recibe permisos en el curso | Confirmado en código |
| 5 | 🟠 Media | Login / importación masiva de usuarios | Contraseñas con espacios al inicio o al final dejan al usuario sin poder entrar | Confirmado en código |
| 6 | ✅ Corregido | «Visto por» en la agenda | Desaparecía cuando lo último del hilo era una cancelación | Corregido en `e43402d` |
| 7 | 🟠 Media | Tarjeta de entrega (estudiante) | Muestra «NaN/NaN» si la actividad no tiene fecha límite | Confirmado en código |
| 8 | ✅ Corregido | Frontend (tipos) | Error de tipos en `student/Activities/Index.svelte` | Corregido en `e43402d` |
| 9 | 🟡 Baja | Migración 10 (contexto del titular) | Puede abortar en algunos datos; no repara los roles «Docente Componente» | Plausible |
| 10 | 🟡 Baja | Cancelar entrega | Dos escrituras sin transacción | Confirmado en código |
| 11 | 🟡 Baja | Foto de perfil (Intranet) | RUT con `k` o de 7 dígitos → no carga la foto; lógica duplicada | Confirmado en código |
| 12 | 🟡 Baja | Agenda docente | El hilo no incluye los tipos de apelación (T48) | Confirmado en código |
| 13 | 🔴 Alta | Cancelar entrega (estudiante) | En actividades formativas se puede cancelar una entrega ya evaluada | Reproducido |
| 14 | 🟠 Media | Copiar curso (admin) | Copiar un curso con actividades falla siempre (falta la unidad obligatoria) | Reproducido |

**Prioridad sugerida:** 1, 2, 3 y 13 juntos (siguen abiertos al 05/10) (son el mismo flujo, commit `4be5a6c`), después 4 y 5, y el resto cuando haya tiempo.

---

## 🔴 1. Cualquier mensaje con archivo se puede «cancelar» como si fuera una entrega

- **Funcionalidad afectada:** cancelación de entregas del estudiante (T36/T37/T43). Endpoint `DELETE /estudiante/grupos-asignados/{grupo}/entregas/{agenda}`.
- **Dónde:** `app/Http/Controllers/Student/AgendaController.php`, método `destroyEntrega` (~línea 256).
- **Qué pasa:** el método solo comprueba que la fila de agenda pertenezca al grupo. No comprueba que:
  - sea del tipo «Entrega de archivo»,
  - no esté ya cancelada,
  - la haya enviado un integrante del grupo (y no el docente).
- **Consecuencias (reproducidas):**
  - Si un estudiante manda el id de un **Feedback del docente que trae archivo**, el archivo del docente queda marcado `pendiente_de_borrado = true` y se registra una «Cancelación de entrega» falsa. AgendaHilo, al no encontrar la entrega por uuid, marca como cancelada **la última entrega real del estudiante**.
  - Al cancelar la misma entrega dos veces, se crean **dos registros de cancelación**.
  - Si un proceso de limpieza purga los archivos pendientes de borrado, se pierde el archivo del docente.
- **Solución propuesta:**
  1. Rechazar (422/403) si `$agenda->tipo_mensaje !== TipoMensaje::ENTREGA_DE_ARCHIVO`.
  2. Rechazar si ya existe una «Cancelación de entrega» con el mismo `uuid_archivo_subido` en el grupo.
  3. Comprobar que `id_usuario_emisor` sea un integrante del grupo (o, según la regla de negocio, el propio estudiante; ver «Decisiones pendientes»).
  4. Agregar un test de Feature para cada caso.

## 🔴 2. Cancelar una entrega antigua oculta la entrega vigente

- **Funcionalidad afectada:** tarjeta «Entrega» de la página de actividad del estudiante (estado entregado / sin entrega, botones ver, reemplazar y borrar).
- **Dónde:** `app/Http/Controllers/Student/ActivityController.php`, líneas 186-193 (cálculo de `ultima_entrega`).
- **Qué pasa:** el bucle recorre el hilo desde el final y se detiene en **cualquier** cancelación, sin mirar a qué entrega corresponde.
- **Consecuencias (reproducidas):** con entrega A → entrega B → cancelar A, `ultima_entrega` queda en `null` aunque B sigue vigente. Todo el grupo ve «No se ha subido un archivo» y no puede ver, reemplazar ni borrar B. En actividades grupales pasa fácilmente: un integrante con la página abierta desde antes borra la entrega «antigua» que su pantalla todavía muestra.
- **Solución propuesta:** construir el conjunto de uuids cancelados y tomar como `ultima_entrega` la entrega más reciente cuyo uuid **no** esté en ese conjunto. Esto, más el punto 1, impide además cancelar una entrega que no sea la vigente.

## 🔴 3. El docente ve y puede evaluar entregas canceladas

- **Funcionalidad afectada:** «Ver entregas», evaluación con rúbrica y contador de entregas por actividad del portal docente.
- **Dónde:** `app/Http/Controllers/Docente/DocenteActivityController.php`:
  - `listarEntregas` (~línea 1579),
  - `storeEvaluacion`, búsqueda de `id_agenda_entrega` (~línea 1831),
  - `total_entregas` en el listado de actividades (~línea 270).

  También el listado `entradas` de `Student/ActivityController.php` (~línea 208).
- **Qué pasa:** las tres consultas filtran solo por tipo «Entrega de archivo» y no excluyen las canceladas. Solo AgendaHilo y `ConversacionDocenteService` reconocen la cancelación.
- **Consecuencias (reproducidas):** la entrega cancelada sigue en la lista del docente, que puede **calificar un archivo retirado** (y quizás ya purgado), con lo que la nota queda asociada a algo que el estudiante retiró. El contador de entregas queda inflado.
- **Solución propuesta:** centralizar en el modelo `Agenda` un scope `entregasVigentes()` que excluya las entregas cuyo uuid tenga una «Cancelación de entrega» en el mismo grupo, y usarlo en las cuatro consultas. En `storeEvaluacion`, rechazar la entrega si está cancelada.

## 🟠 4. Al copiar un curso, el titular queda sin permisos

- **Funcionalidad afectada:** copia/duplicado de cursos desde el panel de administración; permisos del docente titular en el curso nuevo (por ejemplo, crear el syllabus).
- **Dónde:** `app/Services/CursoService.php`, líneas 173-201 (método de copia).
- **Qué pasa:** el commit `ace2202` corrigió `create()` para usar el `id_contexto` que asigna el trigger `tr_curso_pre_insert`, pero la copia sigue asignando el rol «Docente Titular» en `$contexto->id_contexto`, un contexto «Curso: <cod>» que el trigger descarta.
- **Consecuencias:** el titular de un curso copiado recibe el rol en un contexto huérfano: no ve el curso como titular y se le niegan acciones como crear el programa. Es el mismo bug que la migración 10 repara para los datos antiguos, así que **vuelve a aparecer** con cada copia.
- **Solución propuesta:** aplicar en la copia el mismo arreglo (`$nuevoCurso->refresh()` y usar `$nuevoCurso->id_contexto`). A mediano plazo, dejar de llamar a `createOrUpdateContext()` antes del `INSERT` en create/copia, porque el trigger ya crea el contexto (hoy se crea un contexto inútil en cada alta).

## 🟠 5. Contraseñas con espacios: usuarios que no pueden entrar

- **Funcionalidad afectada:** inicio de sesión e importación masiva de usuarios por xlsx (T54).
- **Dónde:** `app/Http/Middleware/TrimStrings.php` (ahora recorta `password`) y `app/Http/Controllers/Admin/UsuarioController.php:704` (`Hash::make($datos['password'])` en la importación, datos que vienen de `mapearFila`).
- **Qué pasa:** el recorte solo se aplica a lo que llega por request. La contraseña que viene en una celda del xlsx no pasa por el middleware y se guarda sin recortar. Tampoco se recortaron las contraseñas ya guardadas antes del cambio.
- **Consecuencias:** si la celda trae `"Utamed2026! "` (espacio al final) o un usuario creó antes su clave con un espacio al inicio o al final, el login recorta lo que escribe, el hash no coincide y **el usuario queda sin poder entrar**, aunque escriba la clave exacta.
- **Solución propuesta:**
  1. Recortar (`trim`) la contraseña en `mapearFila` antes de validar y hashear.
  2. Para las claves antiguas: en el login, si falla la comparación con la clave recortada, intentar con la original sin recortar y, si coincide, volver a guardar el hash ya recortado (migración transparente).
  3. Agregar al test `PasswordTrimTest` un caso de importación xlsx.

## ✅ 6. «Visto por» desaparece después de una cancelación (corregido)

> **Actualización 05/10:** corregido en `e43402d`. `LecturaAgendaService` ahora agrega `visto_por` al último elemento del hilo que no es una cancelación.


- **Funcionalidad afectada:** confirmación de lectura «Visto por» en los hilos de la agenda (T07), para estudiante y docente.
- **Dónde:** `app/Services/Agenda/LecturaAgendaService.php:94` (agrega `visto_por` al último elemento) y `resources/js/pages/student/Activities/Agenda/AgendaHilo.svelte:103` (filtra las cancelaciones antes de pintar).
- **Consecuencias:** si lo último del hilo es una cancelación, el elemento que lleva `visto_por` se descarta y la marca no se ve hasta que alguien escriba otro mensaje.
- **Solución propuesta:** en AgendaHilo, antes de filtrar las cancelaciones, pasar su `visto_por` al último elemento visible (o hacer que el backend lo agregue al último elemento que no sea una cancelación).

## 🟠 7. Tarjeta de entrega con «NaN/NaN» sin fecha límite

- **Funcionalidad afectada:** tarjeta «Plazo y entrega» en actividades con entrega obligatoria y sin fecha límite.
- **Dónde:** `resources/js/pages/student/Activities/Index.svelte:234` y `cards/ActivitySubmissionCard.svelte`. El backend manda `fecha_limite = ''` (`Student/ActivityController.php:226`).
- **Qué pasa:** la tarjeta se muestra si `entrega_obligatoria` es verdadero aunque no haya fecha, y llama a `parseFechaSoloDia('')`, que devuelve una fecha inválida.
- **Consecuencias:** el estudiante ve «NaN/NaN», «NaN días restantes» y un estado «En plazo» incorrecto.
- **Solución propuesta:** en la tarjeta, si no hay `fecha_limite`, ocultar el bloque de plazo y mostrar «Sin fecha límite»; no calcular `fechaEfectiva` ni `diasRestantes`. De paso, quitar de `Index.svelte` el código que quedó sin uso (`fechaEfectiva`, `formatFechaCorta`, `Info`).

## ✅ 8. Error de tipos nuevo en la página de actividad (corregido)

> **Actualización 05/10:** corregido en `e43402d`. `ActivityAgendaCard` ahora usa `InteraccionItem` de `@/types/agenda`.


- **Funcionalidad afectada:** compilación y chequeo de tipos del frontend (no rompe en ejecución, pero ensucia el punto de partida de svelte-check).
- **Dónde:** `resources/js/pages/student/Activities/Index.svelte:255`. Viene del commit `336757d`.
- **Qué pasa:** se pasa `InteraccionItem[]` (de `types/agenda.ts`) a `ActivityAgendaCard`, que declara su propio tipo inline, incompatible.
- **Solución propuesta:** hacer que `ActivityAgendaCard` use `InteraccionItem` de `@/types/agenda`.
- **Nota:** svelte-check reporta otros 89 errores: 2 son anteriores a este rango (`docente/Dashboard.svelte:203`, `syllabusTexto.ts:80`) y 87 vienen de archivos generados de Wayfinder duplicados en el entorno (`resources/js/actions`), que se resuelven regenerándolos.

## 🟡 9. Migración `10_reparar_contexto_docente_titular_cursos`

- **Funcionalidad afectada:** reparación de datos de las asignaciones del rol «Docente Titular».
- **Estado en la BD local:** pendiente. La simulación con consultas de solo lectura indica que movería 4 asignaciones sin conflictos.
- **Riesgos:**
  - El paso 2 puede abortar con `uq_no_solapar_roles` si un mismo titular tiene **dos** asignaciones activas con fechas superpuestas en contextos huérfanos distintos del mismo curso: el `UPDATE` las mueve a la vez y la guarda `NOT EXISTS` no las ve entre sí.
  - Solo repara «Docente Titular»; si hay roles «Docente Componente» en contextos huérfanos, quedan sin reparar.
  - El emparejamiento depende del texto `contexto_display = 'Curso: ' || cod_curso`.
  - El renombre de `07_` a `10_` es de bajo riesgo: si ya corrió con el nombre viejo, volver a correrla no cambia nada.
- **Solución propuesta:** antes de aplicarla en un entorno compartido, ejecutar el `SELECT` equivalente para ver las filas afectadas. En el paso 2, quedarse con una sola fila por (usuario, rol, curso), por ejemplo con `DISTINCT ON` o `ROW_NUMBER()`, y desactivar las demás como en el paso 1.

## 🟡 10. Cancelación sin transacción

- **Dónde:** `Student/AgendaController.php` ~285.
- **Consecuencia:** si falla la creación de la fila de cancelación, el archivo ya quedó marcado `pendiente_de_borrado`, pero la entrega sigue figurando como vigente y evaluable.
- **Solución propuesta:** envolver las dos escrituras en `DB::transaction()`. Este código está marcado como temporal (FEAT-04, mover a `AgendaArchiveHandler`); conviene resolverlo ahí.

## 🟡 11. Foto de perfil desde la Intranet

- **Funcionalidad afectada:** avatar en el menú de usuario y perfil del estudiante.
- **Dónde:** `app/Services/FotoIntranetService.php`, `Student/PerfilController.php`, `HandleInertiaRequests.php`.
- **Qué pasa:**
  - `formatRut` no pasa a mayúscula el dígito verificador `k` y solo contempla RUT de 8 o 9 caracteres: con un RUT de 7 dígitos o con `k` minúscula, la foto no carga y se muestra la genérica.
  - Duplica la lógica de `App\Support\Rut`.
  - `Student/PerfilController` crea el servicio con `new` y manda la misma URL que ya se comparte globalmente en `auth.urlFotoPerfil`.
- **A decidir por el equipo:** cada página hace que el navegador pida a `portal.uta.cl` una URL que contiene el RUT del usuario.
- **Solución propuesta:** usar `Rut::soloDigitos()` más `strtoupper` y `str_pad(..., 10, '0', STR_PAD_LEFT)`; inyectar el servicio por constructor y quitar el prop duplicado.

## 🟡 12. El hilo del docente no incluye las apelaciones

- **Dónde:** `app/Services/Docente/ConversacionDocenteService.php`, constante `TIPOS_HILO_COMPLETO`.
- **Consecuencia:** cuando se implemente T48, el docente no verá en el hilo las «Solicitud de apelación» ni las «Resolución de apelación».
- **Solución propuesta:** agregar esos dos tipos a la constante cuando se implemente T48.

## 🔴 13. En actividades formativas se puede cancelar una entrega ya evaluada

> Agregado el 05/10 a partir de un caso que planteó Tomás.

- **Funcionalidad afectada:** cancelación de entregas del estudiante; integridad de lo que el docente ya evaluó.
- **Dónde:** `app/Http/Controllers/Student/AgendaController.php`, `destroyEntrega` (bloqueo «ya evaluada»).
- **Contexto:** las evaluaciones no están amarradas a las entregas. En `storeEvaluacion`, `id_agenda_entrega` es opcional (la fila «Evaluación» solo lleva el `uuid` de la entrega si el docente la eligió), y en una actividad formativa la `nota` del grupo queda en NULL. Que las evaluaciones no dependan de las entregas está asumido como deuda (TODO) y no es urgente; lo que sí importa es el efecto sobre la cancelación.
- **Qué pasa:** `destroyEntrega` considera evaluada la entrega solo si hay nota de grupo, nota individual, o si el `uuid` de esa entrega aparece en una fila «Evaluación». En una formativa evaluada **sin vincular la entrega** no se cumple ninguna de las tres condiciones.
- **Resultado de la prueba (BD local, transacción revertida):**

  | Caso | ¿Se puede cancelar después de evaluar? |
  |---|---|
  | Sumativa, evaluación vinculada | No |
  | Sumativa, evaluación sin vincular | No (lo frena la nota del grupo) |
  | Formativa, evaluación vinculada | No |
  | **Formativa, evaluación sin vincular** | **Sí** |

- **Consecuencias:** el estudiante retira un trabajo que el docente ya evaluó; su archivo queda `pendiente_de_borrado` y la evaluación queda apuntando a algo retirado (se pierde la evidencia). Además es incoherente con `storeEntrega`, que en ese mismo caso **sí** bloquea la subida porque revisa si existe *cualquier* evaluación en el grupo.
- **Solución propuesta:** usar en `destroyEntrega` el mismo criterio que `storeEntrega`: bloquear si el grupo tiene alguna evaluación (`$actividadAsignadaGrupo->entregas()->whereHas('evaluacion')->exists()`), además de la nota. Idealmente, extraer ese criterio a un método del modelo (por ejemplo `ActividadAsignadaGrupo::tieneEvaluacion()`) y usarlo en los dos lugares. Agregar un test de Feature con los cuatro casos de la tabla.

## 🟠 14. Copiar un curso con actividades falla siempre

> Encontrado el 05/10 al verificar el arreglo del punto 4.

- **Funcionalidad afectada:** «Copiar curso» del panel de administración.
- **Dónde:** `app/Services/CursoService.php`, método `copiar()`.
- **Qué pasa:** `agenda.actividad.id_unidad` es obligatorio y las unidades pertenecen al curso, pero `copiar()` no copiaba las unidades ni pasaba `id_unidad` al crear las actividades.
- **Consecuencias (reproducidas):** cualquier curso con actividades falla al copiarse con `Not null violation … «id_unidad»`, y la copia completa se revierte.
- **Solución aplicada:** copiar las unidades del curso de origen y asignar cada actividad a la copia de su unidad (`21ff982`).

---

## Decisiones pendientes (negocio)

- **¿Quién puede cancelar una entrega grupal?** Hoy cualquier integrante del grupo puede cancelar la entrega que subió otro. Hay que definir si solo puede hacerlo quien la subió.

## Lo que se verificó y está bien

- Perfil del docente: el middleware `is_docente` exige el perfil (`usuario.docente`), así que no hay error por docente nulo.
- El cálculo de la holgura (general + personal) en `storeEntrega`/`destroyEntrega` coincide con `calcularEstadoGrupo`.
- El valor `Cancelación de entrega` existe en el enum `agenda.en_tipo_mensaje` de la BD.

## Limitaciones de esta revisión

- **Tests automáticos:** de los tests tocados en el rango, 20 de 46 fallan porque la BD de integración (`test_pgdb_integration`, puerto 16666) está atrasada: le falta la columna `fecha_cambio_passhash`. Es un problema del entorno, no del código. Hay que resincronizarla (`composer db:soft-reset-testing`) y volver a correrlos.
- La migración 10 no se ejecutó; solo se simuló con consultas de lectura.
- El build de producción con Vite no se usó como criterio, porque falla en este entorno al generar las rutas de Wayfinder.
