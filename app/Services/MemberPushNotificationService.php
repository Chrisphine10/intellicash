<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MemberPushNotificationService
{
    /**
     * Send push notification to member
     */
    public function sendToMember($memberId, $title, $body, $data = [])
    {
        try {
            $member = \App\Models\Member::find($memberId);
            if (!$member || !$member->user) {
                return false;
            }

            // Get member's push subscription
            $subscription = $this->getMemberSubscription($member->user->id);
            if (!$subscription) {
                Log::info("No push subscription found for member {$memberId}");
                return false;
            }

            $payload = [
                'title' => $title,
                'body' => $body,
                'icon' => '/public/uploads/media/pwa-icon-192x192.png',
                'badge' => '/public/uploads/media/pwa-icon-72x72.png',
                'data' => array_merge($data, [
                    'member_id' => $memberId,
                    'timestamp' => now()->toISOString(),
                    'url' => '/dashboard?mobile=1'
                ]),
                'actions' => [
                    [
                        'action' => 'view',
                        'title' => 'View Details',
                        'icon' => '/public/uploads/media/pwa-icon-72x72.png'
                    ],
                    [
                        'action' => 'dismiss',
                        'title' => 'Dismiss',
                        'icon' => '/public/uploads/media/pwa-icon-72x72.png'
                    ]
                ],
                'vibrate' => [100, 50, 100],
                'requireInteraction' => true,
                'tag' => 'member-notification-' . $memberId
            ];

            return $this->sendPushNotification($subscription, $payload);
        } catch (\Exception $e) {
            Log::error("Failed to send push notification to member {$memberId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send transaction notification
     */
    public function sendTransactionNotification($memberId, $transaction)
    {
        $title = $transaction->type === 'credit' ? 'Deposit Received' : 'Payment Processed';
        $body = $transaction->type === 'credit' 
            ? "You received " . decimalPlace($transaction->amount, currency()) . " in your account"
            : "Payment of " . decimalPlace($transaction->amount, currency()) . " has been processed";

        return $this->sendToMember($memberId, $title, $body, [
            'type' => 'transaction',
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount,
            'url' => '/transactions/index?mobile=1'
        ]);
    }

    /**
     * Send loan payment reminder
     */
    public function sendLoanPaymentReminder($memberId, $loan, $payment)
    {
        $title = 'Loan Payment Due';
        $body = "Payment of " . decimalPlace($payment->amount_to_pay, currency()) . " is due on " . $payment->repayment_date;

        return $this->sendToMember($memberId, $title, $body, [
            'type' => 'loan_payment',
            'loan_id' => $loan->id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount_to_pay,
            'due_date' => $payment->repayment_date,
            'url' => '/loans/my_loans?mobile=1'
        ]);
    }

    /**
     * Send account balance update
     */
    public function sendBalanceUpdate($memberId, $account, $oldBalance, $newBalance)
    {
        $title = 'Account Balance Updated';
        $body = "Your {$account->savings_type->name} balance is now " . decimalPlace($newBalance, currency($account->savings_type->currency->name));

        return $this->sendToMember($memberId, $title, $body, [
            'type' => 'balance_update',
            'account_id' => $account->id,
            'old_balance' => $oldBalance,
            'new_balance' => $newBalance,
            'url' => '/dashboard?mobile=1'
        ]);
    }

    /**
     * Send loan approval notification
     */
    public function sendLoanApproval($memberId, $loan)
    {
        $title = 'Loan Approved';
        $body = "Your loan application for " . decimalPlace($loan->applied_amount, currency()) . " has been approved";

        return $this->sendToMember($memberId, $title, $body, [
            'type' => 'loan_approval',
            'loan_id' => $loan->id,
            'amount' => $loan->applied_amount,
            'url' => '/loans/my_loans?mobile=1'
        ]);
    }

    /**
     * Send general notification
     */
    public function sendGeneralNotification($memberId, $title, $body, $url = '/dashboard?mobile=1')
    {
        return $this->sendToMember($memberId, $title, $body, [
            'type' => 'general',
            'url' => $url
        ]);
    }

    /**
     * Get member's push subscription
     */
    private function getMemberSubscription($userId)
    {
        return \App\Models\PushSubscription::where('user_id', $userId)
            ->where('active', true)
            ->first();
    }

    /**
     * Send push notification using Web Push
     */
    private function sendPushNotification($subscription, $payload)
    {
        try {
            $endpoint = $subscription->endpoint;
            $p256dh = $subscription->p256dh;
            $auth = $subscription->auth;

            // Use a web push service (like web-push-php library)
            // For now, we'll simulate the notification
            Log::info("Sending push notification to endpoint: {$endpoint}");
            Log::info("Payload: " . json_encode($payload));

            // In a real implementation, you would use a library like:
            // \Minishlink\WebPush\WebPush::send($notification, $subscription);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send push notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Register member for push notifications
     */
    public function registerMember($userId, $subscriptionData)
    {
        try {
            \App\Models\PushSubscription::updateOrCreate(
                [
                    'user_id' => $userId,
                    'endpoint' => $subscriptionData['endpoint']
                ],
                [
                    'p256dh' => $subscriptionData['keys']['p256dh'],
                    'auth' => $subscriptionData['keys']['auth'],
                    'active' => true,
                    'updated_at' => now()
                ]
            );

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to register push subscription: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unregister member from push notifications
     */
    public function unregisterMember($userId, $endpoint = null)
    {
        try {
            $query = \App\Models\PushSubscription::where('user_id', $userId);
            
            if ($endpoint) {
                $query->where('endpoint', $endpoint);
            }
            
            $query->update(['active' => false]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to unregister push subscription: " . $e->getMessage());
            return false;
        }
    }
}
