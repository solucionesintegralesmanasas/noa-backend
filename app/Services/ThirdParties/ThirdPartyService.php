<?php

declare(strict_types=1);

namespace App\Services\ThirdParties;

use App\Models\ThirdParty;
use App\Models\User;
use App\Services\BaseService;
use App\Services\Fleet\DriverLicenseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Servicio de negocio altamente detallado para la gestión integral de Tercero.
 *
 * Este servicio asume la responsabilidad exclusiva de procesar, validar internamente
 * y persistir las operaciones de Tercero en el dominio del negocio.
 * Se encarga de aplicar las políticas corporativas asociadas y mantener la integridad referencial.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ThirdPartyService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['trade_name', 'company_name', 'first_name', 'last_name', 'document_number', 'email'];

    public function __construct(
        protected DriverLicenseService $driverLicenseService
    ) {
        parent::__construct();
    }

    /**
     * Obtiene una instancia del modelo asociado al servicio.
     */
    protected function getModelInstance(): Model
    {
        return new ThirdParty;
    }

    /**
     * Método getAllThirdPartiesWithPagination.
     */
    public function getAllThirdPartiesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $type = null
    ): LengthAwarePaginator {
        $query = $this->query()->with('driverLicenses');

        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid');
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $companyUuid && $user) {
            $companyUuid = $user->companies()->first()?->uuid;
        }

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if (! empty($search) && ! empty($this->searchableFields)) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $index => $field) {
                    if ($index === 0) {
                        $q->where($field, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                }
            });
        }

        if ($type && in_array($type, ['is_customer', 'is_supplier', 'is_employee', 'is_affiliate', 'is_driver', 'is_others'])) {
            $query->where($type, true);
        }

        $paginator = $query->paginate($perPage, [
            'uuid',
            'document_number',
            'nit_check_digit',
            'company_name',
            'first_name',
            'last_name',
            'email',
            'phone',
            'address',
            'is_customer',
            'is_supplier',
            'is_employee',
            'is_affiliate',
            'is_driver',
            'is_others',
            'is_active',
            'company_uuid',
        ], 'page', $page);

        // Optimización de payload in-place
        $paginator->getCollection()->transform(fn ($tp) => [
            'uuid' => $tp->uuid,
            'document_number' => $tp->document_number,
            'full_name' => $tp->company_name ?: "{$tp->first_name} {$tp->last_name}",
            'email' => $tp->email,
            'phone' => $tp->phone,
            'is_active' => (bool) $tp->is_active,
            'is_driver' => (bool) $tp->is_driver,
            'is_employee' => (bool) $tp->is_employee,
            'is_affiliate' => (bool) $tp->is_affiliate,
            'is_customer' => (bool) $tp->is_customer,
            'is_supplier' => (bool) $tp->is_supplier,
            'is_others' => (bool) $tp->is_others,
            'company_uuid' => $tp->company_uuid,
            'has_valid_license' => $tp->driverLicenses->where('expiration_date', '>', now()->toDateString())->isNotEmpty(),
        ]);

        return $paginator;
    }

    /**
     * Método getAllThirdParties.
     */
    public function getAllThirdParties(?string $companyUuid = null, ?string $type = null): Collection
    {
        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid');
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $companyUuid && $user) {
            $companyUuid = $user->companies()->first()?->uuid;
        }

        $query = $this->buildQuery($companyUuid)->where('is_active', true);

        $columns = [
            'uuid',
            'document_number',
            'company_name',
            'first_name',
            'last_name',
            'is_active',
            'company_uuid',
        ];

        if ($type && in_array($type, ['is_customer', 'is_supplier', 'is_employee', 'is_affiliate', 'is_driver', 'is_others'])) {
            $query->where($type, true);
            $columns[] = $type; // Agrega dinámicamente solo la columna que se solicitó (la que será true)
        } else {
            // Si no se filtra por tipo, traemos todas para saber qué roles tiene
            array_push($columns, 'is_customer', 'is_supplier', 'is_employee', 'is_affiliate', 'is_driver', 'is_others');
        }

        return $query->get($columns);
    }

    /**
     * Método getThirdPartyByUuid.
     *
     * Adjunta además el atributo `roles` con los nombres RBAC del usuario
     * vinculado al tercero (si existe) para poder editar las asignaciones.
     */
    public function getThirdPartyByUuid(string $uuid): ?Model
    {
        $record = $this->findByUuid($uuid);

        if ($record) {
            $this->appendLinkedRoles($record);
        }

        return $record;
    }

    /**
     * Adjunta los roles RBAC del usuario vinculado al tercero en el atributo `roles`.
     */
    private function appendLinkedRoles(Model $record): void
    {
        $user = User::query()
            ->whereHas('companies', fn ($q) => $q->where('third_party_uuid', $record->uuid))
            ->first();

        if ($user) {
            $record->setAttribute('roles', array_values($user->getRoleNames()->all()));
        }
    }

    /**
     * Método createThirdParty.
     */
    public function createThirdParty(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $thirdParty = ThirdParty::create([
                'company_uuid' => $data['company_uuid'],
                'person_type' => $data['person_type'],
                'document_type_uuid' => $data['document_type_uuid'],
                'document_number' => $data['document_number'],
                'nit_check_digit' => $data['nit_check_digit'] ?? null,
                'trade_name' => $data['trade_name'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'municipality_uuid' => $data['municipality_uuid'],
                'tax_regime' => $data['tax_regime'],
                'tax_responsibility_uuid' => $data['tax_responsibility_uuid'],
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'bank_account_type' => $data['bank_account_type'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'cost_center_uuid' => $data['cost_center_uuid'] ?? null,
                'is_customer' => $data['is_customer'] ?? false,
                'is_supplier' => $data['is_supplier'] ?? false,
                'is_employee' => $data['is_employee'] ?? false,
                'is_affiliate' => $data['is_affiliate'] ?? false,
                'is_driver' => $data['is_driver'] ?? false,
                'is_others' => $data['is_others'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Crear licencia de conducción si es conductor y se envían los datos
            if (($data['is_driver'] ?? false) && ! empty($data['license_number'])) {
                $affiliateUuid = $data['affiliate_uuid'] ?? ($thirdParty->is_affiliate ? $thirdParty->uuid : null);

                $this->driverLicenseService->createDriverLicense([
                    'company_uuid' => $thirdParty->company_uuid,
                    'third_party_uuid' => $thirdParty->uuid,
                    'affiliate_uuid' => $affiliateUuid,
                    'number' => $data['license_number'],
                    'category' => $data['license_category'],
                    'issue_date' => $data['license_issue_date'],
                    'expiration_date' => $data['license_expiration_date'],
                    'restrictions' => $data['license_restrictions'] ?? null,
                    'status' => $data['license_status'] ?? 'ACTIVA',
                ]);
            }

            // Provisionamos el usuario vinculado si el tercero tiene al menos
            // uno de los roles de acceso al sistema (empleado, afiliado, conductor).
            if (isset($data['registration_from']) && $data['registration_from'] === 'Company') {
                return $thirdParty;
            }

            $this->provisionLinkedUser($thirdParty, $data);

            return $thirdParty;
        });
    }

    /**
     * Roles de acceso gestionados automáticamente por los flags del tercero.
     * Solo estos roles se reemplazan de forma exacta al actualizar; los demás
     * (p. ej. ADMIN_EMPRESA, SUPERADMIN) se preservan.
     */
    private const MANAGED_ACCESS_ROLES = ['EMPLEADO', 'AFILIADO', 'CONDUCTOR'];

    /**
     * Crea (o recupera) el usuario vinculado al tercero y le asigna los roles
     * de acceso correspondientes según los flags is_employee, is_affiliate,
     * is_driver y/o los roles RBAC solicitados de forma explícita.
     *
     * La contraseña temporal se genera a partir del número de documento para que
     * sea predecible durante el onboarding y el usuario pueda cambiarla en su
     * primer inicio de sesión.
     *
     * @param  ThirdParty  $thirdParty  Tercero recién creado.
     * @param  array<string, mixed>  $data  Payload original de la creación.
     */
    private function provisionLinkedUser(ThirdParty $thirdParty, array $data): void
    {
        // Resolver roles deseados considerando el estado final del tercero
        // (para update, $thirdParty ya contiene los flags actualizados).
        $rolesToAssign = $this->resolveRolesToAssign($data, $thirdParty);

        $email = $data['email'] ?? $thirdParty->email;

        // Buscar usuario vinculado por pivote (más fiable en updates) y por email
        $linkedUser = User::whereHas('companies', fn ($q) => $q->where('company_user.third_party_uuid', $thirdParty->uuid))->first();
        $existingUser = $linkedUser ?: ($email ? User::where('email', $email)->first() : null);

        // Si no hay roles deseados y no existe usuario, no hay nada que hacer.
        if (empty($rolesToAssign) && ! $existingUser) {
            return;
        }

        // Si no hay roles deseados pero sí existe usuario, quitar los gestionados.
        if (empty($rolesToAssign) && $existingUser) {
            // Si el email cambió, actualizarlo
            if ($linkedUser && $email && $linkedUser->email !== $email) {
                $linkedUser->update(['email' => $email]);
            }
            $this->syncLinkedUserRoles($existingUser, []);
            // Mantener el vínculo empresa-tercero aunque se quiten los roles
            $existingUser->companies()->syncWithoutDetaching([
                $thirdParty->company_uuid => [
                    'third_party_uuid' => $thirdParty->uuid,
                    'is_active' => true,
                ],
            ]);

            return;
        }

        // Determinar si es empleado: para empleado se sincroniza EXACTAMENTE el único rol (sin preservar otros)
        $flagEmp = $data['is_employee'] ?? $thirdParty->is_employee ?? false;
        $isEmployee = $flagEmp === true || $flagEmp === 1 || $flagEmp === '1' || $flagEmp === 'true';

        // Si existe usuario vinculado por pivote, actualizar su email si cambió
        if ($linkedUser) {
            if ($email && $linkedUser->email !== $email) {
                $linkedUser->update(['email' => $email]);
            }
            $linkedUser->companies()->syncWithoutDetaching([
                $thirdParty->company_uuid => [
                    'third_party_uuid' => $thirdParty->uuid,
                    'is_active' => true,
                ],
            ]);
            if ($isEmployee) {
                $linkedUser->syncRoles($rolesToAssign);
            } else {
                $this->syncLinkedUserRoles($linkedUser, $rolesToAssign);
            }

            return;
        }

        $firstName = $data['first_name'] ?? $thirdParty->first_name ?? $data['trade_name'] ?? $thirdParty->trade_name ?? $data['company_name'] ?? $thirdParty->company_name ?? 'Usuario';
        $lastName = $data['last_name'] ?? $thirdParty->last_name ?? '';
        $fullName = trim($firstName.' '.$lastName);
        $userName = Str::slug($email);

        // Contraseña temporal = número de documento (el usuario debe cambiarla)
        $tempPassword = $data['document_number'] ?? $thirdParty->document_number;

        /** @var User $user */
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $fullName,
                'user_name' => $userName,
                'password' => Hash::make($tempPassword),
                'email_verified_at' => now(),
                'status' => 1,
            ]
        );

        // Asegurar que el usuario quede vinculado a la empresa y al tercero
        // en la tabla pivote 'company_user'
        $user->companies()->syncWithoutDetaching([
            $thirdParty->company_uuid => [
                'third_party_uuid' => $thirdParty->uuid,
                'is_active' => true,
            ],
        ]);

        if ($isEmployee) {
            $user->syncRoles($rolesToAssign);
        } else {
            $this->syncLinkedUserRoles($user, $rolesToAssign);
        }
    }

    /**
     * Resuelve la lista de roles RBAC a asignar al tercero, combinando los roles
     * solicitados de forma explícita (campo `roles`) con los derivados de los
     * flags de acceso del tercero (is_employee, is_affiliate, is_driver).
     *
     * Si se proporciona $thirdParty, los flags que no vengan en $data se toman
     * del modelo ya actualizado, para no perder roles cuando el frontend solo
     * envía los flags modificados.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function resolveRolesToAssign(array $data, ?ThirdParty $thirdParty = null): array
    {
        // TIPO DE TERCERO y ROL RBAC son conceptos independientes.
        // No se deriva automáticamente EMPLEADO/AFILIADO/CONDUCTOR desde los flags is_*.
        // El frontend envía `roles` explícitamente desde el MultiSelect "Roles de Acceso".
        // Si `roles` no viene, se considera vacío para permitir quitar.
        return array_values(array_unique(array_filter((array) ($data['roles'] ?? []))));
    }

    /**
     * Sincroniza los roles RBAC de un usuario vinculado a un tercero.
     * - Los roles gestionados (EMPLEADO, AFILIADO, CONDUCTOR) se reemplazan de
     *   forma exacta según $rolesToAssign (permite quitar).
     * - Los roles no gestionados (p. ej. ADMIN_EMPRESA) se preservan intactos
     *   y solo se añaden si vienen en $rolesToAssign.
     *
     * @param  User  $user
     * @param  array<int, string>  $rolesToAssign
     */
    private function syncLinkedUserRoles(User $user, array $rolesToAssign): void
    {
        $current = $user->getRoleNames()->all();

        // Roles no gestionados que se preservan tal cual
        $preserved = array_values(array_diff($current, self::MANAGED_ACCESS_ROLES));

        // Si $rolesToAssign está vacío, el resultado son solo los preservados
        // (se quitan todos los gestionados).
        if (empty($rolesToAssign)) {
            $user->syncRoles($preserved);

            return;
        }

        // Mezcla preservados + deseados (gestionados se reemplazan, no se acumulan)
        $final = array_values(array_unique(array_merge($preserved, $rolesToAssign)));

        $user->syncRoles($final);
    }

    /**
     * Método updateThirdParty.
     */
    public function updateThirdParty(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            if (! $record) {
                throw new \RuntimeException('ThirdParty not found.');
            }

            $record->update([
                'person_type' => $data['person_type'] ?? $record->person_type,
                'document_type_uuid' => $data['document_type_uuid'] ?? $record->document_type_uuid,
                'document_number' => $data['document_number'] ?? $record->document_number,
                'nit_check_digit' => $data['nit_check_digit'] ?? $record->nit_check_digit,
                'trade_name' => $data['trade_name'] ?? $record->trade_name,
                'company_name' => $data['company_name'] ?? $record->company_name,
                'first_name' => $data['first_name'] ?? $record->first_name,
                'last_name' => $data['last_name'] ?? $record->last_name,
                'email' => $data['email'] ?? $record->email,
                'phone' => $data['phone'] ?? $record->phone,
                'address' => $data['address'] ?? $record->address,
                'municipality_uuid' => $data['municipality_uuid'] ?? $record->municipality_uuid,
                'tax_regime' => $data['tax_regime'] ?? $record->tax_regime,
                'tax_responsibility_uuid' => $data['tax_responsibility_uuid'] ?? $record->tax_responsibility_uuid,
                'bank_account_number' => $data['bank_account_number'] ?? $record->bank_account_number,
                'bank_account_type' => $data['bank_account_type'] ?? $record->bank_account_type,
                'bank_name' => $data['bank_name'] ?? $record->bank_name,
                'cost_center_uuid' => $data['cost_center_uuid'] ?? $record->cost_center_uuid,
                'is_customer' => isset($data['is_customer']) ? (bool) $data['is_customer'] : $record->is_customer,
                'is_supplier' => isset($data['is_supplier']) ? (bool) $data['is_supplier'] : $record->is_supplier,
                'is_employee' => isset($data['is_employee']) ? (bool) $data['is_employee'] : $record->is_employee,
                'is_affiliate' => isset($data['is_affiliate']) ? (bool) $data['is_affiliate'] : $record->is_affiliate,
                'is_driver' => isset($data['is_driver']) ? (bool) $data['is_driver'] : $record->is_driver,
                'is_others' => isset($data['is_others']) ? (bool) $data['is_others'] : $record->is_others,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $record->is_active,
            ]);

            // Actualizamos o creamos la licencia de conducción si es conductor
            if (($data['is_driver'] ?? $record->is_driver) && ! empty($data['license_number'])) {
                $license = $this->driverLicenseService->getAllDriverLicenses($record->company_uuid, $record->uuid)->first();
                $isAffiliate = isset($data['is_affiliate']) ? (bool) $data['is_affiliate'] : (bool) $record->is_affiliate;
                $affiliateUuid = $data['affiliate_uuid'] ?? ($isAffiliate ? $record->uuid : null);

                $licenseData = [
                    'company_uuid' => $record->company_uuid,
                    'third_party_uuid' => $record->uuid,
                    'affiliate_uuid' => $affiliateUuid,
                    'number' => $data['license_number'],
                    'category' => $data['license_category'] ?? ($license ? $license->category : null),
                    'issue_date' => $data['license_issue_date'] ?? ($license ? $license->issue_date : null),
                    'expiration_date' => $data['license_expiration_date'] ?? ($license ? $license->expiration_date : null),
                    'restrictions' => $data['license_restrictions'] ?? ($license ? $license->restrictions : null),
                    'status' => $data['license_status'] ?? ($license ? $license->status : 'ACTIVA'),
                ];

                if ($license) {
                    $this->driverLicenseService->updateDriverLicense($license->uuid, $licenseData);
                } else {
                    $this->driverLicenseService->createDriverLicense($licenseData);
                }
            }

            // Asegura o actualiza el usuario vinculado y sus roles RBAC según la
            // selección actual de roles del formulario.
            $this->provisionLinkedUser($record, $data);

            return $record->fresh();
        });
    }

    /**
     * Método deleteThirdParty.
     */
    public function deleteThirdParty(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException('Failed to delete ThirdParty.');
        }
    }

    /**
     * Método toggleStatusThirdParty.
     */
    public function toggleStatusThirdParty(string $uuid): Model
    {
        if (! $this->toggleStatus($uuid, 'is_active')) {
            throw new \RuntimeException('Failed to toggle status for ThirdParty.');
        }

        return $this->findByUuid($uuid);
    }

    /**
     * Método technicalSheet.
     */
    public function technicalSheet(string $companyUuid, string $thirdPartyUuid, ?string $vehicleUuid = null): Model
    {
        $query = $this->query()
            ->where('company_uuid', $companyUuid)
            ->with([
                'company:id,uuid,business_name,trade_name,document_number,verification_digit',
                'company.media',
                'documentType',
                'municipality.department',
                'driverLicenses' => function ($q) {
                    $q->orderBy('created_at', 'desc');
                },
                'socialSecurityContributions',
            ]);

        $record = $query->where('uuid', $thirdPartyUuid)->firstOrFail();

        if ($record->company) {
            $record->company->makeHidden(['media']);
        }

        return $record;
    }

    /**
     * Método uploadThirdPartyPhoto.
     */
    public function uploadThirdPartyPhoto(string $uuid, UploadedFile $file): string
    {
        $thirdParty = $this->findByUuid($uuid);
        $thirdParty->addFile($file, 'FOTO_PERFIL');

        return $thirdParty->getFirstMediaUrl('FOTO_PERFIL');
    }
}
