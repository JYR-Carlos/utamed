<?php

namespace App\Services;

use App\Support\Rut;

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

    /**
     * El portal nombra las fotos con el RUT sin puntos ni guion, DV en
     * mayúscula y relleno con ceros a la izquierda hasta 10 caracteres:
     * 12024627-5 → 0120246275.JPG, 1234567-k → 001234567K.JPG.
     */
    private function formatRut(string $rut): string
    {
        return str_pad(Rut::soloDigitos($rut), 10, '0', STR_PAD_LEFT) . '.JPG';
    }

}