<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\Company;
use App\Models\TaxRegime;
use App\Models\TaxResponsibility;
use App\Models\User;
use App\Services\BaseService;
use App\Services\ThirdParties\ThirdPartyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

/**
 * Servicio de negocio para la gestión maestra de Empresas en el ecosistema Falcon.
 *
 * Esta clase orquestra el ciclo de vida completo de una entidad Empresa, desde su registro inicial
 * y validación de reglas de negocio tributarias hasta su deshabilitación administrativa.
 * Garantiza que cada organización posea un UUID único y mantenga la integridad con sus catálogos
 * relacionados como regímenes fiscales y ubicaciones geográficas.
 *
 * @author   Darwin Montes
 *
 * @version  1.1.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 *
 * @updated  2026-05-30 — Refactorizado para usar correctamente los métodos del BaseService.
 */
class CompanyService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['business_name', 'document_number', 'email'];

    public function __construct(
        private readonly BranchService $branchService,
        private readonly ThirdPartyService $thirdPartyService
    ) {
        parent::__construct();
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'media',
        'documentType:uuid,prefix,name',
        'municipality:uuid,department_uuid,name',
        'municipality.department:uuid,name',
        'taxRegime:uuid,code,name',
    ];

    /**
     * {@inheritDoc}
     */
    protected function getModelInstance(): Model
    {
        return new Company;
    }

    /**
     * Sobrescribe el filtro de empresa.
     * Como este es el catálogo maestro de empresas, filtramos por la columna uuid.
     */
    protected function applyCompanyFilter(Builder $query, string $companyUuid): void
    {
        $query->where($this->model->getTable().'.uuid', $companyUuid);
    }

    /**
     * Método getAllCompaniesWithPagination.
     */
    public function getAllCompaniesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        $paginator = $this->getPaginatedData(
            $perPage,
            $page,
            $search,
            $companyUuid,
            [], // Sin cargar relaciones para optimizar la cuadrícula
            ['uuid', 'document_number', 'verification_digit', 'business_name', 'phone', 'email', 'is_active']
        );

        // Optimización de payload in-place
        $paginator->getCollection()->transform(fn ($company) => [
            'uuid' => $company->uuid,
            'business_name' => $company->business_name,
            'document_number' => $company->document_number,
            'phone' => $company->phone,
            'email' => $company->email,
            'is_active' => (bool) $company->is_active,
        ]);

        return $paginator;
    }

    /**
     * Método getAllCompanies.
     */
    public function getAllCompanies(?string $companyUuid = null): Collection
    {
        $query = $this->query()->without('media')->select(['uuid', 'business_name']);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        return $query->get();
    }

    /**
     * Método getCompanyByUuid.
     */
    public function getCompanyByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * Método createCompany.
     */
    public function createCompany(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $company = Company::create([
                'person_type' => $data['person_type'],
                'type_of_company' => $data['type_of_company'],
                'economic_sector' => $data['economic_sector'] ?? null,
                'legal_structure' => $data['legal_structure'] ?? null,
                'document_type_uuid' => $data['document_type_uuid'],
                'document_number' => $data['document_number'],
                'verification_digit' => $data['verification_digit'] ?? null,
                'business_name' => $data['business_name'],
                'trade_name' => $data['trade_name'] ?? null,
                'commercial_registration' => $data['commercial_registration'] ?? null,
                'municipality_uuid' => $data['municipality_uuid'],
                'address' => $data['address'],
                'postal_code' => $data['postal_code'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'],
                'tax_regime_uuid' => $data['tax_regime_uuid'],
                'currency_code' => $data['currency_code'] ?? 'COP',
                'approximate_number_of_employees' => $data['approximate_number_of_employees'] ?? null,
                'web_page' => $data['web_page'],
                'country_code' => $data['country_code'] ?? 'CO',
                'legal_representative_name' => $data['legal_representative_name'],
                'legal_representative_last_name' => $data['legal_representative_last_name'],
                'legal_representative_document_type' => $data['legal_representative_document_type'],
                'legal_representative_document_number' => $data['legal_representative_document_number'],
                'legal_representative_nationality' => $data['legal_representative_nationality'] ?? null,
                'legal_representative_document_issue_date' => $data['legal_representative_document_issue_date'] ?? null,
                'is_active' => $data['is_active'] ?? 1,
            ]);

            // Crear la Sede Principal delegando al BranchService
            $this->branchService->createBranch([
                'company_uuid' => $company->uuid,
                'name' => 'Sede Principal',
                'address' => $data['address'],
                'municipality_uuid' => $data['municipality_uuid'],
                'is_primary' => true,
                'status' => true,
            ]);

            // Obtener el código de régimen tributario
            $taxRegime = TaxRegime::where('uuid', $data['tax_regime_uuid'])->first();
            $taxRegimeCode = $taxRegime ? $taxRegime->code : '48';

            // Obtener responsabilidad fiscal por defecto
            $taxResponsibility = TaxResponsibility::where('code', 'R-99-PN')->first() ?? TaxResponsibility::first();
            $taxResponsibilityUuid = $taxResponsibility ? $taxResponsibility->uuid : null;

            // Normalizar el tipo de persona para el tercero (debe ser JURIDICA o NATURAL)
            $personType = 'JURIDICA';
            if (! empty($data['person_type'])) {
                if (str_contains(strtoupper($data['person_type']), 'JURIDICA')) {
                    $personType = 'JURIDICA';
                } elseif (str_contains(strtoupper($data['person_type']), 'NATURAL')) {
                    $personType = 'NATURAL';
                }
            }

            // Registrar los datos de la empresa como tercero de tipo afiliado
            $thirdParty = $this->thirdPartyService->createThirdParty([
                'company_uuid' => $company->uuid,
                'person_type' => $personType,
                'document_type_uuid' => $data['document_type_uuid'],
                'document_number' => $data['document_number'],
                'nit_check_digit' => $data['verification_digit'] ?? null,
                'trade_name' => $data['trade_name'] ?? null,
                'company_name' => $data['business_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'],
                'municipality_uuid' => $data['municipality_uuid'],
                'tax_regime' => $taxRegimeCode,
                'tax_responsibility_uuid' => $taxResponsibilityUuid,
                'is_affiliate' => true,
                'is_active' => true,
                'registration_from' => 'Company',
            ]);

            // Crear el primer usuario Administrador de Empresa
            $user = User::create([
                'name' => trim($data['legal_representative_name'].' '.$data['legal_representative_last_name']),
                'email' => $data['email'],
                'user_name' => $data['document_number'], // Nombre de usuario por defecto
                'password' => Hash::make($data['document_number']), // Contraseña por defecto
                'third_party_uuid' => $thirdParty->uuid,
                'status' => 1,
            ]);

            $user->companies()->attach($company->uuid, ['is_active' => true]);

            // Asignar rol
            $user->assignRole('ADMIN_EMPRESA');

            return $company;
        });
    }

    /**
     * Método updateCompany.
     */
    public function updateCompany(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'person_type' => $data['person_type'] ?? $record->person_type,
                'type_of_company' => $data['type_of_company'] ?? $record->type_of_company,
                'economic_sector' => $data['economic_sector'] ?? $record->economic_sector,
                'legal_structure' => $data['legal_structure'] ?? $record->legal_structure,
                'document_type_uuid' => $data['document_type_uuid'] ?? $record->document_type_uuid,
                'document_number' => $data['document_number'] ?? $record->document_number,
                'verification_digit' => $data['verification_digit'] ?? $record->verification_digit,
                'business_name' => $data['business_name'] ?? $record->business_name,
                'trade_name' => $data['trade_name'] ?? $record->trade_name,
                'commercial_registration' => $data['commercial_registration'] ?? $record->commercial_registration,
                'municipality_uuid' => $data['municipality_uuid'] ?? $record->municipality_uuid,
                'address' => $data['address'] ?? $record->address,
                'postal_code' => $data['postal_code'] ?? $record->postal_code,
                'phone' => $data['phone'] ?? $record->phone,
                'email' => $data['email'] ?? $record->email,
                'tax_regime_uuid' => $data['tax_regime_uuid'] ?? $record->tax_regime_uuid,
                'currency_code' => $data['currency_code'] ?? $record->currency_code,
                'approximate_number_of_employees' => $data['approximate_number_of_employees'] ?? $record->approximate_number_of_employees,
                'web_page' => $data['web_page'] ?? $record->web_page,
                'country_code' => $data['country_code'] ?? $record->country_code,
                'legal_representative_name' => $data['legal_representative_name'] ?? $record->legal_representative_name,
                'legal_representative_last_name' => $data['legal_representative_last_name'] ?? $record->legal_representative_last_name,
                'legal_representative_document_type' => $data['legal_representative_document_type'] ?? $record->legal_representative_document_type,
                'legal_representative_document_number' => $data['legal_representative_document_number'] ?? $record->legal_representative_document_number,
                'legal_representative_nationality' => $data['legal_representative_nationality'] ?? $record->legal_representative_nationality,
                'legal_representative_document_issue_date' => $data['legal_representative_document_issue_date'] ?? $record->legal_representative_document_issue_date,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteCompany.
     */
    public function deleteCompany(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar la empresa con UUID: {$uuid}");
        }
    }

    /**
     * Método toggleCompanyStatus.
     */
    public function toggleCompanyStatus(string $uuid): Model
    {
        $this->toggleStatus($uuid, 'is_active');

        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * Método uploadCompanyLogo.
     */
    public function uploadCompanyLogo(string $uuid, UploadedFile $file): string
    {
        $company = $this->findByUuid($uuid);
        $company->addFile($file, 'LOGO');

        return $company->logo_url;
    }

    /**
     * Método uploadLegalRepresentativeSignature.
     */
    public function uploadLegalRepresentativeSignature(string $uuid, UploadedFile $file): string
    {
        $company = $this->findByUuid($uuid);
        $company->addFile($file, 'FIRMA');

        return $company->signature_url;
    }

    /**
     * Método getCompanyProfile.
     *
     *
     * @return array<string, mixed>|null
     */
    public function getCompanyProfile(string $uuid): ?array
    {
        $administrationRelations = [
            'taxInformation',
            'bankDetails',
            'branches',
            'economicActivities',
            'enablingResolutions',
            'experiences',
            'financialStatements',
            'occupationalSafetyRecords',
            'rupRecords',
            'taxDeclarations',
            'contacts',
        ];

        $company = $this->findByUuid($uuid, relations: array_merge(self::RELATIONS, $administrationRelations));
        if (! $company) {
            return null;
        }

        return [
            'uuid' => $company->uuid,
            'document_number' => $company->document_number,
            'verification_digit' => $company->verification_digit,
            'business_name' => $company->business_name,
            'trade_name' => $company->trade_name,
            'economic_sector' => $company->economic_sector,
            'commercial_registration' => $company->commercial_registration,
            'approximate_number_of_employees' => $company->approximate_number_of_employees,
            'address' => $company->address,
            'postal_code' => $company->postal_code,
            'phone' => $company->phone,
            'email' => $company->email,
            'web_page' => $company->web_page,
            'legal_representative_document_number' => $company->legal_representative_document_number,
            'legal_representative_document_issue_date' => $company->legal_representative_document_issue_date,
            'legal_representative_nationality' => $company->legal_representative_nationality,
            'legal_representative_name' => $company->legal_representative_name,
            'legal_representative_last_name' => $company->legal_representative_last_name,
            'is_active' => (bool) $company->is_active,
            'logo_url' => $company->logo_url,
            'signature_url' => $company->signature_url,
            'branches' => $company->branches ? $company->branches->map(fn ($b) => [
                'uuid' => $b->uuid,
                'name' => $b->name,
                'is_primary' => (bool) $b->is_primary,
            ])->toArray() : [],
            'enabling_resolutions' => $company->enablingResolutions ? $company->enablingResolutions->map(fn ($er) => [
                'uuid' => $er->uuid,
                'resolution_number' => $er->resolution_number,
                'resolution_date' => $er->resolution_date,
            ])->toArray() : [],
        ];
    }
}
