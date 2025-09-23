<?php

namespace App\Mail;

use App\Models\ESignatureDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ESignatureDocumentSigned extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $document;

    /**
     * Create a new message instance.
     */
    public function __construct(ESignatureDocument $document)
    {
        $this->document = $document;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = "Document Signed - {$this->document->title}";

        return $this->subject($subject)
            ->view('emails.esignature.document-signed')
            ->with([
                'document' => $this->document,
                'completedAt' => $this->document->completed_at,
                'signerCount' => $this->document->getSignerCount(),
                'downloadUrl' => route('esignature.documents.download-signed', $this->document->id),
            ]);
    }
}
