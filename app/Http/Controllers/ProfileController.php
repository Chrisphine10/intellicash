<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FAQRCode\Google2FA as Google2FAQRCode;
use PragmaRX\Google2FA\Google2FA;

class ProfileController extends Controller {
    public function __construct() {
        date_default_timezone_set(get_timezone());
    }

    public function index() {
        $alert_col = 'col-lg-8 offset-lg-2';
        $profile   = User::find(Auth::User()->id);
        return view('profile.profile_view', compact('profile', 'alert_col'));
    }

    public function membership_details(Request $request) {
        $alert_col = 'col-lg-8 offset-lg-2';
        $auth      = auth()->user();
        return view('profile.membership_details', compact('auth', 'alert_col'));
    }

    public function show_notification($tenant, $id) {
        if (auth()->user()->user_type == 'customer') {
            $notification = auth()->user()->member->notifications()->find($id);
        } else {
            $notification = auth()->user()->notifications()->find($id);
        }

        if ($notification && request()->ajax()) {
            $notification->markAsRead();
            return new Response('<div class="mx-2 p-3 border rounded local-notification">' . $notification->data['message'] . '</div>');
        }
        return back();
    }

    public function notification_mark_as_read($tenant, $id) {
        if (auth()->user()->user_type == 'customer') {
            $notification = auth()->user()->member->notifications()->find($id);
        } else {
            $notification = auth()->user()->notifications()->find($id);
        }

        if ($notification) {
            $notification->markAsRead();
        }
    }

    public function edit() {
        $alert_col = 'col-lg-10 offset-lg-1';
        $profile   = User::find(Auth::User()->id);
        return view('profile.profile_edit', compact('profile', 'alert_col'));
    }

    public function update(Request $request) {
        $this->validate($request, [
            'name'            => 'required',
            'email'           => [
                'required',
                Rule::unique('users')->ignore(Auth::User()->id),
            ],
            'profile_picture' => 'nullable|image|max:5120',
            'country_code'    => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->filled('mobile') && empty($value)) {
                        $fail('The country code is required when mobile is provided.');
                    }
                },
            ],
        ]);

        DB::beginTransaction();

        $profile        = Auth::user();
        $profile->name  = $request->name;
        $profile->email = $request->email;

        $profile->country_code = $request->input('country_code');
        $profile->mobile       = $request->input('mobile');
        $profile->city         = $request->input('city');
        $profile->state        = $request->input('state');
        $profile->zip          = $request->input('zip');
        $profile->address      = $request->input('address');

        if ($request->hasFile('profile_picture')) {
            $image           = $request->file('profile_picture');
            $profile_picture = "profile_" . time() . '.' . $image->getClientOriginalExtension();

            $manager = new ImageManager(new Driver());
            $img     = $manager->read($image);
            $img->cover(300, 300)->save(base_path('public/uploads/profile/') . $profile_picture);
            $profile->profile_picture = $profile_picture;
        }

        $profile->save();

        DB::commit();

        $isAadminRoute = auth()->user()->user_type == 'superadmin' ? 'admin.' : '';

        return redirect()->route($isAadminRoute . 'profile.index')->with('success', _lang('Updated successfully'));
    }

    /**
     * Show the form for change_password the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function change_password() {
        $alert_col = 'col-lg-6 offset-lg-3';
        $user_type = auth()->user()->user_type;
        return view('profile.change_password', compact('alert_col'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update_password(Request $request) {
        $user = auth()->user();

        $this->validate($request, [
            'oldpassword' => $user->password != null ? 'required' : 'nullable',
            'password'    => 'required|string|min:6|confirmed',
        ]);

        if ($user->password == null) {
            $user->password = Hash::make($request->password);
            $user->save();
            return back()->with('success', _lang('Password has been changed'));
        }

        if (Hash::check($request->oldpassword, $user->password)) {
            $user->password = Hash::make($request->password);
            $user->save();
        } else {
            return back()->with('error', _lang('Old Password did not match !'));
        }

        return back()->with('success', _lang('Password has been changed'));
    }

    public function enable_2fa(Request $request) {
        $user = auth()->user();

        if ($user->uses_two_factor_auth == 1) {
            $isAadminRoute = auth()->user()->user_type == 'superadmin' ? 'admin.' : '';
            return redirect()->route($isAadminRoute.'profile.index')->with('error', _lang('Two Factor Authentication is already enabled'));
        }

        if ($request->isMethod('get')) {
            $google2fa = new Google2FAQRCode();

            // Generate a new secret key for the user
            $secret = $google2fa->generateSecretKey();
            session()->put('google2fa_secret', $secret);

            // Generate the QR code URL
            $qrCodeUrl = $google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $secret
            );

            if ($user->user_type == 'superadmin') {
                return view('profile.manage_2fa', ['qrCodeUrl' => $qrCodeUrl, 'secret' => $secret, 'actionUrl' => route('admin.profile.enable_2fa'), 'alert_col' => 'col-xl-4 col-lg-6 offset-xl-4 offset-lg-3']);
            } else{
                return view('profile.manage_2fa', ['qrCodeUrl' => $qrCodeUrl, 'secret' => $secret, 'actionUrl' => route('profile.enable_2fa'), 'alert_col' => 'col-xl-4 col-lg-6 offset-xl-4 offset-lg-3']);
            }
        } else {
            $request->validate([
                'one_time_password' => 'required|string',
            ]);

            $google2fa  = new Google2FA();
            $otp_secret = session()->get('google2fa_secret');

            if (! $google2fa->verifyKey($otp_secret, $request->one_time_password)) {
                throw ValidationException::withMessages([
                    'one_time_password' => [_lang('The one time password is invalid.')],
                ]);
            }
            $user->google2fa_secret     = $otp_secret;
            $user->uses_two_factor_auth = 1;
            $user->save();

            $google2fa->verifyKey($otp_secret, $request->one_time_password);

            session()->forget('google2fa_secret');

            $isAadminRoute = auth()->user()->user_type == 'superadmin' ? 'admin.' : '';
            return redirect()->route($isAadminRoute.'profile.index')->with('success', _lang('Two Factor Authentication has been enabled'));
        }
    }

    public function disable_2fa(Request $request) {
        $user = auth()->user();

        if ($user->uses_two_factor_auth == 0 || $user->google2fa_secret == null) {
            $isAadminRoute = auth()->user()->user_type == 'superadmin' ? 'admin.' : '';
            return redirect()->route($isAadminRoute.'profile.index')->with('error', _lang('Two Factor Authentication is already disabled'));
        }

        if ($request->isMethod('get')) {
            if ($user->user_type == 'superadmin') {
                return view('profile.manage_2fa', ['actionUrl' => route('admin.profile.disable_2fa'), 'alert_col' => 'col-lg-6 offset-lg-3']);
            } else{
                return view('profile.manage_2fa', ['actionUrl' => route('profile.disable_2fa'), 'alert_col' => 'col-lg-6 offset-lg-3']);
            }
        } else {
            $request->validate([
                'one_time_password' => 'required|string',
            ]);

            $google2fa  = new Google2FA();
            $otp_secret = $user->google2fa_secret;

            if (! $google2fa->verifyKey($otp_secret, $request->one_time_password)) {
                throw ValidationException::withMessages([
                    'one_time_password' => [_lang('The one time password is invalid.')],
                ]);
            }
            $user->google2fa_secret     = null;
            $user->uses_two_factor_auth = 0;
            $user->save();

            $isAadminRoute = auth()->user()->user_type == 'superadmin' ? 'admin.' : '';
            return redirect()->route($isAadminRoute .'profile.index')->with('success', _lang('Two Factor Authentication has been disabled'));
        }
    }

}
