<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Digest consolidado de documentos próximos a vencer o vencidos.
 * Lista en un solo correo los vehículos, documentos y fechas de vencimiento.
 */
class VehicleExpiryDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Crea una nueva instancia del mensaje.
     *
     * @param  array<int, array{placa: string, documento: string, fecha: string, days_left: int, estado: string}>  $items
     */
    public function __construct(
        public string $companyName,
        public array $items,
        public string $asunto,
        public string $intro
    ) {}

    /**
     * Obtiene la envoltura del mensaje.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->asunto,
        );
    }

    /**
     * Obtiene la definición del contenido del mensaje.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.vehicle-documents.digest',
            with: [
                'companyName' => $this->companyName,
                'items' => $this->items,
                'intro' => $this->intro,
            ],
        );
    }

    /**
     * Obtiene los adjuntos del mensaje.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
