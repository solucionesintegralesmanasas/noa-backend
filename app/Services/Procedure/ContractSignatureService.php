<?php

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Mail\FirmaContratoMail;
use App\Models\Company;
use App\Models\ContractSignature;
use App\Models\FleetServiceContract;
use App\Models\ServiceProvisionContract;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ContractSignatureService extends BaseService
{
    /** Vigencia del enlace de firma. */
    public const VIGENCIA_HORAS = 72;

    protected function getModelInstance(): Model
    {
        return new ContractSignature;
    }

    /**
     * Crea un enlace de un solo uso para que el afiliado firme un contrato.
     *
     * Devuelve la URL del frontend (no la de la API) para que el afiliado abra
     * directamente la pantalla de firma desde el enlace que recibe.
     */
    public function crearEnlace(array $data): array
    {
        $expira = now()->addHours(self::VIGENCIA_HORAS);
        $token = Str::random(64);

        $firma = $this->transaction(fn () => ContractSignature::create([
            'company_uuid' => $data['company_uuid'],
            'contract_origin' => $data['contract_origin'],
            'contract_uuid' => $data['contract_uuid'],
            'signer_role' => $data['signer_role'],
            'signer_name' => $data['signer_name'],
            'signer_document' => $data['signer_document'],
            'signer_email' => $data['signer_email'] ?? null,
            'signer_phone' => $data['signer_phone'] ?? null,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expira,
            'status' => 'PENDIENTE',
        ]));

        $contexto = $this->contextoDe($firma);

        return [
            'firma' => $firma,
            'token' => $token,
            'url' => $this->urlFirma($token, $expira),
            'expira_en' => $expira->toIso8601String(),
            'vigencia_horas' => self::VIGENCIA_HORAS,
            'documento' => $contexto['documento'],
            'procedimiento_uuid' => $contexto['procedimiento_uuid'],
        ];
    }

    /**
     * Enlace del frontend con la firma temporal de la ruta pública de la API,
     * que es la que valida realmente el acceso.
     */
    private function urlFirma(string $token, \Illuminate\Support\Carbon $expira): string
    {
        $apiUrl = URL::temporarySignedRoute(
            'api.v1.public.contracts.sign-show',
            $expira,
            ['token' => $token],
            false
        );

        parse_str(parse_url($apiUrl, PHP_URL_QUERY) ?: '', $query);

        $base = rtrim((string) (config('app.frontend_url') ?: url('/')), '/');

        return $base.'/#/firmar-contrato/'.$token.'?'.http_build_query($query);
    }

    /**
     * Resuelve el expediente y el documento PDF asociados a la firma.
     *
     * @return array{documento: ?string, procedimiento_uuid: ?string, numero: ?string}
     */
    public function contextoDe(ContractSignature $firma): array
    {
        $contrato = $firma->contract_origin === 'ADMIN_FLOTA'
            ? FleetServiceContract::where('uuid', $firma->contract_uuid)->first()
            : ServiceProvisionContract::where('uuid', $firma->contract_uuid)->first();

        $paso = $contrato?->procedure_uuid
            ? \App\Models\Procedure::where('uuid', $contrato->procedure_uuid)->first()
            : null;

        return [
            'documento' => RadicacionDocumentoService::documentoPara($firma->contract_origin),
            'procedimiento_uuid' => $paso?->parent_procedure_uuid ?: null,
            'numero' => $contrato?->contract_number,
        ];
    }

    /**
     * Resuelve la empresa asociada a la firma, ya sea la del contrato o, si no
     * se puede determinar, la de la firma misma.
     */
    public function empresaDelFirma(ContractSignature $firma): ?Company
    {
        $contrato = $firma->contract_origin === 'ADMIN_FLOTA'
            ? FleetServiceContract::where('uuid', $firma->contract_uuid)->first()
            : ServiceProvisionContract::where('uuid', $firma->contract_uuid)->first();

        return $contrato?->company ?? Company::where('uuid', $firma->company_uuid)->first();
    }

    /**
     * Envía el enlace de firma al correo del firmante, referenciando el PDF
     * principal del contrato. Si falla, el enlace sigue siendo válido.
     *
     * @return array{enviado: bool, mensaje: string|null}
     */
    public function notificarEnlacePorCorreo(ContractSignature $firma, string $url, string $documento, string $expiraEn): array
    {
        return $this->enviarPorCorreo($firma, $url, [
            'nombre_firmante' => $firma->signer_name,
            'empresa' => $this->empresaDelFirma($firma)?->business_name ?? 'Transportadora',
            'documento' => $documento,
            'expira' => \Carbon\Carbon::parse($expiraEn)->translatedFormat('d/m/Y \a\l\a\s H:i'),
            'rol' => $firma->signer_role,
        ]);
    }

    /**
     * Busca una firma por el token en claro que llegó en la URL.
     */
    public function findByToken(string $token): ContractSignature
    {
        return ContractSignature::where('token_hash', hash('sha256', $token))->firstOrFail();
    }

    /**
     * Envía el enlace de firma al correo del firmante, si tiene uno registrado.
     *
     * @return array{enviado: bool, mensaje: string}
     */
    public function enviarPorCorreo(ContractSignature $firma, string $url, array $datos): array
    {
        $correo = $firma->signer_email ?: null;
        if (blank($correo)) {
            return ['enviado' => false, 'mensaje' => 'El firmante no tiene correo registrado.'];
        }

        try {
            Mail::to($correo)->send(new FirmaContratoMail(
                nombre: $datos['nombre_firmante'],
                empresa: $datos['empresa'],
                documento: $datos['documento'],
                url: $url,
                expira: $datos['expira'],
                rol: $datos['rol'],
            ));
        } catch (\Throwable $e) {
            // El correo es un extra: si el SMTP falla, el enlace sigue siendo válido.
            Log::warning('No se pudo enviar el enlace de firma por correo', [
                'firma_uuid' => $firma->uuid,
                'correo' => $correo,
                'error' => $e->getMessage(),
            ]);

            return ['enviado' => false, 'mensaje' => 'No se pudo enviar el correo: '.$e->getMessage()];
        }

        return ['enviado' => true, 'mensaje' => 'Enlace enviado a '.$correo.'.'];
    }

    public function firmarPorToken(string $token, array $evidencia): ContractSignature
    {
        $firma = $this->findByToken($token);
        abort_if($firma->status !== 'PENDIENTE', 422, 'Enlace ya usado o revocado.');
        abort_if($firma->expires_at->isPast(), 422, 'Enlace expirado.');

        return $this->transaction(function () use ($firma, $evidencia) {
            $firma->update([
                'signed_at' => now(),
                'ip_address' => $evidencia['ip'] ?? request()->ip(),
                'signature_data' => $evidencia['signature_data'] ?? null,
                'document_hash' => $evidencia['document_hash'] ?? null,
                'status' => 'FIRMADO',
            ]);

            return $firma->fresh();
        });
    }

    /**
     * Busca la firma pendiente más reciente de un contrato para un rol dado.
     */
    public function pendienteDe(string $contractUuid, string $origen, string $rol): ?ContractSignature
    {
        return ContractSignature::where('contract_uuid', $contractUuid)
            ->where('contract_origin', $origen)
            ->where('signer_role', $rol)
            ->where('status', 'PENDIENTE')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Normaliza el valor de una firma para las plantillas PDF: la firma tomada en
     * pantalla llega como imagen base64 y la tomada por enlace como nombre escrito.
     *
     * @return array{imagen: ?string, texto: ?string}
     */
    public static function firmaParaPdf(?string $valor): array
    {
        if (blank($valor)) {
            return ['imagen' => null, 'texto' => null];
        }

        $esImagen = str_starts_with(trim($valor), 'data:image');

        return $esImagen
            ? ['imagen' => $valor, 'texto' => null]
            : ['imagen' => null, 'texto' => $valor];
    }
}