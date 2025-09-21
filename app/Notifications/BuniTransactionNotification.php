<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SmsMessage;
use Illuminate\Notifications\Notification;

class BuniTransactionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $transaction;
    protected $type; // 'deposit' or 'withdraw'

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($transaction, $type = 'deposit')
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
        $channels = ['database'];
        
        // Add SMS if mobile number is available
        if ($notifiable->mobile) {
            $channels[] = 'sms';
        }
        
        // Add email if email is available
        if ($notifiable->email) {
            $channels[] = 'mail';
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
        $subject = $this->type === 'deposit' ? 'Deposit Received via KCB Buni' : 'Withdrawal Sent via KCB Buni';
        $greeting = $this->type === 'deposit' ? 'Deposit Confirmed!' : 'Withdrawal Processed!';
        
        $message = new MailMessage;
        $message->subject($subject)
                ->greeting('Hello ' . $notifiable->first_name . '!')
                ->line($greeting)
                ->line('Transaction Details:')
                ->line('Amount: ' . decimalPlace($this->transaction->amount, currency($this->transaction->account->savings_type->currency->name)))
                ->line('Date: ' . $this->transaction->trans_date->format('Y-m-d H:i:s'))
                ->line('Reference: ' . $this->transaction->id);
                
        if ($this->type === 'deposit') {
            $message->line('Your account has been credited successfully.');
        } else {
            $message->line('The money has been sent to your mobile phone number.');
        }
        
        $message->action('View Transaction', route('dashboard.index'))
                ->line('Thank you for using IntelliCash!');
                
        return $message;
    }

    /**
     * Get the SMS representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\SmsMessage
     */
    public function toSms($notifiable)
    {
        $amount = decimalPlace($this->transaction->amount, currency($this->transaction->account->savings_type->currency->name));
        
        if ($this->type === 'deposit') {
            $message = "IntelliCash: Deposit of {$amount} received via KCB Buni. Ref: {$this->transaction->id}. Your account has been credited.";
        } else {
            $message = "IntelliCash: Withdrawal of {$amount} sent to your mobile via KCB Buni. Ref: {$this->transaction->id}. Check your phone for confirmation.";
        }
        
        return new SmsMessage($message);
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
            'transaction_id' => $this->transaction->id,
            'type' => $this->type,
            'amount' => $this->transaction->amount,
            'currency' => $this->transaction->account->savings_type->currency->name,
            'date' => $this->transaction->trans_date,
            'status' => $this->transaction->status,
            'message' => $this->type === 'deposit' 
                ? 'Deposit received via KCB Buni' 
                : 'Withdrawal sent via KCB Buni'
        ];
    }
}
