<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\RentalContract;

use App\Models\Owner;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use App\Traits\HandlesApiResponse;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRentalContractRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_uuid' => ['required', 'uuid', $this->reglaVehiculoDelContexto()],
            'owner_uuid' => ['nullable', 'uuid', 'exists:owners,uuid', $this->reglaPropietarioDelContexto()],
            'start_date' => ['required', 'date'],
            'monthly_term' => ['nullable', 'integer', 'min:1', 'max:60'],
            'monthly_fee' => ['required', 'numeric', 'gt:0'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * El vehículo debe pertenecer a la empresa (y a la sede) activa de la petición.
     * Se consulta el modelo con sus global scopes en vez de `exists`, igual que el resto
     * de requests de flota, para no referenciar un vehículo de otra empresa.
     */
    private function reglaVehiculoDelContexto(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! $this->hayContextoDeEmpresa()) {
                $fail('No hay empresa activa en la petición.');

                return;
            }

            $vehiculo = Vehicle::query()->where('uuid', $value)->first(['uuid', 'third_party_uuid']);

            if (! $vehiculo) {
                $fail('El vehículo no pertenece a la empresa o sede activa.');

                return;
            }

            if (
                empty($this->input('owner_uuid'))
                && ! Owner::query()->where('vehicle_uuid', $value)->exists()
                && empty($vehiculo->third_party_uuid)
            ) {
                $fail('El vehículo seleccionado no tiene un propietario registrado.');
            }
        };
    }

    /**
     * Si el propietario está asociado a un vehículo o tercero, debe pertenecer
     * a la empresa activa de la petición.
     */
    private function reglaPropietarioDelContexto(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '' || ! $this->hayContextoDeEmpresa()) {
                return;
            }

            $owner = Owner::query()->where('uuid', $value)->first(['uuid', 'vehicle_uuid', 'third_party_uuid']);
            if (! $owner) {
                return;
            }

            if ($owner->vehicle_uuid && ! Vehicle::query()->where('uuid', $owner->vehicle_uuid)->exists()) {
                $fail('El propietario seleccionado no pertenece a la empresa activa.');

                return;
            }

            if ($owner->third_party_uuid && ! ThirdParty::query()->where('uuid', $owner->third_party_uuid)->exists()) {
                $fail('El propietario seleccionado no pertenece a la empresa activa.');
            }
        };
    }

    /**
     * Sin empresa activa solo un SUPERADMIN puede operar (en cualquier empresa);
     * para el resto se falla cerrado.
     */
    private function hayContextoDeEmpresa(): bool
    {
        return (bool) $this->attributes->get('current_company_uuid')
            || (bool) $this->user()?->hasRole('SUPERADMIN');
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }

    public function attributes(): array
    {
        return [
            'vehicle_uuid' => 'Vehículo',
            'owner_uuid' => 'Propietario',
            'start_date' => 'Fecha de inicio',
            'monthly_term' => 'Plazo (meses)',
            'monthly_fee' => 'Canon mensual',
            'observations' => 'Observaciones',
        ];
    }
}
