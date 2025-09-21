<?php
namespace App\Http\Controllers\SubscriptionGateway\PayPal;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\PaymentGateway;
use App\Utilities\PaypalService;
use Illuminate\Support\Facades\DB;
use App\Models\SubscriptionPayment;
use App\Http\Controllers\Controller;
use App\Notifications\SubscriptionNotification;

class ProcessController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
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
    public static function process($user, $slug) {
        $data = [];

        $gateway = PaymentGateway::where('slug', $slug)->first();
        $package = $user->tenant->package;

        $clientId     = $gateway->parameters->client_id;
        $clientSecret = $gateway->parameters->client_secret;
        $env          = $gateway->parameters->environment;

        $paypalService = new PaypalService($clientId, $clientSecret, $env);

        $amount      = ($package->cost - ($package->discount / 100) * $package->cost);
        $currency    = get_option('currency', 'USD');
        $returnUrl   = route('subscription_callback.' . $slug) . '?type=return&user_id=' . $user->id . '&slug=' . $slug;
        $cancelUrl   = route('subscription_callback.' . $slug) . '?type=cancel&user_id=' . $user->id;
        $description = get_option('company_name') . ' ' . _lang('Subscription');

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
     **/
    public function callback(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        if ($request->type == 'cancel') {
            return redirect()->route('membership.payment_gateways')->with('error', _lang('Payment Cancelled'));
        }

        $user    = User::find($request->user_id);
        $package = $user->tenant->package;

        $gateway = PaymentGateway::where('slug', $request->slug)->first();

        $clientId     = $gateway->parameters->client_id;
        $clientSecret = $gateway->parameters->client_secret;
        $env          = $gateway->parameters->environment;

        $paypalService = new PaypalService($clientId, $clientSecret, $env);

        try {
            $paymentId = $request->paymentId;
            $payerId   = $request->PayerID;

            $payment = $paypalService->executePayment($paymentId, $payerId);

            // Check if payment was successful
            if (! isset($payment['state']) || $payment['state'] !== 'approved') {
                return redirect()->route('membership.payment_gateways')->with('error', _lang('Payment was not approved.'));
            }

            // Extract payment details
            $transaction = $payment['transactions'][0] ?? null;
            if (! $transaction) { 
                return redirect()->route('membership.payment_gateways')->with('error', _lang('Transaction details missing.'));
            }

            $amount       = $transaction['amount']['total'] ?? 0;

            //Update Membership
            $packageAmount = $package->cost - ($package->discount / 100) * $package->cost;

            if (bccomp((string)$packageAmount, (string)$amount, 2) <= 0) {
                DB::beginTransaction();

                $subscriptionpayment                  = new SubscriptionPayment();
                $subscriptionpayment->tenant_id       = $user->tenant->id;
                $subscriptionpayment->order_id        = $paymentId;
                $subscriptionpayment->payment_method  = $gateway->name;
                $subscriptionpayment->package_id      = $package->id;
                $subscriptionpayment->amount          = $amount;
                $subscriptionpayment->status          = 1;
                $subscriptionpayment->created_user_id = $user->id;

                $subscriptionpayment->save();

                $tenant                    = $user->tenant;
                $tenant->membership_type   = 'member';
                $tenant->subscription_date = now();
                $tenant->valid_to          = update_membership_date($package, $tenant->getRawOriginal('subscription_date'));
                $tenant->s_email_send_at   = null;
                $tenant->save();

                DB::commit();

                event(new \App\Events\SubscriptionPayment($user, $subscriptionpayment));

                try {
                    $user->notify(new SubscriptionNotification($user));
                } catch (\Exception $e) {}
            }

            return redirect()->route('dashboard.index', ['tenant' => $user->tenant->slug])->with('success', _lang('Payment made successfully'));

        } catch (\Exception $ex) {
            DB::rollBack();
            return redirect()->route('membership.payment_gateways')->with('error', $ex->getMessage());
        }
    }

}