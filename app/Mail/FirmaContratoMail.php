<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Enlace de firma de un contrato de vinculación o de prestación de servicios.
 * Se envía al afiliado para que revise y firme desde el enlace.
 */
class FirmaContratoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nombre,
        public string $empresa,
        public string $documento,
        public string $url,
        public string $expira,
        public string $rol,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Enlace para firmar el '.$this->rotulo().' — '.$this->empresa);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.radicacion.firma-contrato',
            with: [
                'nombre' => $this->nombre,
                'empresa' => $this->empresa,
                'documento' => $this->documento,
                'url' => $this->url,
                'expira' => $this->expira,
                'rotulo' => $this->rotulo(),
            ],
        );
    }

    private function rotulo(): string
    {
        return $this->rol === 'PROPIETARIO'
            ? 'contrato de vinculación por administración de flota'
            : 'contrato de prestación de servicios';
    }
}