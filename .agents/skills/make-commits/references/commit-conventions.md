# 📖 Guía de Convenciones y Formato de Commits

Esta referencia detalla el estándar estricto de Conventional Commits para la skill `make-commits`.

---

## 1. Estructura General

```text
<tipo>(<alcance>): <resumen en minúsculas, imperativo, sin punto final>

- <detalle de impacto 1>
- <detalle de impacto 2>
```

### Ejecución en Git CLI:
```bash
git commit -m "feat(estudiante): sincronizar datos desde intranet oracle" -m "- nuevo comando artisan estudiantes:sync para ejecucion manual o cron
- requiere configurar variable INTRANET_DB_SERVICE en .env
- agrega suite de pruebas de integracion con oracle"
```

---

## 2. Reglas para los Detalles (Cuerpo del Mensaje)

> [!IMPORTANT]
> **Los detalles son OPCIONALES.**  
> Si el título es autoexplicativo (ej: `docs(permisos): corregir enlaces rotos en guia`), **no se agrega ningún detalle**.
> 
> Úsalos únicamente cuando aporten valor real sobre las siguientes 9 categorías permitidas.

### Categorías Permitidas de Detalles (Sin orden obligatorio):

| Categoría | ¿Cuándo aplica? | Ejemplo de Viñeta |
| :--- | :--- | :--- |
| **1. Nuevas habilidades para el desarrollador** | Comandos, scripts, seeds, helpers | `- nuevo comando artisan intranet:inspect para auditar registros raw` |
| **2. Nuevas configuraciones requeridas** | Cambios en `.env`, configs, migraciones | `- requiere configurar DB_ORACLE_PORT y DB_ORACLE_SID en .env` |
| **3. Cambios de filosofía / arquitectura** | Refactorización de contratos, inversión de control | `- desacopla la consulta de intranet de la creacion de usuarios en el seeder` |
| **4. Resumen de cambios de alto volumen** | Muchos archivos creados o actualizados | `- 4 modelos externos configurados con llaves compuestas y casting` |
| **5. Cambios en tech stack / dependencias** | Nuevos paquetes, versiones, drivers | `- actualiza dependencia yajra/laravel-oci8 a v11.x` |
| **6. Exclusivo de FIXES (causa y solución)** | Errores corregidos (técnico pero conciso) | `- resuelve timeout en consultas masivas aplicando eager loading de cursos` |
| **7. Nuevos tests** | Suites de prueba agregadas | `- agrega tests de integracion para validacion de rut duplicado` |
| **8. Cambios en documentación** | Guías, diagramas, manuales | `- documenta formato del payload json esperado por el endpoint` |
| **9. Cambios en archivos parcialmente estáticos** | Modelos, constantes, enumeraciones | `- agrega constantes de estado de matricula en EstudianteEnum` |

---

## 3. Ejemplos Prácticos (Buenos vs Malos)

### Ejemplo 1: Nueva funcionalidad con impacto en configuración y comandos

#### ❌ Incorrecto (Detalles técnicos redundantes o narrativos):
```text
feat(oracle): agregar conexion oracle
- modifique el archivo config/database.php agregando la conexion
- cree el modelo VwAlumno en app/Models/External
- agregue una funcion para hacer select
```

#### ✅ Correcto (Enfocado en habilidades y configuración):
```text
feat(intranet): habilitar conexion y modelos de lectura oracle

- nuevo comando artisan intranet:test-connection para diagnostico de red
- requiere definir credenciales de conexion oracle en .env
- 3 modelos externos configurados para vistas de alumnos, cursos e inscripciones
```

---

### Ejemplo 2: Commit sin detalles necesarios (Resumen autoexplicativo)

#### ✅ Correcto:
```text
docs(permisos): actualizar matriz de politicas de roles de docente
```
*(No requiere detalles porque el título explica completamente el alcance).*

---

### Ejemplo 3: Corrección de un Bug (Fix)

#### ✅ Correcto:
```text
fix(auth): corregir denegacion de acceso en endpoint de inscripcion rapida

- resuelve error 403 al validar incorrectamente el rol docente en lugar de admin
- agrega prueba de feature para verificar autorizacion de endpoints ajax
```

---

### Ejemplo 4: Refactorización arquitectónica

#### ✅ Correcto:
```text
refactor(estudiante): separar capa de sincronizacion de la persistencia local

- traslada la logica de conversion de rut y nombres a EstudianteService
- desacopla la vista de oracle del modelo interno de usuario
- 2 nuevas suites de pruebas unitarias para metodos de parseo
```
