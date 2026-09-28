# Cambios de base de datos del tablero «arreglos-reunion-23-09-2026»

**Fecha:** 2026-09-28
**Rama:** `arreglos-reunion-23-09`
**Tablero:** https://trello.com/b/H5mt3Cd8/arreglos-reunion-23-09-2026

Se revisaron las 74 tarjetas del tablero y se compararon con el esquema base
(`database-model/init_scripts/01-sql_def.sql`) y con las migraciones de
`database/migrations/`. Solo **5 tarjetas cambian el esquema**:

- **T10, T07 y T48:** migraciones 07, 08 y 09 escritas y aplicadas en la base local (`utamed_1ra_fase`) el 2026-09-28.
- **T50 y T14:** sin migración todavía.

El modelo de `database-model` (commit `b500520`) ya refleja T10, T07, T48 y también `foto_url` (T14).

Además hay un cambio de datos (T53) y una advertencia sobre las contraseñas
existentes (T54).

> Solo se aplicó a la base local; el resto de las bases (integración, producción) sigue pendiente. El esquema cambia solo
> por migraciones de Laravel; no hay que editar `01-sql_def.sql`, que es el
> baseline congelado.

---

## Resumen

| Tarjeta | Estado en Trello | Cambio | Situación |
|---|---|---|---|
| T10 | En revisión | 3 columnas en `usuario.estudiante` | Migración 07, aplicada en local |
| T07 | En progreso | Registro de lectura de la agenda | Migración 08 aplicada en local; código hecho (`c6ff836`) |
| T48 | Por definir | 2 valores en `agenda.en_tipo_mensaje` + 2 columnas | Migración 09 aplicada en local; reglas definidas; faltan `permite_apelacion` y `apelacion_acogida` en el modelo |
| T50 | Por definir | Rediseño de la mensajería y limpieza de tablas | Espera la respuesta del director |
| T14 | Pendiente | `foto_url` del usuario | Descartada en este tablero |
| T53 | En revisión | Dato: asistencia DM095 75 → 70 | El seeder ya está corregido |
| T54 | En revisión | Ninguno (advertencia sobre claves guardadas) | Solo informativo |

---

## 1. Ya escrita, falta aplicarla

### T10: datos de contacto del estudiante

- **Commit:** `4a49c78`
- **Migración:** `database/migrations/07_add_contacto_to_estudiante_table.php`

```sql
ALTER TABLE usuario.estudiante ADD COLUMN IF NOT EXISTS correo_personal varchar(255) DEFAULT NULL;
ALTER TABLE usuario.estudiante ADD COLUMN IF NOT EXISTS celular        varchar(30)  DEFAULT NULL;
ALTER TABLE usuario.estudiante ADD COLUMN IF NOT EXISTS redes_sociales jsonb        DEFAULT NULL;
```

**Por qué es urgente:** el código ya usa estas columnas en tres lugares:

- `Student\PerfilController::updateContacto`
- `Estudiante::contacto()`
- la ficha del estudiante en `DocenteCursoController`

En cualquier base donde no se haya corrido la migración, el perfil del
estudiante y la ficha del docente van a fallar.

**Reglas que implementa el código:**

- Las tres columnas están en `$hidden` del modelo `Estudiante`, para que ningún
  alumno vea los datos de otro.
- `redes_sociales` admite solo YouTube, X, Instagram y LinkedIn.

---

## 2. Tarjetas abiertas que necesitan esquema (aún sin migración)

### T07: confirmación de lectura («Visto») en la agenda

Hoy `agenda.agenda` no guarda ninguna lectura. La tarjeta propone una columna
`visto_at`, pero no alcanza: en una actividad grupal leen varias personas (cada
integrante del grupo y cada docente). Por eso se propone una tabla con una fila
por lector, igual que la que ya existe para la mensajería del curso
(`curso.interaccion_mensaje`):

```sql
CREATE TABLE agenda.lectura_agenda (
  id_lectura_agenda integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  id_agenda         integer   NOT NULL REFERENCES agenda.agenda(id_agenda),
  id_usuario_lector integer   NOT NULL REFERENCES usuario.usuario(id_usuario),
  fecha_lectura     timestamp NOT NULL DEFAULT now(),
  CONSTRAINT uq_lectura_agenda UNIQUE (id_agenda, id_usuario_lector)
);
```

Cómo quedó implementado (`c6ff836`, `LecturaAgendaService`):

- Al abrir un hilo se inserta una fila por cada mensaje recibido que el
  usuario aún no había leído. Los mensajes propios no cuentan.
- Se registra en la actividad del estudiante, en la agenda del grupo del
  docente, en la bandeja de Mensajes y en la conversación por estudiante.
- Como en Instagram, solo el último mensaje del hilo muestra «Visto por Ana,
  Juan y N más»; al pasar el cursor aparece la lista completa con la hora. No
  se incluye a quien está mirando la pantalla.

### T48: apelación de entregas

La migración 09 ya agregó los dos tipos de mensaje (`'Solicitud de apelación'`
y `'Resolución de apelación'`) y el enum `TipoMensaje` está regenerado. El
flujo todavía no está programado.

**Reglas definidas (2026-09-28):**

- Se puede apelar solo cuando la entrega ya se subió **y ya fue evaluada**.
- La solicitud lleva la razón en texto libre. Se guarda como una fila de
  `agenda.agenda` de tipo «Solicitud de apelación», con la razón en `mensaje`.
- Si la entrega es grupal, la apelación es del grupo: la agenda cuelga del
  grupo (`id_actividad_asignada_grupo`), igual que la entrega.
- No hay plazo ni límite de veces. Se puede apelar hasta que el docente
  desactive la apelación **en esa actividad** desde la gestión del curso.
- Resuelve el docente o el administrador, con una fila de tipo «Resolución de
  apelación».
- Si la apelación se acoge, **la entrega se reabre**: el grupo puede subir una
  nueva versión, aunque haya pasado la fecha límite, y el docente la evalúa
  como una entrega normal. La nueva evaluación reemplaza la nota.

**Falta en la BD (pedir al equipo del modelo):**

```sql
-- Interruptor por actividad para que el docente desactive la apelación.
ALTER TABLE agenda.actividad ADD COLUMN permite_apelacion boolean NOT NULL DEFAULT TRUE;

-- Resultado de la resolución: TRUE = acogida (reabre la entrega),
-- FALSE = rechazada. Solo aplica a las filas de resolución.
ALTER TABLE agenda.agenda ADD COLUMN apelacion_acogida boolean NULL;
ALTER TABLE agenda.agenda ADD CONSTRAINT chk_apelacion_acogida_solo_en_resolucion
  CHECK (apelacion_acogida IS NULL OR tipo_mensaje = 'Resolución de apelación');
```

Sin `apelacion_acogida` no se puede saber si una resolución reabre la entrega:
el tipo de mensaje es el mismo si se acoge o si se rechaza.

**Cómo se derivaría el estado (sin más columnas):**

- *Apelación pendiente:* la última «Solicitud de apelación» del grupo no tiene
  una «Resolución de apelación» posterior. Mientras haya una pendiente, no se
  ofrece apelar otra vez.
- *Entrega reabierta:* la última resolución fue acogida y es posterior a la
  última «Evaluación». Se cierra sola cuando llega la nueva evaluación.

**Código que falta (después de la migración):**

- Estudiante: botón «Apelar» con el campo de razón en la agenda de la
  actividad. Solo aparece si hay una entrega evaluada, `permite_apelacion` es
  verdadero y no hay otra apelación pendiente.
- Docente: interruptor «Permitir apelaciones» en la gestión de la actividad y
  una forma de resolver (acoger o rechazar, con texto) desde la agenda del grupo.
- Administrador: una vista para ver y resolver apelaciones. Hoy el panel admin
  no muestra la agenda.
- Entrega: `AgendaController::storeEntrega` tiene que aceptar una entrega fuera
  de plazo cuando la entrega está reabierta.

### T50: rediseño de la mensajería (épica; absorbe T49 y T51)

Está a la espera de la respuesta del director.

**Qué cambia:** hoy `curso.mensaje` cuelga de un componente
(`id_componente NOT NULL`) y tiene un solo receptor (`id_usuario_receptor`,
nulo si es un mensaje grupal). Para escribir al curso, a un grupo, al profesor
o a un compañero habrá que cambiar ese modelo, por ejemplo:

- un tipo de canal;
- una referencia al grupo;
- permitir mensajes entre alumnos.

**Qué falta definir:** la tarjeta también pide migrar y limpiar «tablas
deprecadas», pero nadie ha dicho todavía cuáles son. La pregunta original de
T51 era: «¿Qué tablas se deprecaron y quién lo decidió?».

No se puede escribir DDL hasta tener esas respuestas.

### T14: foto del estudiante desde la Intranet

Necesitaría guardar la URL remota de la foto (sin guardar el archivo en el
disco de UTAMED), por ejemplo:

```sql
ALTER TABLE usuario.usuario ADD COLUMN foto_url varchar NULL;
```

**Decisión vigente:** en este tablero **quedó fuera** (junto con T15) porque
obliga a tocar la BD, aunque en Trello sigue en «Pendiente».

---

## 3. Cambios de datos (no de esquema)

### T53: asistencia obligatoria de DM095 al 70 %

- **Commit:** `9efdadf`
- El seeder ya dice 70 (`database/seeders/Dm095/Dm095SyllabusBuilder.php:153`).
- Las bases que ya se sembraron siguen con 75 hasta que se vuelva a sembrar
  DM095 o se corrija ese valor a mano.

**Regla acordada:** la asistencia mínima es 70 % sin excepción. El 75 % que
llega de la Intranet en `curso.componente` no manda.

### T54: recorte de espacios en las contraseñas

- **Commit:** `9b18a2d`
- No cambia columnas. Desde ahora las contraseñas se recortan al guardarlas y
  al compararlas.

**Efecto sobre claves antiguas:** si alguien guardó una clave que empieza o
termina con espacio, ya no podrá entrar hasta que la restablezca. Es poco
probable, pero conviene saberlo si aparece un reclamo de ese tipo.

---

## 4. Tarjetas revisadas que NO requieren cambios de BD

El resto del tablero es de frontend o de backend sin esquema nuevo. En tres
tarjetas parecía que hacía falta un cambio, pero el dato ya existe:

| Tarjeta | Dato que necesita | Dónde está ya |
|---|---|---|
| FEAT-03 | Nombre original del archivo | `operaciones.archivo.nombre_original` |
| T02 | Días de holgura | `agenda.actividad.nro_dias_adicionales_para_bloqueo` |
| T36 | Si la entrega ya fue evaluada | `agenda.evaluacion` |
