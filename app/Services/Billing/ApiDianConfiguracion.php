<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use App\Models\Company;

/**
 * Módulo 01 - Configuraciones Básicas de ApiDian.
 *
 * Proxy autenticado de los pasos de configuración de la cuenta del proveedor
 * tecnológico (empresa, software, certificado, resolución, ambiente y webhook).
 * NOA no guarda estos datos: viven en ApiDian y aquí solo se consultan/envían.
 *
 * Referencia: colección Postman, sección "01 - Configuraciones Básicas".
 */
final class ApiDianConfiguracion
{
    private readonly ApiDianCliente $cliente;

    public function __construct(private readonly BillingProvider $proveedor)
    {
        if (! $proveedor->usaApiDian()) {
            throw new \InvalidArgumentException("El proveedor {$proveedor->name} no usa ApiDian.");
        }

        $this->cliente = new ApiDianCliente($proveedor);
    }

    /** Paso 1: consulta la empresa configurada en ApiDian para el NIT. */
    public function consultarEmpresa(): array
    {
        return $this->llamar('get', 'config/'.$this->nit());
    }

    /** Paso 1: crea o actualiza la empresa en ApiDian. */
    public function guardarEmpresa(array $datos): array
    {
        return $this->llamar('post', 'config/'.$this->nit(), $datos);
    }

    /** Paso 2: datos del software propio (id, pin y url). */
    public function guardarSoftware(array $datos): array
    {
        return $this->llamar('put', 'config/software', $datos);
    }

    /** Paso 2b: software de nómina electrónica. */
    public function guardarSoftwareNomina(array $datos): array
    {
        return $this->llamar('put', 'config/softwarepayroll', $datos);
    }

    /** Paso 3: certificado digital (.p12 en base64) y su clave. */
    public function guardarCertificado(array $datos): array
    {
        return $this->llamar('put', 'config/certificate', $datos);
    }

    /** Paso 4/5: resolución de facturación o de notas (según type_document_id). */
    public function guardarResolucion(array $datos): array
    {
        return $this->llamar('post', 'config/resolution', $datos);
    }

    /** Paso 6: ambiente (1 producción, 2 habilitación) y test_set_id. */
    public function cambiarAmbiente(array $datos): array
    {
        return $this->llamar('put', 'config/environment', $datos);
    }

    /** Webhook para notificaciones de estado. */
    public function guardarWebhook(array $datos): array
    {
        return $this->llamar('put', 'config/webhook', $datos);
    }

    /** Elimina la empresa configurada en ApiDian (irreversible). */
    public function eliminarEmpresa(): array
    {
        return $this->llamar('delete', 'config/'.$this->nit());
    }

    private function llamar(string $metodo, string $ruta, array $datos = []): array
    {
        $respuesta = $this->cliente->enviar(
            $metodo,
            $this->cliente->baseUrl().'/api/ubl2.1/'.$ruta,
            $datos,
        );

        $cuerpo = $respuesta->json();
        if (! is_array($cuerpo)) {
            $cuerpo = $respuesta->body() !== ''
                ? ['respuesta_no_json' => \Illuminate\Support\Str::limit($respuesta->body(), 500)]
                : ['http' => $respuesta->status()];
        }

        if ($respuesta->failed()) {
            throw new \RuntimeException('ApiDian: '.$this->mensajeDeError($cuerpo, $respuesta->status()));
        }

        return $cuerpo;
    }

    private function mensajeDeError(array $cuerpo, int $http): string
    {
        $mensaje = $cuerpo['message'] ?? null;
        if (is_string($mensaje) && $mensaje !== '') {
            return $mensaje;
        }

        foreach (['errors_messages', 'errors'] as $clave) {
            if (! empty($cuerpo[$clave]) && is_array($cuerpo[$clave])) {
                return (string) reset($cuerpo[$clave]);
            }
        }

        return "respuesta HTTP {$http}";
    }

    /** NIT de la empresa dueña del proveedor (ruta /config/{nit}). */
    private function nit(): string
    {
        $nit = (string) Company::query()
            ->where('uuid', $this->proveedor->company_uuid)
            ->value('document_number');

        if ($nit === '') {
            throw new \RuntimeException('La empresa del proveedor no tiene NIT configurado.');
        }

        return $nit;
    }
}
