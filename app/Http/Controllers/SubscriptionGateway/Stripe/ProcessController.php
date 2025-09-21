<?php
namespace App\Http\Controllers\SubscriptionGateway\Stripe;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\SubscriptionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Charge;
use Stripe\Stripe;

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
        $data                 = [];
        $data['callback_url'] = route('subscription_callback.' . $slug);
        $data['user_id']      = $user->id;
        $data['view']         = 'membership.gateway.' . $slug;

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

        $user    = User::find($request->user_id);
        $package = $user->tenant->package;

        $gateway = PaymentGateway::where('slug', $request->slug)->first();

        Stripe::setApiKey($gateway->parameters->secret_key);

        $charge = Charge::create([
            "amount"      => round($package->cost - ($package->discount / 100) * $package->cost) * 100,
            "currency"    => get_option('currency', 'USD'),
            "source"      => $request->stripeToken,
            "description" => get_option('company_name') . ' ' . _lang('Subscription'),
        ]);

        if ($charge->amount_refunded == 0 && $charge->failure_code == null && $charge->paid == true && $charge->captured == true) {

            $amount = $charge->amount / 100;

            //Update Membership
            $packageAmount = $package->cost - ($package->discount / 100) * $package->cost;

            if ($packageAmount <= $amount) {
                DB::beginTransaction();

                $subscriptionpayment                  = new SubscriptionPayment();
                $subscriptionpayment->tenant_id       = $user->tenant->id;
                $subscriptionpayment->order_id        = $charge->id;
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