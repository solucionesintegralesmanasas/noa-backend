<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ConfiguracionApiDianRequest;
use App\Services\Billing\ApiDianConfiguracion;
use App\Services\Billing\BillingProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * Módulo 01 - Configuraciones Básicas de ApiDian (por proveedor tecnológico).
 *
 * Proxy de los pasos de configuración de la cuenta: empresa, software,
 * certificado, resolución, ambiente y webhook. Los datos viven en ApiDian.
 */
class BillingProviderConfigController extends Controller
{
    public function __construct(
        private readonly BillingProviderService $proveedores
    ) {}

    #[OA\Get(path: '/api/v1/billing/billing-providers/{uuid}/configuracion', summary: 'Consultar configuración básica de ApiDian', operationId: 'showBillingProviderConfig', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function show(string $uuid): JsonResponse
    {
        return $this->responder($uuid, fn (ApiDianConfiguracion $config) => $config->consultarEmpresa(),
            'Configuración consultada con éxito.'
        );
    }

    #[OA\Post(path: '/api/v1/billing/billing-providers/{uuid}/configuracion', summary: 'Guardar empresa en ApiDian (paso 1)', operationId: 'storeBillingProviderCompany', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function guardarEmpresa(ConfiguracionApiDianRequest $request, string $uuid): JsonResponse
    {
        return $this->responder($uuid, fn (ApiDianConfiguracion $config) => $config->guardarEmpresa($request->validated()),
            'Empresa guardada en ApiDian.'
        );
    }

    #[OA\Put(path: '/api/v1/billing/billing-providers/{uuid}/configuracion/software', summary: 'Configurar software (paso 2)', operationId: 'updateBillingProviderSoftware', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function software(ConfiguracionApiDianRequest $request, string $uuid): JsonResponse
    {
        return $this->responder($uuid, fn (ApiDianConfiguracion $config) => $config->guardarSoftware($request->validated()),
            'Software configurado en ApiDian.'
        );
    }

    #[OA\Put(path: '/api/v1/billing/billing-providers/{uuid}/configuracion/software-nomina', summary: 'Configurar software de nómina (paso 2b)', operationId: 'updateBillingProviderPayrollSoftware', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function softwareNomina(ConfiguracionApiDianRequest $request, string $uuid): JsonResponse
    {
        return $this->responder($uuid, fn (ApiDianConfiguracion $config) => $config->guardarSoftwareNomina($request->validated()),
            'Software de nómina configurado en ApiDian.'
        );
    }

    #[OA\Put(path: '/api/v1/billing/billing-providers/{uuid}/configuracion/certificado', summary: 'Cargar certificado digital (paso 3)', operationId: 'updateBillingProviderCertificate', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function certificado(ConfiguracionApiDianRequest $request, string $uuid): JsonResponse
    {
        return $this->responder($uuid, fn (ApiDianConfiguracion $config) => $config->guardarCertificado($request->validated()),
            'Certificado cargado en ApiDian.'
        );
    }

    #[OA\Post(path: '/api/v1/billing/billing-providers/{uuid}/configuracion/resoluciones', summary: 'Configurar resolución (pasos 4 y 5)', operationId: 'storeBillingProviderResolution', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function resoluciones(ConfiguracionApiDianRequest $request, string $uuid): JsonResponse
    {
        return $this->responder($uuid, fn (ApiDianConfiguracion $config) => $config->guardarResolucion($request->validated()),
            'Resolución configurada en ApiDian.'
        );
    }

    #[OA\Put(path: '/api/v1/billing/billing-providers/{uuid}/configuracion/ambiente', summary: 'Cambiar ambiente y set de pruebas (paso 6)', operationId: 'updateBillingProviderEnvironment', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function ambiente(ConfiguracionApiDianRequest $request, string $uuid): JsonResponse
    {
        return $this->responder($uuid, fn (ApiDianConfiguracion $config) => $config->cambiarAmbiente($request->validated()),
            'Ambiente actualizado en ApiDian.'
        );
    }

    #[OA\Put(path: '/api/v1/billing/billing-providers/{uuid}/configuracion/webhook', summary: 'Configurar webhook', operationId: 'updateBillingProviderWebhook', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function webhook(ConfiguracionApiDianRequest $request, string $uuid): JsonResponse
    {
        return $this->responder($uuid, fn (ApiDianConfiguracion $config) => $config->guardarWebhook($request->validated()),
            'Webhook configurado en ApiDian.'
        );
    }

    #[OA\Delete(path: '/api/v1/billing/billing-providers/{uuid}/configuracion/empresa', summary: 'Eliminar empresa en ApiDian', operationId: 'destroyBillingProviderCompany', tags: ['Facturación'], security: [['bearerAuth' => []]])]
    public function eliminarEmpresa(string $uuid): JsonResponse
    {
        return $this->responder($uuid, fn (ApiDianConfiguracion $config) => $config->eliminarEmpresa(),
            'Configuración de la empresa eliminada en ApiDian.'
        );
    }

    /** @param callable(ApiDianConfiguracion): array $accion */
    private function responder(string $uuid, callable $accion, string $mensaje): JsonResponse
    {
        try {
            $proveedor = $this->proveedores->findByUuid($uuid);
            if (! $proveedor) {
                return $this->errorResponse('El proveedor solicitado no existe.', 404);
            }
            if (! $proveedor->usaApiDian()) {
                return $this->errorResponse('El proveedor no usa ApiDian; no tiene configuraciones básicas que administrar.', 422);
            }

            return $this->successResponse(
                $accion(new ApiDianConfiguracion($proveedor)),
                $mensaje
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
