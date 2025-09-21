<?php
namespace App\Notifications;

use App\Channels\SmsMessage;
use App\Utilities\Overrider;
use App\Models\EmailTemplate;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OverdueLoanPayment extends Notification {
    use Queueable;

    private $loanPayment;
    private $template;
    private $replace = [];
    private $tenant;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($loanPayment, $tenantId) {
        Overrider::load("Settings");
        $this->loanPayment = $loanPayment;
        $this->template    = EmailTemplate::where('slug', 'OVERDUE_LOAN_PAYMENT')
            ->where('tenant_id', $tenantId)
            ->first();
        $this->tenant      = Tenant::find($tenantId);

        $this->replace['name']        = $this->loanPayment->loan->borrower->name;
        $this->replace['dueAmount']   = decimalPlace($this->loanPayment->total_due, currency($loanPayment->loan->currency->name));
        $this->replace['numberOfDue'] = $this->loanPayment->total_due_repayment;
        $this->replace['loanID']      = $loanPayment->loan->loan_id;
        $this->replace['dueDate']     = $loanPayment->repayment_date;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable) {
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
    public function toMail($notifiable) {
        $message = processShortCode($this->template->email_body, $this->replace);

        return (new MailMessage)
            ->subject($this->template->subject)
            ->markdown('email.notification', ['message' => $message, 'tenant' => $this->tenant]);
    }

    /**
     * Get the sms representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toSMS($notifiable) {
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
    public function toArray($notifiable) {
        $message = processShortCode($this->template->notification_body, $this->replace);
        return ['subject' => $this->template->subject, 'message' => $message];
    }
}
