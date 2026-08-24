# Puntos débiles del plan "Creación Híbrida de Componentes" — explicado en simple

> Este documento explica, sin tecnicismos, los problemas encontrados al revisar el plan
> `implementation_plan_autocreacion_componentes.md` contra el sistema real. La idea es que
> cualquier persona del equipo (no solo quien programa) entienda **qué podría salir mal** y
> **por qué importa** antes de dar luz verde a la implementación.

---

## Resumen rápido

| # | Problema | Qué tan grave |
|---|----------|----------------|
| 1 | El plan no fue revisado contra el sistema real antes de escribirlo | ⚠️ Señal de alerta general |
| 2 | La función que busca en Intranet está "amarrada" a un curso guardado, aunque no necesita serlo | 🟠 Importante |
| 3 | El "paralelo" (sección A, B, C...) del curso no queda bien guardado | 🟠 Importante |
| 4 | La regla para elegir la componente "principal" ya existe en el sistema, solo falta conectarla | 🟠 Importante |
| 5 | Falta un paso de "revisar y confirmar antes de guardar" — hoy el sistema guarda directo y recién después avisa | 🟠 Importante |
| 6 | Falta dejar un aviso cuando Intranet (fuente de verdad) no coincide con el Plan de Estudios | 🟠 Importante |
| 7 | Si alguien aprieta el botón de sincronizar dos veces, podría duplicar información — se mitiga con un aviso previo | 🟡 Menor |
| 8 | El botón "sincronizar varios cursos a la vez" no está definido | 🟡 Menor |
| 9 | Los nombres de las pruebas no coinciden con lo que ya existe | 🟡 Menor |

---

## 1. El plan no fue revisado contra el sistema real

**En simple:** el documento tiene direcciones de archivos que pertenecen al computador de otra persona (`C:/Users/dyri0n/...`) y menciona un archivo de rutas (`routes/admin.php`) que **no existe** en este proyecto — las rutas están en otro archivo (`routes/web.php`).

**Por qué importa:** esto no rompe nada por sí solo, pero es una señal de que quien escribió el plan probablemente lo redactó de memoria o copiando de otro proyecto parecido, sin abrir el código actual para confirmar los detalles. Si esa parte del plan tiene errores tan visibles, es razonable sospechar que otras partes menos visibles (la lógica de negocio) también puedan tener supuestos incorrectos.

**Qué pediría:** que quien implemente revise cada afirmación del plan contra el código actual antes de dar por buena una sección, en vez de asumir que está bien porque "suena razonable".

---

## 2. La función que busca en Intranet está "amarrada" a un curso guardado, aunque no necesita serlo

**En simple:** el plan quiere que, mientras la persona está *creando* un curso nuevo (aún no guardado), la pantalla le muestre automáticamente qué componentes tendrá (Cátedra, Taller, Laboratorio) consultando la Intranet.

Revisé de nuevo `IntranetService.php` para confirmar esto con detalle, y hay que matizar lo que dije antes: **la información que realmente hace falta para preguntarle a Intranet no es "el curso ya guardado" — son solo 5 datos sueltos:** a qué asignatura pertenece, a qué plan de estudios (carrera), el año, el semestre y el paralelo (letra A, B, C...). Los cinco son datos que la persona **ya escribió o seleccionó en el formulario, antes de guardar nada**.

El problema real es más chico de lo que parece a primera vista: la función que hace la consulta (`resolverComponentesIntranet`) está escrita de una forma que **exige recibir un curso ya guardado como "llave de entrada"**, y de ahí adentro saca esos 5 datos sueltos. Es como si, para preguntar "¿qué remedios toma el paciente Juan Pérez, nacido el 3 de marzo?", el sistema exigiera primero que Juan Pérez tenga una ficha creada en la clínica — aunque en realidad, con el nombre y la fecha de nacimiento sueltos ya alcanza para responder la pregunta.

**Por qué importa:** no es que falte información — la información ya existe en el formulario. Lo que falta es reescribir esa función para que acepte esos 5 datos sueltos directamente, en vez de exigir un curso ya guardado. Es un ajuste puntual y acotado, no un problema de diseño profundo, pero el plan no lo menciona como tarea pendiente, y si se ignora, la pantalla de "vista previa antes de guardar" que el plan promete simplemente no se puede construir con el código tal como está hoy.

**Un detalle adicional (corregido con el equipo):** el plan describe el endpoint de vista previa recibiendo `id_asignatura`, `agno`, `semestre` y `letra_grupo`, pero se olvida de pedir también **el código de la carrera**. Ese dato es justo el que Intranet necesita para filtrar correctamente, y además ya está disponible desde el primer paso del asistente de creación (la persona elige primero la carrera, y recién después el plan y la asignatura) — así que no hay que "adivinarlo" ni derivarlo de otro dato, solo incluirlo explícitamente en la consulta.

---

## 3. El "paralelo" del curso (sección A, B, C...) no se está guardando de forma confiable

**En simple:** cuando se crea un curso hoy, el sistema guarda un número interno (1, 2, 3...) para saber qué paralelo es, pero **no guarda la letra** (A, B, C) en el campo que la función de consulta a Intranet necesita para filtrar.

Como confirmó el equipo: la conversión es simple y ya tiene una regla fija — **1 es A, 2 es B, 3 es C, y así sucesivamente**. De hecho, esa exacta conversión **ya está programada y funcionando en la pantalla** donde la persona elige la letra del grupo al crear el curso (el sistema calcula "1 → A" ahí mismo para mostrársela). El problema no es que la regla no exista o sea ambigua — es que esa conversión **solo se usa para mostrarla en pantalla**, y nunca se guarda ni se reutiliza en la función que consulta a Intranet, que sigue buscando una letra en un campo que queda vacío.

Consecuencia: al preguntarle a Intranet "tráeme los datos de la sección A", si ese campo está vacío, Intranet podría entender la pregunta como "tráeme los datos de **todas** las secciones" (A, B, C juntas) en lugar de solo la que corresponde.

**Por qué importa:** esto es exactamente el tipo de error silencioso que el plan dice querer eliminar. Si no se corrige, el sistema podría terminar mostrando o inscribiendo componentes/alumnos que corresponden a un paralelo distinto al que la persona está creando — sin que nadie se dé cuenta, porque no hay ningún aviso de que esto está pasando.

**La buena noticia:** al existir ya la regla (1=A, 2=B, 3=C...) y estar probada en pantalla, el arreglo es acotado: usar esa misma conversión también del lado del servidor, en el momento de preguntarle a Intranet, en vez de depender de un campo que nunca se llena.

---

## 4. La regla para elegir la componente "principal" ya existe — solo falta conectarla

**En simple:** hoy, cuando alguien crea un curso a mano, tiene que elegir cuál de las componentes (Cátedra, Taller, Laboratorio) es la "principal" — esto determina, por ejemplo, quién queda como profesor a cargo del acta principal del curso.

El plan quiere eliminar esa elección manual, y **la buena noticia, confirmada por el equipo, es que el problema ya está resuelto de fondo**: el sistema ya tiene guardada la regla de prioridad (Cátedra es más importante que Taller, y Taller más que Laboratorio) y una función lista para usarla y decidir sola cuál es la "principal" de un curso.

**Por qué sigue apareciendo como pendiente:** el plan no menciona en ningún lado que hay que usar esa regla ya existente — así que hay riesgo de que, al programarlo, alguien no la encuentre y termine escribiendo una regla nueva desde cero (duplicando trabajo, o peor, escribiéndola distinta a la que ya usa el resto del sistema). Además, el formulario de creación de curso hoy **exige** que la persona indique la componente principal a mano; si se elimina esa pregunta de la pantalla sin también quitar esa exigencia del formulario, el sistema va a **rechazar** la creación del curso por "falta un dato obligatorio" — y el plan no incluye ese ajuste en su lista de cosas por cambiar.

---

## 5. Falta un paso de "revisar y confirmar antes de guardar"

**En simple:** el plan describe que, a medida que el sistema va revisando cada componente y cada alumno, debería ir agregando avisos a una lista ("esta componente no se pudo emparejar", "este alumno tiene datos raros") y mostrarlos **después** de haber creado todo. El problema de fondo es ese orden: hoy, cuando se marca "inscribir automáticamente" al crear un curso, el sistema **primero guarda todo en la base de datos** y **recién después** muestra el resumen con los avisos. Para ese momento, ya no hay vuelta atrás — el aviso es solo informativo, no una oportunidad real de decidir.

**Propuesta acordada con el equipo:** cambiar el orden completo del proceso a uno de "mirar antes de tocar":

1. **Mapear:** el sistema revisa qué componentes y alumnos encontraría en Intranet, sin guardar nada todavía.
2. **Concentrar:** junta todo eso en un solo reporte — qué está correcto y qué tiene algún problema.
3. **Enviar a la persona (cliente):** le muestra ese reporte completo en pantalla, antes de escribir nada en la base de datos.
4. **La persona decide:** con tres opciones claras —
   - **Revisar** el detalle de cada cosa antes de decidir.
   - **Aceptar solo lo que está correcto** (avanzar únicamente con lo que no tuvo problemas, dejando fuera — a propósito y a la vista — lo que sí los tuvo).
   - **Cancelar todo**, para ir a investigar con calma qué está pasando antes de tocar nada.

**Por qué esto es mejor que lo que describe el plan:** cumple mucho mejor el objetivo de "cero skip silencioso", porque mueve la decisión **antes** de que se guarde algo, en vez de solo informar después de un hecho que ya no se puede deshacer con un clic. Es la diferencia entre "te aviso lo que ya hice" y "te muestro lo que voy a hacer, decide tú".

**Qué falta definir para que esto funcione en la práctica:** el plan tendría que dividir la operación en dos pasos técnicos separados — uno que solo "mira" (arma el reporte, sin escribir nada) y otro que "ejecuta" (guarda solo lo que la persona aceptó). Y hay que decidir qué pasa con lo que quedó afuera al elegir "aceptar solo lo correcto": ¿desaparece del reporte, o queda pendiente para reintentar más adelante una vez corregido el problema de origen? El plan no lo dice, y conviene definirlo para que nada quede "perdido" silenciosamente incluso dentro de este nuevo flujo.

---

## 6. Falta dejar un aviso cuando Intranet (la fuente de verdad) no coincide con el Plan de Estudios

**En simple:** el plan solo contempla dos escenarios:
- Intranet responde con información → se usa esa información.
- Intranet no responde (está caída o no tiene datos) → se usa como respaldo la información del Plan de Estudios (horas de Cátedra/Taller/Laboratorio).

Pero hay un tercer escenario, probablemente el más común en la práctica: **Intranet responde, pero con una información distinta a la que dice el Plan de Estudios.** Por ejemplo: Intranet dice que el curso tiene Cátedra y Taller, pero el Plan de Estudios (que alguien actualizó después) dice que también debería tener Laboratorio.

**Ya está resuelto quién manda:** el equipo confirmó la regla de negocio — **Intranet es la fuente de verdad**. Si hay una discrepancia, se usa lo que diga Intranet, sin ambigüedad. Eso simplifica el problema: no hace falta inventar un mecanismo para "decidir quién tiene la razón".

**Lo que sí falta en el plan:** aunque ya sabemos qué información usar, el plan no dice que además hay que **dejar un aviso visible** cuando esto ocurre — es decir, cuando el sistema nota que el Plan de Estudios sugería algo distinto a lo que finalmente vino de Intranet. Sin ese aviso, la persona que crea el curso nunca se entera de que hubo una diferencia entre ambas fuentes, aunque el sistema haya "hecho lo correcto" al usar Intranet. Y precisamente evitar que este tipo de diferencias pase inadvertida es el objetivo que el propio plan dice perseguir ("ninguna discrepancia se omite silenciosamente").

---

## 7. Si alguien aprieta el botón "Sincronizar con Intranet" dos veces, podría duplicar información

**En simple:** el plan propone un botón para volver a sincronizar un curso ya existente con Intranet, pero no dice qué pasa si alguien lo aprieta dos veces (por ejemplo, por accidente, o porque la pantalla tardó en responder y la persona volvió a hacer clic).

**Solución acordada con el equipo:** en vez de (o además de) bloquearlo técnicamente, se agrega un aviso preventivo antes de ejecutar la acción, algo como: *"Si usted ya presionó este botón antes, corre el riesgo de duplicar información. Revise los últimos cursos para asegurar el registro."* Así la persona que sincroniza toma la decisión informada antes de repetir la acción.

**Una salvedad a tener presente:** este aviso reduce el riesgo porque obliga a pensarlo dos veces, pero depende de que la persona lo lea y actúe en consecuencia — no impide técnicamente que el duplicado ocurra si igual se repite la acción. Vale la pena que quien lo implemente sepa que el aviso es la barrera principal acordada, y no asuma además que el sistema previene el duplicado por sí solo.

---

## 8. El botón para sincronizar "varios cursos a la vez" no está definido

**En simple:** en la sección de pantallas, el plan menciona de pasada un botón para sincronizar "por curso o masivo" (varios a la vez), pero después no explica cómo funcionaría eso: ¿qué pasa si sincronizas 20 cursos y 3 tienen problemas? ¿se muestra un aviso por cada uno? ¿una lista combinada?

**Por qué importa:** no es grave, pero si se implementa sin definir esto primero, probablemente haya que rehacer esa parte de la pantalla una vez que se descubra en la práctica cómo debería verse.

---

## 9. Los nombres de las pruebas de calidad no coinciden con lo que ya existe

**En simple:** el plan propone un archivo de pruebas automáticas llamado como si fuera "el tercero de una serie" (dando a entender que ya existen una prueba "1" y una prueba "2" parecidas), pero esas pruebas anteriores no existen en este proyecto.

**Por qué importa:** es un detalle menor, pero refuerza la sospecha del punto 1: el plan parece haber sido escrito pensando en otro proyecto o en una versión distinta de este, sin confirmar contra el estado real de los archivos.

---

## En resumen

La idea general del plan es buena y resuelve un problema real (hoy el sistema "se traga" errores en silencio en vez de avisar). Tras revisar los puntos 2, 3, 4, 5, 6 y 7 con el equipo, quedaron con decisión tomada — lo que falta ahora es que el plan **las escriba explícitamente como tareas**, porque hoy no las menciona:

1. **Punto 2:** la función que consulta Intranet debe recibir los datos sueltos (asignatura, código de carrera, año, semestre, paralelo) en vez de exigir un curso ya guardado.
2. **Punto 3:** usar la misma regla ya programada en pantalla (1=A, 2=B, 3=C...) también del lado del servidor, en vez de depender de un campo que nunca se llena.
3. **Punto 4:** conectar la regla de prioridad que ya existe en el sistema (Cátedra > Taller > Laboratorio) para elegir la componente principal sola, y quitar la exigencia de elegirla a mano en el formulario.
4. **Punto 5:** dividir el proceso en "mirar y armar un reporte" (sin guardar nada) y "ejecutar solo lo que la persona confirmó" (mapear → concentrar → mostrar → revisar/aceptar lo correcto/cancelar), en vez de guardar directo y avisar después.
5. **Punto 6:** cuando Intranet y el Plan de Estudios no coincidan, usar siempre Intranet (ya es la fuente de verdad acordada) pero dejar un aviso visible de que hubo una diferencia — que puede mostrarse justo en la pantalla de revisión del punto 5.
6. **Punto 7:** agregar el aviso preventivo antes de sincronizar dos veces, sabiendo que es una barrera informativa, no una que bloquee técnicamente el duplicado.

Los puntos 1, 8 y 9 quedan pendientes de revisar en una próxima conversación.
