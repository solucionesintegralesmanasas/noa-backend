<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\Contact;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio especializado en la gestión de Contactos Corporativos.
 *
 * Administra la información de personas clave dentro de las organizaciones,
 * gestionando sus roles representativos (Comercial, Legal, Técnico, etc.) y asegurando
 * que cada contacto esté correctamente vinculado a una empresa matriz.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ContactService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['first_name', 'last_name', 'email'];

    protected function getModelInstance(): Model
    {
        return new Contact;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'company:uuid,business_name',
        'municipality:uuid,name',
    ];

    /**
     * Método getAllContactsWithPagination.
     */
    public function getAllContactsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, self::RELATIONS);
    }

    /**
     * Método getAllContacts.
     */
    public function getAllContacts(): Collection
    {
        return $this->all(relations: self::RELATIONS);
    }

    /**
     * Método getContactByUuid.
     */
    public function getContactByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * Método createContact.
     */
    public function createContact(array $data): Model
    {
        return $this->transaction(fn () => Contact::create([
            'company_uuid' => $data['company_uuid'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'representative_type' => $data['representative_type'],
            'country' => $data['country'] ?? null,
            'municipality_uuid' => $data['municipality_uuid'] ?? null,
            'email' => $data['email'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]));
    }

    /**
     * Método updateContact.
     */
    public function updateContact(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'first_name' => $data['first_name'] ?? $record->first_name,
                'last_name' => $data['last_name'] ?? $record->last_name,
                'representative_type' => $data['representative_type'] ?? $record->representative_type,
                'country' => $data['country'] ?? $record->country,
                'municipality_uuid' => $data['municipality_uuid'] ?? $record->municipality_uuid,
                'email' => $data['email'] ?? $record->email,
                'phone_number' => $data['phone_number'] ?? $record->phone_number,
                'remarks' => $data['remarks'] ?? $record->remarks,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteContact.
     */
    public function deleteContact(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el contacto con UUID: {$uuid}");
        }
    }
}
