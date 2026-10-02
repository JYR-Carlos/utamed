<?php

namespace App\Services;

/**
 * Se encarga de generar un url válido para cargar una imagen en el frontend.
 * Sólo retorna el URL formateado, no manda el jpg por la respuesta ni verifica que exista.
 * 
 * EJEMPLO
 * 
 * 1. Controlador de Perfiles: Busca información de perfil
 * 2. Controlador de Perfiles: Agregar Link de Búsqueda de Imagen de Perfil (llama al servicio)
 * 3. FotoIntranetService:     Generar Link y Devolver al Controlador
 * 
 */ 
class FotoIntranetService {
    /**
     * Guarda la dirección base del link de fotos
     * @var string
     */
    private $intranetFotoBaseUrl = "https://portal.uta.cl/fotos/";

    public function __construct() {
        
    }

    public function getImagenPerfilURL(string $rut): string {
        // format rut
        // armar link
        //
        $rutFormateado = $this->formatRut($rut); 
        $url = $this->intranetFotoBaseUrl.$rutFormateado;
        return $url;
    }

    private function formatRut(string $rut): string {
        // limpiar rut, sacar puntos y guion
        // sacar largo del rut:
        // largo == 9:
        //      dejar en el formato 0XXXXXXXXX.JPG
        // largo == 8:
        //      dejar en el formato 00XXXXXXXX.JPG
        $rutLimpio = str_replace(['.', '-'], '', $rut);
        $largoRut = strlen($rutLimpio);

        $extension = "0";
        if ($largoRut == 8) {
            $extension = "00";
        }
        $formateado = $extension.$rutLimpio.".JPG"; 
        return $formateado;
    }

}