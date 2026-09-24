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
     * Create a new message instance.
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
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->asunto,
        );
    }

    /**
     * Get the message content definition.
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
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
