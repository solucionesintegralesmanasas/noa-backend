<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasFiles;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;

/**
 * Proyecto maestro que agrupa a varios conductores (terceros) y varios vehículos.
 *
 * @author   Darwin Montes
 * @version  1.0.0
 * @since    1.0.0
 * @created  2026-09-01
 */
class Project extends Model implements HasMedia
{
    use BelongsToCompany, FormatsDates, HasFactory, HasFiles, HasUuid, LogsActivity;

    /**
     * Define las colecciones de archivos del proyecto.
     * La orden de compra es un único PDF (subir uno nuevo reemplaza el anterior).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('ORDEN_COMPRA')->singleFile();
    }

    /**
     * Serializa el modelo limpiando la lista de media y exponiendo el PDF
     * de la orden de compra sin pasar por $appends (evita recursión infinita).
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        $order = $this->media->firstWhere('collection_name', 'ORDEN_COMPRA');
        $array['purchase_order_url'] = $order?->getUrl();
        $array['purchase_order_name'] = $order?->file_name;
        unset($array['media']);

        return $array;
    }

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'projects';

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'project_name',
        'start_date',
        'completion_date',
        'project_value',
        'purchase_order',
    ];

    /**
     * Los atributos que deben ocultarse para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
    ];

    /**
     * Los atributos que deben ser casteados.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'completion_date' => 'date',
        'project_value' => 'float',
    ];

    /**
     * Obtiene la empresa propietaria del proyecto.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Obtiene las asignaciones conductor-vehículo del proyecto.
     *
     * Cada fila es un conductor con su vehículo (relación 1:1).
     */
    public function driverVehicleAssignments(): HasMany
    {
        return $this->hasMany(ProjectDriverVehicle::class, 'project_uuid', 'uuid');
    }

    /**
     * Obtiene los conductores (terceros) asignados al proyecto.
     *
     * El pivote incluye el vehículo asignado a cada conductor.
     */
    public function thirdParties(): BelongsToMany
    {
        return $this->belongsToMany(
            ThirdParty::class,
            'project_driver_vehicles',
            'project_uuid',
            'third_party_uuid',
            'uuid',
            'uuid'
        )->withPivot('uuid', 'vehicle_uuid')->withTimestamps();
    }

    /**
     * Obtiene los vehículos asignados al proyecto (uno por conductor).
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(
            Vehicle::class,
            'project_driver_vehicles',
            'project_uuid',
            'vehicle_uuid',
            'uuid',
            'uuid'
        )->withPivot('uuid', 'third_party_uuid')->withTimestamps();
    }

    /**
     * Obtiene el vehículo asignado a un conductor dentro del proyecto.
     */
    public function vehicleForThirdParty(string $thirdPartyUuid): ?Vehicle
    {
        $assignment = $this->driverVehicleAssignments()
            ->where('third_party_uuid', $thirdPartyUuid)
            ->with('vehicle')
            ->first();

        return $assignment?->vehicle;
    }

    /**
     * Scope para filtrar los proyectos a los que pertenece un conductor (tercero).
     */
    public function scopeForThirdParty(Builder $query, string $thirdPartyUuid): Builder
    {
        return $query->whereHas('thirdParties', function (Builder $q) use ($thirdPartyUuid) {
            $q->where('third_parties.uuid', $thirdPartyUuid);
        });
    }
}