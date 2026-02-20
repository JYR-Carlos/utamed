<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Curso\TipoSeccion;
use App\Models\Usuario\Docente;
use Illuminate\Validation\Validator;

class StoreSeccionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id_tipo_seccion' => 'required|integer',
            'id_docente' => 'nullable|integer',
        ];
    }

    /**
     * Custom validation logic using the 'after' hook to properly handle docente existence.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Validar que el tipo de sección existe
            if ($this->has('id_tipo_seccion')) {
                $tipoExists = TipoSeccion::where('id_tipo_seccion', $this->id_tipo_seccion)->exists();
                if (!$tipoExists) {
                    $validator->errors()->add('id_tipo_seccion', 'El tipo de sección no existe.');
                }
            }

            // Validar que el docente existe (sin filtros de contexto)
            if ($this->has('id_docente') && $this->id_docente !== null) {
                $docenteExists = Docente::withoutGlobalScopes()->where('id_docente', $this->id_docente)->exists();
                if (!$docenteExists) {
                    $validator->errors()->add('id_docente', 'El docente seleccionado no existe.');
                }
            }
        });
    }
}
