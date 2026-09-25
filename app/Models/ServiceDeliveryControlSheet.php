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
     * SPEC-002 §4 — Estado administrativo derivado.
     *
     * No reemplaza a `is_active` (que se conserva como bandera de compatibilidad):
     * lo enriquece para distinguir en qué punto del ciclo está la evidencia.
     *
     * @param  bool|null  $certificada  Pasa el resultado ya calculado para evitar
     *                                  una consulta por fila en listados (§7.5).
     */
    public function estadoAdministrativo(?bool $certificada = null): string
    {
        if ($this->is_active) {
            $total = (int) ($this->children_total ?? 0);
            $abiertos = (int) ($this->children_open ?? 0);

            if ($total > 0 && $abiertos < $total) {
                return self::ESTADO_PARCIAL;
            }

            $sinRutas = $this->relationLoaded('routes')
                ? $this->routes->isEmpty()
                : $this->routes()->count() === 0;

            if (empty($this->start_time) && $sinRutas) {
                return self::ESTADO_BORRADOR;
            }

            return self::ESTADO_EN_CURSO;
        }

        if ($this->esCerradaConExcepcion()) {
            return self::ESTADO_CERRADA_CON_EXCEPCION;
        }

        $certificada ??= $this->tieneCertificacionVigente();

        return $certificada ? self::ESTADO_CERTIFICADA : self::ESTADO_CERRADA_OPERATIVAMENTE;
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
            ? 'VEHÍCULO EN DISPONIBILIDAD — '.$motivo
            : 'VEHÍCULO EN DISPONIBILIDAD';
    }

    protected $with = ['project:id,uuid,project_name,start_date,completion_date', 'routes', 'internalControl.vehicle', 'internalControl.thirdParty', 'subcontractedControl.vehicleClass'];

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
        if ($this->type_of_control_sheet === 'DIRECTO_CON_LA_EMPRESA') {
            return $this->internalControl && $this->internalControl->vehicle ? $this->internalControl->vehicle->vehicle_license_plate : null;
        }

        if (in_array($this->type_of_control_sheet, ['SUBCONTRATADO', 'CON_VEHICULO_CONTRATADO', 'EXTERNO_PLATAFORMA'], true)) {
            if ($this->subcontractedControl) {
                return $this->subcontractedControl->vehicle_license_plate;
            }
            // Fallback: vehículo externo de plataforma guardado como interno
            return $this->internalControl && $this->internalControl->vehicle ? $this->internalControl->vehicle->vehicle_license_plate : null;
        }

        return null;
    }

    public function getDriverNameAttribute(): ?string
    {
        if ($this->type_of_control_sheet === 'DIRECTO_CON_LA_EMPRESA') {
            $driver = $this->internalControl ? $this->internalControl->thirdParty : null;
            if ($driver) {
                return trim($driver->first_name.' '.$driver->last_name);
            }
        }

        if (in_array($this->type_of_control_sheet, ['SUBCONTRATADO', 'CON_VEHICULO_CONTRATADO', 'EXTERNO_PLATAFORMA'], true)) {
            if ($this->subcontractedControl) {
                return $this->subcontractedControl->driver_name_and_surname;
            }
            $driver = $this->internalControl ? $this->internalControl->thirdParty : null;
            if ($driver) {
                return trim($driver->first_name.' '.$driver->last_name);
            }
        }

        return null;
    }
}
