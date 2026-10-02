<?php

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Models\ContractSignature;
use App\Models\FleetServiceContract;
use App\Models\Procedure;
use App\Models\RuntTxtBatch;
use App\Models\ServiceProvisionContract;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class RadicacionService extends BaseService
{
    public const RUTA_NUEVO = ['CAPACIDAD_TRANSPORTADORA', 'CARTA_DE_ACEPTACION', 'INCLUSION_DE_POLIZAS', 'TARJETA_DE_OPERACION'];
    public const RUTA_CAMBIO = ['INCLUSION_DE_POLIZAS', 'CARTA_DE_ACEPTACION', 'CAPACIDAD_TRANSPORTADORA', 'TARJETA_DE_OPERACION'];
    public const RUTA_RENOVACION = ['RENOVACION_TARJETA'];
    public const RUTA_DESVINCULACION_MUTUO = ['DESVINCULACION_MUTUO'];
    public const RUTA_DESVINCULACION_UNILATERAL = ['DESVINCULACION_UNILATERAL'];

    protected function getModelInstance(): Model
    {
        return new Procedure;
    }

    public function rutaPara(string $linkType): array
    {
        return match ($linkType) {
            'NUEVO_VEHICULO' => self::RUTA_NUEVO,
            'RENOVACION' => self::RUTA_RENOVACION,
            'DESVINCULACION_MUTUO' => self::RUTA_DESVINCULACION_MUTUO,
            'DESVINCULACION_UNILATERAL' => self::RUTA_DESVINCULACION_UNILATERAL,
            default => self::RUTA_CAMBIO,
        };
    }

    public function crearExpediente(array $data): array
    {
        return $this->transaction(function () use ($data) {
            $base = [
                'link_type' => $data['link_type'],
                'company_uuid' => $data['company_uuid'] ?? null,
                'third_party_uuid' => $data['third_party_uuid'] ?? null,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'procedure_code' => $data['procedure_code'],
                'date_of_creation' => $data['date_of_creation'],
                'city_uuid' => $data['city_uuid'],
                'subject' => $data['subject'] ?? null,
                'territorial_director_uuid' => $data['territorial_director_uuid'],
                'status' => 'EN_PROCESO',
            ];
            $ruta = $this->rutaPara($data['link_type']);
            $padre = Procedure::create($base + [
                'procedure_type' => $ruta[0],
                'current_step' => $ruta[0],
                'global_status' => 'EN_PROCESO',
            ]);
            $hijos = [];
            foreach ($ruta as $paso) {
                $hijos[] = Procedure::create($base + [
                    'parent_procedure_uuid' => $padre->uuid,
                    'procedure_type' => $paso,
                    'procedure_code' => $data['procedure_code'].'-'.substr($paso, 0, 3),
                    'status' => $paso === $ruta[0] ? 'EN_PROCESO' : 'RECIBIDO',
                ]);
            }

            return ['expediente' => $padre->fresh(), 'pasos' => $hijos, 'ruta' => $ruta];
        });
    }

    public function puedeAvanzar(Procedure $expediente, string $paso): bool
    {
        $ruta = $this->rutaPara($expediente->link_type ?? 'CAMBIO_DE_EMPRESA');
        $idx = array_search($paso, $ruta, true);
        if ($idx === false || $idx === 0) {
            return true;
        }
        $previo = $ruta[$idx - 1];
        $hijo = Procedure::where('parent_procedure_uuid', $expediente->uuid)
            ->where('procedure_type', $previo)->where('status', 'COMPLETADO')->exists();

        return $hijo;
    }

    public function validarRequisitos(Procedure $hijo): array
    {
        $padre = Procedure::where('uuid', $hijo->parent_procedure_uuid)->first();
        $vehicle = $hijo->vehicle_uuid ? Vehicle::where('uuid', $hijo->vehicle_uuid)->first() : null;
        if ($vehicle && method_exists($vehicle, 'esParticular') && $vehicle->esParticular()) {
            return ['ok' => false, 'mensaje' => 'Vehículo particular no requiere TO ni RCC/RCE.'];
        }
        switch ($hijo->procedure_type) {
            case 'RENOVACION_TARJETA':
                if ($hijo->simit_clear === false || $hijo->simit_clear === 0) {
                    return ['ok' => false, 'mensaje' => 'No se puede renovar con comparendos sin acuerdo de pago en SIMIT.'];
                }
                if (! $vehicle) {
                    return ['ok' => false, 'mensaje' => 'Sin vehículo asociado.'];
                }
                $soat = VehicleDocument::where('vehicle_uuid', $vehicle->uuid)->where('document_type', 'SOAT')
                    ->where('expiry_date', '>=', now()->toDateString())->exists();
                $rtm = VehicleDocument::where('vehicle_uuid', $vehicle->uuid)->where('document_type', 'RTM')
                    ->where('expiry_date', '>=', now()->toDateString())->exists();
                if (! $soat || ! $rtm) {
                    return ['ok' => false, 'mensaje' => 'SOAT y revisión técnico-mecánica deben estar vigentes en RUNT.'];
                }
                $card = \App\Models\OperationCard::where('vehicle_uuid', $vehicle->uuid)->orderByDesc('expiration_date')->first();
                if ($card && now()->diffInDays($card->expiration_date, false) > 60) {
                    return ['ok' => true, 'mensaje' => 'Aún faltan más de 2 meses; se recomienda iniciar 2 meses antes.'];
                }

                return ['ok' => true];
            case 'DESVINCULACION_MUTUO':
            case 'DESVINCULACION_UNILATERAL':
                return ['ok' => true, 'mensaje' => 'Adjunte acta, paz y salvo y tarjeta física para radicar ante el Ministerio.'];
            case 'CAPACIDAD_TRANSPORTADORA':
                if (($padre?->link_type ?? '') === 'CAMBIO_DE_EMPRESA') {
                    return ['ok' => true];
                }

                return ['ok' => true];
            case 'INCLUSION_DE_POLIZAS':
                if (! $vehicle) {
                    return ['ok' => false, 'mensaje' => 'Sin vehículo asociado.'];
                }
                if (($padre?->link_type ?? '') === 'NUEVO_VEHICULO' && empty($vehicle->vehicle_license_plate)) {
                    return ['ok' => false, 'mensaje' => 'Falta matrícula (placa) antes de pólizas.'];
                }
                $companyUuid = $hijo->company_uuid ?? $padre?->company_uuid;
                $config = $companyUuid ? \App\Models\SystemConfiguration::where('company_uuid', $companyUuid)->first() : null;
                if ($config && (bool) $config->fuec_use_corporate_policies) {
                    $insurer = $config->rcc_insurer_company ?? $config->corporate_rcc_insurer;
                    $expiry = $config->corporate_rce_expiration ?? $config->corporate_rcc_expiration;
                    if (empty($insurer) || empty($expiry)) {
                        return ['ok' => false, 'mensaje' => 'Configure la aseguradora y el vencimiento de las pólizas corporativas RCC/RCE.'];
                    }
                    if (\Carbon\Carbon::parse($expiry)->lt(now()->toDateString())) {
                        return ['ok' => false, 'mensaje' => 'Las pólizas corporativas RCC/RCE están vencidas.'];
                    }

                    return ['ok' => true, 'mensaje' => 'Vehículo amparado por pólizas corporativas vigentes.'];
                }
                $rcc = VehicleDocument::where('vehicle_uuid', $vehicle->uuid)->where('document_type', 'RCC')
                    ->where('expiry_date', '>=', now()->toDateString())->exists();
                $rce = VehicleDocument::where('vehicle_uuid', $vehicle->uuid)->where('document_type', 'RCE')
                    ->where('expiry_date', '>=', now()->toDateString())->exists();
                if (! $rcc || ! $rce) {
                    return ['ok' => false, 'mensaje' => 'RCC y RCE vigentes son obligatorias.'];
                }

                return ['ok' => true];
            case 'TARJETA_DE_OPERACION':
                $admin = \App\Models\ContractSignature::where('contract_origin', 'ADMIN_FLOTA')
                    ->whereIn('contract_uuid', function ($q) use ($hijo) {
                        $q->select('uuid')->from('fleet_service_contracts')->where('procedure_uuid', $hijo->uuid);
                    })
                    ->where('status', 'FIRMADO')
                    ->exists();
                $prest = \App\Models\ContractSignature::where('contract_origin', 'PRESTACION')
                    ->whereIn('contract_uuid', function ($q) use ($hijo) {
                        $q->select('uuid')->from('service_provision_contracts')->where('procedure_uuid', $hijo->uuid);
                    })
                    ->where('status', 'FIRMADO')
                    ->exists();
                if (! $admin || ! $prest) {
                    return ['ok' => false, 'mensaje' => 'Se exigen ambos contratos firmados (admin flota + prestación).'];
                }

                return ['ok' => true];
            default:
                return ['ok' => true];
        }
    }

    public function generarTxt(Procedure $hijoTo, string $origin): RuntTxtBatch
    {
        if ($origin === 'ADMIN_FLOTA') {
            $c = FleetServiceContract::where('procedure_uuid', $hijoTo->uuid)->firstOrFail();
            $linea = implode('|', ['TO', $origin, $c->contract_number, $c->issue_date, $c->start_date, $c->end_date, $c->contract_type, $c->type_of_action]);
        } else {
            $c = ServiceProvisionContract::where('procedure_uuid', $hijoTo->uuid)->firstOrFail();
            $linea = implode('|', ['TO', $origin, $c->contract_number, $c->issue_date, $c->start_date, $c->end_date, $c->coverage]);
        }
        $seq = (RuntTxtBatch::where('procedure_uuid', $hijoTo->uuid)->where('origin', $origin)->max('sequence') ?? 0) + 1;

        return $this->transaction(fn () => RuntTxtBatch::create([
            'company_uuid' => $hijoTo->company_uuid,
            'procedure_uuid' => $hijoTo->uuid,
            'origin' => $origin,
            'contract_uuid' => $c->uuid,
            'sequence' => $seq,
            'content' => $linea."\r\n",
            'content_hash' => hash('sha256', $linea),
            'status' => 'GENERADO',
        ]));
    }

    public function listarExpedientes(int $perPage = 15, int $page = 1, string $search = '', ?string $companyUuid = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Procedure::with(['vehicle:id,uuid,vehicle_license_plate,line'])
            ->where(function ($q) {
                $q->whereNull('parent_procedure_uuid')
                    ->orWhere('parent_procedure_uuid', '');
            })->orderByDesc('created_at');
        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('procedure_code', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $paginator->getCollection()->transform(function ($exp) {
            $ruta = $this->rutaPara($exp->link_type ?? 'CAMBIO_DE_EMPRESA');
            $hijos = Procedure::where('parent_procedure_uuid', $exp->uuid)->get(['procedure_type', 'status']);
            $linea = [];
            foreach ($ruta as $paso) {
                $h = $hijos->firstWhere('procedure_type', $paso);
                $linea[] = ['paso' => $paso, 'estado' => $h?->status ?? 'PENDIENTE'];
            }
            $exp->setAttribute('ruta', $ruta);
            $exp->setAttribute('linea_tiempo', $linea);
            $exp->setAttribute('avance', count(array_filter($linea, fn ($p) => $p['estado'] === 'COMPLETADO')).'/'.count($linea));

            return $exp;
        });

        return $paginator;
    }

    public function detalleExpediente(string $uuid): array
    {
        $exp = Procedure::with('vehicle')->where('uuid', $uuid)->firstOrFail();
        $ruta = $this->rutaPara($exp->link_type ?? 'CAMBIO_DE_EMPRESA');
        $hijos = Procedure::where('parent_procedure_uuid', $exp->uuid)->orderBy('id')->get();
        $linea = [];
        foreach ($ruta as $paso) {
            $h = $hijos->firstWhere('procedure_type', $paso);
            $linea[] = ['paso' => $paso, 'estado' => $h?->status ?? 'PENDIENTE', 'uuid' => $h?->uuid];
        }
        $propietario = null;
        if ($exp->vehicle_uuid) {
            $owner = \App\Models\Owner::with('thirdParty')->where('vehicle_uuid', $exp->vehicle_uuid)->first();
            // El afiliado puede venir del registro de propietario, del tercero del
            // vehículo o del propio expediente, en ese orden.
            $vehiculo = \App\Models\Vehicle::with('thirdParty')->where('uuid', $exp->vehicle_uuid)->first();
            $tercero = $owner?->thirdParty
                ?? $vehiculo?->thirdParty
                ?? ($exp->third_party_uuid ? \App\Models\ThirdParty::where('uuid', $exp->third_party_uuid)->first() : null);

            if ($owner) {
                $propietario = [
                    'nombre' => $owner->owner_name ?: $tercero?->full_name,
                    'documento' => $owner->document_number ?: $tercero?->document_number,
                    'origen' => 'propietario',
                    'telefono' => $tercero?->phone,
                    'correo' => $tercero?->email,
                ];
            } elseif ($tercero) {
                $propietario = [
                    'nombre' => $tercero->full_name,
                    'documento' => $tercero->document_number,
                    'origen' => 'afiliado',
                    'telefono' => $tercero->phone,
                    'correo' => $tercero->email,
                ];
            }
        }
        $toHijo = $hijos->firstWhere('procedure_type', 'TARJETA_DE_OPERACION');
        $contratos = [];
        if ($toHijo) {
            $admin = FleetServiceContract::where('procedure_uuid', $toHijo->uuid)->get(['uuid', 'contract_number']);
            $prest = ServiceProvisionContract::where('procedure_uuid', $toHijo->uuid)->get(['uuid', 'contract_number', 'status']);
            foreach ($admin as $c) {
                $firmado = \App\Models\ContractSignature::where('contract_origin', 'ADMIN_FLOTA')
                    ->where('contract_uuid', $c->uuid)
                    ->where('status', 'FIRMADO')
                    ->exists();
                $contratos[] = ['origen' => 'ADMIN_FLOTA', 'uuid' => $c->uuid, 'numero' => $c->contract_number, 'firmado' => $firmado];
            }
            foreach ($prest as $c) {
                $firmado = \App\Models\ContractSignature::where('contract_origin', 'PRESTACION')
                    ->where('contract_uuid', $c->uuid)
                    ->where('status', 'FIRMADO')
                    ->exists();
                $contratos[] = ['origen' => 'PRESTACION', 'uuid' => $c->uuid, 'numero' => $c->contract_number, 'firmado' => $firmado];
            }
        }

        $documentos = app(RadicacionDocumentoService::class)->documentosPorPaso($exp, $hijos);

        return ['expediente' => $exp, 'ruta' => $ruta, 'linea_tiempo' => $linea, 'pasos' => $hijos, 'propietario' => $propietario, 'contratos' => $contratos, 'documentos' => $documentos];
    }

    public function avanzarPaso(string $hijoUuid): array
    {
        return $this->transaction(function () use ($hijoUuid) {
            $hijo = Procedure::where('uuid', $hijoUuid)->firstOrFail();
            $val = $this->validarRequisitos($hijo);
            if (! $val['ok']) {
                abort(422, $val['mensaje'] ?? 'No cumple los requisitos del paso.');
            }
            $padreUuid = $hijo->parent_procedure_uuid ?: $hijo->uuid;
            $padre = Procedure::where('uuid', $padreUuid)->firstOrFail();
            $hijo->update(['status' => 'COMPLETADO']);
            $ruta = $this->rutaPara($padre->link_type ?? 'CAMBIO_DE_EMPRESA');
            $idx = array_search($hijo->procedure_type, $ruta, true);
            $siguiente = ($idx !== false && isset($ruta[$idx + 1])) ? $ruta[$idx + 1] : null;
            if ($siguiente) {
                Procedure::where('parent_procedure_uuid', $padre->uuid)
                    ->where('procedure_type', $siguiente)->update(['status' => 'EN_PROCESO']);
                $padre->update(['current_step' => $siguiente, 'global_status' => 'EN_PROCESO', 'status' => 'EN_PROCESO']);
            } else {
                $padre->update(['current_step' => $hijo->procedure_type, 'global_status' => 'COMPLETADO', 'status' => 'COMPLETADO']);
            }

            return $this->detalleExpediente($padre->uuid);
        });
    }
}
