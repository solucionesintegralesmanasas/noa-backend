<?php

declare(strict_types=1);

namespace App\Services\Assistant;

/**
 * Genera respuestas locales a partir de los datos obtenidos del ERP,
 * sin necesidad de conectarse a APIs externas de IA.
 *
 * Soporta todas las entidades del sistema.
 */
class LocalResponseBuilder
{
    private const SERVICE_TYPES = [
        'PUBLICO' => 'Público',
        'PARTICULAR' => 'Particular',
    ];

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $erpData
     */
    public function build(string $userMessage, array $erpData, ?string $userName = null): string
    {
        $greeting = $this->timeGreeting($userName);

        if (empty($erpData)) {
            return $this->buildHelpMessage($greeting);
        }

        $parts = [];

        foreach ($erpData as $entity => $results) {
            $parts[] = match ($entity) {
                'vehiculos' => $this->formatVehicles($results),
                'terceros' => $this->formatThirdParties($results),
                'fuecs' => $this->formatFuecs($results),
                'sucursales' => $this->formatBranches($results),
                'mantenimientos' => $this->formatMaintenance($results),
                'empresas' => $this->formatCompanies($results),
                'contratistas' => $this->formatContractors($results),
                'documentos' => $this->formatVehicleDocuments($results),
                'tarjetas_operacion' => $this->formatOperationCards($results),
                'licencias' => $this->formatDriverLicenses($results),
                'inspecciones' => $this->formatInspections($results),
                'propietarios' => $this->formatOwners($results),
                'cargos' => $this->formatCharges($results),
                default => '',
            };
        }

        $response = implode("\n\n", array_filter($parts));

        if (empty($response)) {
            return $this->buildHelpMessage($greeting);
        }

        return "{$greeting}, esto es lo que encontré:\n\n{$response}";
    }

    private function formatVehicles(array $vehicles): string
    {
        if (empty($vehicles)) {
            return 'No se encontraron vehículos con esos criterios.';
        }

        $lines = ['🚛 VEHÍCULOS ENCONTRADOS:'];
        foreach ($vehicles as $v) {
            $service = self::SERVICE_TYPES[$v['type_of_service'] ?? ''] ?? ($v['type_of_service'] ?? 'N/A');
            $lines[] = "  • Placa: {$v['vehicle_license_plate']} - Modelo: {$v['model']} - Servicio: {$service}";
        }
        $lines[] = '(Puedes consultar más detalles en el módulo de Flota)';

        return implode("\n", $lines);
    }

    private function formatThirdParties(array $thirdParties): string
    {
        if (empty($thirdParties)) {
            return 'No se encontraron terceros con esos criterios.';
        }

        $lines = ['👤 TERCEROS ENCONTRADOS:'];
        foreach ($thirdParties as $t) {
            $name = $t['company_name'] ?? trim(($t['first_name'] ?? '').' '.($t['last_name'] ?? ''));
            $lines[] = "  • {$name} | Doc: {$t['document_number']} | Email: {$t['email']} | Tel: {$t['phone']}";
        }
        $lines[] = '(Revisa el módulo de Terceros para más información)';

        return implode("\n", $lines);
    }

    private function formatFuecs(array $fuecs): string
    {
        if (empty($fuecs)) {
            return 'No se encontraron FUECs con esos criterios.';
        }

        $lines = ['📄 FUECs ENCONTRADOS:'];
        foreach ($fuecs as $f) {
            $lines[] = "  • FUEC #{$f['number_fuec']} - Solicitud: {$f['request_number']} - Estado: {$f['status']} - Vigencia: {$f['effective_date']} al {$f['expiration_date']}";
        }
        $lines[] = '(Puedes gestionar estos FUECs en el módulo de Contratos)';

        return implode("\n", $lines);
    }

    private function formatBranches(array $branches): string
    {
        if (empty($branches)) {
            return 'No se encontraron sucursales con esos criterios.';
        }

        $lines = ['🏢 SUCURSALES ENCONTRADAS:'];
        foreach ($branches as $b) {
            $principal = ! empty($b['is_primary']) ? ' (Principal)' : '';
            $lines[] = "  • {$b['name']}{$principal} - Dirección: {$b['address']}";
        }

        return implode("\n", $lines);
    }

    private function formatMaintenance(array $maintenance): string
    {
        if (empty($maintenance)) {
            return 'No se encontraron mantenimientos con esos criterios.';
        }

        $lines = ['🔧 MANTENIMIENTOS ENCONTRADOS:'];
        foreach ($maintenance as $m) {
            $total = ($m['labor_cost'] ?? 0) + ($m['parts_cost'] ?? 0);
            $lines[] = "  • {$m['service_description']} - Fecha: {$m['maintenance_date']} - Estado: {$m['status']} - Costo: \${$total}";
        }
        $lines[] = '(Consulta el módulo de Mantenimiento para ver el detalle completo)';

        return implode("\n", $lines);
    }

    private function formatCompanies(array $companies): string
    {
        if (empty($companies)) {
            return 'No se encontraron empresas con esos criterios.';
        }

        $lines = ['🏢 EMPRESAS ENCONTRADAS:'];
        foreach ($companies as $c) {
            $estado = ! empty($c['is_active']) ? 'Activa' : 'Inactiva';
            $nombre = $c['business_name'] ?? $c['trade_name'] ?? 'N/A';
            $lines[] = "  • {$nombre} - NIT: {$c['document_number']} - Email: {$c['email']} - Tel: {$c['phone']} - {$estado}";
        }

        return implode("\n", $lines);
    }

    private function formatContractors(array $contractors): string
    {
        if (empty($contractors)) {
            return 'No se encontraron contratistas con esos criterios.';
        }

        $lines = ['📋 CONTRATISTAS ENCONTRADOS:'];
        foreach ($contractors as $c) {
            $estado = ! empty($c['status']) ? 'Activo' : 'Inactivo';
            $lines[] = "  • {$c['company_name']} - Doc: {$c['document_number']} - Contrato: {$c['contract_number']} - Tel: {$c['telephone']} - {$estado}";
        }

        return implode("\n", $lines);
    }

    private function formatVehicleDocuments(array $docs): string
    {
        if (empty($docs)) {
            return 'No se encontraron documentos vehiculares con esos criterios.';
        }

        $lines = ['📋 DOCUMENTOS VEHICULARES ENCONTRADOS:'];
        foreach ($docs as $d) {
            $estado = $d['status'] ?? 'N/A';
            $lines[] = "  • Tipo: {$d['document_type']} - Póliza #{$d['policy_number']} - Vigencia: {$d['effective_date']} al {$d['expiry_date']} - Estado: {$estado}";
        }
        $lines[] = '(Revisa el módulo de Documentos Vehiculares para más detalle)';

        return implode("\n", $lines);
    }

    private function formatOperationCards(array $cards): string
    {
        if (empty($cards)) {
            return 'No se encontraron tarjetas de operación con esos criterios.';
        }

        $lines = ['🪪 TARJETAS DE OPERACIÓN ENCONTRADAS:'];
        foreach ($cards as $c) {
            $estado = ! empty($c['status']) ? 'Activa' : 'Inactiva';
            $lines[] = "  • #{$c['operating_card_number']} - Servicio: {$c['service_type']} - Emisión: {$c['issue_date']} - Vence: {$c['expiration_date']} - {$estado}";
        }
        $lines[] = '(Consulta el módulo de Tarjetas de Operación)';

        return implode("\n", $lines);
    }

    private function formatDriverLicenses(array $licenses): string
    {
        if (empty($licenses)) {
            return 'No se encontraron licencias de conducción con esos criterios.';
        }

        $lines = ['🪪 LICENCIAS DE CONDUCCIÓN ENCONTRADAS:'];
        foreach ($licenses as $l) {
            $estado = $l['status'] ?? 'N/A';
            $lines[] = "  • #{$l['number']} - Categoría: {$l['category']} - Emisión: {$l['issue_date']} - Vence: {$l['expiration_date']} - Estado: {$estado}";
        }
        $lines[] = '(Ver más en el módulo de Conductores)';

        return implode("\n", $lines);
    }

    private function formatInspections(array $inspections): string
    {
        if (empty($inspections)) {
            return 'No se encontraron inspecciones con esos criterios.';
        }

        $lines = ['🔍 INSPECCIONES ENCONTRADAS:'];
        foreach ($inspections as $i) {
            $lines[] = "  • Fecha: {$i['inspection_date']} - Inspector: {$i['inspector_name']} - Kilometraje: {$i['mileage']} km";
            if (! empty($i['notes'])) {
                $lines[] = "    Notas: {$i['notes']}";
            }
        }
        $lines[] = '(Consulta el módulo de Inspecciones para más detalle)';

        return implode("\n", $lines);
    }

    private function formatOwners(array $owners): string
    {
        if (empty($owners)) {
            return 'No se encontraron propietarios con esos criterios.';
        }

        $lines = ['👤 PROPIETARIOS ENCONTRADOS:'];
        foreach ($owners as $o) {
            $lines[] = "  • {$o['owner_name']} - Doc: {$o['document_number']}";
        }
        $lines[] = '(Revisa el módulo de Propietarios para más información)';

        return implode("\n", $lines);
    }

    private function formatCharges(array $charges): string
    {
        if (empty($charges)) {
            return 'No se encontraron cargos/pagos con esos criterios.';
        }

        $lines = ['💰 CARGOS / PAGOS ENCONTRADOS:'];
        foreach ($charges as $c) {
            $estado = $c['status'] ?? 'N/A';
            $monto = $c['amount'] ?? 0;
            $vence = $c['due_date'] ?? 'N/A';
            $pago = $c['payment_date'] ?? '—';
            $lines[] = "  • Ref: {$c['payment_reference']} - {$c['concept']} - \${$monto} - Vence: {$vence} - Pagado: {$pago} - Estado: {$estado}";
        }
        $lines[] = '(Consulta el módulo de Cargos Administrativos para más detalle)';

        return implode("\n", $lines);
    }

    private function buildHelpMessage(string $greeting = 'Hola'): string
    {
        return "{$greeting}, soy el Next tu asistente virtual ¿en qué puedo ayudarte?";
    }

    private function timeGreeting(?string $userName = null): string
    {
        $hour = (int) now()->format('G');

        $timeGreeting = match (true) {
            $hour >= 0 && $hour < 12 => 'Buenos días',
            $hour >= 12 && $hour < 18 => 'Buenas tardes',
            default => 'Buenas noches',
        };

        return $userName ? "{$timeGreeting}, {$userName}" : $timeGreeting;
    }
}
