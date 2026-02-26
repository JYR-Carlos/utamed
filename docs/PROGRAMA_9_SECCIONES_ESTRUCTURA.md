# Estructura del Programa - 9 Secciones

## Resumen de Secciones

Especificación detallada de las 9 secciones del programa/syllabus con su estructura JSONB.

---

## I. Identificación de la Asignatura

**Origen datos**: Asignatura + Asignación de Curso

**Campos requeridos**:
- Nombre asignatura
- Código
- Créditos SCT
- Horas presenciales (desglose: Cátedra, Taller, Laboratorio)
- Categoría (Si no existe: "Obligatorio")

**Estructura JSONB**:
```json
{
  "nombre_seccion": "I. Identificación de la Asignatura",
  "numeral_romano": "I",
  "orden": 1,
  "contenidos": {
    "nombre_asignatura": "Programación I",
    "codigo": "INF101",
    "creditos_sct": 6,
    "horas":
    {
      "catedra": 3,
      "taller": 2,
      "laboratorio": 1
    },
    "categoria": "Obligatorio"
  }
}
```

---

## II. Presentación, Descripción y Propósito Formativo

**Tipo**: Texto narrativo

**Campos requeridos**:
- Texto descriptivo (paragraph)

**Estructura JSONB**:
```json
{
  "nombre_seccion": "II. Presentación, Descripción y Propósito Formativo",
  "numeral_romano": "II",
  "orden": 2,
  "contenidos": {
    "texto": "Esta asignatura introduce a los estudiantes a los fundamentos de la programación orientada a objetos utilizando lenguaje Python..."
  }
}
```

---

## III. Estándares de la Profesión

**Tipo**: Texto narrativo

**Campos requeridos**:
- Texto descriptivo (paragraph)

**Estructura JSONB**:
```json
{
  "nombre_seccion": "III. Estándares de la Profesión",
  "numeral_romano": "III",
  "orden": 3,
  "contenidos": {
    "texto": "Los profesionales de la computación deben ser capaces de diseñar e implementar soluciones de software que cumplan con los estándares internacionales..."
  }
}
```

---

## IV. ÁREAS, COMPETENCIAS ESPECÍFICAS Y COMPETENCIAS GENÉRICAS

**Tipo**: Lista de items

**Campos requeridos por item**:
- Título (nombre de la competencia)
- Descripción (texto descriptivo)

**Estructura JSONB**:
```json
{
  "nombre_seccion": "IV. Áreas, Competencias Específicas y Competencias Genéricas",
  "numeral_romano": "IV",
  "orden": 4,
  "contenidos": [
    {
      "titulo": "Competencia Específica 1: Diseño de Algoritmos",
      "descripcion": "El estudiante será capaz de diseñar y analizar algoritmos eficientes para resolver problemas computacionales complejos.",
      "tipo": "especifica",
      "orden_item": 1
    },
    {
      "titulo": "Competencia Genérica 1: Comunicación Efectiva",
      "descripcion": "Capacidad para expresar ideas de forma clara y coherente tanto oralmente como por escrito.",
      "tipo": "generica",
      "orden_item": 2
    }
  ]
}
```

---

## V. EVALUACIÓN DIAGNÓSTICA PARA DETERMINAR LOS APRENDIZAJES PREVIOS

**Tipo**: Lista de items

**Campos requeridos por item**:
- Título
- Descripción

**Estructura JSONB**:
```json
{
  "nombre_seccion": "V. Evaluación Diagnóstica para Determinar los Aprendizajes Previos",
  "numeral_romano": "V",
  "orden": 5,
  "contenidos": [
    {
      "titulo": "Evaluación de Conceptos Basicos de Matematicas",
      "descripcion": "Se evaluará el conocimiento previo en áreas de álgebra, geometría y lógica matemática necesarios para la programación.",
      "orden_item": 1
    },
    {
      "titulo": "Evaluación de Pensamiento Algorítmico",
      "descripcion": "Se evalúan capacidades de análisis y descomposición de problemas en pasos lógicos.",
      "orden_item": 2
    }
  ]
}
```

---

## VI. UNIDADES Y CONTENIDOS DE APRENDIZAJE

**Tipo**: Lista de items

**Campos requeridos por item**:
- Título/Nombre de unidad
- Descripción de contenidos
- Número de unidad (opcional pero recomendado)

**Estructura JSONB**:
```json
{
  "nombre_seccion": "VI. Unidades y Contenidos de Aprendizaje",
  "numeral_romano": "VI",
  "orden": 6,
  "contenidos": [
    {
      "num_unidad": 1,
      "titulo": "Fundamentos de Programación",
      "descripcion": "Variables, tipos de datos, operadores, estructuras de control (if, for, while), funciones básicas.",
      "orden_item": 1
    },
    {
      "num_unidad": 2,
      "titulo": "Programación Orientada a Objetos",
      "descripcion": "Clases, objetos, herencia, polimorfismo, encapsulamiento, abstracción.",
      "orden_item": 2
    }
  ]
}
```

---

## VII. PLANIFICACIÓN DE LA ENSEÑANZA

**Tipo**: Sección compleja con subsecciones especializadas

**Subsecciones requeridas**:

### A. Resultados de Aprendizaje
- Texto (puede incluir múltiples resultados por unidad)

### B. Metodología
- Tipo estrategia (Cátedra, Taller, Laboratorio, etc.)

### C. Evaluación
- Tipo de evaluación (Rúbrica analítica, etc.)

**Estructura JSONB**:
```json
{
  "nombre_seccion": "VII. Planificación de la Enseñanza",
  "numeral_romano": "VII",
  "orden": 7,
  "contenidos": {
    "resultados_aprendizaje": {
      "titulo": "Resultados de Aprendizaje",
      "texto": "U1: Identificar conceptos fundamentales de programación. U1: Diferenciar paradigmas de programación. U1: Manejar correctamente el flujo de trabajo en desarrollo de software. U1: Aplicar estructuras de control correctamente. U2: Conocer principios de OOP. U2: Aplicar técnicas de diseño orientado a objetos. U2: Identificar patrones de diseño. U2: Utilizar correctamente herramientas de desarrollo integrado (IDE)."
    },
    "metodologia": {
      "titulo": "Metodología",
      "tipo_estrategia": "Taller"
    },
    "evaluacion": {
      "titulo": "Evaluación",
      "tipo_evaluacion": "Rúbrica analítica"
    }
  }
}
```

---

## VIII. RECURSOS PARA EL APRENDIZAJE

**Tipo**: Lista de items

**Campos requeridos por item**:
- Título/Descripción del recurso
- Referencia bibliográfica (enlazada)
- Tipo de recurso

**Estructura JSONB**:
```json
{
  "nombre_seccion": "VIII. Recursos para el Aprendizaje",
  "numeral_romano": "VIII",
  "orden": 8,
  "contenidos": [
    {
      "descripcion": "Python Programming: An Introduction to Computer Science",
      "referencia_bibliografica": {
        "autor": "John M. Zelle",
        "año": 2016,
        "editorial": "Franklin, Beedle & Associates",
        "isbn": "978-1590282778"
      },
      "tipo_recurso": "Libro",
      "orden_item": 1
    },
    {
      "descripcion": "Documentación oficial de Python",
      "referencia_bibliografica": {
        "url": "https://docs.python.org/3/",
        "acceso": "En línea"
      },
      "tipo_recurso": "Documentación Online",
      "orden_item": 2
    }
  ]
}
```

---

## IX. ASPECTOS ADMINISTRATIVOS Y EVALUACIÓN

**Tipo**: Sección con tablas

**Subsecciones requeridas**:

### A. Descripción administrativa (Título + Texto)

### B. Tabla de Ponderación de Pruebas
- Columnas: Nombre Prueba | Ponderación (%) | Prueba Optativa

### C. Tabla de Evaluación General
- Columnas: Componente | Genera Acta | Porcentaje (%) | Aprobación Obligatoria | Asistencia Obligatoria (%)

**Estructura JSONB**:
```json
{
  "nombre_seccion": "IX. Aspectos Administrativos y Evaluación",
  "numeral_romano": "IX",
  "orden": 9,
  "contenidos": {
    "descripcion": {
      "titulo": "Aspectos Administrativos",
      "texto": "Esta sección detalla los requisitos administrativos y la evaluación de la asignatura conforme a los estándares de la institución."
    },
    "tabla_ponderacion": {
      "titulo": "Ponderación de Pruebas Optativas",
      "filas": [
        {
          "nombre_prueba": "Trabajo Práctico 1",
          "ponderacion": 10,
          "es_optativa": false,
          "orden": 1
        },
        {
          "nombre_prueba": "Proyecto Final",
          "ponderacion": 20,
          "es_optativa": true,
          "orden": 2
        }
      ]
    },
    "tabla_evaluacion": {
      "titulo": "Componentes de Evaluación",
      "filas": [
        {
          "componente": "Pruebas",
          "genera_acta": true,
          "porcentaje": 60,
          "aprobacion_obligatoria": true,
          "asistencia_obligatoria": 80,
          "orden": 1
        },
        {
          "componente": "Trabajos Prácticos",
          "genera_acta": false,
          "porcentaje": 30,
          "aprobacion_obligatoria": false,
          "asistencia_obligatoria": null,
          "orden": 2
        },
        {
          "componente": "Participación",
          "genera_acta": false,
          "porcentaje": 10,
          "aprobacion_obligatoria": false,
          "asistencia_obligatoria": null,
          "orden": 3
        }
      ]
    }
  }
}
```

---

## Estructura Completa en JSONB

```json
{
  "metadata": {
    "asignatura": {
      "id_asignatura": 1,
      "nombre": "Programación I",
      "codigo": "INF101",
      "creditos_sct": 6
    },
    "curso": {
      "id_curso": 1,
      "codigo": "INF101-001",
      "año_academico": 2025,
      "semestre": 1,
      "docente_principal": "Dr. Juan Pérez García"
    }
  },
  "secciones": [
    { "nombre_seccion": "I. Identificación de la Asignatura", ... },
    { "nombre_seccion": "II. Presentación, Descripción y Propósito Formativo", ... },
    { "nombre_seccion": "III. Estándares de la Profesión", ... },
    { "nombre_seccion": "IV. Áreas, Competencias Específicas y Competencias Genéricas", ... },
    { "nombre_seccion": "V. Evaluación Diagnóstica para Determinar los Aprendizajes Previos", ... },
    { "nombre_seccion": "VI. Unidades y Contenidos de Aprendizaje", ... },
    { "nombre_seccion": "VII. Planificación de la Enseñanza", ... },
    { "nombre_seccion": "VIII. Recursos para el Aprendizaje", ... },
    { "nombre_seccion": "IX. Aspectos Administrativos y Evaluación", ... }
  ],
  "timestamp": "2025-02-25T10:30:00Z"
}
```

---

## Notas Importantes

1. **Categoría predeterminada**: Si no existe, se asigna "Obligatorio"
2. **Orden de secciones**: Fijo (1-9)
3. **Numerales romanos**: Automáticos (I-IX)
4. **Referencias bibliográficas**: Se pueden enlazar a una referencia externa (tabla separada)
5. **Tablas administrativas**: Flexibles en cantidad de filas, pero con estructura definida de columnas

---

## Validaciones Sugeridas

- Sección I: Todos los campos obligatorios
- Secciones II-III: Mínimo 100 caracteres de texto
- Secciones IV-VI: Mínimo 1 item en `contenidos`
- Sección VII: Las 3 subsecciones deben existir
- Sección VIII: Mínimo 2 recursos
- Sección IX: Ambas tablas con estructura definida
