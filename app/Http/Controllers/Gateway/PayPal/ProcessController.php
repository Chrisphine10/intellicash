<?php
namespace App\Http\Controllers\Gateway\PayPal;

use App\Http\Controllers\Controller;
use App\Models\AutomaticGateway;
use App\Models\Transaction;
use App\Notifications\DepositMoney;
use App\Utilities\PaypalService;
use Illuminate\Http\Request;

class ProcessController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');

        date_default_timezone_set(get_timezone());
    }

    /**
     * Process Payment Gateway
     *
     * @return \Illuminate\Http\Response
     */
    public static function process($deposit)
    {
        $data = [];

        $slug    = $deposit->gateway->slug;
        $gateway = AutomaticGateway::where('slug', $slug)->where('tenant_id', $deposit->tenant_id)->first();

        $clientId     = $gateway->parameters->client_id;
        $clientSecret = $gateway->parameters->client_secret;
        $env          = $gateway->parameters->environment;

        $paypalService = new PaypalService($clientId, $clientSecret, $env);

        $amount      = $deposit->gateway_amount;
        $currency    = $deposit->gateway->currency;
        $returnUrl   = route('callback.' . $slug) . '?type=return&deposit_id=' . $deposit->id . '&slug=' . $slug;
        $cancelUrl   = route('callback.' . $slug) . '?type=cancel&deposit_id=' . $deposit->id;
        $description = _lang('Deposit Via PayPal');

        $payment = $paypalService->createPayment($amount, $currency, $returnUrl, $cancelUrl, $description);

        foreach ($payment['links'] as $link) {
            if ($link['rel'] == 'approval_url') {
                $data['redirect_url'] = $link['href'];
            }
        }

        $data['redirect'] = true;
        return json_encode($data);
    }

    /**
     * Callback function from Payment Gateway
     *
     * @return \Illuminate\Http\Response
     */
    public function callback(Request $request)
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        if ($request->type == 'cancel') {
            return redirect()->route('deposit.automatic_methods')->with('error', _lang('Payment Cancelled'));
        }

        $transaction = Transaction::find($request->deposit_id);

        // Creating an environment
        $clientId     = $transaction->gateway->parameters->client_id;
        $clientSecret = $transaction->gateway->parameters->client_secret;
        $env          = $transaction->gateway->parameters->environment;

        $paypalService = new PaypalService($clientId, $clientSecret, $env);

        try {
            $paymentId = $request->paymentId;
            $payerId   = $request->PayerID;

            $payment = $paypalService->executePayment($paymentId, $payerId);

            // Check if payment was successful
            if (! isset($payment['state']) || $payment['state'] !== 'approved') {
                return redirect()->route('deposit.automatic_methods')->with('error', _lang('Payment was not approved.'));
            }

            // Extract payment details
            $paypalTransaction = $payment['transactions'][0] ?? null;
            if (! $paypalTransaction) {
                return redirect()->route('deposit.automatic_methods')->with('error', _lang('Transaction details missing.'));
            }

            $amount = $paypalTransaction['amount']['total'] ?? 0;

            //Update Transaction
            if (bccomp((string) $transaction->gateway_amount, (string) $amount, 2) <= 0) {
                $transaction->status              = 2; // Completed
                $transaction->transaction_details = json_encode($payment);
                $transaction->save();

                try {
                    $transaction->member->notify(new DepositMoney($transaction));
                } catch (\Exception $e) {}

                return redirect()->route('dashboard.index')->with('success', _lang('Money Deposited Successfully'));
            }

        } catch (\Exception $ex) {
            return redirect()->route('deposit.automatic_methods')->with('error', $ex->getMessage());
        }
    }

}
