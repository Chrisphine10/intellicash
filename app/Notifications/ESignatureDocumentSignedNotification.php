<?php

namespace App\Notifications;

use App\Channels\SmsMessage;
use App\Models\EmailTemplate;
use App\Models\ESignatureDocument;
use App\Models\ESignatureSignature;
use App\Utilities\Overrider;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ESignatureDocumentSignedNotification extends Notification
{
    use Queueable;

    private $document;
    private $signature;
    private $template;
    private $replace = [];

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(ESignatureDocument $document, ESignatureSignature $signature)
    {
        $this->document = $document;
        $this->signature = $signature;
        $this->template = EmailTemplate::where('slug', 'ESIGNATURE_DOCUMENT_SIGNED')
            ->where('tenant_id', request()->tenant->id)
            ->first();

        Overrider::load("Settings");

        // Set up replacement variables
        $this->replace['signerName'] = $signature->signer_name;
        $this->replace['signerEmail'] = $signature->signer_email;
        $this->replace['documentTitle'] = $document->title;
        $this->replace['documentType'] = ucfirst($document->document_type);
        $this->replace['senderName'] = $document->sender_name;
        $this->replace['senderCompany'] = $document->sender_company;
        $this->replace['signedAt'] = $signature->signed_at ? $signature->signed_at->format('M d, Y \a\t g:i A') : now()->format('M d, Y \a\t g:i A');
        $this->replace['documentUrl'] = route('esignature.esignature-documents.show', $document->id);
        $this->replace['downloadUrl'] = route('esignature.esignature-documents.download', $document->id);
        $this->replace['loginUrl'] = route('login');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $channels = [];
        
        if ($this->template != null && $this->template->email_status == 1) {
            array_push($channels, 'mail');
        }
        if ($this->template != null && $this->template->sms_status == 1) {
            array_push($channels, \App\Channels\SMS::class);
        }
        if ($this->template != null && $this->template->notification_status == 1) {
            array_push($channels, 'database');
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $message = processShortCode($this->template->email_body, $this->replace);

        return (new MailMessage)
            ->subject($this->template->subject)
            ->markdown('email.notification', ['message' => $message]);
    }

    /**
     * Get the sms representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \App\Channels\SmsMessage
     */
    public function toSMS($notifiable)
    {
        $message = processShortCode($this->template->sms_body, $this->replace);

        return (new SmsMessage())
            ->setContent($message)
            ->setRecipient($notifiable->country_code . $notifiable->mobile);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        $message = processShortCode($this->template->notification_body, $this->replace);
        return [
            'subject' => $this->template->subject,
            'message' => $message,
            'document_id' => $this->document->id,
            'signature_id' => $this->signature->id,
            'type' => 'esignature_document_signed'
        ];
    }
}
