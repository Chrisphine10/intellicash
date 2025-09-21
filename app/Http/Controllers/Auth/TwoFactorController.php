<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_option('timezone', 'Africa/Nairobi'));
    }

    public function show() {
        if(session('google2fa.auth_passed') == true){
            return redirect()->route('dashboard.index');
        }
        return view('auth.2fa');
    }

    public function verify(Request $request) {
        if(session('google2fa.auth_passed') == true){
            return redirect()->route('dashboard.index');
        }
        $request->validate([
            'one_time_password' => 'required|string',
        ]);

        $user = auth()->user();

        $google2fa  = new Google2FA();
        $otp_secret = $user->google2fa_secret;

        if (! $google2fa->verifyKey($otp_secret, $request->one_time_password)) {
            throw ValidationException::withMessages([
                'one_time_password' => [_lang('The one time password is invalid.')],
            ]);
        }

        return redirect()->route('dashboard.index');
        
    }
}
