<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Trait HasFiles
 *
 * Wrapper empresarial sobre Spatie MediaLibrary.
 * Mantiene la semántica de negocio (vencimientos, roles de archivos)
 * pero delega el procesamiento, almacenamiento y base de datos a Spatie.
 *
 * IMPORTANTE: El Modelo que use este trait DEBE implementar
 * la interfaz \Spatie\MediaLibrary\HasMedia
 *
 * @property int|string $id
 * @property-read \Illuminate\Database\Eloquent\Collection|Media[] $media
 *
 * @mixin Model
 */
trait HasFiles
{
    // Carga la lógica core de Spatie MediaLibrary
    use InteractsWithMedia;

    // ============================================================================
    // CONFIGURACIÓN SPATIE (Colecciones)
    // ============================================================================

    /**
     * Define las colecciones de Spatie (El reemplazo del antiguo "file_type").
     * Esto le dice a Spatie automáticamente que el LOGO solo puede ser uno, etc.
     */
    public function registerMediaCollections(): void
    {
        // Documentos o imágenes únicas (Si subís una nueva, borra la anterior)
        $this->addMediaCollection('LOGO')->singleFile();
        $this->addMediaCollection('FIRMA')->singleFile();
        $this->addMediaCollection('FOTO_PERFIL')->singleFile();

        $this->addMediaCollection('SOAT')->singleFile();
        $this->addMediaCollection('RTM')->singleFile();
        $this->addMediaCollection('TARJETA_OPERACION')->singleFile();
        $this->addMediaCollection('LICENCIA')->singleFile();

        // Colecciones Múltiples
        $this->addMediaCollection('FOTO_VEHICULO');
        $this->addMediaCollection('DOCUMENTOS');
        $this->addMediaCollection('CONTROL_SHEET_PDF');
    }

    // ============================================================================
    // ACCESORES RÁPIDOS (Compatibilidad Hacia Atrás)
    // ============================================================================

    public function getLogoAttribute(): ?Media
    {
        return $this->getFirstMedia('LOGO');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('LOGO');
    }

    public function getSignatureAttribute(): ?Media
    {
        return $this->getFirstMedia('FIRMA');
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('FIRMA');
    }

    public function getProfilePhotoAttribute(): ?Media
    {
        return $this->getFirstMedia('FOTO_PERFIL');
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('FOTO_PERFIL');
    }

    public function getVehiclePhotosAttribute(): Collection
    {
        // Spatie ordena por order_column por defecto
        return $this->getMedia('FOTO_VEHICULO');
    }

    public function getPrimaryVehiclePhotoAttribute(): ?Media
    {
        // Buscamos la que tenga property is_primary
        return $this->getMedia('FOTO_VEHICULO')->first(function (Media $media) {
            return $media->getCustomProperty('is_primary', false);
        }) ?? $this->getFirstMedia('FOTO_VEHICULO');
    }

    // ============================================================================
    // MÉTODOS DE GESTIÓN DE ARCHIVOS
    // ============================================================================

    /**
     * Agrega un archivo integrándose nativamente a Spatie
     *
     * @param  string|UploadedFile  $file
     */
    public function addFile($file, string $collectionName, bool $isPrimary = false, array $extraMeta = []): Media
    {
        $customProperties = $extraMeta;

        if ($isPrimary) {
            $customProperties['is_primary'] = true;

            // Desmarcamos las otras si esta pasa a ser primaria
            $this->getMedia($collectionName)->each(function (Media $media) {
                $media->setCustomProperty('is_primary', false);
                $media->save();
            });
        }

        $adder = $this->addMedia($file)
            ->withCustomProperties($customProperties);

        // Si es un UploadedFile, preservamos el nombre original para mantener la extensión
        if ($file instanceof UploadedFile) {
            $adder = $adder->usingFileName($file->getClientOriginalName());
        }

        return $adder->toMediaCollection($collectionName);
    }

    public function setFileAsPrimary(Media $file): bool
    {
        if ($file->model_id !== $this->id || $file->model_type !== get_class($this)) {
            return false;
        }

        $this->getMedia($file->collection_name)->each(function (Media $media) use ($file) {
            $media->setCustomProperty('is_primary', $media->uuid === $file->uuid);
            $media->save();
        });

        return true;
    }

    public function removeFile(string $fileUuid): bool
    {
        // Usamos la propiedad de colección en lugar del query builder
        // para evitar conflictos si la configuración de Spatie no resolvió el modelo.
        $media = $this->media->where('uuid', $fileUuid)->first();

        if ($media) {
            $media->delete();

            return true;
        }

        return false;
    }

    public function clearFilesByType(string $collectionName): int
    {
        $count = $this->getMedia($collectionName)->count();
        $this->clearMediaCollection($collectionName);

        return $count;
    }

    public function clearAllFiles(): int
    {
        $count = $this->media()->count();
        $this->clearMediaCollection();

        return $count;
    }

    // ============================================================================
    // DOCUMENTOS Y VENCIMIENTOS
    // ============================================================================

    public function soatDocument(): ?Media
    {
        return $this->getFirstMedia('SOAT');
    }

    public function rtmDocument(): ?Media
    {
        return $this->getFirstMedia('RTM');
    }

    public function operationCardDocument(): ?Media
    {
        return $this->getFirstMedia('TARJETA_OPERACION');
    }

    public function licenseDocument(): ?Media
    {
        return $this->getFirstMedia('LICENCIA');
    }

    public function isDocumentExpired(string $collectionName): ?bool
    {
        $media = $this->getFirstMedia($collectionName);

        if (! $media || ! $media->hasCustomProperty('expiry_date')) {
            return null;
        }

        $expiryDate = Carbon::parse($media->getCustomProperty('expiry_date'));

        return now()->isAfter($expiryDate);
    }

    public function getExpiringDocuments(int $days = 30): Collection
    {
        $threshold = now()->addDays($days)->format('Y-m-d');
        $today = now()->format('Y-m-d');

        return $this->media()
            ->whereNotNull('custom_properties->expiry_date')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(custom_properties, '$.expiry_date')) <= ?", [$threshold])
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(custom_properties, '$.expiry_date')) >= ?", [$today])
            ->get();
    }

    // ============================================================================
    // SCOPES DE BÚSQUEDA (Refactorizados para buscar en la tabla de Spatie)
    // ============================================================================

    public function scopeHasFileType(Builder $query, string $collectionName): Builder
    {
        return $query->whereHas('media', function (Builder $q) use ($collectionName) {
            $q->where('collection_name', $collectionName);
        });
    }

    public function scopeDoesntHaveFileType(Builder $query, string $collectionName): Builder
    {
        return $query->whereDoesntHave('media', function (Builder $q) use ($collectionName) {
            $q->where('collection_name', $collectionName);
        });
    }

    public function scopeWithExpiredDocuments(Builder $query, string $collectionName): Builder
    {
        return $query->whereHas('media', function (Builder $q) use ($collectionName) {
            $q->where('collection_name', $collectionName)
                ->whereNotNull('custom_properties->expiry_date')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(custom_properties, '$.expiry_date')) < ?", [now()->format('Y-m-d')]);
        });
    }

    // ============================================================================
    // SERIALIZACIÓN (JSON)
    // ============================================================================

    /**
     * Sobrescribe la serialización del modelo para limpiar el array de 'media'
     * y agregar logo_url / signature_url sin usar $appends (que causa recursión infinita).
     *
     * ¿Por qué no $appends?
     * $appends dispara los accessors de Eloquent → getFirstMediaUrl() → $this->media →
     * serialización del Media → referencia circular al modelo padre → toArray() → bucle.
     *
     * Solución: leer las URLs directamente desde la colección ya cargada en memoria.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        $logoUrl = null;
        $signatureUrl = null;
        $photoUrl = null;
        $formattedMedia = [];

        // Iteramos una sola vez sobre la relación pre-cargada
        foreach ($this->media as $mediaItem) {
            $url = $mediaItem->getUrl();
            $type = $mediaItem->collection_name;

            $formattedMedia[] = [
                'uuid' => $mediaItem->uuid,
                'name' => $mediaItem->file_name,
                'url' => $url,
                'type' => $type,
            ];

            if ($type === 'LOGO') {
                $logoUrl = $url;
            }
            if ($type === 'FIRMA') {
                $signatureUrl = $url;
            }
            if ($type === 'FOTO_PERFIL') {
                $photoUrl = $url;
            }
        }

        // Sobreescribimos la clave 'media' con el formato limpio
        if (array_key_exists('media', $array)) {
            $array['media'] = $formattedMedia;
        }

        // Inyectamos logo_url y signature_url sin pasar por $appends
        $array['logo_url'] = $logoUrl;
        $array['signature_url'] = $signatureUrl;
        $array['photo_url'] = $photoUrl;

        return $array;
    }
}
