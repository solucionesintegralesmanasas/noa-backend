<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\InspectionItem;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la carga masiva de ítems de inspección desde Excel.
 */
class ImportInspectionItemRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Debes seleccionar un archivo Excel para importar.',
            'file.file' => 'El archivo adjunto no es válido.',
            'file.mimes' => 'Solo se permiten archivos Excel (.xlsx, .xls) o CSV.',
            'file.max' => 'El archivo no debe superar los 5 MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'Archivo Excel',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
