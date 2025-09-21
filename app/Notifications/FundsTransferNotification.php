<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FundsTransferNotification extends Notification
{
    use Queueable;

    protected $transaction;
    protected $type;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Transaction $transaction, $type = 'completed')
    {
        $this->transaction = $transaction;
        $this->type = $type;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $subject = $this->type === 'completed' 
            ? 'Funds Transfer Completed Successfully' 
            : 'Funds Transfer Failed';

        $message = $this->type === 'completed'
            ? 'Your funds transfer has been completed successfully.'
            : 'Your funds transfer has failed. Please try again or contact support.';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($message)
            ->line('Transfer Details:')
            ->line('Amount: ' . decimalPlace($this->transaction->amount, currency('KES')))
            ->line('Description: ' . $this->transaction->description)
            ->line('Date: ' . $this->transaction->trans_date)
            ->action('View Transaction', route('funds_transfer.history'))
            ->line('Thank you for using IntelliCash!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'title' => $this->type === 'completed' 
                ? 'Funds Transfer Completed' 
                : 'Funds Transfer Failed',
            'message' => $this->type === 'completed'
                ? 'Your funds transfer of ' . decimalPlace($this->transaction->amount, currency('KES')) . ' has been completed successfully.'
                : 'Your funds transfer of ' . decimalPlace($this->transaction->amount, currency('KES')) . ' has failed.',
            'type' => $this->type === 'completed' ? 'success' : 'error',
            'transaction_id' => $this->transaction->id,
            'amount' => $this->transaction->amount,
            'description' => $this->transaction->description,
            'date' => $this->transaction->trans_date,
        ];
    }
}
