<?php

namespace App\Api\V1\Requests\Student\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class GetAcademicCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('incluir_otros')) {
            $val = $this->query('incluir_otros', $this->input('incluir_otros'));
            $bool = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool !== null) {
                $this->merge(['incluir_otros' => $bool]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'periodo' => ['sometimes', 'nullable', 'string', 'in:todos'],
            'semestre' => ['sometimes', 'nullable', 'integer', 'in:1,2'],
            'agno' => ['sometimes', 'nullable', 'integer', 'min:2000', 'max:2100'],
            'id_curso' => ['sometimes', 'nullable', 'integer'],
            'incluir_otros' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
