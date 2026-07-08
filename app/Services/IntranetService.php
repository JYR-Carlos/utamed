<?php

namespace App\Services;


use App\Models\Usuario;


class IntranetService {
    /**
     * 
     * COSAS QUE HACE ESTE SERVICIO
     * 
     * - METODOS DE LOGICA DE NEGOCIO:
     *      - BUSCAR CÓDIGO CURSO (COMPONENTE (CUR_CODIGO))
     *          (DURANTE LA CREACIÓN DEL CURSO Y EDIT DEL CURSO)
     *      - BUSCAR CÓODIGO DE INSCRIPCIÓN
     *          (LUEGO DE HABER CREADO UN CURSO)
     *      - INSCRIBIR ALUMNOS AUTOMÁTICAMENTE 
     *          (CON VISTA DE INSCRIPCIÓN POR CURSO, CON LA FUNCIÓN ANTERIOR, RECUPERAR LISTA INSCRITOS EN CURSO INTRANET)
     *          (RECUPERAR LISTA ALUMNOS REGISTRADOS EN LA UTAMED)
     *          (CON EL RUT, APAREAR ALUMNO INSCRITO INTRANET > CREANDO INSCRIPCION COMPONENTE (TABLA UTAMED) DEL CURSO AUTOMATICAMENTE POR CADA ALUMNO INSCRITO AL CURSO)
     *              [LA IDEA ES QUE TODOS LOS ALUMNOS ESTEN DE ANTEMANO REGISTRADOS PARA QUE NO FALLE, COONVERSAR]
     *              [SI FALLA, OMITIR ALUMNO]
     *          (DURANTE LA CREACION DE INSCRIPCION COMPONENTE, ASOCIAR INS_ID A CODIGO INSCRIPCION DE TABLA UTAMED)
     *          (DAR ROL ESTUDIANTE AL ESTUDIANTE POR COMPONENTE INSCRITA (VER SI HAY FUNCION REUTILIZABLE, VER FRONT INSCRIPCION ALUMNO))
     * 
     * actualizarInscripciones(cur_codigo) (ve los estudiantes inscritos de intranet, y verifica su inscripcion, se hace cada vez que hay procesos de inscripcion)
     * verificarInscripcion(rut) (ratificacion del alumno)
     * 
     * asociarCursoNuevoConCurCodigo(semestre actual, año actual, cod carrera, plan año, cod asignatura) (le pasa datos y recupera los codigos de todas las componentes asociadas al curso)
     * 
     * 
     * 
     * 
     */


}