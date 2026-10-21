<?php

namespace App\Http\Requests\Settings;

use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'nombre1' => ['sometimes', 'string', 'max:255'],
            'nombre2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'apellido1' => ['sometimes', 'string', 'max:255'],
            'apellido2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(Usuario::class)->ignore($this->user()->id_usuario, 'id_usuario'),
            ],
        ];
    }
}
