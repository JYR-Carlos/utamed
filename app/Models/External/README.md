# Modelos de Intranet Externa (Oracle)

Este módulo contiene los modelos Eloquent que mapean las vistas y tablas de la base de datos externa de la **Intranet Universitaria (Oracle DB)** hacia **UTAMED**.

---

## 🏛️ Modelos Disponibles

| Modelo | Tabla / Vista Oracle | Clave Primaria | Propósito |
| :--- | :--- | :--- | :--- |
| [`VwAlumno`](file:///c:/Users/dyri0n/Code/utamed/app/Models/External/VwAlumno.php) | `ALUMNO` | `ALUM_RUT` | Datos personales de estudiantes inscritos en la universidad. |
| [`VwCarreraCurso`](file:///c:/Users/dyri0n/Code/utamed/app/Models/External/VwCarreraCurso.php) | `CARRERA_CURSO` | `CUR_CODIGO` | Información académica de asignaturas, tipos de componente y secciones. |
| [`VwInscripcion`](file:///c:/Users/dyri0n/Code/utamed/app/Models/External/VwInscripcion.php) | `INSCRIPCION` | `INS_ID` | Registro de matrícula/inscripción de alumnos a cursos y componentes. |

---

## 🔍 Hallazgos y Análisis del Esquema Real

A partir de la inspección directa sobre la base de datos en producción/desarrollo de Oracle, se documentan las siguientes características:

### 1. `ALUMNO`
* **`ALUM_RUT`** (`NUMBER(9)`): Contiene exclusivamente el cuerpo numérico del RUT sin puntos ni guion (ej: `18401835`, `9944487`).
* **`ALUM_DIGITO`** (`CHAR(1)`): Dígito verificador (`0-9`, `K`). En registros históricos puede venir en minúscula (`k`).
* **`ALUM_NOMBRE`** (`VARCHAR2(35)` / `CHAR`): Almacena los nombres de pila completos, frecuentemente compuestos (ej: `"WALTER ARMAND"`, `"MARÍA-JOSÉ"`).
* **`ALUM_APELLIDO_PAT`** (`VARCHAR2(25)` / `CHAR`): Primer apellido.
* **`ALUM_APELLIDO_MAT`** (`VARCHAR2(25)` / `CHAR`): Segundo apellido. **Puede ser `NULL`** para estudiantes extranjeros o personas con un solo apellido legal.

### 2. `CARRERA_CURSO`
* **`CUR_CODIGO`** (`NUMBER(12)`): Código único con formato `[AÑO][SEMESTRE][CORRELATIVO]`, por ejemplo `201320002661` (Año 2013, Semestre 2, Correlativo 002661).
* **`ASIG_CODIGO`** (`VARCHAR2(10)` / `CHAR`): Código alfanumérico de la asignatura (ej: `"EN155"`, `"DI021"`, `"BS362"`).
* **`CURSO_TIPO_ASIG`** (`CHAR(1)`): Tipo de componente con valores estrictos:
  * `'C'` $\rightarrow$ Cátedra
  * `'T'` $\rightarrow$ Taller
  * `'L'` $\rightarrow$ Laboratorio
* **`CURSO_GRUPO_ASIG`** (`CHAR(1-2)`): Letra identificadora de la sección/grupo (ej: `'A'`, `'B'`, `'C'`, hasta `'Z'`).
* **`CARRERA_COD`** (`NUMBER(3)`): Código numérico de la carrera.
* **`PLAN_ANO`** (`NUMBER(4)`): Año de vigencia del plan de estudios.

### 3. `INSCRIPCION`
* **`INS_ID`** (`NUMBER(7)`): Representa el folio de inscripción/sesión. Un mismo `INS_ID` puede vincular a múltiples asignaturas para un mismo estudiante en un periodo académico.
* **`INSCRIP_NOTA`** (`NUMBER(2,1)`): Calificación numérica o `NULL` si el curso se encuentra en desarrollo.

---

## 🔤 Estándar de Nombres en Base de Datos vs Frontend
> [!IMPORTANT]
> **Regla del Sistema:** Todos los nombres y apellidos se guardan **SIEMPRE en MAYÚSCULAS** (`MB_CASE_UPPER`) en la base de datos PostgreSQL (`Usuario->nombre1`, `Usuario->apellido1`, etc.).
> 
> * **Backend / Base de datos**: Almacena en mayúsculas sin espacios redundantes para garantizar búsquedas deterministas, unicidad y cero diferencias de formato.
> * **Frontend (Svelte / UI)**: Es responsable exclusivo de aplicar la transformación visual para mostrar los nombres capitalizados (*Title Case* / `capitalize`), manteniendo la coherencia estética en la interfaz sin alterar la fuente de verdad en la base de datos.

---

## ⚠️ Casos Especiales Identificados y Manejados

1. **Relleno de espacios en columnas `CHAR` (*Padding*)**:
   Oracle rellena las columnas de tipo `CHAR` con espacios a la derecha. Todos los modelos externos aplican `trim()` automático en sus accesores.
2. **Segundo Apellido Nulo**:
   El sistema maneja `ALUM_APELLIDO_MAT` como opcional (`nullable`) sin romper la persistencia de usuarios ni la generación de nombres abreviados.
3. **Caracteres Especiales en Nombres**:
   Se han identificado y validado nombres reales con:
   * Guiones: `"MARÍA-JOSÉ"`, `"JEAN-PIERRE"`, `"BEN-HUR"`.
   * Apóstrofes: `"N'KARA"`, `"K'ANTU"`, `"D'ALESSANDRA"`, `"MELANY'S"`.
   * Tildes y Ñ: `"SÁNCHEZ"`, `"ZÚÑIGA"`, `"PEÑA"`.
   * Mayúsculas/minúsculas inconsistentes: Normalizados automáticamente a mayúsculas limpias (`MB_CASE_UPPER`).
4. **Normalización del Dígito Verificador**:
   La letra `k` se convierte siempre a mayúscula `K`.
5. **Cero `COUNT(*)` en Consultas Masivas**:
   Dado el alto volumen de registros en Oracle (cientos de millones), nunca se debe invocar `::count()` sin filtros indexados (`where('ALUM_RUT', ...)` o `take($limit)`).

---

## 🏭 Creación de Entidades UTAMED (`Estudiante::createFromIntranet`)

Para instanciar alumnos desde la Intranet a UTAMED de forma limpia y consistente, se utiliza el *Factory Method* en [`Estudiante`](file:///c:/Users/dyri0n/Code/utamed/app/Models/Usuario/Estudiante.php):

```php
use App\Models\Usuario\Estudiante;
use App\Models\Administrativo\Carrera;

// A partir de un AlumnoIntranetData o un modelo VwAlumno
$estudiante = Estudiante::createFromIntranet($alumnoData, $carrera);
```

Este método:
1. Normaliza y formatea el RUT (utilizando [`App\Support\Rut`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Rut.php)).
2. Limpia y transforma nombres y apellidos (trim, mayúsculas, manejo de materno nulo).
3. Asigna la contraseña temporal por defecto (hash del RUT).
4. Crea atómicamente en una transacción el `Usuario` y el `Estudiante` asociado a la `Carrera`.
