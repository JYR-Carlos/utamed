<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Services\FotoIntranetService;

/**
 * Ficha del estudiante autenticado.
 *
 * Los datos institucionales (nombre, RUT, correo, carrera…) vienen de la
 * Intranet y son de sólo lectura. Lo único que el alumno edita es su contacto
 * personal: correo personal, celular y redes sociales (T10).
 */
class PerfilController extends Controller
{
    public function __construct(private FotoIntranetService $fotoIntranet) {}

    /** Dominios aceptados por red: sólo enlaces al perfil en esa red. */
    private const DOMINIOS_REDES = [
        'youtube' => ['youtube.com', 'youtu.be'],
        'x' => ['x.com', 'twitter.com'],
        'instagram' => ['instagram.com'],
        'linkedin' => ['linkedin.com'],
    ];

   
    public function show()
    {
        /** @var Usuario $user */
        $user = Auth::user();
        $estudiante = $user->estudiante()->with('carrera')->firstOrFail();

        $totalCursos = InscripcionCurso::where('id_estudiante', $estudiante->id_estudiante)
            ->where('estado_inscripcion', 'INSCRITO')
            ->count();

        $urlImagenPerfil = $this->fotoIntranet->getImagenPerfilURL($user->rut);

        return Inertia::render('student/Perfil', [
            'perfil' => [
                'nombre_completo' => $user->nombre_completo,
                'rut' => $user->rut,
                'email' => $user->email,
                'username' => $user->username,
                'carrera_nombre' => $estudiante->carrera->nombre,
                'agno_ingreso' => $estudiante->agno_ingreso,
                'total_cursos' => $totalCursos,
            ],
            'urlImagenPerfil' => $urlImagenPerfil,
            'contacto' => $estudiante->contacto(),
            'semestreActual' => Carbon::now()->month > 6 ? 2 : 1,
        ]);
    }

    /**
     * Guarda el contacto personal del alumno.
     *
     * Se toman sólo las tres claves validadas y se asignan una a una: cualquier
     * otro campo que venga en la petición (agno_ingreso, id_carrera, email…)
     * se ignora, así que no hay mass-assignment posible sobre lo institucional.
     */
    public function updateContacto(Request $request): RedirectResponse
    {
        /** @var Usuario $user */
        $user = Auth::user();
        $estudiante = $user->estudiante()->firstOrFail();

        $reglasRed = fn (string $red) => [
            'nullable',
            'string',
            'max:255',
            'url:https,http',
            function (string $attribute, mixed $value, \Closure $fail) use ($red) {
                $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                $host = preg_replace('/^(www\.|m\.)/', '', $host);

                if (!in_array($host, self::DOMINIOS_REDES[$red], true)) {
                    $fail('El enlace debe ser de ' . implode(' o ', self::DOMINIOS_REDES[$red]) . '.');
                }
            },
        ];

        $validated = $request->validate([
            'correo_personal' => ['nullable', 'string', 'max:255', 'email:rfc'],
            'celular' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'redes_sociales' => ['nullable', 'array:' . implode(',', Estudiante::REDES_SOCIALES)],
            'redes_sociales.youtube' => $reglasRed('youtube'),
            'redes_sociales.x' => $reglasRed('x'),
            'redes_sociales.instagram' => $reglasRed('instagram'),
            'redes_sociales.linkedin' => $reglasRed('linkedin'),
        ], [
            'correo_personal.email' => 'Ingresa un correo válido.',
            'celular.regex' => 'Ingresa sólo números, con + y código de país si quieres (por ejemplo +56 9 1234 5678).',
            'redes_sociales.array' => 'Sólo se aceptan YouTube, X, Instagram y LinkedIn.',
            'redes_sociales.*.url' => 'Ingresa el enlace completo, empezando por https://.',
        ]);

        $redes = collect(Estudiante::REDES_SOCIALES)
            ->mapWithKeys(fn (string $red) => [$red => $validated['redes_sociales'][$red] ?? null])
            ->filter()
            ->all();

        $estudiante->correo_personal = $validated['correo_personal'] ?? null;
        $estudiante->celular = isset($validated['celular'])
            ? preg_replace('/\s+/', ' ', trim($validated['celular']))
            : null;
        $estudiante->redes_sociales = $redes === [] ? null : $redes;
        $estudiante->save();

        return back()->with('success', 'Tus datos de contacto se guardaron.');
    }
}
