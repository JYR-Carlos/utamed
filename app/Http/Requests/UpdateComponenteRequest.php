<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use Illuminate\Validation\Validator;

class UpdateComponenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_tipo_componente' => 'required|integer',
            'id_docente' => 'nullable|integer',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->has('id_tipo_componente')) {
                $tipoExists = TipoComponente::where('id_tipo_componente', $this->id_tipo_componente)->exists();
                if (!$tipoExists) {
                    $validator->errors()->add('id_tipo_componente', 'El tipo de componente no existe.');
                }
            }

            if ($this->has('id_docente') && $this->id_docente !== null) {
                $docenteExists = Docente::withoutGlobalScopes()->where('id_docente', $this->id_docente)->exists();
                if (!$docenteExists) {
                    $validator->errors()->add('id_docente', 'El docente seleccionado no existe.');
                }
            }
        });
    }
}
