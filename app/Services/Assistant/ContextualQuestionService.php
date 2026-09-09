<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\AffiliateAdminCharge;
use App\Models\Branch;
use App\Models\Company;
use App\Models\ContextualQuestion;
use App\Models\Contractor;
use App\Models\DriverLicense;
use App\Models\Fuec;
use App\Models\Maintenance;
use App\Models\OperationCard;
use App\Models\Owner;
use App\Models\OwnerDriver;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleInspection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Servicio para detectar y ejecutar preguntas contextuales predefinidas.
 *
 * Busca coincidencias en la tabla `contextual_questions` y ejecuta
 * consultas reales a la BD según el tipo de respuesta definido.
 */
class ContextualQuestionService
{
    /** @var array<string, class-string<Model>> */
    private const ENTITY_MAP = [
        'vehicles' => Vehicle::class,
        'third_parties' => ThirdParty::class,
        'fuecs' => Fuec::class,
        'branches' => Branch::class,
        'maintenances' => Maintenance::class,
        'contractors' => Contractor::class,
        'vehicle_documents' => VehicleDocument::class,
        'operation_cards' => OperationCard::class,
        'driver_licenses' => DriverLicense::class,
        'inspections' => VehicleInspection::class,
        'owners' => Owner::class,
        'owner_drivers' => OwnerDriver::class,
        'companies' => Company::class,
        'affiliate_charges' => AffiliateAdminCharge::class,
    ];

    private const DEFAULT_LIMIT = 10;

    /** @var array<string, string> Mapeo de entidad → campo de fecha de vencimiento */
    private const EXPIRY_FIELD_MAP = [
        'vehicle_documents' => 'expiry_date',
        'driver_licenses' => 'expiration_date',
        'operation_cards' => 'expiration_date',
        'fuecs' => 'expiration_date',
        'affiliate_charges' => 'due_date',
    ];

    /** @var array<string, list<string>> Mapeo de entidad → keywords para detectar intención */
    private const ENTITY_KEYWORDS = [
        'vehiculos' => ['vehículo', 'vehiculo', 'vehículos', 'vehiculos', 'placa', 'camión', 'camion', 'moto', 'carro', 'flota', 'automóvil'],
        'soat_documentos' => ['soat', 'seguro', 'tecnomecánica', 'tecnomecanica', 'documento vehículo'],
        'terceros' => ['cliente', 'tercero', 'persona', 'contratista', 'proveedor'],
        'conductores' => ['conductor', 'chofer', 'licencia', 'conducción'],
        'fuecs' => ['fuec', 'contrato', 'viaje', 'extracto', 'planilla'],
        'mantenimientos' => ['mantenimiento', 'reparación', 'taller', 'mecánico'],
        'sucursales' => ['sucursal', 'sede', 'oficina', 'dirección'],
        'empresas' => ['empresa', 'compañía', 'transportadora', 'nit'],
        'pagos' => ['pago', 'cargo', 'cuota', 'deuda', 'mora', 'pendiente', 'al día'],
        'inspecciones' => ['inspección', 'revisión', 'checklist'],
        'propietarios' => ['propietario', 'dueño', 'titular'],
        'tarjetas_operacion' => ['tarjeta operación', 'tdo', 'operación'],
    ];

    /**
     * Busca una pregunta contextual que coincida con el mensaje del usuario.
     */
    public function findMatch(string $message): ?ContextualQuestion
    {
        $lowerMsg = mb_strtolower(trim($message));

        if ($lowerMsg === '') {
            return null;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, ContextualQuestion> $questions */
        $questions = ContextualQuestion::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($questions as $question) {
            $patterns = $question->patterns ?? [];

            foreach ($patterns as $pattern) {
                $pattern = mb_strtolower(trim((string) $pattern));

                if ($pattern === '') {
                    continue;
                }

                if (str_contains($lowerMsg, $pattern)) {
                    return $question;
                }
            }
        }

        return null;
    }

    /**
     * Retorna sugerencias de preguntas basadas en las palabras del mensaje.
     */
    public function getSuggestionsForMessage(string $message): string
    {
        $lowerMsg = mb_strtolower(trim($message));

        if ($lowerMsg === '') {
            return $this->getGeneralHelp();
        }

        $detectedEntities = [];

        foreach (self::ENTITY_KEYWORDS as $entity => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lowerMsg, $keyword)) {
                    $detectedEntities[] = $entity;
                    break;
                }
            }
        }

        $detectedEntities = array_unique($detectedEntities);

        if (empty($detectedEntities)) {
            return $this->getGeneralHelp();
        }

        $suggestionLines = [];

        foreach ($detectedEntities as $entity) {
            array_push($suggestionLines, ...$this->getSuggestionsForEntityLines($entity));
        }

        return "🤔 No encontré resultados para tu consulta.\n\n".implode("\n", $suggestionLines);
    }

    private function getGeneralHelp(): string
    {
        return "🤖 **Hola, soy tu asistente Next**\n\n"
            ."Puedes preguntarme sobre:\n\n"
            ."🚛 **Vehículos** — contar, listar, activos, buscar por placa\n"
            ."📋 **SOAT y Documentos** — SOAT por vencer, documentos vehiculares\n"
            ."👤 **Terceros / Clientes** — contar, listar\n"
            ."📄 **FUECs / Contratos** — listar, contar, por vencer\n"
            ."🔧 **Mantenimientos** — próximos, pendientes\n"
            ."🪪 **Licencias y TDO** — vigentes, por vencer\n"
            ."🏢 **Sucursales** — listar, contar\n"
            ."💰 **Pagos** — pendientes, por vencer, al día\n"
            ."🔍 **Inspecciones** — recientes\n"
            ."👤 **Propietarios** — listar\n\n"
            ."💡 **Ejemplos:**\n"
            ."• \"cuántos vehículos tengo\"\n"
            ."• \"lista de clientes\"\n"
            ."• \"SOAT por vencer\"\n"
            ."• \"estoy al día con los pagos\"\n"
            ."• \"vehículos activos\"\n"
            .'• "ABC123" (buscar por placa)';
    }

    /** @return list<string> */
    private function getSuggestionsForEntityLines(string $entity): array
    {
        return match ($entity) {
            'vehiculos' => [
                '🚛 **Vehículos** — prueba con:',
                '  • "cuántos vehículos tengo"',
                '  • "vehículos activos"',
                '  • "lista de vehículos"',
                '  • "[placa]" (ej: ABC123)',
            ],
            'soat_documentos' => [
                '📋 **SOAT / Documentos** — prueba con:',
                '  • "SOAT por vencer"',
                '  • "documentos del vehículo [placa]"',
            ],
            'terceros' => [
                '👤 **Terceros / Clientes** — prueba con:',
                '  • "cuántos clientes hay"',
                '  • "lista de clientes"',
                '  • "[nombre o documento]"',
            ],
            'conductores' => [
                '🪪 **Conductores / Licencias** — prueba con:',
                '  • "cuántos conductores hay"',
                '  • "licencias por vencer"',
                '  • "[nombre del conductor]"',
            ],
            'fuecs' => [
                '📄 **FUECs / Contratos** — prueba con:',
                '  • "últimos FUECs"',
                '  • "FUECs activos"',
                '  • "contratos por vencer"',
                '  • "[número de FUEC]"',
            ],
            'mantenimientos' => [
                '🔧 **Mantenimientos** — prueba con:',
                '  • "próximos mantenimientos"',
                '  • "mantenimientos del vehículo [placa]"',
            ],
            'sucursales' => [
                '🏢 **Sucursales** — prueba con:',
                '  • "sucursales"',
                '  • "cuántas sucursales hay"',
            ],
            'empresas' => [
                '🏢 **Empresas** — prueba con:',
                '  • "datos de la empresa"',
                '  • "[nombre o NIT]"',
            ],
            'pagos' => [
                '💰 **Pagos** — prueba con:',
                '  • "estoy al día con los pagos"',
                '  • "pagos pendientes"',
                '  • "pagos por vencer"',
                '  • "todos los pagos"',
            ],
            'inspecciones' => [
                '🔍 **Inspecciones** — prueba con:',
                '  • "inspecciones recientes"',
                '  • "inspecciones del vehículo [placa]"',
            ],
            'propietarios' => [
                '👤 **Propietarios** — prueba con:',
                '  • "propietarios"',
                '  • "dueños de vehículos"',
            ],
            'tarjetas_operacion' => [
                '🪪 **Tarjetas de Operación** — prueba con:',
                '  • "tarjetas de operación activas"',
                '  • "tarjetas por vencer"',
            ],
            default => [],
        };
    }

    /**
     * Ejecuta la pregunta contextual y devuelve la respuesta formateada.
     */
    public function execute(ContextualQuestion $question): string
    {
        return match ($question->response_type) {
            'count' => $this->executeCount($question),
            'list' => $this->executeList($question),
            'expiring' => $this->executeExpiring($question),
            default => $question->response_template,
        };
    }

    private function executeCount(ContextualQuestion $question): string
    {
        $query = $this->buildQuery($question);
        $count = $query->count();

        return str_replace(
            ['{count}', '{entity}'],
            [(string) $count, $question->title],
            $question->response_template
        );
    }

    private function executeList(ContextualQuestion $question): string
    {
        $query = $this->buildQuery($question);
        $records = $query->limit(self::DEFAULT_LIMIT)->get();

        if ($records->isEmpty()) {
            return str_replace(
                ['{results}', '{count}', '{entity}'],
                ['No se encontraron registros.', '0', $question->title],
                $question->response_template
            );
        }

        $formatted = $this->formatRecords($question->entity_type, $records);
        $count = $records->count();

        return str_replace(
            ['{results}', '{count}', '{entity}'],
            [$formatted, (string) $count, $question->title],
            $question->response_template
        );
    }

    private function executeExpiring(ContextualQuestion $question): string
    {
        $days = $question->expiring_days ?? 30;
        $entity = $question->entity_type ?? '';
        $dateField = self::EXPIRY_FIELD_MAP[$entity] ?? 'expiration_date';
        $query = $this->buildQuery($question);

        $records = $query
            ->whereDate($dateField, '<=', now()->addDays($days))
            ->whereDate($dateField, '>=', now()->subDay())
            ->limit(self::DEFAULT_LIMIT)
            ->get();

        if ($records->isEmpty()) {
            return str_replace(
                ['{results}', '{count}', '{entity}', '{days}'],
                ['No hay registros próximos a vencer.', '0', $question->title, (string) $days],
                $question->response_template
            );
        }

        $formatted = $this->formatRecords($question->entity_type, $records);
        $count = $records->count();

        return str_replace(
            ['{results}', '{count}', '{entity}', '{days}'],
            [$formatted, (string) $count, $question->title, (string) $days],
            $question->response_template
        );
    }

    /**
     * @return Builder
     */
    private function buildQuery(ContextualQuestion $question)
    {
        $modelClass = self::ENTITY_MAP[$question->entity_type] ?? null;

        if ($modelClass === null) {
            throw new \RuntimeException("Entidad desconocida: {$question->entity_type}");
        }

        $query = $modelClass::query();
        $filters = $question->filters ?? [];

        foreach ($filters as $field => $value) {
            if ($value === null) {
                $query->whereNull($field);
            } elseif (is_array($value)) {
                $query->whereIn($field, $value);
            } elseif ($value === true || $value === false) {
                $query->where($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    /**
     * @param  Collection<int, Model>  $records
     */
    private function formatRecords(?string $entityType, Collection $records): string
    {
        if ($records->isEmpty()) {
            return 'Sin resultados.';
        }

        $lines = [];

        foreach ($records as $record) {
            $lines[] = match ($entityType) {
                'vehicles' => $this->formatVehicle($record),
                'third_parties' => $this->formatThirdParty($record),
                'fuecs' => $this->formatFuec($record),
                'branches' => $this->formatBranch($record),
                'maintenances' => $this->formatMaintenance($record),
                'contractors' => $this->formatContractor($record),
                'vehicle_documents' => $this->formatVehicleDocument($record),
                'operation_cards' => $this->formatOperationCard($record),
                'driver_licenses' => $this->formatDriverLicense($record),
                'inspections' => $this->formatInspection($record),
                'owners' => $this->formatOwner($record),
                'owner_drivers' => $this->formatOwnerDriver($record),
                'companies' => $this->formatCompany($record),
                'affiliate_charges' => $this->formatAffiliateCharge($record),
                default => "• {$record->getKey()}",
            };
        }

        return implode("\n", $lines);
    }

    private function formatVehicle(Model $vehicle): string
    {
        $placa = $vehicle->vehicle_license_plate ?? 'N/A';
        $modelo = $vehicle->model ?? 'N/A';
        $servicio = $vehicle->type_of_service ?? 'N/A';
        $activo = ! empty($vehicle->is_active) ? '✅ Activo' : '❌ Inactivo';

        return "  • {$placa} - {$modelo} - {$servicio} - {$activo}";
    }

    private function formatThirdParty(Model $tp): string
    {
        $nombre = $tp->company_name ?? trim(($tp->first_name ?? '').' '.($tp->last_name ?? ''));
        $doc = $tp->document_number ?? 'N/A';
        $email = $tp->email ?? 'N/A';

        return "  • {$nombre} | Doc: {$doc} | Email: {$email}";
    }

    private function formatFuec(Model $fuec): string
    {
        $numero = $fuec->number_fuec ?? $fuec->request_number ?? 'N/A';
        $estado = $fuec->status ?? 'N/A';
        $vence = $fuec->expiration_date ?? 'N/A';

        return "  • FUEC #{$numero} - Estado: {$estado} - Vence: {$vence}";
    }

    private function formatBranch(Model $branch): string
    {
        $principal = ! empty($branch->is_primary) ? ' (Principal)' : '';
        $estado = ! empty($branch->status) ? '✅' : '❌';

        return "  • {$branch->name}{$principal} - {$branch->address} {$estado}";
    }

    private function formatMaintenance(Model $maint): string
    {
        $fecha = $maint->maintenance_date ?? 'N/A';
        $total = ($maint->labor_cost ?? 0) + ($maint->parts_cost ?? 0);
        $estado = $maint->status ?? 'N/A';

        return "  • {$maint->service_description} - {$fecha} - \${$total} - {$estado}";
    }

    private function formatContractor(Model $c): string
    {
        $estado = ! empty($c->status) ? 'Activo' : 'Inactivo';

        return "  • {$c->company_name} - Doc: {$c->document_number} - Tel: {$c->telephone} - {$estado}";
    }

    private function formatVehicleDocument(Model $doc): string
    {
        $tipo = $doc->document_type ?? 'N/A';
        $poliza = $doc->policy_number ?? 'N/A';
        $vence = $doc->expiry_date ?? 'N/A';
        $estado = $doc->status ?? 'N/A';

        return "  • {$tipo} - Póliza #{$poliza} - Vence: {$vence} - {$estado}";
    }

    private function formatOperationCard(Model $card): string
    {
        $numero = $card->operating_card_number ?? 'N/A';
        $servicio = $card->service_type ?? 'N/A';
        $vence = $card->expiration_date ?? 'N/A';
        $estado = ! empty($card->status) ? 'Activa' : 'Inactiva';

        return "  • #{$numero} - {$servicio} - Vence: {$vence} - {$estado}";
    }

    private function formatDriverLicense(Model $lic): string
    {
        $numero = $lic->number ?? 'N/A';
        $cat = $lic->category ?? 'N/A';
        $vence = $lic->expiration_date ?? 'N/A';
        $estado = $lic->status ?? 'N/A';

        return "  • #{$numero} - Cat: {$cat} - Vence: {$vence} - {$estado}";
    }

    private function formatInspection(Model $ins): string
    {
        $fecha = $ins->inspection_date ?? 'N/A';
        $inspector = $ins->inspector_name ?? 'N/A';
        $km = $ins->mileage ?? 'N/A';

        return "  • {$fecha} - Inspector: {$inspector} - Km: {$km}";
    }

    private function formatOwner(Model $owner): string
    {
        return "  • {$owner->owner_name} - Doc: {$owner->document_number}";
    }

    private function formatOwnerDriver(Model $od): string
    {
        return "  • Conductor UUID: {$od->third_party_uuid}";
    }

    private function formatCompany(Model $c): string
    {
        $nombre = $c->business_name ?? $c->trade_name ?? $c->name ?? 'N/A';
        $doc = $c->document_number ?? 'N/A';
        $email = $c->email ?? 'N/A';
        $tel = $c->phone ?? 'N/A';
        $activo = ! empty($c->is_active) ? '✅' : '❌';

        return "  • {$nombre} - NIT: {$doc} - Email: {$email} - Tel: {$tel} {$activo}";
    }

    private function formatAffiliateCharge(Model $charge): string
    {
        $ref = $charge->payment_reference ?? 'N/A';
        $concepto = $charge->concept ?? 'N/A';
        $monto = $charge->amount ?? 0;
        $vence = $charge->due_date ?? 'N/A';
        $pago = $charge->payment_date ?? '—';
        $estado = $charge->status ?? 'N/A';

        return "  • Ref: {$ref} - {$concepto} - \${$monto} - Vence: {$vence} - Pagado: {$pago} - Estado: {$estado}";
    }
}
