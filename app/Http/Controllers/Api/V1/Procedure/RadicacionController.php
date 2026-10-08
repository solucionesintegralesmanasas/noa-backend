<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Procedure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procedure\Radicacion\GenerarTxtRequest;
use App\Http\Requests\Procedure\Radicacion\StoreEnlaceFirmaRequest;
use App\Http\Requests\Procedure\Radicacion\StoreExpedienteRadicacionRequest;
use App\Services\Procedure\ContractSignatureService;
use App\Services\Procedure\RadicacionDocumentoService;
use App\Services\Procedure\RadicacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class RadicacionController extends Controller
{
    public function __construct(
        private readonly RadicacionService $radicacion,
        private readonly ContractSignatureService $firmas,
        private readonly RadicacionDocumentoService $radicacionDocumentos
    ) {}

    public function ruta(string $uuid): JsonResponse
    {
        try {
            $exp = $this->radicacion->findByUuid($uuid);
            $ruta = $this->radicacion->rutaPara($exp->link_type ?? 'CAMBIO_DE_EMPRESA');

            return $this->successResponse(['ruta' => $ruta, 'expediente' => $exp], 'Ruta de radicación.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function validar(string $uuid): JsonResponse
    {
        try {
            $hijo = $this->radicacion->findByUuid($uuid);

            return $this->successResponse($this->radicacion->validarRequisitos($hijo), 'Validación de requisitos.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function enlaceFirma(StoreEnlaceFirmaRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $res = $this->firmas->crearEnlace($data);
            $firma = $res['firma'];

            $correo = ['enviado' => false, 'mensaje' => null];
            if ($request->boolean('enviar_correo')) {
                $correo = $this->firmas->notificarEnlacePorCorreo($firma, $res['url'], $res['documento'], $res['expira_en']);
            }

            return $this->successResponse([
                'url' => $res['url'],
                'firma_uuid' => $firma->uuid,
                'expira_en' => $res['expira_en'],
                'vigencia_horas' => $res['vigencia_horas'],
                'documento' => $res['documento'],
                'correo' => $correo,
            ], 'Enlace de firma generado.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function crearExpediente(StoreExpedienteRadicacionRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            // Cinturón y tirantes: si la validación dejó pasar un nulo (SUPERADMIN sin
            // contexto), se toma la empresa activa; sin empresa no se crea el trámite.
            $data['company_uuid'] ??= $request->attributes->get('current_company_uuid');
            if (! $data['company_uuid']) {
                return $this->errorResponse('La empresa es obligatoria.', 422);
            }
            $res = $this->radicacion->crearExpediente($data);

            return $this->successResponse($res, 'Expediente de radicación creado.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function expedientes(Request $request): JsonResponse
    {
        try {
            $data = $this->radicacion->listarExpedientes(
                (int) $request->query('per_page', '15'),
                (int) $request->query('page', '1'),
                (string) $request->query('search', ''),
                $request->query('company_uuid')
            );

            return $this->successResponse($data, 'Expedientes de radicación.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function detalle(Request $request, string $uuid): JsonResponse
    {
        try {
            return $this->successResponse($this->radicacion->detalleExpediente($uuid), 'Detalle del expediente con línea de tiempo.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function avanzar(Request $request, string $uuid): JsonResponse
    {
        try {
            return $this->successResponse($this->radicacion->avanzarPaso($uuid), 'Paso avanzado.', 200);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function generarTxt(GenerarTxtRequest $request, string $uuid): JsonResponse
    {
        try {
            $origin = $request->validated()['origin'];
            $hijo = $this->radicacion->findByUuid($uuid);
            $val = $this->radicacion->validarRequisitos($hijo);
            if (! $val['ok']) {
                return $this->errorResponse($val['mensaje'], 422);
            }
            $lote = $this->radicacion->generarTxt($hijo, $origin);

            return $this->successResponse($lote, 'TXT RUNT generado.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function documento(Request $request, string $uuid, string $documento): Response|JsonResponse
    {
        try {
            $descargar = $request->boolean('descargar');

            return $this->radicacionDocumentos->generar($uuid, $documento, $descargar);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Datos públicos de la firma, para que el afiliado sepa qué va a firmar.
     */
    public function showPublic(string $token): JsonResponse
    {
        try {
            $firma = $this->firmas->findByToken($token);
            $contexto = $this->firmas->contextoDe($firma);

            $expediente = $contexto['procedimiento_uuid']
                ? $this->radicacion->findByUuid($contexto['procedimiento_uuid'])
                : null;

            // El mismo enlace sirve para ver el PDF antes de firmar y para descargarlo ya firmado.
            // La firma debe ser relativa porque la ruta usa el middleware `signed:relative`.
            $vigencia = $firma->expires_at->isPast() ? now() : $firma->expires_at;

            return $this->successResponse([
                'firmante' => $firma->signer_name,
                'documento' => $firma->signer_document,
                'contrato' => $contexto['documento'],
                'numero_contrato' => $contexto['numero'],
                'estado' => $firma->status,
                'expirado' => $firma->expires_at->isPast(),
                'expira_en' => $firma->expires_at->toIso8601String(),
                'vehiculo' => $expediente?->vehicle?->vehicle_license_plate,
                'empresa' => $expediente?->company?->business_name,
                'url_documento' => $contexto['documento']
                    ? $this->urlPublicaDocumento('api.v1.public.contracts.documento', $token, $vigencia)
                    : null,
                'url_documento_descarga' => $contexto['documento']
                    ? $this->urlPublicaDocumento('api.v1.public.contracts.documento-descarga', $token, $vigencia)
                    : null,
            ], 'Detalle de firma.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
 * URL absoluta y firmable del PDF del contrato.
 *
 * La ruta usa `signed:relative`, así que la firma debe calcularse sobre el path;
 * pero el firmante abre el enlace desde otro origen, por lo que hay que anteponer
 * la URL de la API.
 */
private function urlPublicaDocumento(string $ruta, string $token, \Illuminate\Support\Carbon $vigencia): string
{
    $relativa = URL::temporarySignedRoute(
        $ruta,
        $vigencia,
        ['token' => $token],
        false
    );

    return rtrim((string) config('app.url'), '/').$relativa;
}

/**
     * PDF del contrato para el firmante: puede verlo antes de firmar y descargarlo
     * firmado después. El acceso se valida con la misma firma temporal del enlace.
     */
    public function documentoPublico(Request $request, string $token): Response|JsonResponse
    {
        try {
            $firma = $this->firmas->findByToken($token);
            $contexto = $this->firmas->contextoDe($firma);

            if (! $contexto['procedimiento_uuid'] || ! $contexto['documento']) {
                abort(422, 'No se encontró el contrato asociado a este enlace.');
            }

            // La ruta terminada en /descargar fuerza la descarga; en la de solo
            // ver se muestra en el navegador. El parámetro no puede viajar en la
            // URL porque `signed` lo incluiría en el cálculo de la firma.
            $descargar = $request->routeIs('api.v1.public.contracts.documento-descarga')
                || $request->boolean('descargar');

            return $this->radicacionDocumentos->generar(
                $contexto['procedimiento_uuid'],
                $contexto['documento'],
                $descargar
            );
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function signPublic(Request $request, string $token): JsonResponse
    {
        try {
            $firma = $this->firmas->firmarPorToken($token, $request->only(['signature_data', 'document_hash']) + ['ip' => $request->ip()]);

            return $this->successResponse($firma, 'Contrato firmado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
