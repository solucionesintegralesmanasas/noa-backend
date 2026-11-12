<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Hoja de control de entrega de servicios.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2026-06-19
 */
class ServiceDeliveryControlSheet extends Model implements HasMedia
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, InteractsWithMedia, LogsActivity;

    protected $table = 'service_delivery_control_sheet';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'project_uuid',
        'official_name_and_surname',
        'service_date',
        'start_date',
        'end_date',
        'parent_uuid',
        'daily_route',
        'start_time',
        'end_time',
        'total_hours',
        'starting_kilometer',
        'ending_kilometer',
        'number_of_tolls',
        'total_toll_value',
        'type_of_control_sheet',
        'day_kind',
        'availability_reason',
        'exception_reason',
        'exception_approved_by',
        'exception_approved_at',
        'is_active',
        'retirada_de_proyecto',
    ];

    protected $casts = [
        'service_date' => 'date:Y-m-d',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'total_hours' => 'datetime:H:i:s',
        'number_of_tolls' => 'integer',
        'total_toll_value' => 'decimal:2',
        'is_active' => 'boolean',
        'retirada_de_proyecto' => 'boolean',
        'exception_approved_at' => 'datetime',
    ];

    protected $appends = ['vehicle_license_plate', 'driver_name'];

    /** Modalidad del día (SPEC-002 §5.2). */
    public const DIA_OPERACION = 'operacion';

    public const DIA_DISPONIBILIDAD = 'disponibilidad';

    /** Estados administrativos derivados (SPEC-002 §4). */
    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_EN_CURSO = 'EN_CURSO';

    public const ESTADO_PARCIAL = 'PARCIAL';

    public const ESTADO_CERRADA_OPERATIVAMENTE = 'CERRADA_OPERATIVAMENTE';

    public const ESTADO_CERTIFICADA = 'CERTIFICADA';

    public const ESTADO_CERRADA_CON_EXCEPCION = 'CERRADA_CON_EXCEPCION';

    /**
     * ¿Se cerró con excepción aprobada? (§4)
     */
    public function esCerradaConExcepcion(): bool
    {
        return ! $this->is_active && $this->motivoExcepcion() !== null;
    }

    /**
     * Motivo de la excepción, si se registró.
     */
    public function motivoExcepcion(): ?string
    {
        $motivo = trim((string) $this->exception_reason);

        return $motivo !== '' ? $motivo : null;
    }

    /**
     * ¿Tiene firma vigente del coordinador? Consulta de 1 fila; en listados se
     * evita pasando el valor calculado en lote.
     */
    public function tieneCertificacionVigente(): bool
    {
        return Signature::query()
            ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheetCoordinator')
            ->where('entity_id', $this->id)
            ->where('signer_role', Signature::ROL_COORDINADOR)
            ->where('status', Signature::STATUS_VIGENTE)
            ->exists();
    }

    /**
     * ¿El día es una jornada en disponibilidad?
     *
     * Se toma la declaración explícita (day_kind) y, para no romper el histórico,
     * se cae a la inferencia antigua (sin recorridos) cuando no se declaró nada.
     * Esto evita que un día al que aún no se le cargaron rutas se reporte como
     * disponibilidad declarada.
     */
    public function esDisponibilidad(): bool
    {
        if ($this->day_kind === self::DIA_DISPONIBILIDAD) {
            return true;
        }

        return $this->relationLoaded('routes') ? $this->routes->isEmpty() : $this->routes()->count() === 0;
    }

    /**
     * Motivo declarado de la jornada en disponibilidad (SPEC-002 §5.2).
     */
    public function motivoDisponibilidad(): ?string
    {
        $motivo = trim((string) $this->availability_reason);

        return $motivo !== '' ? $motivo : null;
    }

    /**
     * Texto listo para PDF/Excel: nunca inventa un motivo que no se registró.
     */
    public function textoDisponibilidad(): string
    {
        $motivo = $this->motivoDisponibilidad();

        return $motivo !== null
            ? 'VEHÍCULO EN DISPONIBILIDAD — ' . $motivo
            : 'VEHÍCULO EN DISPONIBILIDAD';
    }

    public const RELACIONES_BASICAS = [
        'project:id,uuid,project_name,start_date,completion_date',
        'routes',
        'internalControl.vehicle',
        'internalControl.thirdParty',
        'subcontractedControl.vehicleClass',
    ];

    public const RELACIONES_DETALLE = [
        'project:id,uuid,project_name,start_date,completion_date',
        'routes',
        'internalControl.vehicle',
        'internalControl.thirdParty',
        'subcontractedControl.vehicleClass',
        'children.routes',
        'children.internalControl.vehicle',
        'children.internalControl.thirdParty',
        'children.subcontractedControl.vehicleClass',
    ];

    /**
     * Scope para cargar relaciones completas requeridas en vistas de listado y reportes (SPEC-012).
     */
    public function scopeConRelacionesCompletas($query)
    {
        return $query->with(self::RELACIONES_BASICAS);
    }

    /**
     * Scope para cargar relaciones completas con detalle de hijos para vista individual (SPEC-012).
     */
    public function scopeConRelacionesDetalle($query)
    {
        return $query->with(self::RELACIONES_DETALLE);
    }

    /**
     * Colecciones de archivos de la hoja.
     * ROUTE_MAP guarda la captura del mapa del recorrido pegada desde el frontend
     * (una sola imagen por hoja; subir una nueva reemplaza la anterior).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('ROUTE_MAP')->singleFile();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_uuid', 'uuid');
    }

    public function internalControl(): HasOne
    {
        return $this->hasOne(ServiceInternalControl::class, 'service_delivery_control_sheet_uuid', 'uuid');
    }

    public function subcontractedControl(): HasOne
    {
        return $this->hasOne(ServiceInternalControlSubcontracted::class, 'service_delivery_control_sheet_uuid', 'uuid');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ServiceDeliveryControlSheet::class, 'parent_uuid', 'uuid');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ServiceDeliveryControlSheet::class, 'parent_uuid', 'uuid');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(ServiceDeliveryControlSheetRoute::class, 'service_delivery_control_sheet_uuid', 'uuid')->orderBy('order_index');
    }

    public function getVehicleLicensePlateAttribute(): ?string
    {
        if (in_array($this->type_of_control_sheet, ['SUBCONTRATADO', 'CON_VEHICULO_CONTRATADO', 'EXTERNO_PLATAFORMA'], true)) {
            if ($this->relationLoaded('subcontractedControl') && $this->subcontractedControl?->vehicle_license_plate) {
                return $this->subcontractedControl->vehicle_license_plate;
            }
        }

        if ($this->relationLoaded('internalControl') && $this->internalControl) {
            $ic = $this->internalControl;
            $vehicle = $ic->relationLoaded('vehicle') ? $ic->vehicle : null;
            if ($vehicle?->vehicle_license_plate) {
                return $vehicle->vehicle_license_plate;
            }
        }

        if ($this->parent_uuid && $this->relationLoaded('parent') && $this->parent) {
            return $this->parent->vehicle_license_plate;
        }

        return null;
    }

    public function getDriverNameAttribute(): ?string
    {
        if (in_array($this->type_of_control_sheet, ['SUBCONTRATADO', 'CON_VEHICULO_CONTRATADO', 'EXTERNO_PLATAFORMA'], true)) {
            if ($this->relationLoaded('subcontractedControl') && ! empty($this->subcontractedControl?->driver_name_and_surname)) {
                return trim($this->subcontractedControl->driver_name_and_surname);
            }
        }

        if ($this->relationLoaded('internalControl') && $this->internalControl) {
            $ic = $this->internalControl;
            $driver = $ic->relationLoaded('thirdParty') ? $ic->thirdParty : null;
            if ($driver) {
                $nombre = trim(($driver->first_name ?? '') . ' ' . ($driver->last_name ?? ''));
                if ($nombre !== '') {
                    return $nombre;
                }
            }
        }

        if ($this->parent_uuid && $this->relationLoaded('parent') && $this->parent) {
            $parentDriver = $this->parent->driver_name;
            if ($parentDriver) {
                return $parentDriver;
            }
        }

        return ! empty($this->official_name_and_surname) ? trim($this->official_name_and_surname) : null;
    }
}
