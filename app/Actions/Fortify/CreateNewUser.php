<?php

namespace App\Actions\Fortify;

use App\Models\Usuario\Usuario;
use App\Support\DataGenerators\ChileanNameGenerator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): Usuario
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(Usuario::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $partes = preg_split('/\s+/', trim($input['name']), 2);
        $nombre1 = $partes[0] ?? 'Usuario';
        $apellido1 = $partes[1] ?? 'Nuevo';

        $baseUsername = strtolower(explode('@', $input['email'])[0]);
        $baseUsername = preg_replace('/[^a-z0-9]/', '', $baseUsername) ?: 'user';
        $username = $baseUsername;
        $counter = 1;
        while (Usuario::where('username', $username)->exists()) {
            $username = $baseUsername . $counter++;
        }

        $rut = ChileanNameGenerator::generarRUT();
        while (Usuario::where('rut', $rut)->exists()) {
            $rut = ChileanNameGenerator::generarRUT();
        }

        return Usuario::create([
            'rut' => $rut,
            'username' => $username,
            'nombre1' => $nombre1,
            'apellido1' => $apellido1,
            'email' => $input['email'],
            'passhash' => Hash::make($input['password']),
            'fecha_cambio_passhash' => now(),
            'esta_activo' => true,
        ]);
    }
}
