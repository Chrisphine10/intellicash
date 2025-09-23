<?php

namespace App\Mail;

use App\Models\ESignatureDocument;
use App\Models\ESignatureSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ESignatureDocumentSent extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $document;
    public $signature;

    /**
     * Create a new message instance.
     */
    public function __construct(ESignatureDocument $document, ESignatureSignature $signature)
    {
        $this->document = $document;
        $this->signature = $signature;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = $this->document->sender_company 
            ? "Document for Signature - {$this->document->sender_company}"
            : "Document for Signature";

        return $this->subject($subject)
            ->view('emails.esignature.document-sent')
            ->with([
                'document' => $this->document,
                'signature' => $this->signature,
                'signingUrl' => $this->signature->getSignatureUrl(),
                'expiresAt' => $this->signature->expires_at,
                'senderName' => $this->document->sender_name,
                'senderCompany' => $this->document->sender_company,
                'customMessage' => $this->document->custom_message,
            ]);
    }
}
