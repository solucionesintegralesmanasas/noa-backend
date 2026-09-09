<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\ControlSheet;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Servicio para la gestión de hojas de control (ControlSheet).
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.0
 *
 * @since    V 1.0.0
 *
 * @created  2026-06-28
 */
class ControlSheetService extends BaseService
{
    protected array $searchableFields = [
        'observations',
    ];

    protected function getModelInstance(): Model
    {
        return new ControlSheet;
    }

    public function getAllControlSheetsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query()->with([
            'vehicle:uuid,vehicle_license_plate',
            'company:uuid,business_name',
            'media',
        ]);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('observations', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', function ($vq) use ($search) {
                        $vq->where('vehicle_license_plate', 'like', "%{$search}%");
                    })
                    ->orWhereHas('company', function ($cq) use ($search) {
                        $cq->where('business_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($perPage, ['id', 'uuid', 'company_uuid', 'vehicle_uuid', 'observations', 'is_active'], 'page', $page);
    }

    public function getAllControlSheets(): Collection
    {
        return $this->all(relations: ['vehicle']);
    }

    public function getControlSheetByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: ['vehicle']);
    }

    public function createControlSheet(array $data): Model
    {
        return $this->transaction(fn () => ControlSheet::create([
            'company_uuid' => $data['company_uuid'],
            'vehicle_uuid' => $data['vehicle_uuid'],
            'observations' => $data['observations'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    public function updateControlSheet(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            $record->update([
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'observations' => $data['observations'] ?? $record->observations,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record->fresh();
        });
    }

    public function deleteControlSheet(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('ControlSheetService@deleteControlSheet: '.$e->getMessage());
            throw $e;
        }
    }

    public function uploadPdf(string $uuid, UploadedFile $file): string
    {
        $record = $this->findByUuid($uuid);
        $record->clearFilesByType('CONTROL_SHEET_PDF');
        $record->addFile($file, 'CONTROL_SHEET_PDF');

        return $record->getFirstMediaUrl('CONTROL_SHEET_PDF');
    }

    public function uploadMultiplePdfs(string $uuid, array $files): array
    {
        $record = $this->findByUuid($uuid);
        $record->clearFilesByType('CONTROL_SHEET_PDF');

        $urls = [];

        foreach ($files as $file) {
            $media = $record->addFile($file, 'CONTROL_SHEET_PDF');
            $urls[] = [
                'uuid' => $media->uuid,
                'url' => $media->getUrl(),
                'name' => $media->file_name,
            ];
        }

        return $urls;
    }

    public function getPdfs(string $uuid): Collection
    {
        $record = $this->findByUuid($uuid);

        return $record->getMedia('CONTROL_SHEET_PDF');
    }

    public function deletePdf(string $controlSheetUuid, string $mediaUuid): bool
    {
        $record = $this->findByUuid($controlSheetUuid);

        return $record->removeFile($mediaUuid);
    }
}
