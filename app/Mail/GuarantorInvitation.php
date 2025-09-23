<?php

namespace App\Mail;

use App\Models\GuarantorRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuarantorInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $guarantorRequest;

    /**
     * Create a new message instance.
     */
    public function __construct(GuarantorRequest $guarantorRequest)
    {
        $this->guarantorRequest = $guarantorRequest;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Guarantor Invitation - Loan Application',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.guarantor-invitation',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
