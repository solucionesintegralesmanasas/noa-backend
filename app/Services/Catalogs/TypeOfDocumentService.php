<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\TypeOfDocument;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de TipoDocumento.
 *
 * Este servicio gestiona los diferentes tipos de identificación legal, permitiendo
 * clasificar y validar los documentos de identidad de los terceros en el sistema.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class TypeOfDocumentService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name', 'prefix'];

    protected function getModelInstance(): Model
    {
        return new TypeOfDocument;
    }

    /**
     * Método getAllTypeOfDocumentsWithPagination.
     */
    public function getAllTypeOfDocumentsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'name', 'prefix']);
    }

    /**
     * Método getAllTypeOfDocuments.
     */
    public function getAllTypeOfDocuments(): Collection
    {
        return $this->all(columns: ['uuid', 'name', 'prefix']);
    }

    /**
     * Método getTypeOfDocumentByUuid.
     */
    public function getTypeOfDocumentByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createTypeOfDocument.
     */
    public function createTypeOfDocument(array $data): Model
    {
        return $this->transaction(fn () => TypeOfDocument::create([
            'name' => $data['name'],
            'prefix' => $data['prefix'],
            'status' => $data['status'] ?? true,
        ]));
    }

    /**
     * Método updateTypeOfDocument.
     */
    public function updateTypeOfDocument(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var TypeOfDocument $record */
            $record = TypeOfDocument::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'name' => $data['name'] ?? $record->name,
                'prefix' => $data['prefix'] ?? $record->prefix,
                'status' => $data['status'] ?? $record->status,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteTypeOfDocument.
     */
    public function deleteTypeOfDocument(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('TypeOfDocumentService@deleteTypeOfDocument: '.$e->getMessage());
            throw $e;
        }
    }
}
