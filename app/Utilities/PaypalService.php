<?php
namespace App\Utilities;

use Illuminate\Support\Facades\Http;

class PaypalService {
    protected $mode;
    protected $client_id;
    protected $secret;
    protected $base_url;

    public function __construct($client_id, $secret, $mode) {
        $this->client_id = $client_id;
        $this->secret    = $secret;
        $this->mode      = $mode;
        $this->base_url  = $this->mode == 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    // Get PayPal OAuth token
    public function getAccessToken() {
        $response = Http::withBasicAuth($this->client_id, $this->secret)
            ->asForm()
            ->post($this->base_url . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        $body = $response->json();

        // Check if response contains the access_token
        if (! isset($body['access_token'])) {
            throw new \Exception("Failed to obtain access token.");
        }

        return $body['access_token'];
    }

    // Create a PayPal payment
    public function createPayment($amount, $currency = 'USD', $returnUrl, $cancelUrl, $description = 'Payment for Order') {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post($this->base_url . '/v1/payments/payment', [
                'intent'        => 'sale',
                'payer'         => [
                    'payment_method' => 'paypal',
                ],
                'transactions'  => [
                    [
                        'amount'      => [
                            'total'    => $amount,
                            'currency' => $currency,
                        ],
                        'description' => $description,
                    ],
                ],
                'redirect_urls' => [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ]);

        $body = $response->json();

        if (! isset($body['links'])) {
            throw new \Exception("Payment creation failed.");
        }

        return $body;
    }

    // Execute the payment after user approval
    public function executePayment($paymentId, $payerId) {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post("{$this->base_url}/v1/payments/payment/{$paymentId}/execute", [
                'payer_id' => $payerId,
            ]);

        $body = $response->json();

        if (! isset($body['state']) || $body['state'] != 'approved') {
            throw new \Exception("Payment execution failed.");
        }

        return $body;
    }
}
