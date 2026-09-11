<?php

use App\Http\Controllers\Api\V1\Administrations\BankDetailController;
use App\Http\Controllers\Api\V1\Administrations\BranchController;
use App\Http\Controllers\Api\V1\Administrations\CompanyController;
// Controllers de Catálogos
use App\Http\Controllers\Api\V1\Administrations\ContactController;
use App\Http\Controllers\Api\V1\Administrations\ConveyorCapacityController;
use App\Http\Controllers\Api\V1\Administrations\CostCenterController;
use App\Http\Controllers\Api\V1\Administrations\EconomicActivityController;
use App\Http\Controllers\Api\V1\Administrations\EnablingResolutionController;
use App\Http\Controllers\Api\V1\Administrations\ExperienceController;
use App\Http\Controllers\Api\V1\Administrations\FinancialStatementController;
use App\Http\Controllers\Api\V1\Administrations\OccupationalSafetyRecordController;
use App\Http\Controllers\Api\V1\Administrations\RupRecordController;
use App\Http\Controllers\Api\V1\Administrations\TaxDeclarationController;
use App\Http\Controllers\Api\V1\Administrations\TaxInformationController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ModelHasPermissionController;
use App\Http\Controllers\Api\V1\Auth\ModelHasRoleController;
use App\Http\Controllers\Api\V1\Auth\PermissionController;
use App\Http\Controllers\Api\V1\Auth\RoleController;
use App\Http\Controllers\Api\V1\Auth\RoleHasPermissionController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\Auth\UserController;
use App\Http\Controllers\Api\V1\Catalogs\BillingResolutionTypeController;
use App\Http\Controllers\Api\V1\Catalogs\BrandController;
use App\Http\Controllers\Api\V1\Catalogs\CityController;
use App\Http\Controllers\Api\V1\Catalogs\DepartmentController;
// Controllers de Administración
use App\Http\Controllers\Api\V1\Catalogs\DianParameterController;
use App\Http\Controllers\Api\V1\Catalogs\InspectionItemController;
use App\Http\Controllers\Api\V1\Catalogs\MeasurementUnitController;
use App\Http\Controllers\Api\V1\Catalogs\PaymentMethodController;
use App\Http\Controllers\Api\V1\Catalogs\PucCommercialController;
use App\Http\Controllers\Api\V1\Catalogs\TaxRegimeController;
use App\Http\Controllers\Api\V1\Catalogs\TaxResponsibilityController;
use App\Http\Controllers\Api\V1\Catalogs\TaxTypeController;
use App\Http\Controllers\Api\V1\Catalogs\TributeController;
use App\Http\Controllers\Api\V1\Catalogs\TypeOfDocumentController;
use App\Http\Controllers\Api\V1\Catalogs\VehicleClassController;
use App\Http\Controllers\Api\V1\Catalogs\WithholdingController;
use App\Http\Controllers\Api\V1\Chat\ChatController;
use App\Http\Controllers\Api\V1\ContractExtraction\ContractorController;
use App\Http\Controllers\Api\V1\ContractExtraction\FuecController;
use App\Http\Controllers\Api\V1\ContractExtraction\FuecPassengerController;
use App\Http\Controllers\Api\V1\ContractExtraction\ObjectContractController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Fleet\AffiliateAdminChargeController;
use App\Http\Controllers\Api\V1\Fleet\BusinessCollaborationAgreementController;
use App\Http\Controllers\Api\V1\Fleet\ControlSheetController;
use App\Http\Controllers\Api\V1\Fleet\DriverLicenseController;
use App\Http\Controllers\Api\V1\Fleet\InspectionResultController;
use App\Http\Controllers\Api\V1\Fleet\MaintenanceController;
use App\Http\Controllers\Api\V1\Fleet\MaintenancePartController;
use App\Http\Controllers\Api\V1\Fleet\OperationCardController;
use App\Http\Controllers\Api\V1\Fleet\OwnerController;
use App\Http\Controllers\Api\V1\Fleet\OwnerDriverController;
use App\Http\Controllers\Api\V1\Fleet\SocialSecurityContributionController;
use App\Http\Controllers\Api\V1\Fleet\VehicleBranchController;
use App\Http\Controllers\Api\V1\Fleet\VehicleController;
use App\Http\Controllers\Api\V1\Fleet\VehicleDocumentController;
use App\Http\Controllers\Api\V1\Fleet\VehicleInspectionController;
use App\Http\Controllers\Api\V1\Fleet\DriverLocationController;
use App\Http\Controllers\Api\V1\Fleet\GeofenceController;
use App\Http\Controllers\Api\V1\Fleet\LocationHistoryController;
use App\Http\Controllers\Api\V1\Integration\GoogleDriveController;
use App\Http\Controllers\Api\V1\Notifications\NotificationsController;
use App\Http\Controllers\Api\V1\Procedure\CapacityInventoryController;
use App\Http\Controllers\Api\V1\Procedure\FleetServiceContractController;
use App\Http\Controllers\Api\V1\Procedure\ProcedureController;
use App\Http\Controllers\Api\V1\Procedure\TerritorialDirectorController;
use App\Http\Controllers\Api\V1\ServiceDeliveryControlSheet\ServiceDeliveryControlSheetController;
use App\Http\Controllers\Api\V1\Settings\SystemConfigurationController;
use App\Http\Controllers\Api\V1\Signature\SignatureController;
use App\Http\Controllers\Api\V1\ThirdParties\ThirdPartyController;
use App\Http\Controllers\Api\V1\Projects\ProjectController;
use App\Http\Controllers\Api\V1\HumanResources\EmploymentContractController;
use App\Services\Fleet\SocialSecurityContributionService;
use App\Services\Notifications\EmailLogService;
use App\Services\Notifications\NotificationsService;
use Illuminate\Support\Facades\Route;

// ─── API v1 ───
Route::prefix('v1')->group(function () {

    // ─── RUTAS PÚBLICAS DE AUTENTICACIÓN ───
    Route::post('/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    Route::post('/refresh-token', [AuthController::class, 'refresh'])->name('api.v1.auth.refresh');
    Route::get('/health', function () {
        return response()->json(['status' => 'ok', 'message' => 'API saludable']);
    })->name('api.v1.health');

    // ─── RUTAS PÚBLICAS DE FIRMA DIGITAL (Signed URLs) ───
    Route::middleware('signed:relative')->group(function () {
        Route::get('/public/vehicle-inspections/{uuid}', [VehicleInspectionController::class, 'showPublic'])
            ->name('api.v1.public.vehicle-inspections.show');
        Route::post('/public/vehicle-inspections/{uuid}', [VehicleInspectionController::class, 'signPublic'])
            ->name('api.v1.public.vehicle-inspections.sign');
    });

    // ─── RUTAS PÚBLICAS (Sin Autenticación) ───
    Route::prefix('public')->group(function () {
        Route::get('/fuecs/{code}', [FuecController::class, 'validatePublic'])
            ->name('api.v1.public.fuecs.validate');

        // Endpoint para Web Cron (cPanel / Hostings Compartidos)
        Route::get('/cron/trigger-notifications', function (EmailLogService $service) {
            $service->notifyExpiringDocuments();

            return response()->json(['success' => true, 'message' => 'Verificación y envío completados.']);
        })->name('api.v1.public.cron.trigger');

        Route::get('/cron/sync-notifications', function (NotificationsService $service) {
            $service->syncNotifications();

            return response()->json(['success' => true, 'message' => 'Notificaciones sincronizadas correctamente.']);
        })->name('api.v1.public.cron.sync');

        Route::get('/cron/sync-social-security', function (SocialSecurityContributionService $service) {
            $result = $service->autoUpdateExpiredStatuses();

            return response()->json(['success' => true, 'message' => $result['message'], 'data' => $result]);
        })->name('api.v1.public.cron.sync-social-security');

        Route::get('/cron/run-all', function (
            EmailLogService $emailService,
            NotificationsService $notificationsService,
            SocialSecurityContributionService $socialSecurityService
        ) {
            $emailService->notifyExpiringDocuments();
            $notificationsService->syncNotifications();
            $socialSecurityService->autoUpdateExpiredStatuses();

            return response()->json(['success' => true, 'message' => 'Todos los procesos automáticos se ejecutaron correctamente.']);
        })->name('api.v1.public.cron.run-all');
    });

    // ─── SSE NOTIFICACIONES (fuera de auth:sanctum: EventSource no envía
    // headers Authorization, el controlador acepta Bearer o ?token=) ───
    Route::get('/notifications/stream', [NotificationsController::class, 'stream'])->name('api.v1.notifications.stream');

    // ─── RUTAS PROTEGIDAS POR AUTENTICACIÓN (Sanctum) ───
    Route::middleware('auth:sanctum')->group(function () {

        // Perfil y Sesión
        Route::get('/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('/validate-token', [AuthController::class, 'validateToken'])->name('api.v1.auth.validate-token');

        // Gestión de Tokens y Sesiones Activas
        Route::get('/active-sessions', [AuthController::class, 'activeSessions'])->name('api.v1.auth.active-sessions');
        Route::post('/revoke-token', [AuthController::class, 'revokeToken'])->name('api.v1.auth.revoke-token');
        Route::post('/revoke-all-tokens', [AuthController::class, 'revokeAllTokens'])->name('api.v1.auth.revoke-all-tokens');
        Route::get('/token-info', [AuthController::class, 'tokenInfo'])->name('api.v1.auth.token-info');
        Route::get('/check-token-expiration', [AuthController::class, 'checkTokenExpiration'])->name('api.v1.auth.check-token-expiration');
        Route::post('/extend-session', [AuthController::class, 'extendSession'])->name('api.v1.auth.extend-session');

        // ─── RUTAS DE AUTENTICACIÓN DE DOBLE FACTOR (2FA) ───
        Route::prefix('2fa')->group(function () {
            Route::get('/enable', [TwoFactorController::class, 'enable'])->name('api.v1.2fa.enable');
            Route::post('/confirm', [TwoFactorController::class, 'confirm'])->name('api.v1.2fa.confirm');
            Route::post('/verify', [TwoFactorController::class, 'verify'])->name('api.v1.2fa.verify');
            Route::post('/disable', [TwoFactorController::class, 'disable'])->name('api.v1.2fa.disable');
        });

        // ─── MÓDULO DASHBOARD ───
        Route::prefix('dashboard')->group(function () {
            Route::get('/ping', function () {
                return response()->json(['ping' => 'pong']);
            })->name('api.v1.dashboard.ping');
            Route::get('/summary', [DashboardController::class, 'summary'])->name('api.v1.dashboard.summary');
            Route::get('/conductor-summary', [DashboardController::class, 'conductorSummary'])->name('api.v1.dashboard.conductor-summary');
            Route::get('/recent-activity', [DashboardController::class, 'recentActivity'])->name('api.v1.dashboard.recent-activity');
            Route::get('/alerts', [DashboardController::class, 'alerts'])->name('api.v1.dashboard.alerts');
        });

        // ─── MÓDULO AUTH: USUARIOS ───
        Route::prefix('auth')->group(function () {

            // Usuarios
            Route::prefix('users')->group(function () {
                Route::get('/', [UserController::class, 'index'])->name('api.v1.auth.users.index');
                Route::get('/list', [UserController::class, 'list'])->name('api.v1.auth.users.list');
                Route::post('/', [UserController::class, 'store'])->name('api.v1.auth.users.store');
                Route::get('/{uuid}', [UserController::class, 'show'])->name('api.v1.auth.users.show');
                Route::put('/{uuid}', [UserController::class, 'update'])->name('api.v1.auth.users.update');
                Route::delete('/{uuid}', [UserController::class, 'destroy'])->name('api.v1.auth.users.destroy');
                Route::patch('/{uuid}/toggle-status', [UserController::class, 'toggleStatus'])->name('api.v1.auth.users.toggle-status');
            });

            // Roles
            Route::prefix('roles')->group(function () {
                Route::get('/', [RoleController::class, 'index'])->name('api.v1.auth.roles.index');
                Route::get('/list', [RoleController::class, 'list'])->name('api.v1.auth.roles.list');
                Route::post('/', [RoleController::class, 'store'])->name('api.v1.auth.roles.store');
                Route::get('/{id}', [RoleController::class, 'show'])->name('api.v1.auth.roles.show');
                Route::put('/{id}', [RoleController::class, 'update'])->name('api.v1.auth.roles.update');
                Route::delete('/{id}', [RoleController::class, 'destroy'])->name('api.v1.auth.roles.destroy');
            });

            // Permisos
            Route::prefix('permissions')->group(function () {
                Route::get('/', [PermissionController::class, 'index'])->name('api.v1.auth.permissions.index');
                Route::get('/list', [PermissionController::class, 'list'])->name('api.v1.auth.permissions.list');
                Route::post('/', [PermissionController::class, 'store'])->name('api.v1.auth.permissions.store');
                Route::get('/{id}', [PermissionController::class, 'show'])->name('api.v1.auth.permissions.show');
                Route::put('/{id}', [PermissionController::class, 'update'])->name('api.v1.auth.permissions.update');
                Route::delete('/{id}', [PermissionController::class, 'destroy'])->name('api.v1.auth.permissions.destroy');
            });

            // Asignación directa de permisos a modelos (model_has_permissions)
            Route::prefix('model-has-permissions')->group(function () {
                Route::get('/', [ModelHasPermissionController::class, 'index'])->name('api.v1.auth.model-has-permissions.index');
                Route::post('/', [ModelHasPermissionController::class, 'store'])->name('api.v1.auth.model-has-permissions.store');
                Route::delete('/{permissionId}/{modelId}/{modelType}', [ModelHasPermissionController::class, 'destroy'])->name('api.v1.auth.model-has-permissions.destroy');
            });

            // Asignación de roles a modelos (model_has_roles)
            Route::prefix('model-has-roles')->group(function () {
                Route::get('/', [ModelHasRoleController::class, 'index'])->name('api.v1.auth.model-has-roles.index');
                Route::post('/', [ModelHasRoleController::class, 'store'])->name('api.v1.auth.model-has-roles.store');
                Route::delete('/{roleId}/{modelId}/{modelType}', [ModelHasRoleController::class, 'destroy'])->name('api.v1.auth.model-has-roles.destroy');
            });

            // Permisos asignados a roles (role_has_permissions)
            Route::prefix('role-has-permissions')->group(function () {
                Route::get('/', [RoleHasPermissionController::class, 'index'])->name('api.v1.auth.role-has-permissions.index');
                Route::post('/', [RoleHasPermissionController::class, 'store'])->name('api.v1.auth.role-has-permissions.store');
                Route::delete('/{permissionId}/{roleId}', [RoleHasPermissionController::class, 'destroy'])->name('api.v1.auth.role-has-permissions.destroy');
            });
        });

        // ─── INTEGRACIONES: GOOGLE DRIVE ───
        Route::prefix('integrations/google-drive')->group(function () {
            Route::get('/authorize', [GoogleDriveController::class, 'redirectToGoogle'])->name('api.v1.integrations.google-drive.authorize');
            Route::get('/callback', [GoogleDriveController::class, 'handleCallback'])->name('api.v1.integrations.google-drive.callback');
            Route::get('/storage-quota', [GoogleDriveController::class, 'getStorageQuota'])->name('api.v1.integrations.google-drive.storage-quota');
        });

        // ─── RUTAS PARA EL MODULO CATALOGOS ───
        Route::prefix('catalogs')->group(function () {

            Route::prefix('billing-resolution-types')->group(function () {
                Route::get('/', [BillingResolutionTypeController::class, 'index'])->name('api.v1.catalogs.billing-resolution-types.index');
                Route::get('/list', [BillingResolutionTypeController::class, 'list'])->name('api.v1.catalogs.billing-resolution-types.list');
                Route::get('/{uuid}', [BillingResolutionTypeController::class, 'show'])->name('api.v1.catalogs.billing-resolution-types.show');
                Route::post('/', [BillingResolutionTypeController::class, 'store'])->name('api.v1.catalogs.billing-resolution-types.store');
                Route::put('/{uuid}', [BillingResolutionTypeController::class, 'update'])->name('api.v1.catalogs.billing-resolution-types.update');
                Route::delete('/{uuid}', [BillingResolutionTypeController::class, 'destroy'])->name('api.v1.catalogs.billing-resolution-types.destroy');
            });

            Route::prefix('brands')->group(function () {
                Route::get('/', [BrandController::class, 'index'])->name('api.v1.catalogs.brands.index');
                Route::get('/list', [BrandController::class, 'list'])->name('api.v1.catalogs.brands.list');
                Route::get('/{uuid}', [BrandController::class, 'show'])->name('api.v1.catalogs.brands.show');
                Route::post('/', [BrandController::class, 'store'])->name('api.v1.catalogs.brands.store');
                Route::put('/{uuid}', [BrandController::class, 'update'])->name('api.v1.catalogs.brands.update');
                Route::delete('/{uuid}', [BrandController::class, 'destroy'])->name('api.v1.catalogs.brands.destroy');
            });

            Route::prefix('cities')->group(function () {
                Route::get('/', [CityController::class, 'index'])->name('api.v1.catalogs.cities.index');
                Route::get('/list', [CityController::class, 'list'])->name('api.v1.catalogs.cities.list');
                Route::get('/{uuid}', [CityController::class, 'show'])->name('api.v1.catalogs.cities.show');
                Route::post('/', [CityController::class, 'store'])->name('api.v1.catalogs.cities.store');
                Route::put('/{uuid}', [CityController::class, 'update'])->name('api.v1.catalogs.cities.update');
                Route::delete('/{uuid}', [CityController::class, 'destroy'])->name('api.v1.catalogs.cities.destroy');
            });

            Route::prefix('departments')->group(function () {
                Route::get('/', [DepartmentController::class, 'index'])->name('api.v1.catalogs.departments.index');
                Route::get('/list', [DepartmentController::class, 'list'])->name('api.v1.catalogs.departments.list');
                Route::get('/{uuid}', [DepartmentController::class, 'show'])->name('api.v1.catalogs.departments.show');
                Route::post('/', [DepartmentController::class, 'store'])->name('api.v1.catalogs.departments.store');
                Route::put('/{uuid}', [DepartmentController::class, 'update'])->name('api.v1.catalogs.departments.update');
                Route::delete('/{uuid}', [DepartmentController::class, 'destroy'])->name('api.v1.catalogs.departments.destroy');
            });

            Route::prefix('dian-parameters')->group(function () {
                Route::get('/', [DianParameterController::class, 'index'])->name('api.v1.catalogs.dian-parameters.index');
                Route::get('/list', [DianParameterController::class, 'list'])->name('api.v1.catalogs.dian-parameters.list');
                Route::get('/{uuid}', [DianParameterController::class, 'show'])->name('api.v1.catalogs.dian-parameters.show');
                Route::post('/', [DianParameterController::class, 'store'])->name('api.v1.catalogs.dian-parameters.store');
                Route::put('/{uuid}', [DianParameterController::class, 'update'])->name('api.v1.catalogs.dian-parameters.update');
                Route::delete('/{uuid}', [DianParameterController::class, 'destroy'])->name('api.v1.catalogs.dian-parameters.destroy');
            });

            Route::prefix('inspection-items')->group(function () {
                Route::get('/', [InspectionItemController::class, 'index'])->name('api.v1.catalogs.inspection-items.index');
                Route::get('/list', [InspectionItemController::class, 'list'])->name('api.v1.catalogs.inspection-items.list');
                Route::get('/{uuid}', [InspectionItemController::class, 'show'])->name('api.v1.catalogs.inspection-items.show');
                Route::post('/', [InspectionItemController::class, 'store'])->name('api.v1.catalogs.inspection-items.store');
                Route::put('/{uuid}', [InspectionItemController::class, 'update'])->name('api.v1.catalogs.inspection-items.update');
                Route::delete('/{uuid}', [InspectionItemController::class, 'destroy'])->name('api.v1.catalogs.inspection-items.destroy');
            });

            Route::prefix('measurement-units')->group(function () {
                Route::get('/', [MeasurementUnitController::class, 'index'])->name('api.v1.catalogs.measurement-units.index');
                Route::get('/list', [MeasurementUnitController::class, 'list'])->name('api.v1.catalogs.measurement-units.list');
                Route::get('/{uuid}', [MeasurementUnitController::class, 'show'])->name('api.v1.catalogs.measurement-units.show');
                Route::post('/', [MeasurementUnitController::class, 'store'])->name('api.v1.catalogs.measurement-units.store');
                Route::put('/{uuid}', [MeasurementUnitController::class, 'update'])->name('api.v1.catalogs.measurement-units.update');
                Route::delete('/{uuid}', [MeasurementUnitController::class, 'destroy'])->name('api.v1.catalogs.measurement-units.destroy');
            });

            Route::prefix('payment-methods')->group(function () {
                Route::get('/', [PaymentMethodController::class, 'index'])->name('api.v1.catalogs.payment-methods.index');
                Route::get('/list', [PaymentMethodController::class, 'list'])->name('api.v1.catalogs.payment-methods.list');
                Route::get('/{uuid}', [PaymentMethodController::class, 'show'])->name('api.v1.catalogs.payment-methods.show');
                Route::post('/', [PaymentMethodController::class, 'store'])->name('api.v1.catalogs.payment-methods.store');
                Route::put('/{uuid}', [PaymentMethodController::class, 'update'])->name('api.v1.catalogs.payment-methods.update');
                Route::delete('/{uuid}', [PaymentMethodController::class, 'destroy'])->name('api.v1.catalogs.payment-methods.destroy');
            });

            Route::prefix('puc-commercials')->group(function () {
                Route::get('/', [PucCommercialController::class, 'index'])->name('api.v1.catalogs.puc-commercials.index');
                Route::get('/list', [PucCommercialController::class, 'list'])->name('api.v1.catalogs.puc-commercials.list');
                Route::get('/{uuid}', [PucCommercialController::class, 'show'])->name('api.v1.catalogs.puc-commercials.show');
                Route::post('/', [PucCommercialController::class, 'store'])->name('api.v1.catalogs.puc-commercials.store');
                Route::put('/{uuid}', [PucCommercialController::class, 'update'])->name('api.v1.catalogs.puc-commercials.update');
                Route::delete('/{uuid}', [PucCommercialController::class, 'destroy'])->name('api.v1.catalogs.puc-commercials.destroy');
            });

            Route::prefix('tax-regimes')->group(function () {
                Route::get('/', [TaxRegimeController::class, 'index'])->name('api.v1.catalogs.tax-regimes.index');
                Route::get('/list', [TaxRegimeController::class, 'list'])->name('api.v1.catalogs.tax-regimes.list');
                Route::get('/{uuid}', [TaxRegimeController::class, 'show'])->name('api.v1.catalogs.tax-regimes.show');
                Route::post('/', [TaxRegimeController::class, 'store'])->name('api.v1.catalogs.tax-regimes.store');
                Route::put('/{uuid}', [TaxRegimeController::class, 'update'])->name('api.v1.catalogs.tax-regimes.update');
                Route::delete('/{uuid}', [TaxRegimeController::class, 'destroy'])->name('api.v1.catalogs.tax-regimes.destroy');
            });

            Route::prefix('tax-responsibilities')->group(function () {
                Route::get('/', [TaxResponsibilityController::class, 'index'])->name('api.v1.catalogs.tax-responsibilities.index');
                Route::get('/list', [TaxResponsibilityController::class, 'list'])->name('api.v1.catalogs.tax-responsibilities.list');
                Route::get('/{uuid}', [TaxResponsibilityController::class, 'show'])->name('api.v1.catalogs.tax-responsibilities.show');
                Route::post('/', [TaxResponsibilityController::class, 'store'])->name('api.v1.catalogs.tax-responsibilities.store');
                Route::put('/{uuid}', [TaxResponsibilityController::class, 'update'])->name('api.v1.catalogs.tax-responsibilities.update');
                Route::delete('/{uuid}', [TaxResponsibilityController::class, 'destroy'])->name('api.v1.catalogs.tax-responsibilities.destroy');
            });

            Route::prefix('tax-types')->group(function () {
                Route::get('/', [TaxTypeController::class, 'index'])->name('api.v1.catalogs.tax-types.index');
                Route::get('/list', [TaxTypeController::class, 'list'])->name('api.v1.catalogs.tax-types.list');
                Route::get('/{uuid}', [TaxTypeController::class, 'show'])->name('api.v1.catalogs.tax-types.show');
                Route::post('/', [TaxTypeController::class, 'store'])->name('api.v1.catalogs.tax-types.store');
                Route::put('/{uuid}', [TaxTypeController::class, 'update'])->name('api.v1.catalogs.tax-types.update');
                Route::delete('/{uuid}', [TaxTypeController::class, 'destroy'])->name('api.v1.catalogs.tax-types.destroy');
            });

            Route::prefix('tributes')->group(function () {
                Route::get('/', [TributeController::class, 'index'])->name('api.v1.catalogs.tributes.index');
                Route::get('/list', [TributeController::class, 'list'])->name('api.v1.catalogs.tributes.list');
                Route::get('/{uuid}', [TributeController::class, 'show'])->name('api.v1.catalogs.tributes.show');
                Route::post('/', [TributeController::class, 'store'])->name('api.v1.catalogs.tributes.store');
                Route::put('/{uuid}', [TributeController::class, 'update'])->name('api.v1.catalogs.tributes.update');
                Route::delete('/{uuid}', [TributeController::class, 'destroy'])->name('api.v1.catalogs.tributes.destroy');
            });

            Route::prefix('type-of-documents')->group(function () {
                Route::get('/', [TypeOfDocumentController::class, 'index'])->name('api.v1.catalogs.type-of-documents.index');
                Route::get('/list', [TypeOfDocumentController::class, 'list'])->name('api.v1.catalogs.type-of-documents.list');
                Route::get('/{uuid}', [TypeOfDocumentController::class, 'show'])->name('api.v1.catalogs.type-of-documents.show');
                Route::post('/', [TypeOfDocumentController::class, 'store'])->name('api.v1.catalogs.type-of-documents.store');
                Route::put('/{uuid}', [TypeOfDocumentController::class, 'update'])->name('api.v1.catalogs.type-of-documents.update');
                Route::delete('/{uuid}', [TypeOfDocumentController::class, 'destroy'])->name('api.v1.catalogs.type-of-documents.destroy');
            });

            Route::prefix('vehicle-classes')->group(function () {
                Route::get('/', [VehicleClassController::class, 'index'])->name('api.v1.catalogs.vehicle-classes.index');
                Route::get('/list', [VehicleClassController::class, 'list'])->name('api.v1.catalogs.vehicle-classes.list');
                Route::get('/{uuid}', [VehicleClassController::class, 'show'])->name('api.v1.catalogs.vehicle-classes.show');
                Route::post('/', [VehicleClassController::class, 'store'])->name('api.v1.catalogs.vehicle-classes.store');
                Route::put('/{uuid}', [VehicleClassController::class, 'update'])->name('api.v1.catalogs.vehicle-classes.update');
                Route::delete('/{uuid}', [VehicleClassController::class, 'destroy'])->name('api.v1.catalogs.vehicle-classes.destroy');
            });

            Route::prefix('withholdings')->group(function () {
                Route::get('/', [WithholdingController::class, 'index'])->name('api.v1.catalogs.withholdings.index');
                Route::get('/list', [WithholdingController::class, 'list'])->name('api.v1.catalogs.withholdings.list');
                Route::get('/{uuid}', [WithholdingController::class, 'show'])->name('api.v1.catalogs.withholdings.show');
                Route::post('/', [WithholdingController::class, 'store'])->name('api.v1.catalogs.withholdings.store');
                Route::put('/{uuid}', [WithholdingController::class, 'update'])->name('api.v1.catalogs.withholdings.update');
                Route::delete('/{uuid}', [WithholdingController::class, 'destroy'])->name('api.v1.catalogs.withholdings.destroy');
            });
        });

        // ─── RUTAS PARA EL MODULO ADMINISTRATIVO ───
        Route::prefix('administration')->group(function () {

            // Empresas
            Route::prefix('companies')->group(function () {
                Route::get('/', [CompanyController::class, 'index'])->name('api.v1.administration.companies.index');
                Route::get('/list', [CompanyController::class, 'list'])->name('api.v1.administration.companies.list');
                Route::post('/', [CompanyController::class, 'store'])->name('api.v1.administration.companies.store');
                Route::get('/{uuid}', [CompanyController::class, 'show'])->name('api.v1.administration.companies.show');
                Route::get('/{uuid}/profile', [CompanyController::class, 'profile'])->name('api.v1.administration.companies.profile');
                Route::put('/{uuid}', [CompanyController::class, 'update'])->name('api.v1.administration.companies.update');
                Route::delete('/{uuid}', [CompanyController::class, 'destroy'])->name('api.v1.administration.companies.destroy');
                Route::patch('/{uuid}/toggle-status', [CompanyController::class, 'toggleStatus'])->name('api.v1.administration.companies.toggle-status');
                Route::post('/{uuid}/logo', [CompanyController::class, 'uploadLogo'])->name('api.v1.administration.companies.upload-logo');
                Route::post('/{uuid}/signature', [CompanyController::class, 'uploadSignature'])->name('api.v1.administration.companies.upload-signature');
            });

            // Contactos (sin toggle: no tiene campo de estado)
            Route::prefix('contacts')->group(function () {
                Route::get('/', [ContactController::class, 'index'])->name('api.v1.administration.contacts.index');
                Route::get('/list', [ContactController::class, 'list'])->name('api.v1.administration.contacts.list');
                Route::post('/', [ContactController::class, 'store'])->name('api.v1.administration.contacts.store');
                Route::get('/{uuid}', [ContactController::class, 'show'])->name('api.v1.administration.contacts.show');
                Route::put('/{uuid}', [ContactController::class, 'update'])->name('api.v1.administration.contacts.update');
                Route::delete('/{uuid}', [ContactController::class, 'destroy'])->name('api.v1.administration.contacts.destroy');
            });

            // Sucursales
            Route::prefix('branches')->group(function () {
                Route::get('/', [BranchController::class, 'index'])->name('api.v1.administration.branches.index');
                Route::get('/list', [BranchController::class, 'list'])->name('api.v1.administration.branches.list');
                Route::post('/', [BranchController::class, 'store'])->name('api.v1.administration.branches.store');
                Route::get('/{uuid}', [BranchController::class, 'show'])->name('api.v1.administration.branches.show');
                Route::put('/{uuid}', [BranchController::class, 'update'])->name('api.v1.administration.branches.update');
                Route::delete('/{uuid}', [BranchController::class, 'destroy'])->name('api.v1.administration.branches.destroy');
                Route::patch('/{uuid}/toggle-status', [BranchController::class, 'toggleStatus'])->name('api.v1.administration.branches.toggle-status');
            });

            // Actividades Económicas (sin toggle: no tiene campo de estado)
            Route::prefix('economic-activities')->group(function () {
                Route::get('/', [EconomicActivityController::class, 'index'])->name('api.v1.administration.economic-activities.index');
                Route::get('/list', [EconomicActivityController::class, 'list'])->name('api.v1.administration.economic-activities.list');
                Route::post('/', [EconomicActivityController::class, 'store'])->name('api.v1.administration.economic-activities.store');
                Route::get('/{uuid}', [EconomicActivityController::class, 'show'])->name('api.v1.administration.economic-activities.show');
                Route::put('/{uuid}', [EconomicActivityController::class, 'update'])->name('api.v1.administration.economic-activities.update');
                Route::delete('/{uuid}', [EconomicActivityController::class, 'destroy'])->name('api.v1.administration.economic-activities.destroy');
            });

            // Datos Bancarios
            Route::prefix('bank-details')->group(function () {
                Route::get('/', [BankDetailController::class, 'index'])->name('api.v1.administration.bank-details.index');
                Route::get('/list', [BankDetailController::class, 'list'])->name('api.v1.administration.bank-details.list');
                Route::post('/', [BankDetailController::class, 'store'])->name('api.v1.administration.bank-details.store');
                Route::get('/{uuid}', [BankDetailController::class, 'show'])->name('api.v1.administration.bank-details.show');
                Route::put('/{uuid}', [BankDetailController::class, 'update'])->name('api.v1.administration.bank-details.update');
                Route::delete('/{uuid}', [BankDetailController::class, 'destroy'])->name('api.v1.administration.bank-details.destroy');
                Route::patch('/{uuid}/toggle-status', [BankDetailController::class, 'toggleStatus'])->name('api.v1.administration.bank-details.toggle-status');
            });

            // Información Tributaria (sin toggle: no tiene campo de estado)
            Route::prefix('tax-informations')->group(function () {
                Route::get('/', [TaxInformationController::class, 'index'])->name('api.v1.administration.tax-information.index');
                Route::get('/list', [TaxInformationController::class, 'list'])->name('api.v1.administration.tax-information.list');
                Route::post('/', [TaxInformationController::class, 'store'])->name('api.v1.administration.tax-information.store');
                Route::get('/{uuid}', [TaxInformationController::class, 'show'])->name('api.v1.administration.tax-information.show');
                Route::put('/{uuid}', [TaxInformationController::class, 'update'])->name('api.v1.administration.tax-information.update');
                Route::delete('/{uuid}', [TaxInformationController::class, 'destroy'])->name('api.v1.administration.tax-information.destroy');
            });

            // Resoluciones de Habilitación
            Route::prefix('enabling-resolutions')->group(function () {
                Route::get('/', [EnablingResolutionController::class, 'index'])->name('api.v1.administration.enabling-resolutions.index');
                Route::get('/list', [EnablingResolutionController::class, 'list'])->name('api.v1.administration.enabling-resolutions.list');
                Route::post('/', [EnablingResolutionController::class, 'store'])->name('api.v1.administration.enabling-resolutions.store');
                Route::get('/{uuid}', [EnablingResolutionController::class, 'show'])->name('api.v1.administration.enabling-resolutions.show');
                Route::put('/{uuid}', [EnablingResolutionController::class, 'update'])->name('api.v1.administration.enabling-resolutions.update');
                Route::delete('/{uuid}', [EnablingResolutionController::class, 'destroy'])->name('api.v1.administration.enabling-resolutions.destroy');
                Route::patch('/{uuid}/toggle-status', [EnablingResolutionController::class, 'toggleStatus'])->name('api.v1.administration.enabling-resolutions.toggle-status');
            });

            // Capacidad Transportadora
            Route::prefix('conveyor-capacities')->group(function () {
                Route::get('/', [ConveyorCapacityController::class, 'index'])->name('api.v1.administration.conveyor-capacity.index');
                Route::get('/list', [ConveyorCapacityController::class, 'list'])->name('api.v1.administration.conveyor-capacity.list');
                Route::post('/', [ConveyorCapacityController::class, 'store'])->name('api.v1.administration.conveyor-capacity.store');
                Route::get('/{uuid}', [ConveyorCapacityController::class, 'show'])->name('api.v1.administration.conveyor-capacity.show');
                Route::put('/{uuid}', [ConveyorCapacityController::class, 'update'])->name('api.v1.administration.conveyor-capacity.update');
                Route::delete('/{uuid}', [ConveyorCapacityController::class, 'destroy'])->name('api.v1.administration.conveyor-capacity.destroy');
                Route::patch('/{uuid}/toggle-status', [ConveyorCapacityController::class, 'toggleStatus'])->name('api.v1.administration.conveyor-capacity.toggle-status');
            });

            // Experiencias (sin toggle: no tiene campo de estado)
            Route::prefix('experiences')->group(function () {
                Route::get('/', [ExperienceController::class, 'index'])->name('api.v1.administration.experiences.index');
                Route::get('/list', [ExperienceController::class, 'list'])->name('api.v1.administration.experiences.list');
                Route::post('/', [ExperienceController::class, 'store'])->name('api.v1.administration.experiences.store');
                Route::get('/{uuid}', [ExperienceController::class, 'show'])->name('api.v1.administration.experiences.show');
                Route::put('/{uuid}', [ExperienceController::class, 'update'])->name('api.v1.administration.experiences.update');
                Route::delete('/{uuid}', [ExperienceController::class, 'destroy'])->name('api.v1.administration.experiences.destroy');
            });

            // Registros RUP
            Route::prefix('rup')->group(function () {
                Route::get('/', [RupRecordController::class, 'index'])->name('api.v1.administration.rup.index');
                Route::get('/list', [RupRecordController::class, 'list'])->name('api.v1.administration.rup.list');
                Route::post('/', [RupRecordController::class, 'store'])->name('api.v1.administration.rup.store');
                Route::get('/{uuid}', [RupRecordController::class, 'show'])->name('api.v1.administration.rup.show');
                Route::put('/{uuid}', [RupRecordController::class, 'update'])->name('api.v1.administration.rup.update');
                Route::delete('/{uuid}', [RupRecordController::class, 'destroy'])->name('api.v1.administration.rup.destroy');
                Route::patch('/{uuid}/toggle-status', [RupRecordController::class, 'toggleStatus'])->name('api.v1.administration.rup-records.toggle-status');
            });

            // Estados Financieros (sin toggle: no tiene campo de estado)
            Route::prefix('financial-statements')->group(function () {
                Route::get('/', [FinancialStatementController::class, 'index'])->name('api.v1.administration.financial-statements.index');
                Route::get('/list', [FinancialStatementController::class, 'list'])->name('api.v1.administration.financial-statements.list');
                Route::post('/', [FinancialStatementController::class, 'store'])->name('api.v1.administration.financial-statements.store');
                Route::get('/{uuid}', [FinancialStatementController::class, 'show'])->name('api.v1.administration.financial-statements.show');
                Route::put('/{uuid}', [FinancialStatementController::class, 'update'])->name('api.v1.administration.financial-statements.update');
                Route::delete('/{uuid}', [FinancialStatementController::class, 'destroy'])->name('api.v1.administration.financial-statements.destroy');
            });

            // Declaraciones de Renta
            Route::prefix('tax-declarations')->group(function () {
                Route::get('/', [TaxDeclarationController::class, 'index'])->name('api.v1.administration.tax-declarations.index');
                Route::get('/list', [TaxDeclarationController::class, 'list'])->name('api.v1.administration.tax-declarations.list');
                Route::post('/', [TaxDeclarationController::class, 'store'])->name('api.v1.administration.tax-declarations.store');
                Route::get('/{uuid}', [TaxDeclarationController::class, 'show'])->name('api.v1.administration.tax-declarations.show');
                Route::put('/{uuid}', [TaxDeclarationController::class, 'update'])->name('api.v1.administration.tax-declarations.update');
                Route::delete('/{uuid}', [TaxDeclarationController::class, 'destroy'])->name('api.v1.administration.tax-declarations.destroy');
                Route::patch('/{uuid}/toggle-status', [TaxDeclarationController::class, 'toggleStatus'])->name('api.v1.administration.tax-declarations.toggle-status');
            });

            // Seguridad y Salud en el Trabajo (sin toggle: no tiene campo de estado)
            Route::prefix('occupational-safety-records')->group(function () {
                Route::get('/', [OccupationalSafetyRecordController::class, 'index'])->name('api.v1.administration.occupational-safety-records.index');
                Route::get('/list', [OccupationalSafetyRecordController::class, 'list'])->name('api.v1.administration.occupational-safety-records.list');
                Route::post('/', [OccupationalSafetyRecordController::class, 'store'])->name('api.v1.administration.occupational-safety-records.store');
                Route::get('/{uuid}', [OccupationalSafetyRecordController::class, 'show'])->name('api.v1.administration.occupational-safety-records.show');
                Route::put('/{uuid}', [OccupationalSafetyRecordController::class, 'update'])->name('api.v1.administration.occupational-safety-records.update');
                Route::delete('/{uuid}', [OccupationalSafetyRecordController::class, 'destroy'])->name('api.v1.administration.occupational-safety-records.destroy');
            });

            // Centros de Costo
            Route::prefix('cost-centers')->group(function () {
                Route::get('/', [CostCenterController::class, 'index'])->name('api.v1.administration.cost-centers.index');
                Route::get('/list', [CostCenterController::class, 'list'])->name('api.v1.administration.cost-centers.list');
                Route::post('/', [CostCenterController::class, 'store'])->name('api.v1.administration.cost-centers.store');
                Route::get('/{uuid}', [CostCenterController::class, 'show'])->name('api.v1.administration.cost-centers.show');
                Route::put('/{uuid}', [CostCenterController::class, 'update'])->name('api.v1.administration.cost-centers.update');
                Route::delete('/{uuid}', [CostCenterController::class, 'destroy'])->name('api.v1.administration.cost-centers.destroy');
            });
        });

        // ─── RUTAS PARA EL MODULO TERCEROS ───
        Route::prefix('third-parties')->group(function () {
            Route::get('/', [ThirdPartyController::class, 'index'])->name('api.v1.third-parties.index');
            Route::get('/list', [ThirdPartyController::class, 'list'])->name('api.v1.third-parties.list');
            Route::post('/', [ThirdPartyController::class, 'store'])->name('api.v1.third-parties.store');
            Route::get('/{uuid}', [ThirdPartyController::class, 'show'])->name('api.v1.third-parties.show');
            Route::get('/{uuid}/technical-sheet', [ThirdPartyController::class, 'technicalSheet'])->name('api.v1.third-parties.technical-sheet');
            Route::get('/{uuid}/technical-sheet/pdf', [ThirdPartyController::class, 'technicalSheetPdf'])->name('api.v1.third-parties.technical-sheet.pdf');
            Route::put('/{uuid}', [ThirdPartyController::class, 'update'])->name('api.v1.third-parties.update');
            Route::delete('/{uuid}', [ThirdPartyController::class, 'destroy'])->name('api.v1.third-parties.destroy');
            Route::patch('/{uuid}/toggle-status', [ThirdPartyController::class, 'toggleStatus'])->name('api.v1.third-parties.toggle-status');
            Route::post('/{uuid}/photo', [ThirdPartyController::class, 'uploadPhoto'])->name('api.v1.third-parties.photo');
        });

        // ─── ASIGNACIONES DE PROYECTOS A CONDUCTORES ───
        Route::prefix('projects')->group(function () {
            Route::get('/', [ProjectController::class, 'index'])->name('api.v1.projects.index');
            Route::get('/list', [ProjectController::class, 'list'])->name('api.v1.projects.list');
            Route::post('/', [ProjectController::class, 'store'])->name('api.v1.projects.store');
            Route::get('/{uuid}', [ProjectController::class, 'show'])->name('api.v1.projects.show');
            Route::put('/{uuid}', [ProjectController::class, 'update'])->name('api.v1.projects.update');
            Route::delete('/{uuid}', [ProjectController::class, 'destroy'])->name('api.v1.projects.destroy');
        });

        Route::prefix('employment-contracts')->group(function () {
            Route::get('/', [EmploymentContractController::class, 'index'])->name('api.v1.third-parties.employment-contracts.index');
            Route::post('/', [EmploymentContractController::class, 'store'])->name('api.v1.third-parties.employment-contracts.store');
            Route::get('/{uuid}', [EmploymentContractController::class, 'show'])->name('api.v1.third-parties.employment-contracts.show');
            Route::put('/{uuid}', [EmploymentContractController::class, 'update'])->name('api.v1.third-parties.employment-contracts.update');
            Route::delete('/{uuid}', [EmploymentContractController::class, 'destroy'])->name('api.v1.third-parties.employment-contracts.destroy');
        });

        // ─── RUTAS PARA EL MODULO DE FLOTA ───
        Route::prefix('fleet-management')->group(function () {

            // Vehículos
            Route::prefix('vehicles')->group(function () {
                Route::get('/', [VehicleController::class, 'index'])->name('api.v1.fleet.vehicles.index');
                Route::get('/list', [VehicleController::class, 'list'])->name('api.v1.fleet.vehicles.list');
                Route::post('/', [VehicleController::class, 'store'])->name('api.v1.fleet.vehicles.store');
                Route::get('/{uuid}', [VehicleController::class, 'show'])->name('api.v1.fleet.vehicles.show');
                Route::get('/{uuid}/profile', [VehicleController::class, 'profile'])->name('api.v1.fleet.vehicles.profile');
                Route::put('/{uuid}', [VehicleController::class, 'update'])->name('api.v1.fleet.vehicles.update');
                Route::delete('/{uuid}', [VehicleController::class, 'destroy'])->name('api.v1.fleet.vehicles.destroy');
                Route::patch('/{uuid}/toggle-status', [VehicleController::class, 'toggleStatus'])->name('api.v1.fleet.vehicles.toggle-status');
                Route::get('/{uuid}/technical-sheet/pdf', [VehicleController::class, 'technicalSheetPdf'])->name('api.v1.fleet.vehicles.technical-sheet.pdf');
                Route::get('/{uuid}/history', [VehicleController::class, 'history'])->name('api.v1.fleet.vehicles.history');
                Route::get('/{uuid}/history/pdf', [VehicleController::class, 'historyPdf'])->name('api.v1.fleet.vehicles.history.pdf');
                Route::get('/{uuid}/maintenance-history/pdf', [VehicleController::class, 'maintenanceHistoryPdf'])->name('api.v1.fleet.vehicles.maintenance-history.pdf');
                Route::get('/{uuid}/handover-record/pdf', [VehicleController::class, 'handoverRecordPdf'])->name('api.v1.fleet.vehicles.handover-record.pdf');
                Route::get('/{uuid}/maintenance-forecast', [VehicleController::class, 'maintenanceForecast'])->name('api.v1.fleet.vehicles.maintenance-forecast');
            });

            // Sucursales de Vehículos
            Route::prefix('vehicles-branches')->group(function () {
                Route::get('/', [VehicleBranchController::class, 'index'])->name('api.v1.fleet.vehicles-branches.index');
                Route::get('/list', [VehicleBranchController::class, 'list'])->name('api.v1.fleet.vehicles-branches.list');
                Route::post('/', [VehicleBranchController::class, 'store'])->name('api.v1.fleet.vehicles-branches.store');
                Route::get('/{uuid}', [VehicleBranchController::class, 'show'])->name('api.v1.fleet.vehicles-branches.show');
                Route::put('/{uuid}', [VehicleBranchController::class, 'update'])->name('api.v1.fleet.vehicles-branches.update');
                Route::delete('/{uuid}', [VehicleBranchController::class, 'destroy'])->name('api.v1.fleet.vehicles-branches.destroy');
            });

            // Propietarios
            Route::prefix('owners')->group(function () {
                Route::get('/', [OwnerController::class, 'index'])->name('api.v1.fleet.owners.index');
                Route::get('/list', [OwnerController::class, 'list'])->name('api.v1.fleet.owners.list');
                Route::post('/', [OwnerController::class, 'store'])->name('api.v1.fleet.owners.store');
                Route::get('/{uuid}', [OwnerController::class, 'show'])->name('api.v1.fleet.owners.show');
                Route::put('/{uuid}', [OwnerController::class, 'update'])->name('api.v1.fleet.owners.update');
                Route::delete('/{uuid}', [OwnerController::class, 'destroy'])->name('api.v1.fleet.owners.destroy');
            });

            // Conductores y Propietarios vinculados
            Route::prefix('owner-drivers')->group(function () {
                Route::get('/', [OwnerDriverController::class, 'index'])->name('api.v1.fleet.owner-drivers.index');
                Route::get('/list', [OwnerDriverController::class, 'list'])->name('api.v1.fleet.owner-drivers.list');
                Route::post('/', [OwnerDriverController::class, 'store'])->name('api.v1.fleet.owner-drivers.store');
                Route::get('/{uuid}', [OwnerDriverController::class, 'show'])->name('api.v1.fleet.owner-drivers.show');
                Route::put('/{uuid}', [OwnerDriverController::class, 'update'])->name('api.v1.fleet.owner-drivers.update');
                Route::delete('/{uuid}', [OwnerDriverController::class, 'destroy'])->name('api.v1.fleet.owner-drivers.destroy');
            });

            // Mantenimiento
            Route::prefix('maintenances')->group(function () {
                Route::get('/', [MaintenanceController::class, 'index'])->name('api.v1.fleet.maintenances.index');
                Route::get('/list', [MaintenanceController::class, 'list'])->name('api.v1.fleet.maintenances.list');
                Route::post('/', [MaintenanceController::class, 'store'])->name('api.v1.fleet.maintenances.store');
                Route::get('/{uuid}', [MaintenanceController::class, 'show'])->name('api.v1.fleet.maintenances.show');
                Route::put('/{uuid}', [MaintenanceController::class, 'update'])->name('api.v1.fleet.maintenances.update');
                Route::delete('/{uuid}', [MaintenanceController::class, 'destroy'])->name('api.v1.fleet.maintenances.destroy');
            });

            // Repuestos de Mantenimiento
            Route::prefix('maintenance-parts')->group(function () {
                Route::get('/', [MaintenancePartController::class, 'index'])->name('api.v1.fleet.maintenance-parts.index');
                Route::get('/list', [MaintenancePartController::class, 'list'])->name('api.v1.fleet.maintenance-parts.list');
                Route::post('/', [MaintenancePartController::class, 'store'])->name('api.v1.fleet.maintenance-parts.store');
                Route::get('/{uuid}', [MaintenancePartController::class, 'show'])->name('api.v1.fleet.maintenance-parts.show');
                Route::put('/{uuid}', [MaintenancePartController::class, 'update'])->name('api.v1.fleet.maintenance-parts.update');
                Route::delete('/{uuid}', [MaintenancePartController::class, 'destroy'])->name('api.v1.fleet.maintenance-parts.destroy');
            });

            // Inspecciones de Vehículos
            Route::prefix('vehicle-inspections')->group(function () {
                Route::get('/', [VehicleInspectionController::class, 'index'])->name('api.v1.fleet.vehicle-inspections.index');
                Route::get('/list', [VehicleInspectionController::class, 'list'])->name('api.v1.fleet.vehicle-inspections.list');
                Route::get('/check-today', [VehicleInspectionController::class, 'checkToday'])->name('api.v1.fleet.vehicle-inspections.check-today');
                Route::post('/', [VehicleInspectionController::class, 'store'])->name('api.v1.fleet.vehicle-inspections.store');
                Route::get('/{uuid}', [VehicleInspectionController::class, 'show'])->name('api.v1.fleet.vehicle-inspections.show');
                Route::get('/{uuid}/pdf', [VehicleInspectionController::class, 'downloadPdf'])->name('api.v1.fleet.vehicle-inspections.pdf');
                Route::put('/{uuid}', [VehicleInspectionController::class, 'update'])->name('api.v1.fleet.vehicle-inspections.update');
                Route::delete('/{uuid}', [VehicleInspectionController::class, 'destroy'])->name('api.v1.fleet.vehicle-inspections.destroy');
                Route::post('/{uuid}/generate-sign-url', [VehicleInspectionController::class, 'generateSignUrl'])->name('api.v1.fleet.vehicle-inspections.generate-sign-url');
            });

            // Resultados de Inspección (Claves compuestas)
            Route::prefix('inspection-results')->group(function () {
                Route::get('/', [InspectionResultController::class, 'index'])->name('api.v1.fleet.inspection-results.index');
                Route::get('/list', [InspectionResultController::class, 'list'])->name('api.v1.fleet.inspection-results.list');
                Route::post('/', [InspectionResultController::class, 'store'])->name('api.v1.fleet.inspection-results.store');
                Route::get('/{inspectionUuid}/{itemUuid}', [InspectionResultController::class, 'show'])->name('api.v1.fleet.inspection-results.show');
                Route::put('/{inspectionUuid}/{itemUuid}', [InspectionResultController::class, 'update'])->name('api.v1.fleet.inspection-results.update');
                Route::delete('/{inspectionUuid}/{itemUuid}', [InspectionResultController::class, 'destroy'])->name('api.v1.fleet.inspection-results.destroy');
            });

            // Licencias de Conducción
            Route::prefix('driver-licenses')->group(function () {
                Route::get('/', [DriverLicenseController::class, 'index'])->name('api.v1.fleet.driver-licenses.index');
                Route::get('/list', [DriverLicenseController::class, 'list'])->name('api.v1.fleet.driver-licenses.list');
                Route::post('/', [DriverLicenseController::class, 'store'])->name('api.v1.fleet.driver-licenses.store');
                Route::get('/{uuid}', [DriverLicenseController::class, 'show'])->name('api.v1.fleet.driver-licenses.show');
                Route::put('/{uuid}', [DriverLicenseController::class, 'update'])->name('api.v1.fleet.driver-licenses.update');
                Route::delete('/{uuid}', [DriverLicenseController::class, 'destroy'])->name('api.v1.fleet.driver-licenses.destroy');
            });

            // Tarjetas de Operación
            Route::prefix('operation-cards')->group(function () {
                Route::get('/', [OperationCardController::class, 'index'])->name('api.v1.fleet.operation-cards.index');
                Route::get('/list', [OperationCardController::class, 'list'])->name('api.v1.fleet.operation-cards.list');
                Route::post('/', [OperationCardController::class, 'store'])->name('api.v1.fleet.operation-cards.store');
                Route::get('/{uuid}', [OperationCardController::class, 'show'])->name('api.v1.fleet.operation-cards.show');
                Route::put('/{uuid}', [OperationCardController::class, 'update'])->name('api.v1.fleet.operation-cards.update');
                Route::delete('/{uuid}', [OperationCardController::class, 'destroy'])->name('api.v1.fleet.operation-cards.destroy');
                Route::patch('/{uuid}/toggle-status', [OperationCardController::class, 'toggleStatus'])->name('api.v1.fleet.operation-cards.toggle-status');
            });

            // Documentación de Vehículos
            Route::prefix('vehicle-documents')->group(function () {
                Route::get('/', [VehicleDocumentController::class, 'index'])->name('api.v1.fleet.vehicle-documents.index');
                Route::get('/list', [VehicleDocumentController::class, 'list'])->name('api.v1.fleet.vehicle-documents.list');
                Route::post('/', [VehicleDocumentController::class, 'store'])->name('api.v1.fleet.vehicle-documents.store');
                Route::get('/{uuid}', [VehicleDocumentController::class, 'show'])->name('api.v1.fleet.vehicle-documents.show');
                Route::put('/{uuid}', [VehicleDocumentController::class, 'update'])->name('api.v1.fleet.vehicle-documents.update');
                Route::delete('/{uuid}', [VehicleDocumentController::class, 'destroy'])->name('api.v1.fleet.vehicle-documents.destroy');
            });

            // Aportes de Seguridad Social de Conductores
            Route::prefix('social-security-contributions')->group(function () {
                Route::get('/', [SocialSecurityContributionController::class, 'index'])->name('api.v1.fleet.social-security-contributions.index');
                Route::get('/list', [SocialSecurityContributionController::class, 'list'])->name('api.v1.fleet.social-security-contributions.list');
                Route::post('/', [SocialSecurityContributionController::class, 'store'])->name('api.v1.fleet.social-security-contributions.store');
                Route::get('/{uuid}', [SocialSecurityContributionController::class, 'show'])->name('api.v1.fleet.social-security-contributions.show');
                Route::put('/{uuid}', [SocialSecurityContributionController::class, 'update'])->name('api.v1.fleet.social-security-contributions.update');
                Route::delete('/{uuid}', [SocialSecurityContributionController::class, 'destroy'])->name('api.v1.fleet.social-security-contributions.destroy');
            });

            // Convenios de Colaboración Empresarial
            Route::prefix('business-collaboration-agreements')->group(function () {
                Route::get('/', [BusinessCollaborationAgreementController::class, 'index'])->name('api.v1.fleet.business-collaboration-agreements.index');
                Route::get('/list', [BusinessCollaborationAgreementController::class, 'list'])->name('api.v1.fleet.business-collaboration-agreements.list');
                Route::get('/next-consecutive', [BusinessCollaborationAgreementController::class, 'nextConsecutive'])->name('api.v1.fleet.business-collaboration-agreements.next-consecutive');
                Route::post('/', [BusinessCollaborationAgreementController::class, 'store'])->name('api.v1.fleet.business-collaboration-agreements.store');
                Route::get('/{uuid}', [BusinessCollaborationAgreementController::class, 'show'])->name('api.v1.fleet.business-collaboration-agreements.show');
                Route::get('/{uuid}/pdf', [BusinessCollaborationAgreementController::class, 'downloadPdf'])->name('api.v1.fleet.business-collaboration-agreements.pdf');
                Route::put('/{uuid}', [BusinessCollaborationAgreementController::class, 'update'])->name('api.v1.fleet.business-collaboration-agreements.update');
                Route::delete('/{uuid}', [BusinessCollaborationAgreementController::class, 'destroy'])->name('api.v1.fleet.business-collaboration-agreements.destroy');
                Route::patch('/{uuid}/toggle-status', [BusinessCollaborationAgreementController::class, 'toggleStatus'])->name('api.v1.fleet.business-collaboration-agreements.toggle-status');
            });

            // Cargos de Administración de Afiliados
            Route::prefix('affiliate-admin-charges')->group(function () {
                // CRUD estándar
                Route::get('/', [AffiliateAdminChargeController::class, 'index'])->name('api.v1.fleet.affiliate-admin-charges.index');
                Route::get('/list', [AffiliateAdminChargeController::class, 'list'])->name('api.v1.fleet.affiliate-admin-charges.list');
                Route::post('/', [AffiliateAdminChargeController::class, 'store'])->name('api.v1.fleet.affiliate-admin-charges.store');

                // Consultas avanzadas (antes de {uuid} para evitar colisión de rutas)
                Route::get('/summary', [AffiliateAdminChargeController::class, 'getSummary'])->name('api.v1.fleet.affiliate-admin-charges.summary');
                Route::get('/history/{paymentReference}', [AffiliateAdminChargeController::class, 'getPaymentHistory'])->name('api.v1.fleet.affiliate-admin-charges.payment-history');
                Route::get('/pending/{paymentReference}', [AffiliateAdminChargeController::class, 'getPendingCharge'])->name('api.v1.fleet.affiliate-admin-charges.pending-charge');
                Route::get('/vehicle/{vehicleUuid}', [AffiliateAdminChargeController::class, 'getByVehicle'])->name('api.v1.fleet.affiliate-admin-charges.by-vehicle');
                Route::get('/vehicle/{vehicleUuid}/next-due', [AffiliateAdminChargeController::class, 'getNextDueDate'])->name('api.v1.fleet.affiliate-admin-charges.next-due');

                // Operaciones por UUID
                Route::get('/{uuid}', [AffiliateAdminChargeController::class, 'show'])->name('api.v1.fleet.affiliate-admin-charges.show');
                Route::put('/{uuid}', [AffiliateAdminChargeController::class, 'update'])->name('api.v1.fleet.affiliate-admin-charges.update');
                Route::delete('/{uuid}', [AffiliateAdminChargeController::class, 'destroy'])->name('api.v1.fleet.affiliate-admin-charges.destroy');

                // Acciones de negocio
                Route::post('/{uuid}/apply-monthly-payment', [AffiliateAdminChargeController::class, 'applyMonthlyPayment'])->name('api.v1.fleet.affiliate-admin-charges.apply-monthly-payment');
                Route::patch('/{uuid}/update-status', [AffiliateAdminChargeController::class, 'updateStatus'])->name('api.v1.fleet.affiliate-admin-charges.update-status');
                Route::get('/{uuid}/receipt-pdf', [AffiliateAdminChargeController::class, 'generateReceiptPdf'])->name('api.v1.fleet.affiliate-admin-charges.receipt-pdf');
            });

            // ─── RUTAS PARA EL MODULO DE Hojas de Control ───
            Route::prefix('control-sheets')->group(function () {
                Route::get('/', [ControlSheetController::class, 'index'])->name('api.v1.fleet.control-sheets.index');
                Route::get('/list', [ControlSheetController::class, 'list'])->name('api.v1.fleet.control-sheets.list');
                Route::post('/', [ControlSheetController::class, 'store'])->name('api.v1.fleet.control-sheets.store');
                Route::get('/{uuid}', [ControlSheetController::class, 'show'])->name('api.v1.fleet.control-sheets.show');
                Route::put('/{uuid}', [ControlSheetController::class, 'update'])->name('api.v1.fleet.control-sheets.update');
                Route::delete('/{uuid}', [ControlSheetController::class, 'destroy'])->name('api.v1.fleet.control-sheets.destroy');
                Route::post('/{uuid}/upload-pdf', [ControlSheetController::class, 'uploadPdf'])->name('api.v1.fleet.control-sheets.upload-pdf');
                Route::post('/{uuid}/upload-pdfs', [ControlSheetController::class, 'uploadMultiplePdfs'])->name('api.v1.fleet.control-sheets.upload-pdfs');
                Route::get('/{uuid}/pdfs', [ControlSheetController::class, 'getPdfs'])->name('api.v1.fleet.control-sheets.pdfs');
                Route::delete('/{uuid}/pdfs/{mediaUuid}', [ControlSheetController::class, 'deletePdf'])->name('api.v1.fleet.control-sheets.delete-pdf');
            });
        });

        // ─── RUTAS PARA EL MODULO DE EXTRATO DE CONTRATOS ───
        Route::prefix('contract-extract')->group(function () {
            // Contratistas
            Route::prefix('contractors')->group(function () {
                Route::get('/', [ContractorController::class, 'index'])->name('api.v1.contract-extractions.contractors.index');
                Route::get('/list', [ContractorController::class, 'list'])->name('api.v1.contract-extractions.contractors.list');
                Route::post('/', [ContractorController::class, 'store'])->name('api.v1.contract-extractions.contractors.store');
                Route::get('/next-consecutive', [ContractorController::class, 'nextConsecutive'])->name('api.v1.contract-extractions.contractors.next-consecutive');
                Route::get('/{uuid}', [ContractorController::class, 'show'])->name('api.v1.contract-extractions.contractors.show');
                Route::put('/{uuid}', [ContractorController::class, 'update'])->name('api.v1.contract-extractions.contractors.update');
                Route::delete('/{uuid}', [ContractorController::class, 'destroy'])->name('api.v1.contract-extractions.contractors.destroy');
                Route::patch('/{uuid}/toggle-status', [ContractorController::class, 'toggleStatus'])->name('api.v1.contract-extractions.contractors.toggle-status');
            });

            // FUEC (Formato Único de Extracto del Contrato)
            Route::prefix('fuecs')->group(function () {
                Route::get('/', [FuecController::class, 'index'])->name('api.v1.contract-extractions.fuecs.index');
                Route::get('/list', [FuecController::class, 'list'])->name('api.v1.contract-extractions.fuecs.list');
                Route::post('/', [FuecController::class, 'store'])->name('api.v1.contract-extractions.fuecs.store');
                Route::get('/preview-number', [FuecController::class, 'previewFuecNumber'])->name('api.v1.contract-extractions.fuecs.preview-number');
                Route::post('/{uuid}/assign-vehicle', [FuecController::class, 'assignVehicleToContract'])->name('api.v1.contract-extractions.fuecs.assign-vehicle');
                Route::get('/{uuid}', [FuecController::class, 'show'])->name('api.v1.contract-extractions.fuecs.show');
                Route::get('/{uuid}/pdf', [FuecController::class, 'downloadPdf'])->name('api.v1.contract-extractions.fuecs.pdf');
                Route::put('/{uuid}', [FuecController::class, 'update'])->name('api.v1.contract-extractions.fuecs.update');
                Route::delete('/{uuid}', [FuecController::class, 'destroy'])->name('api.v1.contract-extractions.fuecs.destroy');
            });

            // Pasajeros de FUEC
            Route::prefix('fuec-passengers')->group(function () {
                Route::get('/', [FuecPassengerController::class, 'index'])->name('api.v1.contract-extractions.fuec-passengers.index');
                Route::get('/list', [FuecPassengerController::class, 'list'])->name('api.v1.contract-extractions.fuec-passengers.list');
                Route::post('/', [FuecPassengerController::class, 'store'])->name('api.v1.contract-extractions.fuec-passengers.store');
                Route::get('/{uuid}', [FuecPassengerController::class, 'show'])->name('api.v1.contract-extractions.fuec-passengers.show');
                Route::put('/{uuid}', [FuecPassengerController::class, 'update'])->name('api.v1.contract-extractions.fuec-passengers.update');
                Route::delete('/{uuid}', [FuecPassengerController::class, 'destroy'])->name('api.v1.contract-extractions.fuec-passengers.destroy');
            });

            // Objetos de Contratos
            Route::prefix('objects-contracts')->group(function () {
                Route::get('/', [ObjectContractController::class, 'index'])->name('api.v1.contract-extractions.object-contracts.index');
                Route::get('/list', [ObjectContractController::class, 'list'])->name('api.v1.contract-extractions.object-contracts.list');
                Route::post('/', [ObjectContractController::class, 'store'])->name('api.v1.contract-extractions.object-contracts.store');
                Route::get('/{uuid}', [ObjectContractController::class, 'show'])->name('api.v1.contract-extractions.object-contracts.show');
                Route::put('/{uuid}', [ObjectContractController::class, 'update'])->name('api.v1.contract-extractions.object-contracts.update');
                Route::delete('/{uuid}', [ObjectContractController::class, 'destroy'])->name('api.v1.contract-extractions.object-contracts.destroy');
            });
        });

        Route::prefix('control-sheets')->group(function () {
            Route::prefix('service-delivery-control-sheets')->group(function () {
                Route::get('/', [ServiceDeliveryControlSheetController::class, 'index'])->name('api.v1.fleet.service-delivery-control-sheets.index');
                Route::get('/list', [ServiceDeliveryControlSheetController::class, 'list'])->name('api.v1.fleet.service-delivery-control-sheets.list');
                Route::get('/monthly/pdf', [ServiceDeliveryControlSheetController::class, 'downloadMonthlyPdf'])->name('api.v1.fleet.service-delivery-control-sheets.monthly.pdf');
                Route::post('/', [ServiceDeliveryControlSheetController::class, 'store'])->name('api.v1.fleet.service-delivery-control-sheets.store');
                Route::get('/{uuid}', [ServiceDeliveryControlSheetController::class, 'show'])->name('api.v1.fleet.service-delivery-control-sheets.show');
                Route::get('/{uuid}/pdf', [ServiceDeliveryControlSheetController::class, 'downloadDailyPdf'])->name('api.v1.fleet.service-delivery-control-sheets.pdf');
                Route::put('/{uuid}', [ServiceDeliveryControlSheetController::class, 'update'])->name('api.v1.fleet.service-delivery-control-sheets.update');
                Route::delete('/{uuid}', [ServiceDeliveryControlSheetController::class, 'destroy'])->name('api.v1.fleet.service-delivery-control-sheets.destroy');
                Route::post('/{uuid}/start', [ServiceDeliveryControlSheetController::class, 'start'])->name('api.v1.fleet.service-delivery-control-sheets.start');
                Route::post('/{uuid}/close', [ServiceDeliveryControlSheetController::class, 'close'])->name('api.v1.fleet.service-delivery-control-sheets.close');
                Route::post('/{uuid}/close-route', [ServiceDeliveryControlSheetController::class, 'closeRoute'])->name('api.v1.fleet.service-delivery-control-sheets.close-route');
            });
        });

        // ─── RUTAS PARA EL MODULO DE TRAMITES ───
        Route::prefix('procedure')->group(function () {
            // Trámites
            Route::prefix('procedures')->group(function () {
                Route::get('/', [ProcedureController::class, 'index'])->name('api.v1.procedure.procedures.index');
                Route::get('/list', [ProcedureController::class, 'list'])->name('api.v1.procedure.procedures.list');
                Route::post('/', [ProcedureController::class, 'store'])->name('api.v1.procedure.procedures.store');
                Route::get('/{uuid}', [ProcedureController::class, 'show'])->name('api.v1.procedure.procedures.show');
                Route::put('/{uuid}', [ProcedureController::class, 'update'])->name('api.v1.procedure.procedures.update');
                Route::delete('/{uuid}', [ProcedureController::class, 'destroy'])->name('api.v1.procedure.procedures.destroy');
            });

            // Directores Territoriales
            Route::prefix('territorial-directors')->group(function () {
                Route::get('/', [TerritorialDirectorController::class, 'index'])->name('api.v1.procedure.territorial-directors.index');
                Route::get('/list', [TerritorialDirectorController::class, 'list'])->name('api.v1.procedure.territorial-directors.list');
                Route::post('/', [TerritorialDirectorController::class, 'store'])->name('api.v1.procedure.territorial-directors.store');
                Route::get('/{uuid}', [TerritorialDirectorController::class, 'show'])->name('api.v1.procedure.territorial-directors.show');
                Route::put('/{uuid}', [TerritorialDirectorController::class, 'update'])->name('api.v1.procedure.territorial-directors.update');
                Route::delete('/{uuid}', [TerritorialDirectorController::class, 'destroy'])->name('api.v1.procedure.territorial-directors.destroy');
                Route::patch('/{uuid}/toggle-status', [TerritorialDirectorController::class, 'toggleStatus'])->name('api.v1.procedure.territorial-directors.toggle-status');
            });

            // Inventario de Capacidad
            Route::prefix('capacity-inventories')->group(function () {
                Route::get('/', [CapacityInventoryController::class, 'index'])->name('api.v1.procedure.capacity-inventories.index');
                Route::get('/list', [CapacityInventoryController::class, 'list'])->name('api.v1.procedure.capacity-inventories.list');
                Route::post('/', [CapacityInventoryController::class, 'store'])->name('api.v1.procedure.capacity-inventories.store');
                Route::get('/{uuid}', [CapacityInventoryController::class, 'show'])->name('api.v1.procedure.capacity-inventories.show');
                Route::put('/{uuid}', [CapacityInventoryController::class, 'update'])->name('api.v1.procedure.capacity-inventories.update');
                Route::delete('/{uuid}', [CapacityInventoryController::class, 'destroy'])->name('api.v1.procedure.capacity-inventories.destroy');
            });

            // Contratos de Servicio de Flota
            Route::prefix('fleet-service-contracts')->group(function () {
                Route::get('/', [FleetServiceContractController::class, 'index'])->name('api.v1.procedure.fleet-service-contracts.index');
                Route::get('/list', [FleetServiceContractController::class, 'list'])->name('api.v1.procedure.fleet-service-contracts.list');
                Route::post('/', [FleetServiceContractController::class, 'store'])->name('api.v1.procedure.fleet-service-contracts.store');
                Route::get('/{uuid}', [FleetServiceContractController::class, 'show'])->name('api.v1.procedure.fleet-service-contracts.show');
                Route::put('/{uuid}', [FleetServiceContractController::class, 'update'])->name('api.v1.procedure.fleet-service-contracts.update');
                Route::delete('/{uuid}', [FleetServiceContractController::class, 'destroy'])->name('api.v1.procedure.fleet-service-contracts.destroy');
            });
        });

        // ─── MÓDULO CHAT ASISTENTE (OCULTO TEMPORALMENTE) ───
        // Route::prefix('chat')->group(function () {
        //     Route::prefix('sessions')->group(function () {
        //         Route::get('/', [ChatController::class, 'index'])->name('api.v1.chat.sessions.index');
        //         Route::post('/', [ChatController::class, 'store'])->name('api.v1.chat.sessions.store');
        //         Route::get('/{uuid}', [ChatController::class, 'show'])->name('api.v1.chat.sessions.show');
        //         Route::post('/{uuid}/messages', [ChatController::class, 'send'])->name('api.v1.chat.sessions.send')
        //             ->middleware('chat.limiter');
        //         Route::delete('/{uuid}', [ChatController::class, 'destroy'])->name('api.v1.chat.sessions.destroy');
        //     });
        //
        //     Route::post('/messages/{uuid}/feedback', [ChatController::class, 'rateFeedback'])->name('api.v1.chat.messages.feedback');
        // });

        // ─── MÓDULO NOTIFICACIONES ───

        // ─── MÓDULO NOTIFICACIONES ───
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationsController::class, 'index'])->name('api.v1.notifications.index');
            Route::post('/sync', [NotificationsController::class, 'sync'])->name('api.v1.notifications.sync');
            Route::post('/read-all', [NotificationsController::class, 'markAllAsRead'])->name('api.v1.notifications.read-all');
            Route::patch('/{uuid}/toggle-status', [NotificationsController::class, 'toggleStatus'])->name('api.v1.notifications.toggle-status');
            Route::patch('/{uuid}/read', [NotificationsController::class, 'markAsRead'])->name('api.v1.notifications.read');
            Route::delete('/{uuid}', [NotificationsController::class, 'destroy'])->name('api.v1.notifications.destroy');
        });

        // ─── MÓDULO FIRMAS ───
        Route::prefix('signatures')->group(function () {
            Route::post('/', [SignatureController::class, 'store'])->name('api.v1.signatures.store');
            Route::get('/latest', [SignatureController::class, 'latest'])->name('api.v1.signatures.latest');
            Route::put('/{uuid}', [SignatureController::class, 'replace'])->name('api.v1.signatures.replace');
            Route::delete('/{uuid}', [SignatureController::class, 'destroy'])->name('api.v1.signatures.destroy');
        });

        // ─── MÓDULO GEOLOCALIZACIÓN ───
        Route::prefix('tracking')->group(function () {
            // Conductor envía ubicación
            Route::post('/location', [DriverLocationController::class, 'store'])
                ->middleware('permission:locations.track')
                ->name('api.v1.tracking.location.store');

            // Conductor inicia/detiene sesión
            Route::post('/session/start', [DriverLocationController::class, 'startSession'])
                ->middleware('permission:locations.track')
                ->name('api.v1.tracking.session.start');
            Route::post('/session/stop', [DriverLocationController::class, 'stopSession'])
                ->middleware('permission:locations.track')
                ->name('api.v1.tracking.session.stop');

            // Admin: ver conductores activos
            Route::get('/active-drivers', [DriverLocationController::class, 'activeDrivers'])
                ->middleware('permission:locations.view')
                ->name('api.v1.tracking.active-drivers');

            // Admin: última ubicación de un conductor
            Route::get('/last-location/{uuid}', [DriverLocationController::class, 'lastLocation'])
                ->middleware('permission:locations.view')
                ->name('api.v1.tracking.last-location');

            // Historial de rutas
            Route::get('/driver/{uuid}/history', [LocationHistoryController::class, 'driverHistory'])
                ->middleware('permission:locations.history')
                ->name('api.v1.tracking.driver-history');
            Route::get('/driver/{uuid}/stats', [LocationHistoryController::class, 'driverStats'])
                ->middleware('permission:locations.history')
                ->name('api.v1.tracking.driver-stats');

            // CRUD Geocercas
            Route::prefix('geofences')->group(function () {
                Route::get('/', [GeofenceController::class, 'index'])
                    ->middleware('permission:locations.geofences')
                    ->name('api.v1.tracking.geofences.index');
                Route::post('/', [GeofenceController::class, 'store'])
                    ->middleware('permission:locations.geofences')
                    ->name('api.v1.tracking.geofences.store');
                Route::get('/{uuid}', [GeofenceController::class, 'show'])
                    ->middleware('permission:locations.geofences')
                    ->name('api.v1.tracking.geofences.show');
                Route::put('/{uuid}', [GeofenceController::class, 'update'])
                    ->middleware('permission:locations.geofences')
                    ->name('api.v1.tracking.geofences.update');
                Route::delete('/{uuid}', [GeofenceController::class, 'destroy'])
                    ->middleware('permission:locations.geofences')
                    ->name('api.v1.tracking.geofences.destroy');
            });

            // Alertas
            Route::get('/alerts', [DriverLocationController::class, 'alerts'])
                ->middleware('permission:locations.alerts')
                ->name('api.v1.tracking.alerts');
            Route::patch('/alerts/{uuid}/read', [DriverLocationController::class, 'markAlertRead'])
                ->middleware('permission:locations.alerts')
                ->name('api.v1.tracking.alerts.read');
        });

        // ─── MÓDULO CONFIGURACIONES ───
        Route::prefix('settings')->group(function () {
            Route::prefix('system-configurations')->group(function () {
                Route::get('/', [SystemConfigurationController::class, 'index'])->name('api.v1.settings.system-configurations.index');
                Route::post('/', [SystemConfigurationController::class, 'store'])->name('api.v1.settings.system-configurations.store');
                Route::get('/company/{companyUuid}', [SystemConfigurationController::class, 'showByCompany'])->name('api.v1.settings.system-configurations.company');
                Route::get('/{uuid}', [SystemConfigurationController::class, 'show'])->name('api.v1.settings.system-configurations.show');
                Route::put('/{uuid}', [SystemConfigurationController::class, 'update'])->name('api.v1.settings.system-configurations.update');
                Route::delete('/{uuid}', [SystemConfigurationController::class, 'destroy'])->name('api.v1.settings.system-configurations.destroy');

                // Rutas de subida de imágenes
                Route::post('/{uuid}/ministry-logo', [SystemConfigurationController::class, 'uploadMinistryLogo'])->name('api.v1.settings.system-configurations.ministry-logo');
                Route::post('/{uuid}/super-logo', [SystemConfigurationController::class, 'uploadSuperLogo'])->name('api.v1.settings.system-configurations.super-logo');
                Route::post('/{uuid}/letterhead', [SystemConfigurationController::class, 'uploadLetterhead'])->name('api.v1.settings.system-configurations.letterhead');
            });
        });
    });

    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'time' => now()->toDateTimeString(),
        ]);
    });
});
