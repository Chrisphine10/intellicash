<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UtilitySeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        //Default Settings
        DB::table('settings')->insert([
            [
                'name'  => 'mail_type',
                'value' => 'sendmail',
            ],
            [
                'name'  => 'backend_direction',
                'value' => 'ltr',
            ],
            [
                'name'  => 'email_verification',
                'value' => 0,
            ],
            [
                'name'  => 'language',
                'value' => 'English---us',
            ],
            [
                'name'  => 'currency',
                'value' => 'KES',
            ],
            [
                'name'  => 'member_signup',
                'value' => 1,
            ],
            [
                'name'  => 'timezone',
                'value' => 'Africa/Nairobi',
            ],
        ]);

        //Payment Gateways
        $paymentGateways = [
            [
                'name'       => 'PayPal',
                'slug'       => 'PayPal',
                'image'      => 'paypal.png',
                'status'     => 0,
                'is_crypto'  => 0,
                'parameters' => '{"client_id":"","client_secret":"","environment":"sandbox"}',
            ],
            [
                'name'       => 'Stripe',
                'slug'       => 'Stripe',
                'image'      => 'stripe.png',
                'status'     => 0,
                'is_crypto'  => 0,
                'parameters' => '{"secret_key":"","publishable_key":""}',
            ],
            [
                'name'       => 'Razorpay',
                'slug'       => 'Razorpay',
                'image'      => 'razorpay.png',
                'status'     => 0,
                'is_crypto'  => 0,
                'parameters' => '{"razorpay_key_id":"","razorpay_key_secret":""}',
            ],
            [
                'name'       => 'Paystack',
                'slug'       => 'Paystack',
                'image'      => 'paystack.png',
                'status'     => 0,
                'is_crypto'  => 0,
                'parameters' => '{"paystack_public_key":"","paystack_secret_key":""}',
            ],
            [
                'name'       => 'Flutterwave',
                'slug'       => 'Flutterwave',
                'image'      => 'flutterwave.png',
                'status'     => 0,
                'is_crypto'  => 0,
                'parameters' => '{"public_key":"","secret_key":"","encryption_key":"","environment":"sandbox"}',
            ],
            [
                'name'       => 'Mollie',
                'slug'       => 'Mollie',
                'image'      => 'Mollie.png',
                'status'     => 0,
                'is_crypto'  => 0,
                'parameters' => '{"api_key":""}',
            ],
            [
                'name'       => 'Instamojo',
                'slug'       => 'Instamojo',
                'image'      => 'instamojo.png',
                'status'     => 0,
                'is_crypto'  => 0,
                'parameters' => '{"api_key":"","auth_token":"","salt":"","environment":"sandbox"}',
            ],
            [
                'name'       => 'KCB Buni',
                'slug'       => 'Buni',
                'image'      => 'buni.png',
                'status'     => 0,
                'is_crypto'  => 0,
                'parameters' => '{"buni_base_url":"","buni_client_id":"","buni_client_secret":""}',
            ],
        ];

        foreach ($paymentGateways as $gateway) {
            DB::table('payment_gateways')->updateOrInsert(
                ['slug' => $gateway['slug']],
                $gateway
            );
        }

        //Automatic Gateways
        $automaticGateways = [
            [
                'name'                 => 'PayPal',
                'slug'                 => 'PayPal',
                'image'                => 'paypal.png',
                'status'               => 0,
                'is_crypto'            => 0,
                'parameters'           => '{"client_id":"","client_secret":"","environment":"sandbox"}',
                'supported_currencies' => '{"AUD":"AUD","BRL":"BRL","CAD":"CAD","CZK":"CZK","DKK":"DKK","EUR":"EUR","HKD":"HKD","HUF":"HUF","INR":"INR","ILS":"ILS","JPY":"JPY","MYR":"MYR","MXN":"MXN","TWD":"TWD","NZD":"NZD","NOK":"NOK","PHP":"PHP","PLN":"PLN","GBP":"GBP","RUB":"RUB","SGD":"SGD","SEK":"SEK","CHF":"CHF","THB":"THB","USD":"USD"}',
            ],
            [
                'name'                 => 'Stripe',
                'slug'                 => 'Stripe',
                'image'                => 'stripe.png',
                'status'               => 0,
                'is_crypto'            => 0,
                'parameters'           => '{"secret_key":"","publishable_key":""}',
                'supported_currencies' => '{"USD":"USD","AUD":"AUD","BRL":"BRL","CAD":"CAD","CHF":"CHF","DKK":"DKK","EUR":"EUR","GBP":"GBP","HKD":"HKD","INR":"INR","JPY":"JPY","MXN":"MXN","MYR":"MYR","NOK":"NOK","NZD":"NZD","PLN":"PLN","SEK":"SEK","SGD":"SGD"}',
            ],
            [
                'name'                 => 'Razorpay',
                'slug'                 => 'Razorpay',
                'image'                => 'razorpay.png',
                'status'               => 0,
                'is_crypto'            => 0,
                'parameters'           => '{"razorpay_key_id":"","razorpay_key_secret":""}',
                'supported_currencies' => '{"INR":"INR"}',
            ],
            [
                'name'                 => 'Paystack',
                'slug'                 => 'Paystack',
                'image'                => 'paystack.png',
                'status'               => 0,
                'is_crypto'            => 0,
                'parameters'           => '{"paystack_public_key":"","paystack_secret_key":""}',
                'supported_currencies' => '{"GHS":"GHS","NGN":"NGN","ZAR":"ZAR"}',
            ],
            [
                'name'                 => 'Flutterwave',
                'slug'                 => 'Flutterwave',
                'image'                => 'flutterwave.png',
                'status'               => 0,
                'is_crypto'            => 0,
                'parameters'           => '{"public_key":"","secret_key":"","encryption_key":"","environment":"sandbox"}',
                'supported_currencies' => '{"BIF":"BIF","CAD":"CAD","CDF":"CDF","CVE":"CVE","EUR":"EUR","GBP":"GBP","GHS":"GHS","GMD":"GMD","GNF":"GNF","KES":"KES","LRD":"LRD","MWK":"MWK","MZN":"MZN","NGN":"NGN","RWF":"RWF","SLL":"SLL","STD":"STD","TZS":"TZS","UGX":"UGX","USD":"USD","XAF":"XAF","XOF":"XOF","ZMK":"ZMK","ZMW":"ZMW","ZWD":"ZWD","ZAR":"ZAR"}',
            ],
            [
                'name'                 => 'Mollie',
                'slug'                 => 'Mollie',
                'image'                => 'Mollie.png',
                'status'               => 0,
                'is_crypto'            => 0,
                'parameters'           => '{"api_key":""}',
                'supported_currencies' => '{"AED":"AED","AUD":"AUD","BGN":"BGN","BRL":"BRL","CAD":"CAD","CHF":"CHF","CZK":"CZK","DKK":"DKK","EUR":"EUR","GBP":"GBP","HKD":"HKD","HRK":"HRK","HUF":"HUF","ILS":"ILS","ISK":"ISK","JPY":"JPY","MXN":"MXN","MYR":"MYR","NOK":"NOK","NZD":"NZD","PHP":"PHP","PLN":"PLN","RON":"RON","RUB":"RUB","SEK":"SEK","SGD":"SGD","THB":"THB","TWD":"TWD","USD":"USD","ZAR":"ZAR"}',
            ],
            [
                'name'                 => 'CoinPayments',
                'slug'                 => 'CoinPayments',
                'image'                => 'CoinPayments.png',
                'status'               => 0,
                'is_crypto'            => 1,
                'parameters'           => '{"public_key":"","private_key":"","merchant_id":"","ipn_secret":""}',
                'supported_currencies' => '{"BTC":"Bitcoin","BTC.LN":"Bitcoin (Lightning Network)","LTC":"Litecoin","CPS":"CPS Coin","VLX":"Velas","APL":"Apollo","AYA":"Aryacoin","BAD":"Badcoin","BCD":"Bitcoin Diamond","BCH":"Bitcoin Cash","BCN":"Bytecoin","BEAM":"BEAM","BITB":"Bean Cash","BLK":"BlackCoin","BSV":"Bitcoin SV","BTAD":"Bitcoin Adult","BTG":"Bitcoin Gold","BTT":"BitTorrent","CLOAK":"CloakCoin","CLUB":"ClubCoin","CRW":"Crown","CRYP":"CrypticCoin","CRYT":"CryTrExCoin","CURE":"CureCoin","DASH":"DASH","DCR":"Decred","DEV":"DeviantCoin","DGB":"DigiByte","DOGE":"Dogecoin","EBST":"eBoost","EOS":"EOS","ETC":"Ether Classic","ETH":"Ethereum","ETN":"Electroneum","EUNO":"EUNO","EXP":"EXP","Expanse":"Expanse","FLASH":"FLASH","GAME":"GameCredits","GLC":"Goldcoin","GRS":"Groestlcoin","KMD":"Komodo","LOKI":"LOKI","LSK":"LSK","MAID":"MaidSafeCoin","MUE":"MonetaryUnit","NAV":"NAV Coin","NEO":"NEO","NMC":"Namecoin","NVST":"NVO Token","NXT":"NXT","OMNI":"OMNI","PINK":"PinkCoin","PIVX":"PIVX","POT":"PotCoin","PPC":"Peercoin","PROC":"ProCurrency","PURA":"PURA","QTUM":"QTUM","RES":"Resistance","RVN":"Ravencoin","RVR":"RevolutionVR","SBD":"Steem Dollars","SMART":"SmartCash","SOXAX":"SOXAX","STEEM":"STEEM","STRAT":"STRAT","SYS":"Syscoin","TPAY":"TokenPay","TRIGGERS":"Triggers","TRX":" TRON","UBQ":"Ubiq","UNIT":"UniversalCurrency","USDT":"Tether USD (Omni Layer)","VTC":"Vertcoin","WAVES":"Waves","XEM":"NEM","XMR":"Monero","XSN":"Stakenet","XSR":"SucreCoin","XVG":"VERGE","XZC":"ZCoin","ZEC":"ZCash","ZEN":"Horizen"}',
            ],
            [
                'name'                 => 'Instamojo',
                'slug'                 => 'Instamojo',
                'image'                => 'instamojo.png',
                'status'               => 0,
                'is_crypto'            => 0,
                'parameters'           => '{"api_key":"","auth_token":"","salt":"","environment":"sandbox"}',
                'supported_currencies' => '{"INR":"INR"}',
            ],
            [
                'name'                 => 'KCB Buni',
                'slug'                 => 'Buni',
                'image'                => 'buni.png',
                'status'               => 0,
                'is_crypto'            => 0,
                'parameters'           => '{"buni_base_url":"","buni_client_id":"","buni_client_secret":"","till_number":""}',
                'supported_currencies' => '{"KES":"KES"}',
            ],
        ];

        foreach ($automaticGateways as $gateway) {
            DB::table('automatic_gateways')->updateOrInsert(
                ['slug' => $gateway['slug']],
                $gateway
            );
        }

    }
}
