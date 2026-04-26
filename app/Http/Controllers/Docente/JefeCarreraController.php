<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Carrera;
use App\Models\Curso\Programa;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class JefeCarreraController extends Controller
{
    public function dashboard()
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->docente) {
            return redirect('/docente/dashboard')->with('error', 'No tienes acceso a esta sección');
        }

        $jefatura = $this->resolveJefatura($user);

        if (!$jefatura) {
            return redirect('/docente/dashboard')->with('error', 'No tienes rol de Jefe de Carrera activo');
        }

        return Inertia::render('jefe-carrera/Dashboard', [
            'carrera' => [
                'nombre' => $jefatura['carrera_nombre'] ?? 'Diseño Multimedia',
                'semestre' => now()->month <= 6 ? 'Primero' : 'Segundo',
                'ano' => (int) now()->year,
            ],
        ]);
    }

    public function seguimiento()
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->docente) {
            return redirect('/docente/dashboard')->with('error', 'No tienes acceso a esta sección');
        }

        $jefatura = $this->resolveJefatura($user);

        if (!$jefatura) {
            return redirect('/docente/dashboard')->with('error', 'No tienes rol de Jefe de Carrera activo');
        }

        return Inertia::render('jefe-carrera/Seguimiento', [
            'carrera' => [
                'nombre' => $jefatura['carrera_nombre'] ?? 'Diseño Multimedia',
            ],
        ]);
    }

    public function aprobarPrograma(int $programaId)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$this->resolveJefatura($user)) {
            return redirect('/docente/dashboard')->with('error', 'No tienes rol de Jefe de Carrera activo');
        }

        return back()->with('success', "Solicitud de aprobación enviada para el programa #{$programaId}.");
    }

    public function rechazarPrograma(Request $request, int $programaId)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$this->resolveJefatura($user)) {
            return redirect('/docente/dashboard')->with('error', 'No tienes rol de Jefe de Carrera activo');
        }

        $request->validate([
            'notas' => ['nullable', 'string', 'max:3000'],
        ]);

        $programa = Programa::findOrFail($programaId);

        // Autorizar usando la Policy (admins y jefes de carrera pueden rechazar)
        $this->authorize('reject', $programa);

        $estadosPermitidos = ['COMPLETO', 'APROBADO', 'BASICO_COMPLETO'];
        if (!in_array($programa->estado, $estadosPermitidos)) {
            return back()->with('error', "No se puede devolver un programa en estado {$programa->estado}.");
        }

        $razonRechazo = trim($request->input('notas', 'Sin observaciones'));
        $estadoOrigen = $programa->estado;

        DB::transaction(function () use ($programa, $razonRechazo, $user) {
            DB::statement("SELECT set_config('app.accion_tipo',   'RECHAZO', true)");
            DB::statement("SELECT set_config('app.razon_rechazo', ?, true)", [$razonRechazo]);
            DB::statement("SELECT set_config('app.actor_id',      ?, true)", [(string) $user->id_usuario]);

            $programa->update([
                'estado'           => 'BORRADOR',
                'fecha_aprobacion' => null,
                'revisado_por'     => null,
            ]);
        });

        Log::info('Programa devuelto a revisión por Jefe de Carrera', [
            'id_programa'   => $programa->id_programa,
            'estado_origen' => $estadoOrigen,
            'rechazado_por' => $user->id_usuario,
            'razon_rechazo' => $razonRechazo,
        ]);

        return back()->with('warning', "Solicitud de cambios enviada. El programa #{$programaId} fue devuelto a borrador.");
    }

    private function resolveJefatura(Usuario $user): ?array
    {
        $asignacion = UsuarioRolAsignacion::query()
            ->where('id_usuario', $user->id_usuario)
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereHas('rol', fn($q) => $q->where('nombre', 'Jefe de Carrera'))
            ->whereHas('contexto.tipoContexto', fn($q) => $q->where('categoria', 'carrera'))
            ->latest('id_ura')
            ->first();

        if (!$asignacion) {
            return null;
        }

        $carrera = Carrera::query()
            ->select('id_carrera', 'nombre', 'id_contexto')
            ->where('id_contexto', $asignacion->id_contexto)
            ->first();

        return [
            'id_contexto' => $asignacion->id_contexto,
            'carrera_id' => $carrera?->id_carrera,
            'carrera_nombre' => $carrera?->nombre,
        ];
    }
}
