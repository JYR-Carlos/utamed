<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\BuzonEstudiante;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buzón del estudiante (OBS-48, OBS-54): la mensajería de todos sus ramos y
 * la agenda de sus actividades en una lista, cada fila con su origen, para
 * llegar a cualquier conversación en un clic.
 *
 * Es sólo un índice: cada conversación se lee y se responde en su lugar (la
 * Mensajería del ramo o las Consultas de la actividad). Abrir esta página no
 * marca nada como leído.
 */
class MensajesController extends Controller
{
    public function index(BuzonEstudiante $buzon): Response
    {
        return Inertia::render('student/Mensajes', [
            'hilos' => $buzon->hilos((int) Auth::id()),
        ]);
    }
}
