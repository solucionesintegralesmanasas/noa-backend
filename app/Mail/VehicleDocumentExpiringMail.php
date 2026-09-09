<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VehicleDocumentExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public mixed $document,
        public string $milestone
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $docType = $this->document->document_type ?? 'Documento';
        $plate = $this->document->vehicle->vehicle_license_plate ?? 'N/A';

        return new Envelope(
            subject: "Alerta de Vencimiento: {$docType} - {$plate}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.vehicle-documents.expiring',
            with: [
                'document' => $this->document,
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
