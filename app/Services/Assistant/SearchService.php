<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\AffiliateAdminCharge;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contractor;
use App\Models\DriverLicense;
use App\Models\Fuec;
use App\Models\Maintenance;
use App\Models\OperationCard;
use App\Models\Owner;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleInspection;

/**
 * Servicio para detectar intenciones y buscar datos directamente en el ERP.
 *
 * Busca en todos los modelos del sistema: vehículos, terceros, FUECs,
 * sucursales, mantenimientos, empresas, contratistas, documentos,
 * tarjetas de operación, licencias, inspecciones y propietarios.
 */
class SearchService
{
    private const MAX_RESULTS = 5;

    private const MIN_TERM_LENGTH = 3;

    /** Palabras que indican intención pero no son términos de búsqueda reales */
    private const INTENT_WORDS = [
        'vehículo', 'vehiculo', 'vehículos', 'vehiculos', 'placa', 'camión', 'camion',
        'moto', 'automóvil', 'automovil', 'flota', 'parque', 'marca', 'modelo',
        'cliente', 'clientes', 'tercero', 'terceros', 'contratista', 'contratistas',
        'proveedor', 'proveedores', 'conductor', 'conductores', 'chofer',
        'persona', 'personas', 'empleado', 'empleados',
        'fuec', 'fuecs', 'contrato', 'contratos', 'viaje', 'viajes', 'extracto',
        'planilla', 'solicitud', 'numero', 'número', 'consecutivo',
        'sucursal', 'sucursales', 'sede', 'sedes', 'dirección', 'direccion',
        'oficina', 'oficinas',
        'mantenimiento', 'mantenimientos', 'reparación', 'reparacion',
        'taller', 'talleres', 'servicio', 'servicios', 'mecánico', 'mecanico',
        'empresa', 'empresas', 'compañía', 'compania', 'razón social',
        'razon social', 'transportadora', 'nit',
        'soat', 'documento', 'documentos', 'cédula', 'cedula',
        'tecnomecánica', 'tecnomecanica', 'póliza', 'poliza', 'seguro',
        'tarjeta', 'operación', 'operacion', 'tdo',
        'licencia', 'licencias', 'conducción', 'conduccion', 'categoría', 'categoria',
        'inspección', 'inspecciones', 'inspeccion', 'revisión', 'revision',
        'propietario', 'propietarios', 'dueño', 'dueno', 'titular',
    ];

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function detectAndSearch(string $message): array
    {
        $results = [];
        $lowerMsg = mb_strtolower($message);
        $searchTerm = $this->extractSearchTerm($message);

        if ($searchTerm === null || mb_strlen($searchTerm) < self::MIN_TERM_LENGTH) {
            return $results;
        }

        if ($this->isOnlyIntentWords($searchTerm)) {
            return $results;
        }

        // ─── VEHÍCULOS ───
        if (str_contains($lowerMsg, 'vehículo') || str_contains($lowerMsg, 'vehiculo') ||
            str_contains($lowerMsg, 'placa') || str_contains($lowerMsg, 'camión') ||
            str_contains($lowerMsg, 'camion') || str_contains($lowerMsg, 'moto') ||
            str_contains($lowerMsg, 'automóvil') || str_contains($lowerMsg, 'automovil') ||
            str_contains($lowerMsg, 'flota') || str_contains($lowerMsg, 'parque')) {
            $results['vehiculos'] = $this->searchVehicles($searchTerm);
        }

        // ─── TERCEROS ───
        if (str_contains($lowerMsg, 'cliente') || str_contains($lowerMsg, 'tercero') ||
            str_contains($lowerMsg, 'contratista') || str_contains($lowerMsg, 'proveedor') ||
            str_contains($lowerMsg, 'persona') || str_contains($lowerMsg, 'conductor') ||
            str_contains($lowerMsg, 'empleado') || str_contains($lowerMsg, 'chofer')) {
            $results['terceros'] = $this->searchThirdParties($searchTerm);
        }

        // ─── FUECs / CONTRATOS ───
        if (str_contains($lowerMsg, 'fuec') || str_contains($lowerMsg, 'contrato') ||
            str_contains($lowerMsg, 'viaje') || str_contains($lowerMsg, 'extracto') ||
            str_contains($lowerMsg, 'fec') || str_contains($lowerMsg, 'numero') ||
            str_contains($lowerMsg, 'número') || str_contains($lowerMsg, 'consecutivo') ||
            str_contains($lowerMsg, 'planilla')) {
            $results['fuecs'] = $this->searchFuecs($searchTerm);
        }

        // ─── SUCURSALES ───
        if (str_contains($lowerMsg, 'sucursal') || str_contains($lowerMsg, 'sede') ||
            str_contains($lowerMsg, 'dirección') || str_contains($lowerMsg, 'direccion') ||
            str_contains($lowerMsg, 'oficina')) {
            $results['sucursales'] = $this->searchBranches($searchTerm);
        }

        // ─── MANTENIMIENTOS ───
        if (str_contains($lowerMsg, 'mantenimiento') || str_contains($lowerMsg, 'reparación') ||
            str_contains($lowerMsg, 'reparacion') || str_contains($lowerMsg, 'taller') ||
            str_contains($lowerMsg, 'servicio') || str_contains($lowerMsg, 'mecánico') ||
            str_contains($lowerMsg, 'mecanico')) {
            $results['mantenimientos'] = $this->searchMaintenance($searchTerm);
        }

        // ─── EMPRESAS ───
        if (str_contains($lowerMsg, 'empresa') || str_contains($lowerMsg, 'compañía') ||
            str_contains($lowerMsg, 'compania') || str_contains($lowerMsg, 'razón social') ||
            str_contains($lowerMsg, 'razon social') || str_contains($lowerMsg, 'transportadora') ||
            str_contains($lowerMsg, 'nit')) {
            $results['empresas'] = $this->searchCompanies($searchTerm);
        }

        // ─── CONTRATISTAS ───
        if (str_contains($lowerMsg, 'contratista') || str_contains($lowerMsg, 'proveedor') ||
            str_contains($lowerMsg, 'contratante')) {
            $results['contratistas'] = $this->searchContractors($searchTerm);
        }

        // ─── DOCUMENTOS VEHICULARES ───
        if (str_contains($lowerMsg, 'soat') || str_contains($lowerMsg, 'documento') ||
            str_contains($lowerMsg, 'cédula') || str_contains($lowerMsg, 'cedula') ||
            str_contains($lowerMsg, 'tecnomecánica') || str_contains($lowerMsg, 'tecnomecanica') ||
            str_contains($lowerMsg, 'póliza') || str_contains($lowerMsg, 'poliza') ||
            str_contains($lowerMsg, 'seguro')) {
            $results['documentos'] = $this->searchVehicleDocuments($searchTerm);
        }

        // ─── TARJETAS DE OPERACIÓN ───
        if (str_contains($lowerMsg, 'tarjeta de operación') || str_contains($lowerMsg, 'tarjeta de operacion') ||
            str_contains($lowerMsg, 'tarjeta') || str_contains($lowerMsg, 'operación') ||
            str_contains($lowerMsg, 'operacion') || str_contains($lowerMsg, 'tdo')) {
            $results['tarjetas_operacion'] = $this->searchOperationCards($searchTerm);
        }

        // ─── LICENCIAS DE CONDUCCIÓN ───
        if (str_contains($lowerMsg, 'licencia') || str_contains($lowerMsg, 'conducción') ||
            str_contains($lowerMsg, 'conduccion') || str_contains($lowerMsg, 'conducir') ||
            str_contains($lowerMsg, 'categoría') || str_contains($lowerMsg, 'categoria')) {
            $results['licencias'] = $this->searchDriverLicenses($searchTerm);
        }

        // ─── INSPECCIONES ───
        if (str_contains($lowerMsg, 'inspección') || str_contains($lowerMsg, 'inspeccion') ||
            str_contains($lowerMsg, 'revisión') || str_contains($lowerMsg, 'revision') ||
            str_contains($lowerMsg, 'checklist')) {
            $results['inspecciones'] = $this->searchInspections($searchTerm);
        }

        // ─── PROPIETARIOS ───
        if (str_contains($lowerMsg, 'propietario') || str_contains($lowerMsg, 'dueño') ||
            str_contains($lowerMsg, 'dueno') || str_contains($lowerMsg, 'titular')) {
            $results['propietarios'] = $this->searchOwners($searchTerm);
        }

        // ─── CARGOS / PAGOS ───
        if (str_contains($lowerMsg, 'pago') || str_contains($lowerMsg, 'cargo') ||
            str_contains($lowerMsg, 'cuota') || str_contains($lowerMsg, 'al día') ||
            str_contains($lowerMsg, 'al dia') || str_contains($lowerMsg, 'pendiente') ||
            str_contains($lowerMsg, 'payment') || str_contains($lowerMsg, 'deuda') ||
            str_contains($lowerMsg, 'adeudado') || str_contains($lowerMsg, 'mora')) {
            $results['cargos'] = $this->searchAffiliateCharges($searchTerm);
        }

        // ─── BÚSQUEDA DIRECTA (sin keyword) ───
        // Si el término parece placa (ABC123) o documento (12345678),
        // busca en vehículos y terceros aunque no haya keywords
        if (empty($results) && $this->looksLikeDirectQuery($searchTerm, $message)) {
            $results['vehiculos'] = $this->searchVehicles($searchTerm);
            $results['terceros'] = $this->searchThirdParties($searchTerm);
        }

        return $results;
    }

    private function extractSearchTerm(string $message): ?string
    {
        $message = trim($message);

        if ($message === '') {
            return null;
        }

        $tokens = preg_split('/\s+/', $message, -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false || count($tokens) === 0) {
            return null;
        }

        $stopWords = [
            'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
            'de', 'del', 'en', 'con', 'por', 'para', 'sin', 'sobre',
            'y', 'e', 'o', 'u', 'a', 'ante', 'bajo', 'cabe', 'contra',
            'desde', 'entre', 'hasta', 'mediante', 'durante', 'según',
            'segun', 'como', 'cómo', 'que', 'qué', 'cual', 'quien',
            'muéstrame', 'muestrame', 'dime', 'busca', 'buscar',
            'quiero', 'necesito', 'puedes', 'podrías', 'podrias',
            'dame', 'listar', 'mostrar', 'ver', 'consulta', 'consultar',
            'información', 'informacion', 'datos', 'detalle',
            'hola', 'buenos', 'buenas', 'gracias', 'por', 'favor',
            ...self::INTENT_WORDS,
        ];

        $filtered = array_values(array_filter($tokens, fn (string $token) => ! in_array(mb_strtolower($token), $stopWords, true)));

        if (count($filtered) === 0) {
            return null; // Solo palabras vacías/de intención, nada concreto que buscar
        }

        // Busca tokens que parezcan placas (ABC123) o números de documento (12345678)
        $numericTokens = array_values(array_filter($filtered, fn (string $token) => preg_match('/[A-Za-z]{1,4}\d{2,}/', $token) || preg_match('/^\d{4,}$/', $token)));

        if (count($numericTokens) > 0) {
            return implode(' ', $numericTokens);
        }

        // Toma palabras largas (nombres, apellidos, etc.)
        $longTokens = array_values(array_filter($filtered, fn (string $token) => mb_strlen($token) >= self::MIN_TERM_LENGTH));

        if (count($longTokens) > 0) {
            return implode(' ', array_slice($longTokens, 0, 3));
        }

        return implode(' ', $filtered);
    }

    /**
     * Verifica si el término contiene solo palabras de intención genérica
     * (como "cliente", "vehículo", "fuec") sin datos concretos que buscar.
     */
    private function isOnlyIntentWords(string $searchTerm): bool
    {
        $tokens = preg_split('/\s+/', $searchTerm, -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === false || count($tokens) === 0) {
            return true;
        }

        foreach ($tokens as $token) {
            $lower = mb_strtolower($token);
            if (! in_array($lower, self::INTENT_WORDS, true)) {
                return false; // Alguna palabra no es de intención → tiene datos reales
            }
        }

        return true; // Todas son palabras de intención
    }

    private const ENTITY_SEARCH_MAP = [
        'vehiculos' => 'searchVehicles',
        'terceros' => 'searchThirdParties',
        'fuecs' => 'searchFuecs',
        'sucursales' => 'searchBranches',
        'mantenimientos' => 'searchMaintenance',
        'empresas' => 'searchCompanies',
        'contratistas' => 'searchContractors',
        'documentos' => 'searchVehicleDocuments',
        'tarjetas_operacion' => 'searchOperationCards',
        'licencias' => 'searchDriverLicenses',
        'inspecciones' => 'searchInspections',
        'propietarios' => 'searchOwners',
        'cargos' => 'searchAffiliateCharges',
    ];

    /**
     * Busca directamente en una entidad específica sin detección de keywords.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function searchByEntity(string $entityType, string $query): array
    {
        $method = self::ENTITY_SEARCH_MAP[$entityType] ?? null;

        if ($method === null || ! method_exists($this, $method)) {
            return [];
        }

        $results = $this->$method($query);

        if (empty($results)) {
            return [];
        }

        return [$entityType => $results];
    }

    /**
     * Expone extractSearchTerm públicamente para uso externo.
     */
    public function extractSearchTermPublic(string $message): ?string
    {
        return $this->extractSearchTerm($message);
    }

    /**
     * Determina si el mensaje es una consulta directa (placa o número de documento)
     * sin palabras clave adicionales.
     */
    private function looksLikeDirectQuery(string $searchTerm, string $originalMessage): bool
    {
        $lowerTerm = mb_strtolower(trim($searchTerm));
        $lowerMsg = mb_strtolower(trim($originalMessage));

        // Si el searchTerm es igual o muy similar al mensaje original, es consulta directa
        $isDirect = $lowerTerm === $lowerMsg || str_contains($lowerMsg, $lowerTerm);

        if (! $isDirect) {
            return false;
        }

        // Parece placa (ABC123) o número de documento (12345678)
        return (bool) preg_match('/^(?:[A-Za-z]{1,4}\d{2,}|\d{5,})$/', $searchTerm);
    }

    private function searchVehicles(string $query): array
    {
        return Vehicle::query()
            ->where(function ($q) use ($query) {
                $q->where('vehicle_license_plate', 'like', "%{$query}%")
                    ->orWhere('serial_number', 'like', "%{$query}%")
                    ->orWhere('vin_number', 'like', "%{$query}%")
                    ->orWhere('engine_number', 'like', "%{$query}%")
                    ->orWhere('chassis_number', 'like', "%{$query}%")
                    ->orWhere('line', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'vehicle_license_plate', 'model', 'type_of_service'])
            ->toArray();
    }

    private function searchThirdParties(string $query): array
    {
        return ThirdParty::query()
            ->where(function ($q) use ($query) {
                $q->where('document_number', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('company_name', 'like', "%{$query}%")
                    ->orWhere('trade_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'document_number', 'first_name', 'last_name', 'company_name', 'email', 'phone'])
            ->toArray();
    }

    private function searchFuecs(string $query): array
    {
        return Fuec::query()
            ->where(function ($q) use ($query) {
                $q->where('number_fuec', 'like', "%{$query}%")
                    ->orWhere('request_number', 'like', "%{$query}%")
                    ->orWhere('contract_number_display', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'number_fuec', 'request_number', 'status', 'effective_date', 'expiration_date'])
            ->toArray();
    }

    private function searchBranches(string $query): array
    {
        return Branch::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('address', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'name', 'address', 'is_primary'])
            ->toArray();
    }

    private function searchMaintenance(string $query): array
    {
        return Maintenance::query()
            ->where(function ($q) use ($query) {
                $q->where('service_description', 'like', "%{$query}%")
                    ->orWhere('mechanic_name', 'like', "%{$query}%")
                    ->orWhere('workshop_name', 'like', "%{$query}%")
                    ->orWhere('notes', 'like', "%{$query}%")
                    ->orWhere('invoice_number', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'service_description', 'maintenance_date', 'status', 'labor_cost', 'parts_cost'])
            ->toArray();
    }

    private function searchCompanies(string $query): array
    {
        return Company::withoutGlobalScopes()
            ->where(function ($q) use ($query) {
                $q->where('business_name', 'like', "%{$query}%")
                    ->orWhere('trade_name', 'like', "%{$query}%")
                    ->orWhere('document_number', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'business_name', 'document_number', 'trade_name', 'email', 'phone', 'is_active'])
            ->toArray();
    }

    private function searchContractors(string $query): array
    {
        return Contractor::query()
            ->where(function ($q) use ($query) {
                $q->where('document_number', 'like', "%{$query}%")
                    ->orWhere('company_name', 'like', "%{$query}%")
                    ->orWhere('telephone', 'like', "%{$query}%")
                    ->orWhere('contract_number', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'document_number', 'company_name', 'contract_number', 'status', 'telephone'])
            ->toArray();
    }

    private function searchVehicleDocuments(string $query): array
    {
        return VehicleDocument::query()
            ->where(function ($q) use ($query) {
                $q->where('policy_number', 'like', "%{$query}%")
                    ->orWhere('document_type', 'like', "%{$query}%")
                    ->orWhere('issuing_entity', 'like', "%{$query}%")
                    ->orWhere('taker', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'document_type', 'policy_number', 'effective_date', 'expiry_date', 'status'])
            ->toArray();
    }

    private function searchOperationCards(string $query): array
    {
        return OperationCard::query()
            ->where(function ($q) use ($query) {
                $q->where('operating_card_number', 'like', "%{$query}%")
                    ->orWhere('affiliated_company', 'like', "%{$query}%")
                    ->orWhere('service_type', 'like', "%{$query}%")
                    ->orWhere('area_of_coverage', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'operating_card_number', 'issue_date', 'expiration_date', 'service_type', 'status'])
            ->toArray();
    }

    private function searchDriverLicenses(string $query): array
    {
        return DriverLicense::query()
            ->where(function ($q) use ($query) {
                $q->where('number', 'like', "%{$query}%")
                    ->orWhere('category', 'like', "%{$query}%")
                    ->orWhere('restrictions', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'number', 'category', 'issue_date', 'expiration_date', 'status'])
            ->toArray();
    }

    private function searchInspections(string $query): array
    {
        return VehicleInspection::query()
            ->where(function ($q) use ($query) {
                $q->where('inspector_name', 'like', "%{$query}%")
                    ->orWhere('notes', 'like', "%{$query}%")
                    ->orWhere('mileage', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'inspection_date', 'inspector_name', 'mileage', 'notes'])
            ->toArray();
    }

    private function searchAffiliateCharges(string $query): array
    {
        return AffiliateAdminCharge::query()
            ->where(function ($q) use ($query) {
                $q->where('payment_reference', 'like', "%{$query}%")
                    ->orWhere('concept', 'like', "%{$query}%")
                    ->orWhere('status', 'like', "%{$query}%")
                    ->orWhere('charge_type', 'like', "%{$query}%")
                    ->orWhere('notes', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'payment_reference', 'charge_type', 'concept', 'amount', 'due_date', 'status', 'payment_date'])
            ->toArray();
    }

    private function searchOwners(string $query): array
    {
        return Owner::query()
            ->where(function ($q) use ($query) {
                $q->where('owner_name', 'like', "%{$query}%")
                    ->orWhere('document_number', 'like', "%{$query}%");
            })
            ->take(self::MAX_RESULTS)
            ->get(['uuid', 'owner_name', 'document_number', 'third_party_uuid', 'vehicle_uuid'])
            ->toArray();
    }
}
