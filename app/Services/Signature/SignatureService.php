<?php

declare(strict_types=1);

namespace App\Services\Signature;

use App\Models\Signature;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Class SignatureService
 *
 * Gestiona el ciclo de vida de las firmas digitales PNG.
 * Las firmas se reciben como base64 desde el cliente,
 * se decodifican a binario y se almacenan en disco.
 */
class SignatureService extends BaseService
{
    /**
     * Disco de almacenamiento para las firmas.
     */
    private const DISK = 'public';

    /**
     * Carpeta dentro del disco donde se guardan los archivos PNG.
     */
    private const FOLDER = 'signatures';

    /**
     * {@inheritdoc}
     */
    protected function getModelInstance(): Model
    {
        return new Signature;
    }

    /**
     * Campos por los que se puede hacer búsqueda libre.
     *
     * @var array<string>
     */
    protected array $searchableFields = [
        'entity_type',
    ];

    // ----------------------------------------------------------------
    // Escritura
    // ----------------------------------------------------------------

    /**
     * Método store.
     */
    public function store(array $data): Signature
    {
        return $this->transaction(function () use ($data) {
            $path = $this->savePng($data['signature']);

            /** @var Signature $signature */
            $signature = $this->model->create([
                'uuid' => Str::uuid()->toString(),
                'entity_type' => $data['entity_type'],
                'entity_id' => $data['entity_id'],
                'company_uuid' => $data['company_uuid'],
                'path' => $path,
                'disk' => self::DISK,
                'mime_type' => 'image/png',
                'size_bytes' => $this->resolveSize($data['signature']),
                'ip_address' => $data['ip_address'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'speed' => $data['speed'] ?? null,
                'state' => $data['state'] ?? 'DETENIDO',
            ]);

            return $signature;
        });
    }

    /**
     * Método replace.
     */
    public function replace(string $uuid, array $data): Signature
    {
        return $this->transaction(function () use ($uuid, $data) {
            $old = $this->findByUuid($uuid);

            if ($old) {
                $this->deleteFile($old);
                $old->delete();
            }

            $path = $this->savePng($data['signature']);

            return $this->model->create([
                'uuid' => Str::uuid()->toString(),
                'entity_type' => $old?->entity_type ?? $data['entity_type'] ?? null,
                'entity_id' => $old?->entity_id ?? $data['entity_id'] ?? null,
                'company_uuid' => $data['company_uuid'] ?? $old?->company_uuid,
                'path' => $path,
                'disk' => self::DISK,
                'mime_type' => 'image/png',
                'size_bytes' => $this->resolveSize($data['signature']),
                'ip_address' => $data['ip_address'] ?? $old?->ip_address ?? null,
                'latitude' => $data['latitude'] ?? $old?->latitude ?? null,
                'longitude' => $data['longitude'] ?? $old?->longitude ?? null,
                'speed' => $data['speed'] ?? $old?->speed ?? null,
                'state' => $data['state'] ?? $old?->state ?? 'DETENIDO',
            ]);
        });
    }

    /**
     * Método delete.
     */
    public function delete(string $uuid): bool
    {
        return $this->transaction(function () use ($uuid) {
            $record = $this->findByUuid($uuid);

            if (! $record) {
                return false;
            }

            $this->deleteFile($record);

            return $record->delete();
        });
    }

    // ----------------------------------------------------------------
    // Lectura
    // ----------------------------------------------------------------

    /**
     * Método getLatest.
     */
    public function getLatest(string $entityType, int $entityId): ?Signature
    {
        try {
            return $this->query()
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->latest()
                ->first();
        } catch (Throwable $e) {
            Logger::error('SignatureService#getLatest error: '.$e->getMessage(), $e);
            throw $e;
        }
    }

    public function getLatestUrl(string $entityType, int $entityId): ?string
    {
        $signature = $this->getLatest($entityType, $entityId);

        if (! $signature) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($signature->disk);

        return $disk->url($signature->path);
    }

    // ----------------------------------------------------------------
    // Helpers privados
    // ----------------------------------------------------------------

    /**
     * Decodifica el base64 y guarda el PNG en disco.
     * Retorna el path relativo almacenado.
     */
    private function savePng(string $dataUri): string
    {
        // Separar "data:image/png;base64," del contenido real
        [, $base64] = explode(',', $dataUri, 2);

        $binary = base64_decode($base64, strict: true);
        $filename = Str::uuid()->toString().'.png';
        $path = self::FOLDER.'/'.$filename;

        Storage::disk(self::DISK)->put($path, $binary);

        return $path;
    }

    /**
     * Elimina el archivo físico de una firma del disco.
     */
    private function deleteFile(Signature $signature): void
    {
        if (Storage::disk($signature->disk)->exists($signature->path)) {
            Storage::disk($signature->disk)->delete($signature->path);
        }
    }

    /**
     * Calcula el tamaño en bytes del PNG a partir del base64.
     * Fórmula: base64 ocupa ~4/3 del binario original.
     */
    private function resolveSize(string $dataUri): int
    {
        [, $base64] = explode(',', $dataUri, 2);

        return (int) (strlen($base64) * 3 / 4);
    }
}
