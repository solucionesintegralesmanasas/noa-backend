<?php

declare(strict_types=1);

namespace App\Support\Billing;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

/**
 * Genera el QR de validación DIAN como PNG en base64 para incrustarlo en el PDF.
 */
class QrDian
{
    public static function pngBase64(?string $contenido, int $tamano = 220): ?string
    {
        if ($contenido === null || trim($contenido) === '') {
            return null;
        }

        $writer = new Writer(new GDLibRenderer($tamano, 1));

        return base64_encode($writer->writeString(trim($contenido)));
    }
}
