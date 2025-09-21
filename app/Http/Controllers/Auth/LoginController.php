<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller {
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm() {
        return view('auth.tenant-login');
    }

    public function showAdminLoginForm() {
        return view('auth.login', ['postUrl' => route('admin.login'), 'adminLogin' => true]);
    }

    public function showTenantLoginForm() {
        return view('auth.login', ['postUrl' => route('tenant.login', app('tenant')->slug), 'adminLogin' => false]);
    }

    public function showTenants(Request $request) {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;
        $users = User::with('tenant')->where('email', $email)->where('user_type', '!=', 'superadmin')->get();
        if ($users->isEmpty()) {
            return back()->with('error', _lang('No tenant found with this email !'));
        }
        return view('auth.tenants', compact('users', 'email'));
    }

    protected function credentials(Request $request) {
        if ($request->is('admin/*')) {
            return [
                'email'     => $request->{$this->username()},
                'password'  => $request->password,
                'user_type' => 'superadmin',
                'status'    => 1,
                'tenant_id' => null,
            ];
        } else {
            $tenant = app('tenant');
            return [
                'email'     => $request->{$this->username()},
                'password'  => $request->password,
                'status'    => 1,
                'tenant_id' => $tenant->id,
            ];
        }
    }

    /**
     * Validate the user login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateLogin(Request $request) {

        config(['recaptchav3.sitekey' => get_option('recaptcha_site_key')]);
        config(['recaptchav3.secret' => get_option('recaptcha_secret_key')]);

        $request->validate([
            $this->username()      => 'required|string',
            'password'             => 'required|string',
            'g-recaptcha-response' => get_option('enable_recaptcha', 0) == 1 ? 'required|recaptchav3:login,0.5' : '',
        ], [
            'g-recaptcha-response.recaptchav3' => _lang('Recaptcha error!'),
        ]);
    }

    protected function authenticated(Request $request, $user) {
        if ($user->status != 1) {
            $errors = [$this->username() => _lang('Your account is not active !')];
            Auth::logout();
            return back()->withInput($request->only($this->username(), 'remember'))
                ->withErrors($errors);
        }

        if($user->user_type == 'customer' && $user->tenant->package->member_portal != 1){
            $errors = [$this->username() => _lang('Your subscription plan does not include access to the Member Portal.')];
            Auth::logout();
            return back()->withInput($request->only($this->username(), 'remember'))
                ->withErrors($errors);
        }
    }

    protected function sendLoginResponse(Request $request) {
        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        if ($response = $this->authenticated($request, $this->guard()->user())) {
            return $response;
        }

        if ($request->is('admin/*')) {
            return $request->wantsJson()
            ? new JsonResponse([], 204)
            : redirect()->intended(route('admin.dashboard.index'));
        } else {
            $tenant = app('tenant');
            return $request->wantsJson()
            ? new JsonResponse([], 204)
            : redirect()->intended(route('dashboard.index'));
        }
    }

    /**
     * Get the failed login response instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendFailedLoginResponse(Request $request) {
        $errors = [$this->username() => trans('auth.failed')];
        $user   = \App\Models\User::where($this->username(), $request->{$this->username()})->first();

        if ($user && Hash::check($request->password, $user->password) && $user->status != 1) {
            $errors = [$this->username() => _lang('Your account is not active !')];
        }

        if ($request->expectsJson()) {
            return response()->json($errors, 422);
        }
        return back()->withInput($request->only($this->username(), 'remember'))
            ->withErrors($errors);
    }
}
