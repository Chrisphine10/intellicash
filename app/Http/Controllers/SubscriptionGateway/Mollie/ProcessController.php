<?php
namespace App\Http\Controllers\SubscriptionGateway\Mollie;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\SubscriptionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;

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

        $gateway = PaymentGateway::where('slug', $slug)->first();
        $package = $user->tenant->package;

        $data = [];

        $mollie = new MollieApiClient();
        $mollie->setApiKey($gateway->parameters->api_key);

        try {
            $payment = $mollie->payments->create([
                'amount'      => [
                    'currency' => get_option('currency', 'USD'),
                    'value'    => '' . sprintf('%0.2f', round($package->cost - ($package->discount / 100) * $package->cost, 2)) . '',
                ],
                'description' => get_option('company_name') . ' ' . _lang('Subscription'),
                'redirectUrl' => route('subscription_callback.' . $slug),
                'metadata'    => [
                    "invoice_id" => uniqid(),
                ],
            ]);
        } catch (ApiException $e) {
            $data['error']         = true;
            $data['error_message'] = $e->getPlainMessage();
            return json_encode($data);
        }

        session()->put('payment_id', $payment->id);
        session()->put('user_id', $user->id);
        session()->put('slug', $slug);

        $data['redirect']     = true;
        $data['redirect_url'] = $payment->getCheckoutUrl();

        return json_encode($data);
    }

    /**
     * Callback function from Payment Gateway
     *
     * @return \Illuminate\Http\Response
     */
    public function callback(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $payment_id = session()->get('payment_id');
        $user_id    = session()->get('user_id');
        $slug       = session()->get('slug');

        $user    = User::find($user_id);
        $package = $user->tenant->package;

        $gateway = PaymentGateway::where('slug', $slug)->first();

        $mollie = new MollieApiClient();
        $mollie->setApiKey($gateway->parameters->api_key);
        $payment = $mollie->payments->get($payment_id);

        if ($payment->isPaid()) {
            $amount = $payment->amount->value;

            //Update Membership
            $packageAmount = $package->cost - ($package->discount / 100) * $package->cost;

            if ($packageAmount <= $amount) {
                DB::beginTransaction();

                $subscriptionpayment                  = new SubscriptionPayment();
                $subscriptionpayment->tenant_id       = $user->tenant->id;
                $subscriptionpayment->order_id        = $payment->id;
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
        } else {
            return redirect()->route('membership.payment_gateways')->with('error', _lang('Sorry, Payment not completed !'));
        }
    }

}